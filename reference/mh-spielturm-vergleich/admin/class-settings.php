<?php
/**
 * Admin Settings — manage Spielturm comparison groups.
 *
 * v5.7.0: Features-Matrix (Serie × Stufe) replaces global level extras.
 * v5.6.0: Series + Levels fully editable with add/remove.
 *
 * @package MH_Spielturm_Vergleich
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MH_STV_Admin_Settings {

	private static $instance = null;

	public static function boot() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			'Spielturm Vergleich',
			'Spielturm Vergleich',
			'manage_woocommerce',
			'mh-spielturm-vergleich',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'mh_stv_options', 'mh_stv_groups', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_groups' ),
		) );
		register_setting( 'mh_stv_options', 'mh_stv_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
		) );
	}

	public function sanitize_groups( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$out = array();
		foreach ( $input as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$clean = array(
				'group_name'      => sanitize_text_field( isset( $group['group_name'] ) ? $group['group_name'] : '' ),
				'base_features'   => sanitize_text_field( isset( $group['base_features'] ) ? $group['base_features'] : '' ),
				'series'          => array(),
				'levels'          => array(),
				'features_matrix' => array(),
				'products'        => array(),
			);
			if ( ! empty( $group['series'] ) && is_array( $group['series'] ) ) {
				foreach ( $group['series'] as $s ) {
					if ( ! is_array( $s ) ) continue;
					$key = sanitize_key( isset( $s['key'] ) ? $s['key'] : '' );
					if ( empty( $key ) ) continue;
					$clean['series'][] = array(
						'key'      => $key,
						'label'    => sanitize_text_field( isset( $s['label'] ) ? $s['label'] : '' ),
						'icon'     => sanitize_text_field( isset( $s['icon'] ) ? $s['icon'] : '' ),
						'features' => sanitize_text_field( isset( $s['features'] ) ? $s['features'] : '' ),
					);
				}
			}
			if ( ! empty( $group['levels'] ) && is_array( $group['levels'] ) ) {
				foreach ( $group['levels'] as $l ) {
					if ( ! is_array( $l ) ) continue;
					$key = sanitize_key( isset( $l['key'] ) ? $l['key'] : '' );
					if ( empty( $key ) ) continue;
					$clean['levels'][] = array(
						'key'          => $key,
						'label'        => sanitize_text_field( isset( $l['label'] ) ? $l['label'] : '' ),
						'toggle_desc'  => sanitize_text_field( isset( $l['toggle_desc'] ) ? $l['toggle_desc'] : '' ),
						'extras'       => '', // Kept for backwards compat, no longer primary source.
					);
				}
			}
			// Features matrix: [serie_key][level_key] = comma-separated features string.
			if ( ! empty( $group['features_matrix'] ) && is_array( $group['features_matrix'] ) ) {
				foreach ( $group['features_matrix'] as $sk => $level_map ) {
					$sk = sanitize_key( $sk );
					if ( ! is_array( $level_map ) ) continue;
					foreach ( $level_map as $lk => $val ) {
						$clean['features_matrix'][ $sk ][ sanitize_key( $lk ) ] = sanitize_text_field( $val );
					}
				}
			}
			if ( ! empty( $group['products'] ) && is_array( $group['products'] ) ) {
				foreach ( $group['products'] as $sk => $levels ) {
					$sk = sanitize_key( $sk );
					if ( ! is_array( $levels ) ) continue;
					foreach ( $levels as $lk => $pid ) {
						$clean['products'][ $sk ][ sanitize_key( $lk ) ] = absint( $pid );
					}
				}
			}
			$out[] = $clean;
		}
		return $out;
	}

	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		return array(
			'accent_color'     => sanitize_hex_color( isset( $input['accent_color'] ) ? $input['accent_color'] : '#e8910c' ),
			'hide_selector'    => sanitize_text_field( isset( $input['hide_selector'] ) ? $input['hide_selector'] : '' ),
			'show_stock'       => ! empty( $input['show_stock'] ) ? '1' : '',
			'payment_selector' => sanitize_text_field( isset( $input['payment_selector'] ) ? $input['payment_selector'] : '' ),
			'manufacturer_selector' => sanitize_text_field( isset( $input['manufacturer_selector'] ) ? $input['manufacturer_selector'] : '' ),
			'embed_selectors'  => sanitize_textarea_field( isset( $input['embed_selectors'] ) ? $input['embed_selectors'] : '' ),
			'below_grid_selectors' => sanitize_textarea_field( isset( $input['below_grid_selectors'] ) ? $input['below_grid_selectors'] : '' ),
			'trust_1'          => sanitize_text_field( isset( $input['trust_1'] ) ? $input['trust_1'] : '' ),
			'trust_2'          => sanitize_text_field( isset( $input['trust_2'] ) ? $input['trust_2'] : '' ),
			'trust_3'          => sanitize_text_field( isset( $input['trust_3'] ) ? $input['trust_3'] : '' ),
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== 'woocommerce_page_mh-spielturm-vergleich' ) {
			return;
		}
		wp_enqueue_style( 'mh-stv-admin', MH_STV_URL . 'admin/admin.css', array(), MH_STV_VERSION );
		wp_enqueue_script( 'mh-stv-admin', MH_STV_URL . 'admin/admin.js', array(), MH_STV_VERSION, true );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Zugriff verweigert.' );
		}

		$groups   = get_option( 'mh_stv_groups', array() );
		$settings = get_option( 'mh_stv_settings', array() );

		if ( ! is_array( $groups ) ) $groups = array();
		if ( ! is_array( $settings ) ) $settings = array();

		$accent_color  = isset( $settings['accent_color'] ) ? $settings['accent_color'] : '#e8910c';
		$hide_selector = isset( $settings['hide_selector'] ) ? $settings['hide_selector'] : '';
		$show_stock    = ! empty( $settings['show_stock'] );
		$payment_sel   = isset( $settings['payment_selector'] ) ? $settings['payment_selector'] : '';
		$mfg_sel       = isset( $settings['manufacturer_selector'] ) ? $settings['manufacturer_selector'] : '';
		$embed_sels    = isset( $settings['embed_selectors'] ) ? $settings['embed_selectors'] : '';
		$below_sels    = isset( $settings['below_grid_selectors'] ) ? $settings['below_grid_selectors'] : '';
		$trust_1       = isset( $settings['trust_1'] ) ? $settings['trust_1'] : '';
		$trust_2       = isset( $settings['trust_2'] ) ? $settings['trust_2'] : '';
		$trust_3       = isset( $settings['trust_3'] ) ? $settings['trust_3'] : '';
		?>
		<div class="wrap mh-stv-admin">
			<h1>Spielturm Vergleich &mdash; Einstellungen <span class="mh-stv-version">v<?php echo esc_html( MH_STV_VERSION ); ?></span></h1>
			<?php settings_errors(); ?>

			<form method="post" action="options.php" id="mh-stv-settings-form">
				<?php settings_fields( 'mh_stv_options' ); ?>

				<!-- ═══ GENERAL SETTINGS ═══ -->
				<div class="mh-stv-card">
					<h2>Allgemeine Einstellungen</h2>
					<table class="form-table">
						<tr>
							<th><label for="mh_stv_accent">Akzentfarbe</label></th>
							<td>
								<input type="color" name="mh_stv_settings[accent_color]" id="mh_stv_accent"
									value="<?php echo esc_attr( $accent_color ); ?>" />
								<code><?php echo esc_html( $accent_color ); ?></code>
								<p class="description">Mega-Holz Orange: #e8910c</p>
							</td>
						</tr>
						<tr>
							<th><label for="mh_stv_hide">Alte Varianten-Buttons verstecken (CSS-Selektor)</label></th>
							<td>
								<input type="text" name="mh_stv_settings[hide_selector]" id="mh_stv_hide"
									value="<?php echo esc_attr( $hide_selector ); ?>" class="regular-text"
									placeholder="z.B. .product-variant-links" />
							</td>
						</tr>
						<tr>
							<th><label for="mh_stv_show_stock">Lagerbestand anzeigen</label></th>
							<td>
								<label>
									<input type="checkbox" name="mh_stv_settings[show_stock]" id="mh_stv_show_stock"
										value="1" <?php checked( $show_stock ); ?> />
									Lagerstatus-Badge anzeigen
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="mh_stv_payment_sel">Payment-Icons (CSS-Selektor)</label></th>
							<td>
								<input type="text" name="mh_stv_settings[payment_selector]" id="mh_stv_payment_sel"
									value="<?php echo esc_attr( $payment_sel ); ?>" class="regular-text"
									placeholder="z.B. #zahlungsblock" />
							</td>
						</tr>
						<tr>
							<th><label for="mh_stv_mfg_sel">Herstellerinfos (CSS-Selektor)</label></th>
							<td>
								<input type="text" name="mh_stv_settings[manufacturer_selector]" id="mh_stv_mfg_sel"
									value="<?php echo esc_attr( $mfg_sel ); ?>" class="regular-text"
									placeholder="z.B. #div_block-1168-101300" />
								<p class="description">Oxygen-Element mit Herstellerinformationen &rarr; wird in den Produktdaten-Tab verschoben.</p>
							</td>
						</tr>
						<tr>
							<th><label for="mh_stv_embed_sels">Seitenelemente einbinden (rechte Spalte)</label></th>
							<td>
								<textarea name="mh_stv_settings[embed_selectors]" id="mh_stv_embed_sels"
									rows="3" class="large-text" placeholder="z.B. #verfuegbarkeit&#10;#kundenprojekte"><?php echo esc_textarea( $embed_sels ); ?></textarea>
								<p class="description">Ein CSS-Selektor pro Zeile. Elemente landen in der rechten Spalte beim CTA.</p>
							</td>
						</tr>
						<tr>
							<th><label for="mh_stv_below_sels">Unter dem Konfigurator (volle Breite)</label></th>
							<td>
								<textarea name="mh_stv_settings[below_grid_selectors]" id="mh_stv_below_sels"
									rows="3" class="large-text" placeholder="z.B. #trust-icons-row&#10;#klarna-banner"><?php echo esc_textarea( $below_sels ); ?></textarea>
								<p class="description">Ein CSS-Selektor pro Zeile. Volle Breite zwischen Konfigurator und Beschreibung.</p>
							</td>
						</tr>
						<tr>
							<th>Trust Signals</th>
							<td>
								<input type="text" name="mh_stv_settings[trust_1]" value="<?php echo esc_attr( $trust_1 ); ?>" class="regular-text" placeholder="z.B. Schnelle Lieferung" /><br /><br />
								<input type="text" name="mh_stv_settings[trust_2]" value="<?php echo esc_attr( $trust_2 ); ?>" class="regular-text" placeholder="z.B. Sichere Zahlung" /><br /><br />
								<input type="text" name="mh_stv_settings[trust_3]" value="<?php echo esc_attr( $trust_3 ); ?>" class="regular-text" placeholder="z.B. 14 Tage R&uuml;ckgabe" />
							</td>
						</tr>
					</table>
				</div>

				<?php foreach ( $groups as $gi => $group ) :
					$group_name      = isset( $group['group_name'] ) ? $group['group_name'] : '';
					$base_features   = isset( $group['base_features'] ) ? $group['base_features'] : '';
					$series          = isset( $group['series'] ) && is_array( $group['series'] ) ? $group['series'] : array();
					$levels          = isset( $group['levels'] ) && is_array( $group['levels'] ) ? $group['levels'] : array();
					$features_matrix = isset( $group['features_matrix'] ) && is_array( $group['features_matrix'] ) ? $group['features_matrix'] : array();
					$products        = isset( $group['products'] ) && is_array( $group['products'] ) ? $group['products'] : array();
				?>
				<div class="mh-stv-card mh-stv-group-card" data-group-index="<?php echo intval( $gi ); ?>">
					<h2>
						<input type="text" name="mh_stv_groups[<?php echo intval( $gi ); ?>][group_name]"
							value="<?php echo esc_attr( $group_name ); ?>" class="regular-text" placeholder="Gruppenname" />
					</h2>

					<table class="form-table">
						<tr>
							<th><label>Basis-Features (immer inklusive)</label></th>
							<td>
								<input type="text" name="mh_stv_groups[<?php echo intval( $gi ); ?>][base_features]"
									value="<?php echo esc_attr( $base_features ); ?>" class="large-text"
									placeholder="z.B. Sandkasten, Teleskop, Steuerrad, Handgriffe" />
								<p class="description">Kommagetrennt. Erscheinen bei <strong>allen</strong> Serien und Stufen als &#x2713;.</p>
							</td>
						</tr>
					</table>

					<!-- ═══ SERIEN ═══ -->
					<div class="mh-stv-section-header">
						<h3>&#x1F3A8; Serien (Design-Auswahl)</h3>
						<p class="description"><strong>Features</strong> = Extras die <em>immer</em> bei dieser Serie dabei sind (egal welche Stufe).</p>
					</div>
					<table class="widefat mh-stv-editable-table mh-stv-series-table" data-group="<?php echo intval( $gi ); ?>">
						<thead>
							<tr>
								<th class="mh-stv-col-key">Key</th>
								<th class="mh-stv-col-label">Label</th>
								<th class="mh-stv-col-icon">Icon</th>
								<th class="mh-stv-col-features">Immer-dabei Features</th>
								<th class="mh-stv-col-actions"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $series as $si => $serie ) :
								$skey      = isset( $serie['key'] ) ? $serie['key'] : '';
								$slabel    = isset( $serie['label'] ) ? $serie['label'] : '';
								$sicon     = isset( $serie['icon'] ) ? $serie['icon'] : '';
								$sfeatures = isset( $serie['features'] ) ? $serie['features'] : '';
								$prefix    = 'mh_stv_groups[' . intval( $gi ) . '][series][' . intval( $si ) . ']';
							?>
							<tr class="mh-stv-row">
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[key]" value="<?php echo esc_attr( $skey ); ?>" class="mh-stv-input-key" placeholder="z.B. klassisch" pattern="[a-z0-9_-]+" /></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[label]" value="<?php echo esc_attr( $slabel ); ?>" class="regular-text" placeholder="z.B. Klassisch (Nino)" /></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[icon]" value="<?php echo esc_attr( $sicon ); ?>" class="mh-stv-input-icon" placeholder="&#x1F3E0;" /></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[features]" value="<?php echo esc_attr( $sfeatures ); ?>" class="regular-text" placeholder="z.B. Piratenflagge" /></td>
								<td><button type="button" class="button mh-stv-remove-row" title="Entfernen">&times;</button></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr><td colspan="5"><button type="button" class="button mh-stv-add-row" data-type="series" data-group="<?php echo intval( $gi ); ?>">+ Serie hinzuf&uuml;gen</button></td></tr>
						</tfoot>
					</table>

					<!-- ═══ AUSSTATTUNGSSTUFEN ═══ -->
					<div class="mh-stv-section-header">
						<h3>&#x2699;&#xFE0F; Ausstattungsstufen</h3>
						<p class="description">Reihenfolge = Toggles im Frontend (Stufe 1 = Basis).</p>
					</div>
					<table class="widefat mh-stv-editable-table mh-stv-levels-table" data-group="<?php echo intval( $gi ); ?>">
						<thead>
							<tr>
								<th class="mh-stv-col-handle"></th>
								<th class="mh-stv-col-key">Key</th>
								<th class="mh-stv-col-label">Label</th>
								<th class="mh-stv-col-label">Toggle-Text</th>
								<th class="mh-stv-col-actions"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $levels as $li => $level ) :
								$lkey   = isset( $level['key'] ) ? $level['key'] : '';
								$llabel = isset( $level['label'] ) ? $level['label'] : '';
								$ltdesc = isset( $level['toggle_desc'] ) ? $level['toggle_desc'] : '';
								$prefix = 'mh_stv_groups[' . intval( $gi ) . '][levels][' . intval( $li ) . ']';
							?>
							<tr class="mh-stv-row">
								<td class="mh-stv-row-num"><?php echo intval( $li ) + 1; ?></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[key]" value="<?php echo esc_attr( $lkey ); ?>" class="mh-stv-input-key" placeholder="z.B. basic" pattern="[a-z0-9_-]+" /></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[label]" value="<?php echo esc_attr( $llabel ); ?>" class="regular-text" placeholder="z.B. Ohne Schaukel" /></td>
								<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[toggle_desc]" value="<?php echo esc_attr( $ltdesc ); ?>" class="regular-text" placeholder="z.B. Schaukelanbau mit Picknicktisch" /></td>
								<td><button type="button" class="button mh-stv-remove-row" title="Entfernen">&times;</button></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr><td colspan="5"><button type="button" class="button mh-stv-add-row" data-type="levels" data-group="<?php echo intval( $gi ); ?>">+ Stufe hinzuf&uuml;gen</button></td></tr>
						</tfoot>
					</table>

					<!-- ═══ FEATURES-MATRIX (Serie × Stufe) ═══ -->
					<div class="mh-stv-section-header">
						<h3>&#x2705; Features-Matrix (pro Serie &amp; Stufe)</h3>
						<p class="description">Hier legst du fest, welche Features <strong>pro Serie und Ausstattungsstufe</strong> enthalten sind. Kommagetrennt. Diese bestimmen die &#x2713; / &#x2014; in der Vergleichstabelle und der &laquo;Inklusive&raquo;-Anzeige.</p>
					</div>

					<?php if ( ! empty( $series ) && ! empty( $levels ) ) : ?>
					<table class="widefat mh-stv-features-matrix">
						<thead>
							<tr>
								<th class="mh-stv-fm-corner">Serie &#x2193; / Stufe &#x2192;</th>
								<?php foreach ( $levels as $level ) : ?>
									<th><?php echo esc_html( isset( $level['label'] ) ? $level['label'] : $level['key'] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $series as $serie ) :
								$skey  = isset( $serie['key'] ) ? $serie['key'] : '';
								$sname = ( isset( $serie['icon'] ) ? $serie['icon'] . ' ' : '' ) . ( isset( $serie['label'] ) ? $serie['label'] : $skey );
							?>
							<tr>
								<th><?php echo esc_html( $sname ); ?></th>
								<?php foreach ( $levels as $level ) :
									$lkey = isset( $level['key'] ) ? $level['key'] : '';
									$val  = '';
									if ( isset( $features_matrix[ $skey ][ $lkey ] ) ) {
										$val = $features_matrix[ $skey ][ $lkey ];
									}
									$fname = 'mh_stv_groups[' . intval( $gi ) . '][features_matrix][' . esc_attr( $skey ) . '][' . esc_attr( $lkey ) . ']';
								?>
								<td>
									<input type="text"
										name="<?php echo esc_attr( $fname ); ?>"
										value="<?php echo esc_attr( $val ); ?>"
										class="mh-stv-fm-input"
										placeholder="z.B. Schaukel, Kletterseil" />
								</td>
								<?php endforeach; ?>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php else : ?>
						<p class="description"><em>F&uuml;ge erst Serien und Stufen hinzu, um die Features-Matrix zu sehen.</em></p>
					<?php endif; ?>

					<!-- ═══ PRODUKT-IDS ═══ -->
					<div class="mh-stv-section-header" style="margin-top:8px">
						<h3>&#x1F4E6; Produkt-IDs (WooCommerce)</h3>
						<p class="description mh-stv-hint-rebuild"><em>&#x26A0; Wenn du Serien/Stufen &auml;nderst, erst speichern &mdash; Matrix + Produkt-Tabelle aktualisieren sich beim Neuladen.</em></p>
					</div>
					<table class="widefat mh-stv-matrix">
						<thead>
							<tr>
								<th></th>
								<?php foreach ( $levels as $level ) : ?>
									<th><?php echo esc_html( isset( $level['label'] ) ? $level['label'] : $level['key'] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $series as $serie ) :
								$skey = isset( $serie['key'] ) ? $serie['key'] : '';
							?>
							<tr>
								<th><?php echo esc_html( ( isset( $serie['icon'] ) ? $serie['icon'] . ' ' : '' ) . ( isset( $serie['label'] ) ? $serie['label'] : '' ) ); ?></th>
								<?php foreach ( $levels as $level ) :
									$lkey = isset( $level['key'] ) ? $level['key'] : '';
									$pid  = isset( $products[ $skey ][ $lkey ] ) ? absint( $products[ $skey ][ $lkey ] ) : 0;
									$fname = 'mh_stv_groups[' . intval( $gi ) . '][products][' . esc_attr( $skey ) . '][' . esc_attr( $lkey ) . ']';
								?>
								<td>
									<input type="number" name="<?php echo esc_attr( $fname ); ?>"
										value="<?php echo esc_attr( $pid ); ?>" min="0" class="small-text" placeholder="ID" />
									<?php
									if ( $pid > 0 && function_exists( 'wc_get_product' ) ) {
										$p = wc_get_product( $pid );
										if ( $p && is_object( $p ) ) {
											echo '<br><small class="mh-stv-product-hint">' . esc_html( $p->get_name() ) . ' &mdash; ' . wp_kses_post( $p->get_price_html() ) . '</small>';
										} elseif ( $pid > 0 ) {
											echo '<br><small class="mh-stv-product-hint mh-stv-error">Produkt nicht gefunden!</small>';
										}
									}
									?>
								</td>
								<?php endforeach; ?>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endforeach; ?>

				<?php submit_button( 'Speichern' ); ?>
			</form>

			<div class="mh-stv-card">
				<h2>Shortcodes</h2>
				<p><code>[mh_spielturm_vergleich]</code> &mdash; Konfigurator (erkennt Produkt automatisch)</p>
				<p><code>[mh_spielturm_vergleich id="12345"]</code> &mdash; mit expliziter ID</p>
				<p><code>[mh_360_viewer id="12345"]</code> &mdash; 360&deg; Viewer standalone</p>
			</div>
		</div>
		<?php
	}
}

MH_STV_Admin_Settings::boot();
