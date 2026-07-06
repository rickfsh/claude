<?php
/**
 * Analytics — lightweight, privacy-friendly event tracking.
 *
 * Stores aggregated counters per event per day (no personal data, no cookies).
 * Admin dashboard at MH Spielturm → Analytics.
 *
 * @package MH_Spielturm_Vergleich
 * @since   5.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MH_STV_Analytics {

	const TABLE_SUFFIX = 'mh_stv_analytics';
	const DB_VERSION   = '1.0';

	/**
	 * Boot: register AJAX + admin page.
	 */
	public static function boot() {
		// AJAX event endpoint (logged-in + guest).
		add_action( 'wp_ajax_mh_stv_track', array( __CLASS__, 'ajax_track' ) );
		add_action( 'wp_ajax_nopriv_mh_stv_track', array( __CLASS__, 'ajax_track' ) );

		// Admin page.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 99 );
			add_action( 'admin_init', array( __CLASS__, 'handle_csv_export' ) );
		}
	}

	/* ─────────────────────── Database ─────────────────────── */

	/**
	 * Get full table name.
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create / upgrade table. Called on plugin activation.
	 */
	public static function maybe_create_table() {
		if ( get_option( 'mh_stv_analytics_db', '' ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_date date NOT NULL,
			event_name varchar(50) NOT NULL,
			event_value varchar(100) DEFAULT '' NOT NULL,
			count int(10) UNSIGNED DEFAULT 1 NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY date_event_value (event_date, event_name, event_value)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'mh_stv_analytics_db', self::DB_VERSION );
	}

	/* ─────────────────────── Logging ─────────────────────── */

	/**
	 * Increment a counter. Uses INSERT ... ON DUPLICATE KEY UPDATE for atomicity.
	 */
	public static function log( $event_name, $event_value = '' ) {
		global $wpdb;
		$table = self::table();
		$today = current_time( 'Y-m-d' );

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (event_date, event_name, event_value, count)
			 VALUES (%s, %s, %s, 1)
			 ON DUPLICATE KEY UPDATE count = count + 1",
			$today,
			sanitize_key( $event_name ),
			sanitize_text_field( substr( $event_value, 0, 100 ) )
		) );
	}

	/* ─────────────────────── AJAX ─────────────────────── */

	/**
	 * AJAX handler — accepts a batch of events from the frontend.
	 */
	public static function ajax_track() {
		check_ajax_referer( 'mh_stv_track', 'nonce' );

		$events = isset( $_POST['events'] ) ? $_POST['events'] : array();
		if ( ! is_array( $events ) ) {
			wp_send_json_error( array( 'message' => 'Invalid.' ), 400 );
		}

		// Max 20 events per request to prevent abuse.
		$events = array_slice( $events, 0, 20 );

		foreach ( $events as $evt ) {
			if ( ! is_array( $evt ) || empty( $evt['name'] ) ) {
				continue;
			}
			$name  = sanitize_key( $evt['name'] );
			$value = isset( $evt['value'] ) ? sanitize_text_field( $evt['value'] ) : '';
			// Allowlist of valid event names.
			$allowed = array(
				'stv_view', 'stv_serie_switch', 'stv_level_switch',
				'stv_add_to_cart', 'stv_tab_open', 'stv_lightbox_open',
				'stv_360_interact', 'stv_bt_interact', 'stv_gallery_swipe',
				// Spielhaus-Konfigurator (eigener stv_sh_-Namespace, ab v5.12.0).
				'stv_sh_view', 'stv_sh_house_switch', 'stv_sh_extra_toggle',
				'stv_sh_add_to_cart', 'stv_sh_lightbox_open', 'stv_sh_360_interact',
				'stv_sh_share', // v5.42.0 — Klick auf „Konfiguration teilen" (Wert: desktop|mobile)
			);
			if ( in_array( $name, $allowed, true ) ) {
				self::log( $name, $value );
			}
		}

		wp_send_json_success( array( 'tracked' => count( $events ) ) );
	}

	/* ─────────────────────── Query ─────────────────────── */

	/**
	 * Get aggregated stats for a date range.
	 *
	 * @return array [ 'event_name' => [ 'total' => int, 'daily' => [ 'YYYY-MM-DD' => int ] ] ]
	 */
	public static function get_stats( $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT event_date, event_name, SUM(count) AS total
			 FROM {$table}
			 WHERE event_date >= %s
			 GROUP BY event_date, event_name
			 ORDER BY event_date ASC",
			$since
		) );

		$stats = array();
		foreach ( $rows as $row ) {
			$name = $row->event_name;
			if ( ! isset( $stats[ $name ] ) ) {
				$stats[ $name ] = array( 'total' => 0, 'daily' => array() );
			}
			$stats[ $name ]['total'] += intval( $row->total );
			$stats[ $name ]['daily'][ $row->event_date ] = intval( $row->total );
		}

		return $stats;
	}

	/**
	 * Get top values for a given event (e.g. most-opened tabs).
	 */
	public static function get_top_values( $event_name, $days = 30, $limit = 10 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT event_value, SUM(count) AS total
			 FROM {$table}
			 WHERE event_date >= %s AND event_name = %s AND event_value != ''
			 GROUP BY event_value
			 ORDER BY total DESC
			 LIMIT %d",
			$since,
			sanitize_key( $event_name ),
			$limit
		) );
	}

	/* ─────────────────────── CSV Export ─────────────────────── */

	/**
	 * Handle CSV export request.
	 */
	public static function handle_csv_export() {
		if ( ! isset( $_GET['mh_stv_export'] ) || $_GET['mh_stv_export'] !== 'csv' ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '', 'mh_stv_export_csv' ) ) {
			wp_die( 'Sicherheitspr&uuml;fung fehlgeschlagen.' );
		}

		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		if ( $days < 1 || $days > 365 ) $days = 30;

		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT event_date, event_name, event_value, count
			 FROM {$table}
			 WHERE event_date >= %s
			 ORDER BY event_date DESC, event_name ASC, count DESC",
			$since
		) );

		$filename = 'spielturm-analytics-' . $days . 'tage-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		// BOM for Excel UTF-8 compatibility.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'Datum', 'Event', 'Wert', 'Anzahl' ), ';' );

		foreach ( $rows as $row ) {
			fputcsv( $out, array(
				$row->event_date,
				$row->event_name,
				$row->event_value,
				$row->count,
			), ';' );
		}

		fclose( $out );
		exit;
	}

	/* ─────────────────────── Admin Page ─────────────────────── */

	public static function register_admin_page() {
		add_submenu_page(
			'edit.php?post_type=product',
			'Spielturm Analytics',
			'Spielturm Analytics',
			'manage_woocommerce',
			'mh-stv-analytics',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page() {
		$days  = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		if ( $days < 1 || $days > 365 ) $days = 30;
		$stats = self::get_stats( $days );

		$labels = array(
			'stv_view'          => array( 'Konfigurator Aufrufe', '#e8910c' ),
			'stv_serie_switch'  => array( 'Design-Wechsel', '#3b82f6' ),
			'stv_level_switch'  => array( 'Ausstattung-Toggle', '#8b5cf6' ),
			'stv_add_to_cart'   => array( 'Add-to-Cart', '#22c55e' ),
			'stv_tab_open'      => array( 'Tab geöffnet', '#6b7280' ),
			'stv_lightbox_open' => array( 'Lightbox geöffnet', '#ec4899' ),
			'stv_360_interact'  => array( '360° Interaktion', '#14b8a6' ),
			'stv_bt_interact'   => array( 'Zubehör-Klick', '#f59e0b' ),
			'stv_gallery_swipe' => array( 'Gallery Swipe', '#a78bfa' ),
			// Spielhaus-Konfigurator (ab v5.12.0).
			'stv_sh_view'          => array( 'Spielhaus: Aufrufe', '#d97706' ),
			'stv_sh_house_switch'  => array( 'Spielhaus: Modell-Wechsel', '#2563eb' ),
			'stv_sh_extra_toggle'  => array( 'Spielhaus: Zubehör-Toggle', '#7c3aed' ),
			'stv_sh_add_to_cart'   => array( 'Spielhaus: Set in Warenkorb', '#16a34a' ),
			'stv_sh_lightbox_open' => array( 'Spielhaus: Lightbox', '#db2777' ),
			'stv_sh_360_interact'  => array( 'Spielhaus: 360° Interaktion', '#0d9488' ),
			'stv_sh_share'         => array( 'Spielhaus: Konfiguration geteilt', '#0ea5e9' ),
		);

		// Build date labels for chart.
		$date_labels = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date_labels[] = gmdate( 'd.m.', strtotime( "-{$i} days" ) );
		}
		$date_keys = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date_keys[] = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
		}

		// Key metric: conversion rate.
		$views    = isset( $stats['stv_view'] )        ? $stats['stv_view']['total']        : 0;
		$carts    = isset( $stats['stv_add_to_cart'] )  ? $stats['stv_add_to_cart']['total']  : 0;
		$interact = 0;
		foreach ( $stats as $name => $s ) {
			if ( $name !== 'stv_view' ) $interact += $s['total'];
		}
		$conv_rate = $views > 0 ? round( ( $carts / $views ) * 100, 1 ) : 0;
		$engage_rate = $views > 0 ? round( ( $interact / $views ) * 100, 1 ) : 0;

		// Chart datasets.
		$chart_events = array( 'stv_view', 'stv_add_to_cart', 'stv_serie_switch', 'stv_level_switch' );
		$datasets = array();
		foreach ( $chart_events as $ce ) {
			if ( ! isset( $labels[ $ce ] ) ) continue;
			$data = array();
			foreach ( $date_keys as $dk ) {
				$data[] = isset( $stats[ $ce ]['daily'][ $dk ] ) ? $stats[ $ce ]['daily'][ $dk ] : 0;
			}
			$datasets[] = array(
				'label'       => $labels[ $ce ][0],
				'data'        => $data,
				'borderColor' => $labels[ $ce ][1],
				'backgroundColor' => $labels[ $ce ][1] . '20',
				'tension'     => 0.3,
				'fill'        => false,
			);
		}

		// Top tabs.
		$top_tabs = self::get_top_values( 'stv_tab_open', $days, 5 );

		?>
		<div class="wrap">
			<h1>Spielturm Konfigurator — Analytics</h1>

			<div style="margin:16px 0;">
				<?php foreach ( array( 7, 30, 90 ) as $d ) : ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=mh-stv-analytics&days=' . $d ) ); ?>"
					   class="button <?php echo $days === $d ? 'button-primary' : ''; ?>"
					   style="margin-right:4px;">
						<?php echo esc_html( $d ); ?> Tage
					</a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=product&page=mh-stv-analytics&mh_stv_export=csv&days=' . $days ), 'mh_stv_export_csv' ) ); ?>"
				   class="button" style="margin-left:12px;">
					&#8615; CSV Export (<?php echo esc_html( $days ); ?> Tage)
				</a>
			</div>

			<!-- KPI Cards -->
			<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
					<div style="font-size:13px;color:#6b7280;">Konfigurator Aufrufe</div>
					<div style="font-size:28px;font-weight:600;margin-top:4px;"><?php echo number_format_i18n( $views ); ?></div>
				</div>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
					<div style="font-size:13px;color:#6b7280;">Add-to-Cart</div>
					<div style="font-size:28px;font-weight:600;margin-top:4px;color:#22c55e;"><?php echo number_format_i18n( $carts ); ?></div>
				</div>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
					<div style="font-size:13px;color:#6b7280;">Conversion Rate</div>
					<div style="font-size:28px;font-weight:600;margin-top:4px;color:#e8910c;"><?php echo esc_html( $conv_rate ); ?>%</div>
				</div>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
					<div style="font-size:13px;color:#6b7280;">Engagement Rate</div>
					<div style="font-size:28px;font-weight:600;margin-top:4px;color:#3b82f6;"><?php echo esc_html( $engage_rate ); ?>%</div>
				</div>
			</div>

			<!-- Chart -->
			<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:24px;">
				<h2 style="margin:0 0 16px;font-size:16px;">Verlauf (<?php echo esc_html( $days ); ?> Tage)</h2>
				<div style="position:relative;height:320px;">
					<canvas id="mh-stv-chart"></canvas>
				</div>
			</div>

			<!-- Event Breakdown -->
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
					<h2 style="margin:0 0 16px;font-size:16px;">Alle Events</h2>
					<table class="widefat striped">
						<thead><tr><th>Event</th><th style="text-align:right">Gesamt</th><th style="text-align:right">Ø / Tag</th></tr></thead>
						<tbody>
						<?php foreach ( $labels as $key => $info ) :
							$total = isset( $stats[ $key ] ) ? $stats[ $key ]['total'] : 0;
							$avg = $days > 0 ? round( $total / $days, 1 ) : 0;
							?>
							<tr>
								<td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?php echo esc_attr( $info[1] ); ?>;margin-right:8px;"></span><?php echo esc_html( $info[0] ); ?></td>
								<td style="text-align:right;font-weight:500;"><?php echo number_format_i18n( $total ); ?></td>
								<td style="text-align:right;color:#6b7280;"><?php echo esc_html( $avg ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
					<h2 style="margin:0 0 16px;font-size:16px;">Beliebteste Tabs</h2>
					<?php if ( empty( $top_tabs ) ) : ?>
						<p style="color:#6b7280;">Noch keine Daten.</p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th>Tab</th><th style="text-align:right">Klicks</th></tr></thead>
							<tbody>
							<?php foreach ( $top_tabs as $tt ) : ?>
								<tr>
									<td><?php echo esc_html( $tt->event_value ); ?></td>
									<td style="text-align:right;font-weight:500;"><?php echo number_format_i18n( $tt->total ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<h2 style="margin:20px 0 16px;font-size:16px;">Design-Wechsel</h2>
					<?php
					$top_series = self::get_top_values( 'stv_serie_switch', $days, 5 );
					if ( empty( $top_series ) ) : ?>
						<p style="color:#6b7280;">Noch keine Daten.</p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th>Serie</th><th style="text-align:right">Wechsel</th></tr></thead>
							<tbody>
							<?php foreach ( $top_series as $ts ) : ?>
								<tr>
									<td><?php echo esc_html( $ts->event_value ); ?></td>
									<td style="text-align:right;font-weight:500;"><?php echo number_format_i18n( $ts->total ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
		<script>
		new Chart(document.getElementById('mh-stv-chart'), {
			type: 'line',
			data: {
				labels: <?php echo wp_json_encode( $date_labels ); ?>,
				datasets: <?php echo wp_json_encode( $datasets ); ?>
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { intersect: false, mode: 'index' },
				scales: {
					y: { beginAtZero: true, ticks: { stepSize: 1 } }
				},
				plugins: { legend: { position: 'bottom' } }
			}
		});
		</script>
		<?php
	}
}
