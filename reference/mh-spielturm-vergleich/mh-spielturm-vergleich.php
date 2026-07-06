<?php
/**
 * Plugin Name:       MH Spielturm Vergleich
 * Plugin URI:        https://mega-holz.de
 * Description:       Interaktiver Spielturm-Konfigurator mit integrierter Bildgalerie für WooCommerce-Produktseiten.
 * Version:           5.42.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mega-Holz GmbH & Co. KG
 * Author URI:        https://mega-holz.de
 * License:           GPL v2 or later
 * Text Domain:       mh-spielturm-vergleich
 *
 * WC requires at least: 7.0
 * WC tested up to:      9.0
 *
 * @package MH_Spielturm_Vergleich
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MH_STV_VERSION', '5.42.0' );
define( 'MH_STV_FILE', __FILE__ );
define( 'MH_STV_PATH', plugin_dir_path( __FILE__ ) );
define( 'MH_STV_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active before doing anything.
 */
function mh_stv_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>MH Spielturm Vergleich:</strong> ';
			echo esc_html( 'WooCommerce muss installiert und aktiviert sein.' );
			echo '</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Initialize plugin — runs on plugins_loaded (priority 20 so WC is ready).
 */
function mh_stv_init() {
	if ( ! mh_stv_check_woocommerce() ) {
		return;
	}

	require_once MH_STV_PATH . 'includes/class-data-provider.php';
	require_once MH_STV_PATH . 'includes/class-frontend.php';
	require_once MH_STV_PATH . 'includes/class-360-metabox.php';
	require_once MH_STV_PATH . 'includes/class-analytics.php';

	// Spielhaus-Konfigurator (eigenständiger Typ, ab v5.10.0).
	require_once MH_STV_PATH . 'includes/class-spielhaus-data-provider.php';
	require_once MH_STV_PATH . 'includes/class-spielhaus-frontend.php';

	// Boot 360° metabox (admin product edit + static helpers for frontend).
	MH_STV_360_Metabox::boot();
	MH_STV_Analytics::boot();

	if ( is_admin() ) {
		require_once MH_STV_PATH . 'admin/class-settings.php';
		require_once MH_STV_PATH . 'admin/class-spielhaus-settings.php';
	}
}
add_action( 'plugins_loaded', 'mh_stv_init', 20 );

/**
 * #8: Invalidate transient cache when products or settings change.
 */
function mh_stv_register_cache_hooks() {
	$invalidate = array( 'MH_STV_Data_Provider', 'invalidate_cache' );
	add_action( 'woocommerce_update_product', $invalidate );
	add_action( 'woocommerce_new_product', $invalidate );
	add_action( 'save_post_product', $invalidate );
	add_action( 'update_option_mh_stv_groups', function() { MH_STV_Data_Provider::invalidate_cache( 0 ); } );
	add_action( 'update_option_mh_stv_settings', function() { MH_STV_Data_Provider::invalidate_cache( 0 ); } );

	// Spielhaus-Konfigurator: eigene Transients invalidieren.
	if ( class_exists( 'MH_STV_Spielhaus_Data_Provider' ) ) {
		$sh_invalidate = array( 'MH_STV_Spielhaus_Data_Provider', 'invalidate_cache' );
		add_action( 'woocommerce_update_product', $sh_invalidate );
		add_action( 'woocommerce_new_product', $sh_invalidate );
		add_action( 'save_post_product', $sh_invalidate );
		add_action( 'update_option_mh_stv_spielhaeuser', function() { MH_STV_Spielhaus_Data_Provider::invalidate_cache( 0 ); } );
		add_action( 'update_option_mh_stv_settings', function() { MH_STV_Spielhaus_Data_Provider::invalidate_cache( 0 ); } );
	}
}
add_action( 'init', 'mh_stv_register_cache_hooks' );

/**
 * Ensure analytics table exists (for upgrades without re-activation).
 */
function mh_stv_maybe_upgrade_analytics() {
	if ( class_exists( 'MH_STV_Analytics' ) ) {
		MH_STV_Analytics::maybe_create_table();
	}
}
add_action( 'admin_init', 'mh_stv_maybe_upgrade_analytics' );

/**
 * Set defaults on activation.
 */
function mh_stv_activate() {
	update_option( 'mh_stv_settings', array(
		'accent_color'  => '#e8910c',
		'hide_selector' => '.woocommerce-product-gallery, .ct-product-gallery-container',
	), false );

	if ( ! get_option( 'mh_stv_groups' ) ) {
		update_option( 'mh_stv_groups', array(
			array(
				'group_name'    => 'Spielturm Nino / Pirato',
				'base_features' => 'Sandkasten, Teleskop, Steuerrad, Handgriffe',
				'series'        => array(
					array( 'key' => 'klassisch', 'label' => 'Klassisch (Nino)', 'icon' => '', 'features' => '' ),
					array( 'key' => 'piraten',   'label' => 'Piraten (Pirato)', 'icon' => '', 'features' => 'Piratenflagge' ),
				),
				'levels'     => array(
					array( 'key' => 'basic', 'label' => 'Ohne Schaukel',          'extras' => '' ),
					array( 'key' => 'swing', 'label' => 'Mit Schaukel',           'extras' => '' ),
					array( 'key' => 'full',  'label' => 'Schaukel + Kletterwand', 'extras' => '' ),
				),
				'features_matrix' => array(
					'klassisch' => array(
						'basic' => 'Kletterseil',
						'swing' => 'Schaukel, Picknicktisch, Kletterseil',
						'full'  => 'Schaukel, Kletterwand, Kletterseil, Picknicktisch',
					),
					'piraten' => array(
						'basic' => '',
						'swing' => 'Schaukel, Picknicktisch',
						'full'  => 'Schaukel, Kletterwand, Kletterseil, Picknicktisch',
					),
				),
				'products'   => array(
					'klassisch' => array( 'basic' => 0, 'swing' => 0, 'full' => 0 ),
					'piraten'   => array( 'basic' => 0, 'swing' => 0, 'full' => 0 ),
				),
			),
		), false );
	}
}
register_activation_hook( MH_STV_FILE, 'mh_stv_activate' );
register_activation_hook( MH_STV_FILE, function() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-analytics.php';
	MH_STV_Analytics::maybe_create_table();
} );

/**
 * Migrate existing groups to 4.4.0 — fill in base_features + series features
 * that were previously hardcoded in JS. Runs once on upgrade.
 */
function mh_stv_maybe_migrate_440() {
	$migrated = get_option( 'mh_stv_migrated_440', false );
	if ( $migrated ) {
		return;
	}

	$groups = get_option( 'mh_stv_groups', array() );
	if ( empty( $groups ) || ! is_array( $groups ) ) {
		update_option( 'mh_stv_migrated_440', true );
		return;
	}

	$changed = false;
	foreach ( $groups as &$group ) {
		// Add base_features if missing or empty.
		if ( empty( $group['base_features'] ) ) {
			$group['base_features'] = 'Sandkasten, Teleskop, Steuerrad, Handgriffe';
			$changed = true;
		}

		// Add per-series features if missing.
		if ( ! empty( $group['series'] ) && is_array( $group['series'] ) ) {
			foreach ( $group['series'] as &$serie ) {
				if ( ! isset( $serie['features'] ) ) {
					// Piraten series gets Piratenflagge by default.
					if ( isset( $serie['key'] ) && $serie['key'] === 'piraten' ) {
						$serie['features'] = 'Piratenflagge';
					} else {
						$serie['features'] = '';
					}
					$changed = true;
				}
			}
			unset( $serie );
		}
	}
	unset( $group );

	if ( $changed ) {
		update_option( 'mh_stv_groups', $groups );
	}
	update_option( 'mh_stv_migrated_440', true );
}
add_action( 'admin_init', 'mh_stv_maybe_migrate_440' );

/**
 * Migrate existing groups to 5.7.0 — convert levels[].extras to features_matrix.
 *
 * Old model: each level had global extras (same for all series).
 * New model: features_matrix[serie_key][level_key] = comma-separated features (per-serie).
 *
 * Migration copies old levels[].extras into every serie's column in the matrix,
 * so existing behaviour is preserved. Admin can then fine-tune per serie.
 */
function mh_stv_maybe_migrate_570() {
	$migrated = get_option( 'mh_stv_migrated_570', false );
	if ( $migrated ) {
		return;
	}

	$groups = get_option( 'mh_stv_groups', array() );
	if ( empty( $groups ) || ! is_array( $groups ) ) {
		update_option( 'mh_stv_migrated_570', true );
		return;
	}

	$changed = false;
	foreach ( $groups as &$group ) {
		// Skip if features_matrix already exists.
		if ( ! empty( $group['features_matrix'] ) && is_array( $group['features_matrix'] ) ) {
			continue;
		}

		$series = isset( $group['series'] ) ? $group['series'] : array();
		$levels = isset( $group['levels'] ) ? $group['levels'] : array();

		if ( empty( $series ) || empty( $levels ) ) {
			continue;
		}

		$matrix = array();
		foreach ( $series as $serie ) {
			$sk = isset( $serie['key'] ) ? $serie['key'] : '';
			if ( empty( $sk ) ) continue;
			$matrix[ $sk ] = array();
			foreach ( $levels as $level ) {
				$lk = isset( $level['key'] ) ? $level['key'] : '';
				if ( empty( $lk ) ) continue;
				// Copy old global extras to every serie.
				$matrix[ $sk ][ $lk ] = isset( $level['extras'] ) ? $level['extras'] : '';
			}
		}
		$group['features_matrix'] = $matrix;
		$changed = true;
	}
	unset( $group );

	if ( $changed ) {
		update_option( 'mh_stv_groups', $groups );
	}
	update_option( 'mh_stv_migrated_570', true );
}
add_action( 'admin_init', 'mh_stv_maybe_migrate_570' );
