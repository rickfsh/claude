<?php
/**
 * Admin Settings — Spielhaus-Konfigurator (Schritt 1: Datenmodell + UI).
 *
 * Verwaltet die Option `mh_stv_spielhaeuser`. Bewusst getrennt von der
 * Spielturm-Settings-Klasse, damit der Spielturm-Code unangetastet bleibt.
 *
 * ── Datenmodell (Option `mh_stv_spielhaeuser`) ──────────────────────────────
 *
 * Array von Gruppen. Eine Gruppe = ein Konfigurator (Auswahl zwischen N Häusern).
 *
 *   [
 *     'group_name' => string,
 *     'houses'     => [
 *         [
 *             'base_product_id' => int,    // das Spielhaus (WC-Produkt), NIE rabattiert
 *             'label'           => string, // Anzeigename im Auswahl-Toggle
 *             'layout'          => string, // 'standard' | 'wide' | 'compact' (Frontend-Renderer)
 *             'tag'             => string, // Badge auf der Modell-Karte (z.B. "Kompakt")
 *             'size'            => string, // Größen-/Maßangabe (Freitext)
 *             'size_pct'        => int,    // Größen-Balken 0–100 (Frontend-Vergleich)
 *             'blurb'           => string, // kurze Einordnung (1 Satz)
 *             'addons'          => [
 *                 [
 *                     'product_id'     => int,
 *                     'included'       => bool,  // true = "im Lieferumfang", kein Toggle, nie rabattiert
 *                     'default_qty'    => int,   // Startmenge (>=1)
 *                     'allow_qty'      => bool,  // Menge im Frontend wählbar (nur Extras)
 *                     'discount_tiers' => [      // nur relevant für Extras (included=false)
 *                         [ 'from_distinct' => int, 'percent' => float ],
 *                         // "ab N VERSCHIEDENEN Extras im Set → % auf DIESES Add-on"
 *                         // mehrere Zeilen möglich; aufsteigend nach from_distinct sortiert
 *                     ],
 *                 ],
 *             ],
 *         ],
 *     ],
 *   ]
 *
 * Rabatt-Logik (Engine kommt in Schritt 5):
 *   D = Anzahl verschiedener Extra-Add-ons im aktuellen Set.
 *   Für jedes Extra wird dessen höchste passende Tier-% (max from_distinct <= D) auf
 *   die ganze Positions-Summe (inkl. Menge) angewandt. Inkludierte + das Haus: 0 %.
 *
 * @package MH_Spielturm_Vergleich
 * @since   5.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MH_STV_Spielhaus_Settings {

	const OPTION = 'mh_stv_spielhaeuser';

	// Globaler Schalter: Lagerstatus im Konfigurator anzeigen (ja/nein).
	const OPTION_SHOW_STOCK = 'mh_stv_sh_show_stock';

	// Ziel-URL für den „Versandart"-Link im Produktinfo-Akkordeon (z. B. Versandseite).
	// Leer = Versandart wird als reiner Text gerendert (kein Link).
	const OPTION_SHIPPING_URL = 'mh_stv_sh_shipping_url';

	// Optionaler, fest gepinnter Feldschlüssel (ACF/Meta) der Montageanleitung-Datei.
	// Leer = Auto-Erkennung gängiger Schlüssel (montageanleitung, …).
	const OPTION_MANUAL_KEY = 'mh_stv_sh_manual_meta_key';

	// Anzahl sichtbarer Zubehör-Karten, bevor „Mehr anzeigen" den Rest einklappt.
	// 0 = nie einklappen (alle zeigen). Default 3.
	const OPTION_EXTRAS_VISIBLE = 'mh_stv_sh_extras_visible';

	// UTM-Parameter, die an GETEILTE Konfigurations-Links angehängt werden (v5.42.0).
	// Reiner Query-String OHNE führendes „?"/„&", z. B.
	// „utm_source=konfigurator&utm_medium=teilen&utm_campaign=spielhaus_konfig".
	// Leer = keine UTM-Parameter. Das Plugin ergänzt automatisch utm_content=desktop|mobile
	// (welcher Button), sofern nicht selbst gesetzt. Landet NUR im geteilten Link,
	// nie in der eigenen Adresszeile des Teilenden.
	const OPTION_SHARE_UTM = 'mh_stv_sh_share_utm';

	/** Erlaubte Layout-Varianten (Frontend-Renderer schaltet darauf um). */
	const LAYOUTS = array( 'standard', 'wide', 'compact' );

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

	/* ───────────────────────── Menü & Settings-Registrierung ───────────────────────── */

	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			'Spielhaus Konfigurator',
			'Spielhaus Konfigurator',
			'manage_woocommerce',
			'mh-stv-spielhaus',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'mh_stv_spielhaus_options', self::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize' ),
			'default'           => array(),
		) );
		register_setting( 'mh_stv_spielhaus_options', self::OPTION_SHOW_STOCK, array(
			'type'              => 'boolean',
			'sanitize_callback' => 'absint',
			'default'           => 1,
		) );
		register_setting( 'mh_stv_spielhaus_options', self::OPTION_SHIPPING_URL, array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		) );
		register_setting( 'mh_stv_spielhaus_options', self::OPTION_MANUAL_KEY, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'default'           => '',
		) );
		register_setting( 'mh_stv_spielhaus_options', self::OPTION_EXTRAS_VISIBLE, array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 3,
		) );
		register_setting( 'mh_stv_spielhaus_options', self::OPTION_SHARE_UTM, array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_share_utm' ),
			'default'           => 'utm_source=konfigurator&utm_medium=teilen&utm_campaign=spielhaus_konfig',
		) );
	}

	/**
	 * Sanitize des UTM-Query-Strings für geteilte Links. Erlaubt nur key=value-Paare,
	 * mit „&" getrennt; führende „?"/„&" werden entfernt, Keys/Values einzeln gesäubert.
	 */
	public function sanitize_share_utm( $raw ) {
		$raw = sanitize_text_field( (string) $raw );
		$raw = ltrim( $raw, '?&' );
		if ( '' === trim( $raw ) ) {
			return '';
		}
		$pairs = explode( '&', $raw );
		$clean = array();
		foreach ( $pairs as $pair ) {
			if ( '' === $pair || false === strpos( $pair, '=' ) ) {
				continue;
			}
			list( $k, $v ) = array_pad( explode( '=', $pair, 2 ), 2, '' );
			$k = preg_replace( '/[^A-Za-z0-9_\-]/', '', $k );
			$v = rawurlencode( rawurldecode( $v ) ); // normalisiert, hält den Wert URL-sicher
			if ( '' !== $k && '' !== $v ) {
				$clean[] = $k . '=' . $v;
			}
		}
		return implode( '&', $clean );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== 'woocommerce_page_mh-stv-spielhaus' ) {
			return;
		}
		// Bestehende Admin-CSS wird wiederverwendet (.mh-stv-card, .mh-stv-section-header …).
		wp_enqueue_style( 'dashicons' ); // Griff-Icon der Drag-and-Drop-Sortierung (im Admin i. d. R. ohnehin geladen).
		wp_enqueue_style( 'mh-stv-admin', MH_STV_URL . 'admin/admin.css', array(), MH_STV_VERSION );
		wp_enqueue_style( 'mh-stv-sh-admin', MH_STV_URL . 'admin/spielhaus-admin.css', array( 'mh-stv-admin' ), MH_STV_VERSION );
		wp_enqueue_script( 'mh-stv-sh-admin', MH_STV_URL . 'admin/spielhaus-admin.js', array(), MH_STV_VERSION, true );
	}

	/* ───────────────────────── Sanitization ───────────────────────── */

	/**
	 * Baut die gesamte Struktur sequenziell neu auf (Indizes egal → robust gegen
	 * client-seitiges Hinzufügen/Entfernen ohne Reindex).
	 */
	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$out = array();

		foreach ( $input as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$clean_group = array(
				'group_name' => sanitize_text_field( isset( $group['group_name'] ) ? $group['group_name'] : '' ),
				'houses'     => array(),
			);

			$houses = isset( $group['houses'] ) && is_array( $group['houses'] ) ? $group['houses'] : array();
			foreach ( $houses as $house ) {
				if ( ! is_array( $house ) ) {
					continue;
				}

				$base_id = absint( isset( $house['base_product_id'] ) ? $house['base_product_id'] : 0 );
				if ( $base_id < 1 ) {
					continue; // Ein Haus ohne Basis-Produkt ergibt keinen Konfigurator.
				}

				$layout = isset( $house['layout'] ) ? sanitize_key( $house['layout'] ) : 'standard';
				if ( ! in_array( $layout, self::LAYOUTS, true ) ) {
					$layout = 'standard';
				}

				$size_pct = isset( $house['size_pct'] ) ? absint( $house['size_pct'] ) : 0;
				if ( $size_pct > 100 ) {
					$size_pct = 100;
				}

				$clean_house = array(
					'base_product_id' => $base_id,
					'label'           => sanitize_text_field( isset( $house['label'] ) ? $house['label'] : '' ),
					'layout'          => $layout,
					// Frei pflegbare Modell-Details (Frontend-Karten).
					'tag'             => sanitize_text_field( isset( $house['tag'] ) ? $house['tag'] : '' ),
					'size'            => sanitize_text_field( isset( $house['size'] ) ? $house['size'] : '' ),
					'size_pct'        => $size_pct,
					'blurb'           => sanitize_textarea_field( isset( $house['blurb'] ) ? $house['blurb'] : '' ),
					'addons'          => array(),
				);

				$addons = isset( $house['addons'] ) && is_array( $house['addons'] ) ? $house['addons'] : array();
				foreach ( $addons as $addon ) {
					if ( ! is_array( $addon ) ) {
						continue;
					}

					$pid = absint( isset( $addon['product_id'] ) ? $addon['product_id'] : 0 );
					if ( $pid < 1 || $pid === $base_id ) {
						continue; // Ungültig oder = Haus selbst.
					}

					$included    = ! empty( $addon['included'] );
					$default_qty = max( 1, absint( isset( $addon['default_qty'] ) ? $addon['default_qty'] : 1 ) );
					$allow_qty   = ! empty( $addon['allow_qty'] );
					// Vorauswahl nur für Extras (bei „im Lieferumfang" bedeutungslos).
					$preselected = ! $included && ! empty( $addon['preselected'] );

					$tiers = array();
					if ( ! $included && isset( $addon['discount_tiers'] ) && is_array( $addon['discount_tiers'] ) ) {
						foreach ( $addon['discount_tiers'] as $tier ) {
							if ( ! is_array( $tier ) ) {
								continue;
							}
							$from = absint( isset( $tier['from_distinct'] ) ? $tier['from_distinct'] : 0 );
							$pct  = isset( $tier['percent'] ) ? floatval( str_replace( ',', '.', (string) $tier['percent'] ) ) : 0;
							$pct  = max( 0, min( 100, round( $pct, 2 ) ) );
							if ( $from < 2 ) {
								continue; // Stufen starten frühestens bei 2 verschiedenen Extras.
							}
							$tiers[] = array( 'from_distinct' => $from, 'percent' => $pct );
						}
						// Aufsteigend nach Schwelle sortieren → Engine kann „höchste passende" leicht auflösen.
						usort( $tiers, function ( $a, $b ) {
							return $a['from_distinct'] <=> $b['from_distinct'];
						} );
						// Doppelte Schwellen zusammenführen (letzte gewinnt).
						$by_threshold = array();
						foreach ( $tiers as $t ) {
							$by_threshold[ $t['from_distinct'] ] = $t;
						}
						$tiers = array_values( $by_threshold );
					}

					$clean_house['addons'][] = array(
						'product_id'     => $pid,
						'included'       => $included,
						'preselected'    => $preselected,
						'default_qty'    => $default_qty,
						'allow_qty'      => $allow_qty,
						'discount_tiers' => $tiers,
					);
				}

				$clean_group['houses'][] = $clean_house;
			}

			// Gruppe nur speichern, wenn sie mindestens ein Haus hat.
			if ( ! empty( $clean_group['houses'] ) ) {
				$out[] = $clean_group;
			}
		}

		return $out;
	}

	/* ───────────────────────── Render ───────────────────────── */

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Zugriff verweigert.' );
		}

		$groups = get_option( self::OPTION, array() );
		if ( ! is_array( $groups ) ) {
			$groups = array();
		}
		?>
		<div class="wrap mh-stv-admin mh-stv-sh-admin">
			<h1>Spielhaus Konfigurator &mdash; Einstellungen <span class="mh-stv-version">v<?php echo esc_html( MH_STV_VERSION ); ?></span></h1>
			<?php settings_errors(); ?>

			<p class="description" style="max-width:760px;margin:8px 0 16px;">
				Eine <strong>Gruppe</strong> = ein Konfigurator. Der Kunde w&auml;hlt zwischen den hinterlegten
				<strong>H&auml;usern</strong>. Jedes Haus hat eigene <strong>Add-ons</strong>: <em>inkludiert</em>
				(im Lieferumfang, kein Aufpreis) oder <em>extra</em> (zubuchbar, mit Mengenstaffel-Rabatt).
			</p>

			<form method="post" action="options.php" id="mh-stv-sh-form">
				<?php settings_fields( 'mh_stv_spielhaus_options' ); ?>

				<?php $show_stock = (int) get_option( self::OPTION_SHOW_STOCK, 1 ); ?>
				<?php $shipping_url = (string) get_option( self::OPTION_SHIPPING_URL, '' ); ?>
				<?php $manual_key  = (string) get_option( self::OPTION_MANUAL_KEY, '' ); ?>
				<?php $extras_visible = (int) get_option( self::OPTION_EXTRAS_VISIBLE, 3 ); ?>
				<?php $share_utm = (string) get_option( self::OPTION_SHARE_UTM, 'utm_source=konfigurator&utm_medium=teilen&utm_campaign=spielhaus_konfig' ); ?>
				<div class="mh-stv-card" style="padding:14px 18px;">
					<label style="display:inline-flex;align-items:center;gap:10px;font-weight:600;">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_SHOW_STOCK ); ?>" value="0" />
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_SHOW_STOCK ); ?>" value="1" <?php checked( $show_stock, 1 ); ?> />
						Lagerstatus im Konfigurator anzeigen
					</label>
					<p class="description" style="margin:6px 0 0;">Wenn deaktiviert, wird kein Lagerhinweis angezeigt. Nicht lieferbare Artikel bleiben in beiden F&auml;llen ausw&auml;hlbar.</p>

					<label style="display:block;margin:16px 0 4px;font-weight:600;">Versandart-Link (URL)</label>
					<input type="url" class="regular-text" placeholder="https://mega-holz.de/versand" name="<?php echo esc_attr( self::OPTION_SHIPPING_URL ); ?>" value="<?php echo esc_attr( $shipping_url ); ?>" />
					<p class="description" style="margin:6px 0 0;">Ziel f&uuml;r den &bdquo;Versandart&ldquo;-Link unter <em>Montage &amp; Lieferung</em>. Leer lassen &rarr; Versandart wird als reiner Text angezeigt. Die <strong>angezeigte</strong> Versandart kommt aus der Produkt-Eigenschaft &bdquo;Versandgruppe&ldquo; (Fallback: Versandklasse).</p>

					<label style="display:block;margin:16px 0 4px;font-weight:600;">Montageanleitung &ndash; Feldschl&uuml;ssel</label>
					<input type="text" class="regular-text" placeholder="montageanleitung" name="<?php echo esc_attr( self::OPTION_MANUAL_KEY ); ?>" value="<?php echo esc_attr( $manual_key ); ?>" />
					<p class="description" style="margin:6px 0 0;">Feldname (ACF) bzw. Meta-Key der Montageanleitung-Datei am Produkt. <strong>Leer lassen</strong> &rarr; automatische Erkennung (<code>montageanleitung</code> u.&nbsp;a.). Nur ausf&uuml;llen, wenn das Feld anders hei&szlig;t. Die Datei erscheint dann als Download unter <em>Montage &amp; Lieferung</em>.</p>

					<label style="display:block;margin:16px 0 4px;font-weight:600;">Zubeh&ouml;r: sichtbar vor &bdquo;Mehr anzeigen&ldquo;</label>
					<input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( self::OPTION_EXTRAS_VISIBLE ); ?>" value="<?php echo esc_attr( $extras_visible ); ?>" />
					<p class="description" style="margin:6px 0 0;">Ab wie vielen w&auml;hlbaren Zubeh&ouml;r-Karten der Rest hinter einem &bdquo;Mehr anzeigen&ldquo;-Button eingeklappt wird. <strong>0</strong> = nie einklappen (alle zeigen). Vorausgew&auml;hlte Zubeh&ouml;re bleiben immer sichtbar.</p>

					<label style="display:block;margin:16px 0 4px;font-weight:600;">UTM-Parameter f&uuml;r geteilte Links</label>
					<input type="text" class="large-text code" placeholder="utm_source=konfigurator&amp;utm_medium=teilen&amp;utm_campaign=spielhaus_konfig" name="<?php echo esc_attr( self::OPTION_SHARE_UTM ); ?>" value="<?php echo esc_attr( $share_utm ); ?>" />
					<p class="description" style="margin:6px 0 0;">Wird an die per &bdquo;Konfiguration teilen&ldquo; ausgegebenen Links angeh&auml;ngt &rarr; der Traffic taucht in Admetrics/GA als eigener Kanal auf. Reiner Query-String ohne f&uuml;hrendes <code>?</code>. <strong>Leer lassen</strong> = keine UTM-Parameter. Das Plugin erg&auml;nzt automatisch <code>utm_content=desktop</code> bzw. <code>=mobile</code> (welcher Button). Die UTM landen <strong>nur im geteilten Link</strong>, nicht in der eigenen Adresszeile.</p>
				</div>

				<div id="mh-stv-sh-groups">
					<?php
					if ( empty( $groups ) ) {
						echo $this->render_group_block( 0, array( 'group_name' => '', 'houses' => array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					} else {
						foreach ( $groups as $gi => $group ) {
							echo $this->render_group_block( (int) $gi, $group ); // phpcs:ignore WordPress.Security.EscapeOutput
						}
					}
					?>
				</div>

				<p>
					<button type="button" class="button button-secondary mh-sh-add-group">+ Konfigurator-Gruppe hinzuf&uuml;gen</button>
				</p>

				<?php submit_button( 'Speichern' ); ?>
			</form>

			<div class="mh-stv-card">
				<h2>Shortcode</h2>
				<p><code>[mh_spielhaus_konfigurator]</code> &mdash; erkennt das Haus automatisch (wenn die Seite eines Basis-Produkts aufgerufen wird).</p>
				<p><code>[mh_spielhaus_konfigurator group="0"]</code> &mdash; explizite Gruppe (0 = erste Gruppe).</p>
				<p class="description"><em>Modell-Details (Tag, Gr&ouml;&szlig;e, Balken, Kurzbeschreibung) erscheinen auf den Modell-Karten im Konfigurator. Preise/UVP kommen aus dem WooCommerce-Produkt.</em></p>
			</div>

			<?php $this->print_js_templates(); ?>
		</div>
		<?php
	}

	/**
	 * Eine komplette Gruppen-Karte (Gruppe → Häuser → Add-ons → Staffeln).
	 * $gi kann int (echte Daten) oder Token-String (JS-Template) sein.
	 */
	private function render_group_block( $gi, $group ) {
		$group_name = isset( $group['group_name'] ) ? $group['group_name'] : '';
		$houses     = isset( $group['houses'] ) && is_array( $group['houses'] ) ? $group['houses'] : array();
		$base       = self::OPTION . '[' . $gi . ']';

		ob_start();
		?>
		<div class="mh-stv-card mh-sh-group" data-g="<?php echo esc_attr( $gi ); ?>">
			<h2 class="mh-sh-group-head">
				<input type="text" name="<?php echo esc_attr( $base ); ?>[group_name]"
					value="<?php echo esc_attr( $group_name ); ?>" class="regular-text"
					placeholder="Gruppenname, z.B. Spielh&auml;user 2026" />
				<button type="button" class="button mh-stv-remove-row mh-sh-remove-group" title="Gruppe entfernen">&times;</button>
			</h2>

			<div class="mh-sh-houses">
				<?php
				if ( empty( $houses ) ) {
					echo $this->render_house_block( $gi, 0, array() ); // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					foreach ( $houses as $hi => $house ) {
						echo $this->render_house_block( $gi, $hi, $house ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
				}
				?>
			</div>

			<p>
				<button type="button" class="button mh-sh-add-house" data-g="<?php echo esc_attr( $gi ); ?>">+ Haus hinzuf&uuml;gen</button>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Ein Haus-Block (Basis-Produkt + Add-on-Tabelle).
	 */
	private function render_house_block( $gi, $hi, $house ) {
		$base_id  = isset( $house['base_product_id'] ) ? $house['base_product_id'] : '';
		$label    = isset( $house['label'] ) ? $house['label'] : '';
		$layout   = isset( $house['layout'] ) ? $house['layout'] : 'standard';
		$tag      = isset( $house['tag'] ) ? $house['tag'] : '';
		$size     = isset( $house['size'] ) ? $house['size'] : '';
		$size_pct = isset( $house['size_pct'] ) ? $house['size_pct'] : '';
		$blurb    = isset( $house['blurb'] ) ? $house['blurb'] : '';
		$addons   = isset( $house['addons'] ) && is_array( $house['addons'] ) ? $house['addons'] : array();
		$base     = self::OPTION . '[' . $gi . '][houses][' . $hi . ']';

		ob_start();
		?>
		<div class="mh-sh-house" data-g="<?php echo esc_attr( $gi ); ?>" data-h="<?php echo esc_attr( $hi ); ?>">
			<div class="mh-sh-house-head">
				<span class="mh-sh-house-badge">Haus</span>
				<label class="mh-sh-field">
					<span>Basis-Produkt-ID</span>
					<input type="number" min="0" class="small-text mh-sh-base-id"
						name="<?php echo esc_attr( $base ); ?>[base_product_id]"
						value="<?php echo esc_attr( $base_id ); ?>" placeholder="ID" />
					<?php echo $this->product_hint( $base_id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</label>
				<label class="mh-sh-field">
					<span>Label</span>
					<input type="text" class="regular-text"
						name="<?php echo esc_attr( $base ); ?>[label]"
						value="<?php echo esc_attr( $label ); ?>" placeholder="z.B. Spielhaus Lotte (klein)" />
				</label>
				<label class="mh-sh-field">
					<span>Layout</span>
					<select name="<?php echo esc_attr( $base ); ?>[layout]">
						<?php foreach ( self::LAYOUTS as $lv ) : ?>
							<option value="<?php echo esc_attr( $lv ); ?>" <?php selected( $layout, $lv ); ?>><?php echo esc_html( ucfirst( $lv ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="button" class="button mh-stv-remove-row mh-sh-remove-house" title="Haus entfernen">&times;</button>
			</div>

			<div class="mh-sh-section-label">Modell-Details (Frontend-Karte)</div>
			<div class="mh-sh-house-details">
				<label class="mh-sh-field">
					<span>Badge / Tag</span>
					<input type="text" class="regular-text"
						name="<?php echo esc_attr( $base ); ?>[tag]"
						value="<?php echo esc_attr( $tag ); ?>" placeholder="z.B. Kompakt, Allrounder, MEGA" />
				</label>
				<label class="mh-sh-field">
					<span>Gr&ouml;&szlig;e / Ma&szlig;e</span>
					<input type="text" class="regular-text"
						name="<?php echo esc_attr( $base ); ?>[size]"
						value="<?php echo esc_attr( $size ); ?>" placeholder="z.B. 2,16 &times; 2,98 m" />
				</label>
				<label class="mh-sh-field mh-sh-field-narrow">
					<span>Gr&ouml;&szlig;en-Balken (0&ndash;100)</span>
					<input type="number" min="0" max="100" class="small-text"
						name="<?php echo esc_attr( $base ); ?>[size_pct]"
						value="<?php echo esc_attr( $size_pct ); ?>" placeholder="z.B. 100" />
				</label>
				<label class="mh-sh-field mh-sh-field-full">
					<span>Kurzbeschreibung (1 Satz)</span>
					<textarea rows="2" class="large-text"
						name="<?php echo esc_attr( $base ); ?>[blurb]"
						placeholder="z.B. Das gr&ouml;&szlig;te Modell &mdash; maximaler Spielspa&szlig; auf XXL-Fl&auml;che."><?php echo esc_textarea( $blurb ); ?></textarea>
				</label>
			</div>

			<div class="mh-sh-section-label">Add-ons (Zubeh&ouml;r)</div>
			<table class="widefat mh-sh-addons-table">
				<thead>
					<tr>
						<th class="mh-sh-col-drag" aria-hidden="true"></th>
						<th class="mh-sh-col-pid">Produkt-ID</th>
						<th class="mh-sh-col-name">Produkt</th>
						<th class="mh-sh-col-incl">Inkludiert</th>
						<th class="mh-sh-col-presel">Voraus&shy;gew&auml;hlt</th>
						<th class="mh-sh-col-qty">Start&shy;menge</th>
						<th class="mh-sh-col-allowqty">Menge w&auml;hlbar</th>
						<th class="mh-sh-col-tiers">Rabatt-Staffel (nur Extras) &mdash; <em>ab N versch. Extras &rarr; %</em></th>
						<th class="mh-sh-col-actions"></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( empty( $addons ) ) {
						echo $this->render_addon_row( $gi, $hi, 0, array() ); // phpcs:ignore WordPress.Security.EscapeOutput
					} else {
						foreach ( $addons as $ai => $addon ) {
							echo $this->render_addon_row( $gi, $hi, $ai, $addon ); // phpcs:ignore WordPress.Security.EscapeOutput
						}
					}
					?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="9">
							<button type="button" class="button mh-sh-add-addon"
								data-g="<?php echo esc_attr( $gi ); ?>" data-h="<?php echo esc_attr( $hi ); ?>">+ Add-on hinzuf&uuml;gen</button>
						</td>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Eine Add-on-Zeile inkl. verschachtelter Rabatt-Staffel.
	 */
	private function render_addon_row( $gi, $hi, $ai, $addon ) {
		$pid         = isset( $addon['product_id'] ) ? $addon['product_id'] : '';
		$included    = ! empty( $addon['included'] );
		$preselected = ! empty( $addon['preselected'] );
		$default_qty = isset( $addon['default_qty'] ) ? $addon['default_qty'] : 1;
		$allow_qty   = ! empty( $addon['allow_qty'] );
		$tiers       = isset( $addon['discount_tiers'] ) && is_array( $addon['discount_tiers'] ) ? $addon['discount_tiers'] : array();
		$base        = self::OPTION . '[' . $gi . '][houses][' . $hi . '][addons][' . $ai . ']';

		ob_start();
		?>
		<tr class="mh-stv-row mh-sh-addon" data-g="<?php echo esc_attr( $gi ); ?>" data-h="<?php echo esc_attr( $hi ); ?>" data-a="<?php echo esc_attr( $ai ); ?>">
			<td class="mh-sh-col-drag mh-sh-drag-handle" title="Ziehen zum Sortieren" aria-label="Ziehen zum Sortieren"><span class="dashicons dashicons-menu" aria-hidden="true"></span></td>
			<td class="mh-sh-col-pid">
				<input type="number" min="0" class="small-text mh-sh-addon-pid"
					name="<?php echo esc_attr( $base ); ?>[product_id]"
					value="<?php echo esc_attr( $pid ); ?>" placeholder="ID" />
			</td>
			<td class="mh-sh-col-name"><?php echo $this->product_hint( $pid ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			<td class="mh-sh-col-incl">
				<label><input type="checkbox" class="mh-sh-incl-toggle"
					name="<?php echo esc_attr( $base ); ?>[included]" value="1" <?php checked( $included ); ?> /> im Lieferumfang</label>
			</td>
			<td class="mh-sh-col-presel">
				<label title="Startet im Frontend bereits angehakt (Kunde kann abwählen). Bei „im Lieferumfang" wirkungslos."><input type="checkbox" class="mh-sh-presel-toggle"
					name="<?php echo esc_attr( $base ); ?>[preselected]" value="1" <?php checked( $preselected ); ?> /> vorausgew&auml;hlt</label>
			</td>
			<td class="mh-sh-col-qty">
				<input type="number" min="1" class="small-text"
					name="<?php echo esc_attr( $base ); ?>[default_qty]"
					value="<?php echo esc_attr( max( 1, (int) $default_qty ) ); ?>" />
			</td>
			<td class="mh-sh-col-allowqty">
				<label><input type="checkbox"
					name="<?php echo esc_attr( $base ); ?>[allow_qty]" value="1" <?php checked( $allow_qty ); ?> /> ja</label>
			</td>
			<td class="mh-sh-col-tiers">
				<div class="mh-sh-tiers <?php echo $included ? 'mh-sh-tiers-disabled' : ''; ?>">
					<div class="mh-sh-tier-list">
						<?php
						if ( empty( $tiers ) ) {
							echo $this->render_tier_row( $gi, $hi, $ai, 0, array() ); // phpcs:ignore WordPress.Security.EscapeOutput
						} else {
							foreach ( $tiers as $ti => $tier ) {
								echo $this->render_tier_row( $gi, $hi, $ai, $ti, $tier ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
						}
						?>
					</div>
					<button type="button" class="button-link mh-sh-add-tier"
						data-g="<?php echo esc_attr( $gi ); ?>" data-h="<?php echo esc_attr( $hi ); ?>" data-a="<?php echo esc_attr( $ai ); ?>">+ Stufe</button>
					<p class="mh-sh-tiers-hint description">Bei inkludierten Add-ons ohne Wirkung (nie rabattiert).</p>
				</div>
			</td>
			<td class="mh-sh-col-actions">
				<button type="button" class="button mh-stv-remove-row mh-sh-remove-addon" title="Add-on entfernen">&times;</button>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Eine Rabattstufen-Zeile: „ab N → X %".
	 */
	private function render_tier_row( $gi, $hi, $ai, $ti, $tier ) {
		$from = isset( $tier['from_distinct'] ) ? $tier['from_distinct'] : '';
		$pct  = isset( $tier['percent'] ) ? $tier['percent'] : '';
		$base = self::OPTION . '[' . $gi . '][houses][' . $hi . '][addons][' . $ai . '][discount_tiers][' . $ti . ']';

		ob_start();
		?>
		<span class="mh-sh-tier" data-g="<?php echo esc_attr( $gi ); ?>" data-h="<?php echo esc_attr( $hi ); ?>" data-a="<?php echo esc_attr( $ai ); ?>" data-t="<?php echo esc_attr( $ti ); ?>">
			ab
			<input type="number" min="2" class="mh-sh-tier-from"
				name="<?php echo esc_attr( $base ); ?>[from_distinct]"
				value="<?php echo esc_attr( $from ); ?>" placeholder="2" />
			versch. &rarr;
			<input type="number" min="0" max="100" step="0.01" class="mh-sh-tier-pct"
				name="<?php echo esc_attr( $base ); ?>[percent]"
				value="<?php echo esc_attr( $pct ); ?>" placeholder="10" />%
			<button type="button" class="button-link mh-sh-remove-tier" title="Stufe entfernen">&times;</button>
		</span>
		<?php
		return ob_get_clean();
	}

	/**
	 * Kleiner Produktname/-preis-Hinweis (wie in der Spielturm-Matrix).
	 */
	private function product_hint( $pid ) {
		$pid = is_numeric( $pid ) ? absint( $pid ) : 0;
		if ( $pid < 1 || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$p = wc_get_product( $pid );
		if ( $p && is_object( $p ) ) {
			return '<small class="mh-stv-product-hint">' . esc_html( $p->get_name() ) . ' &mdash; ' . wp_kses_post( $p->get_price_html() ) . '</small>';
		}
		return '<small class="mh-stv-product-hint mh-stv-error">Produkt nicht gefunden!</small>';
	}

	/* ───────────────────────── JS-Templates ───────────────────────── */

	/**
	 * Versteckte <template>-Bausteine mit Platzhalter-Tokens ({{g}} usw.),
	 * die spielhaus-admin.js beim Hinzufügen klont. Indizes der neuen Zeilen
	 * vergibt das JS eindeutig; der Sanitizer reindexiert beim Speichern.
	 */
	private function print_js_templates() {
		?>
		<template id="mh-sh-tpl-group"><?php echo $this->render_group_block( '{{g}}', array( 'group_name' => '', 'houses' => array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
		<template id="mh-sh-tpl-house"><?php echo $this->render_house_block( '{{g}}', '{{h}}', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
		<template id="mh-sh-tpl-addon"><?php echo $this->render_addon_row( '{{g}}', '{{h}}', '{{a}}', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
		<template id="mh-sh-tpl-tier"><?php echo $this->render_tier_row( '{{g}}', '{{h}}', '{{a}}', '{{t}}', array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
		<?php
	}
}

MH_STV_Spielhaus_Settings::boot();
