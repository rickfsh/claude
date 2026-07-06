<?php
/**
 * Frontend — Spielhaus-Konfigurator.
 *
 * Shortcode [mh_spielhaus_konfigurator]. Gibt die Provider-Daten via
 * wp_localize_script aus; das JS rendert Haus-Auswahl, Add-on-Karten und die
 * Live-Summe inkl. Rabatt-Vorschau (D = Anzahl verschiedener Extras → Tier
 * pro Add-on).
 *
 * Schritt 4 (v5.11.0): Bundle-Add-to-Cart (Haus + gewählte Extras in einem
 * AJAX-Call, getaggt mit `_mh_sh_*`-Cart-Meta; Muster portiert aus
 * mh-bought-together).
 * Schritt 5 (v5.11.0): Rabatt-Engine als Positionspreis via
 * `woocommerce_before_calculate_totals` (KEINE Fee) — idempotent, reagiert
 * aufs Entfernen von Haus/Extras. D + Staffeln werden server-seitig aus der
 * Option re-aufgelöst (Frontend-Werten wird nie vertraut).
 *
 * @package MH_Spielturm_Vergleich
 * @since   5.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MH_STV_SH_Frontend {

	private static $instance = null;
	private $assets_registered = false;
	private $already_enqueued  = false;

	public static function boot() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'mh_spielhaus_konfigurator', array( $this, 'shortcode_output' ) );

		// Lazy-Detail (Beschreibung/Attribute/Reviews/Voll-Galerie eines Hauses).
		add_action( 'wp_ajax_mh_stv_sh_detail', array( $this, 'ajax_house_detail' ) );
		add_action( 'wp_ajax_nopriv_mh_stv_sh_detail', array( $this, 'ajax_house_detail' ) );

		// Schritt 4 — Bundle-Add-to-Cart (Haus + gewählte Extras in einem Call, getaggt).
		add_action( 'wp_ajax_mh_stv_sh_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_mh_stv_sh_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

		// Schritt 5 — Rabatt-Engine als Positionspreis (NICHT als Fee), idempotent,
		// reagiert aufs Entfernen von Haus/Extras (recompute aus dem Cart-Inhalt).
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_bundle_pricing' ), 20 );

		// Bundle-Meta beim Wiederaufbau des Carts aus der Session wiederherstellen (kritisch!).
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_cart_item' ), 10, 3 );

		// Sichtbarer Hinweis pro Warenkorb-Zeile (Set-Zugehörigkeit / Set-Rabatt).
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_meta_display' ), 10, 2 );

		// Bundle-Meta an die Bestellpositionen schreiben (Fulfillment/Support).
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );
	}

	/* ── Asset-Versionierung (Content-Hash, wie Spielturm) ── */

	private function asset_version( $relative_path ) {
		$file = MH_STV_PATH . $relative_path;
		return file_exists( $file ) ? substr( md5_file( $file ), 0, 8 ) : MH_STV_VERSION;
	}

	private function resolve_asset( $relative_path ) {
		if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			$min_path = preg_replace( '/\.(js|css)$/', '.min.$1', $relative_path );
			if ( file_exists( MH_STV_PATH . $min_path ) && filesize( MH_STV_PATH . $min_path ) > 0 ) {
				return array( MH_STV_URL . $min_path, $this->asset_version( $min_path ) );
			}
		}
		return array( MH_STV_URL . $relative_path, $this->asset_version( $relative_path ) );
	}

	public function register_assets() {
		if ( is_admin() ) {
			return;
		}

		// Geteiltes 360°-Modul (MH360) — wird vom Spielturm bereitgestellt; hier
		// defensiv registrieren, falls der Spielturm-Frontend-Loader nicht greift.
		// Gleiche Handles → keine Doppel-Ladung, wenn beide Konfiguratoren auf
		// einer Seite liegen.
		if ( ! wp_style_is( 'mh-stv-360', 'registered' ) ) {
			list( $s360_css_url, $s360_css_ver ) = $this->resolve_asset( 'public/css/spielturm-360.css' );
			wp_register_style( 'mh-stv-360', $s360_css_url, array(), $s360_css_ver );
		}
		if ( ! wp_script_is( 'mh-stv-360', 'registered' ) ) {
			list( $s360_js_url, $s360_js_ver ) = $this->resolve_asset( 'public/js/spielturm-360.js' );
			wp_register_script( 'mh-stv-360', $s360_js_url, array(), $s360_js_ver, true );
		}

		list( $css_url, $css_ver ) = $this->resolve_asset( 'public/css/spielhaus-konfigurator.css' );
		// Bewusst OHNE Style-Abhängigkeit (das Spielhaus-CSS ist selbsttragend; die
		// optionale 360°-CSS wird separat geladen, falls ein Modell spin_frames hat).
		// Eine Dependency-Kette macht das späte (Shortcode-)Enqueue in Oxygen fragiler.
		wp_register_style( 'mh-sh-front', $css_url, array(), $css_ver );

		list( $js_url, $js_ver ) = $this->resolve_asset( 'public/js/spielhaus-konfigurator.js' );
		wp_register_script( 'mh-sh-front', $js_url, array( 'mh-stv-360' ), $js_ver, true );

		$this->assets_registered = true;
	}

	/**
	 * Druckt die Konfigurator-Styles im Footer nach, falls der Shortcode erst
	 * nach dem <head> gerendert wurde (Page-Builder wie Oxygen). Nur die eigenen
	 * Handles; wp_print_styles() markiert sie als erledigt → kein Doppeldruck.
	 */
	public function print_late_styles() {
		$handles = array();
		if ( wp_style_is( 'mh-sh-front', 'registered' ) && ! wp_style_is( 'mh-sh-front', 'done' ) ) {
			$handles[] = 'mh-sh-front';
		}
		if ( wp_style_is( 'mh-stv-360', 'enqueued' ) && ! wp_style_is( 'mh-stv-360', 'done' ) ) {
			$handles[] = 'mh-stv-360';
		}
		if ( $handles ) {
			wp_print_styles( $handles );
		}
	}

	/* ── Shortcode ── */

	/**
	 * [mh_spielhaus_konfigurator] (Auto-Erkennung) oder [mh_spielhaus_konfigurator group="0"].
	 */
	public function shortcode_output( $atts ) {
		if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'MH_STV_Spielhaus_Data_Provider' ) ) {
			return '<!-- MH SH: WooCommerce / Provider nicht geladen -->';
		}

		$atts = shortcode_atts( array( 'id' => 0, 'group' => '' ), $atts, 'mh_spielhaus_konfigurator' );

		// Optionale Modell-Vorauswahl per URL: ?mh_haus=<product_id>. Damit lässt
		// sich von der Kategorieseite aus ein bestimmtes Modell (z. B. „Stelzenhaus
		// Maxi") direkt vorwählen — die ganze Gruppe (alle Modelle) erscheint, aber
		// das verlinkte Modell ist aktiv statt des ersten. Funktioniert auf
		// Produktseiten wie auf einer geteilten Konfigurator-Seite (group="N").
		// Read-only Navigation (keine Statusänderung) → kein Nonce nötig.
		$param   = (string) apply_filters( 'mh_stv_sh_preselect_param', 'mh_haus' );
		$url_pid = isset( $_GET[ $param ] ) ? absint( wp_unslash( $_GET[ $param ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$match = null;
		if ( $atts['group'] !== '' ) {
			$match = MH_STV_Spielhaus_Data_Provider::get_group_by_index( absint( $atts['group'] ) );
		} else {
			$pid = absint( $atts['id'] );
			if ( $pid < 1 ) {
				$pid = $url_pid;                        // URL-Vorauswahl als Gruppen-Anker
			}
			if ( $pid < 1 ) {
				$pid = $this->get_current_product_id(); // sonst aktuelle Produktseite
			}
			if ( $pid > 0 ) {
				$match = MH_STV_Spielhaus_Data_Provider::get_group_for_product( $pid );
			}
		}

		if ( ! $match ) {
			return '<!-- MH SH: Kein passender Konfigurator gefunden -->';
		}

		// Vorauswahl bestimmen: der URL-Parameter gewinnt, sofern das Produkt zur
		// aufgelösten Gruppe gehört (resolve_current() in build_configurator_data
		// fällt sonst sicher aufs erste Modell zurück). Ohne Parameter bleibt es
		// bei der vom Match gelieferten ID (Produktseite bzw. erstes Modell).
		$current_house_id = $url_pid > 0 ? $url_pid : (int) $match['current_house_id'];

		$data = MH_STV_Spielhaus_Data_Provider::build_configurator_data( $match['group'], $current_house_id );
		if ( empty( $data['houses'] ) ) {
			return '<!-- MH SH: Keine Häuser -->';
		}

		$settings = get_option( 'mh_stv_settings', array() );
		$accent   = '#e8910c';
		if ( ! empty( $settings['accent_color'] ) ) {
			$sane = sanitize_hex_color( $settings['accent_color'] );
			if ( $sane ) {
				$accent = $sane;
			}
		}

		if ( ! $this->already_enqueued ) {
			if ( ! $this->assets_registered ) {
				$this->register_assets();
			}
			wp_enqueue_style( 'mh-sh-front' );
			wp_enqueue_script( 'mh-sh-front' );

			// Mini-Cart-Fragmente für Live-Aktualisierung des Warenkorb-Zählers.
			wp_enqueue_script( 'wc-cart-fragments' );

			wp_localize_script( 'mh-sh-front', 'mhShData', array(
				'config'      => $data,
				'groupIndex'  => (int) $match['group_index'],
				'accentColor' => $accent,
				'showStock'   => (int) get_option( 'mh_stv_sh_show_stock', 1 ) === 1,
				'extrasVisible' => max( 0, absint( get_option( 'mh_stv_sh_extras_visible', 3 ) ) ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'detailNonce' => wp_create_nonce( 'mh_stv_sh_detail' ),
				'cartNonce'   => wp_create_nonce( 'mh_stv_sh_add_to_cart' ),
				'trackNonce'  => wp_create_nonce( 'mh_stv_track' ),
				'cartUrl'     => wc_get_cart_url(),
				// Trust/Payment aus den gemeinsamen Settings (mh_stv_settings) — wie im Spielturm.
				'paymentSelector' => ! empty( $settings['payment_selector'] ) ? trim( $settings['payment_selector'] ) : '',
				// Seiten-Elemente für die RECHTE Spalte (Verfügbarkeit/Versand, Kundenprojekte
				// etc.) — gemeinsames Setting mit dem Spielturm (embed_selectors).
				'embedSelectors' => ! empty( $settings['embed_selectors'] ) ? trim( $settings['embed_selectors'] ) : '',
				// Seiten-Elemente (Klarna etc.) für den vollbreiten Infobereich unter dem
				// Grid — gemeinsames Setting mit dem Spielturm (below_grid_selectors).
				'belowSelectors' => ! empty( $settings['below_grid_selectors'] ) ? trim( $settings['below_grid_selectors'] ) : '',
				// URL-Parameter für die Modell-Vorauswahl (v5.25.0). Wird im Frontend bei
				// jedem Modellwechsel per history.replaceState in die Adresszeile geschrieben
				// (v5.39.0) → geteilter Link öffnet beim Laden direkt das gewählte Modell.
				// Gleicher Filter wie die Server-Auflösung, damit JS und PHP synchron bleiben.
				'preselectParam' => (string) apply_filters( 'mh_stv_sh_preselect_param', 'mh_haus' ),
				// URL-Parameter für die geteilte Zubehör-Auswahl (v5.40.0). Rein
				// CLIENT-seitiger Round-Trip: das Frontend schreibt die gewählten Add-ons
				// (mh_cfg) in die URL und liest sie beim Laden wieder ein (applyUrlConfig);
				// der Server ignoriert den Parameter. Hier nur lokalisiert, damit JS einen
				// einzigen Namens-Anker hat und beide Parameter gemeinsam umbenennbar sind.
				'configParam'    => (string) apply_filters( 'mh_stv_sh_config_param', 'mh_cfg' ),
				// UTM-Parameter für geteilte Links (v5.42.0). Reiner Query-String ohne „?";
				// leer = keine UTM. Wird im Frontend nur an den GETEILTEN Link gehängt,
				// nicht in die Adresszeile geschrieben. Admin-Feld „UTM-Parameter".
				'shareUtm'       => (string) get_option( 'mh_stv_sh_share_utm', 'utm_source=konfigurator&utm_medium=teilen&utm_campaign=spielhaus_konfig' ),
				'priceFormat' => array(
					'thousand' => wc_get_price_thousand_separator(),
					'decimal'  => wc_get_price_decimal_separator(),
					'decimals' => absint( wc_get_price_decimals() ),
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'position' => get_option( 'woocommerce_currency_pos', 'left' ),
				),
				'i18n'        => array(
					'chooseHouse'   => 'Modell w&auml;hlen',
					'chooseHint'    => 'Gleiche Ausstattung &mdash; der Unterschied ist die Gr&ouml;&szlig;e',
					'configure'     => 'Zubeh&ouml;r konfigurieren',
					'configureHint' => 'Ausgew&auml;hltes Zubeh&ouml;r wird im Set automatisch zus&auml;tzlich rabattiert',
					'included'      => '&#10003; im Lieferumfang',
					'inclValue'     => 'inklusive',
					'priceSuffix'   => 'inkl. MwSt, zzgl. Versand',
					'addToCart'     => 'In den Warenkorb',
					'adding'        => 'Wird hinzugef&uuml;gt&hellip;',
					'added'         => 'Im Warenkorb',
					'toCart'        => 'Zum Warenkorb',
					'error'         => 'Etwas ist schiefgelaufen. Bitte erneut versuchen.',
					'summaryTitle'  => 'Deine Konfiguration',
					'houseLabel'    => 'Spielhaus',
					'extrasLabel'   => 'Zubeh&ouml;r',
					'savings'       => 'Set-Vorteil',
					'youSave'       => 'Du sparst',
					'from'          => 'ab',
					'details'       => 'Details',
					'inSet'         => 'im Set',
					'notify'        => 'Per E-Mail benachrichtigen, sobald wieder verf&uuml;gbar &rarr;',
					'oos'           => 'Zurzeit nicht auf Lager',
					'total'         => 'Gesamt',
					'qty'           => 'Menge',
					'lbClose'       => 'Schlie&szlig;en',
					'lbPrev'        => 'Vorheriges Bild',
					'lbNext'        => 'N&auml;chstes Bild',
					'lbImage'       => 'Bild',
					'lbOf'          => 'von',
					// v5.41.0 — Konfiguration teilen (Variante A Desktop + Variante E mobil).
					'shareConfig'   => 'Konfiguration teilen',
					'shareCopied'   => 'Link kopiert',
					'shareTitle'    => 'Meine Stelzenhaus-Konfiguration',
					// v5.14.0 — Produktinfo-Akkordeon + flacher Spar-Hinweis.
					'saved'         => 'gespart',
					'accDescription'=> 'Produktbeschreibung',
					'accTech'       => 'Technische Daten',
					'accShipping'   => 'Montage &amp; Lieferung',
					'accManufacturer'=> 'Herstellerinformationen',
					'accShipMethod' => 'Versandart',
					'accSku'        => 'Artikelnummer',
					'accCategory'   => 'Kategorie',
					'accLoading'    => 'Wird geladen&hellip;',
					// v5.24.0 — Produktinfo als Tabs (ARIA-Label der Tab-Leiste + Leerzustand).
					'infoTabsLabel' => 'Produktinformationen',
					'infoNone'      => 'Keine Produktinformationen vorhanden.',
					// v5.24.1 — Ladefehler des Detail-Endpoints (mit Retry-Button) vs. „leer".
					'infoError'     => 'Produktinformationen konnten nicht geladen werden.',
					'retry'         => 'Erneut versuchen',
					'showMore'      => 'Mehr Zubeh&ouml;r anzeigen',
					'showLess'      => 'Weniger anzeigen',
					// v5.21.0 — Mobile-Sticky-Bar: Hinweis ohne gewähltes Zubehör.
					'mbNoExtras'    => 'Ohne Zubeh&ouml;r',
					// Trust-Signale aus den gemeinsamen Settings (wie Spielturm).
					'trust1'        => ! empty( $settings['trust_1'] ) ? $settings['trust_1'] : '',
					'trust2'        => ! empty( $settings['trust_2'] ) ? $settings['trust_2'] : '',
					'trust3'        => ! empty( $settings['trust_3'] ) ? $settings['trust_3'] : '',
				),
			) );

			$this->already_enqueued = true;

			// Oxygen (und andere Page-Builder) rendern den Shortcode erst beim
			// Content — also NACH dem <head>. Spät via Shortcode enqueuete STYLES
			// werden dann nicht zuverlässig ausgegeben (Skripte landen im Footer und
			// laufen, daher baut das JS die DOM auf — aber ohne CSS = ungestylt).
			// Deshalb die Styles im Footer sicher nachdrucken, wenn <head> schon
			// durch ist. wp_print_styles() markiert die Handles als erledigt → kein
			// Doppeldruck, falls der Core sie ohnehin noch ausgibt.
			if ( did_action( 'wp_head' ) ) {
				add_action( 'wp_footer', array( $this, 'print_late_styles' ), 1 );
			}
		}

		$schema   = $this->build_schema_json_ld( $data );
		$skeleton = $this->build_skeleton_html();

		return $schema
			. '<div id="mh-sh-configurator" style="--mh-stv-accent:' . esc_attr( $accent ) . '">'
			. $skeleton
			. '</div>';
	}

	/* ── Lazy-Detail AJAX ── */

	public function ajax_house_detail() {
		check_ajax_referer( 'mh_stv_sh_detail', 'nonce' );
		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( $pid < 1 ) {
			wp_send_json_error( array( 'message' => 'Ungültige ID.' ), 400 );
		}
		$detail = MH_STV_Spielhaus_Data_Provider::get_house_detail( $pid );
		if ( ! $detail ) {
			wp_send_json_error( array( 'message' => 'Nicht gefunden.' ), 404 );
		}
		wp_send_json_success( $detail );
	}

	/* ══════════════════════════════════════════════════════════════════════
	   SCHRITT 4 — Bundle-Add-to-Cart
	   ----------------------------------------------------------------------
	   Haus + alle gewählten Extras in EINEM Call. Jede Warenkorb-Position wird
	   mit Bundle-Meta getaggt (portiert aus mh-bought-together, eigener
	   `_mh_sh_*`-Namespace). Inkludierte Add-ons werden NICHT in den Warenkorb
	   gelegt — sie gehören zum Lieferumfang des Hauses (Preis 0, nicht in der
	   Summe). Soll das geändert werden (z. B. 0-€-Positionen fürs Picking),
	   ist das hier der einzige Eingriffspunkt.

	   Cart-Item-Meta (Source of Truth bleibt die Option, NICHT diese Werte):
	     _mh_sh_bundle_id    eindeutige Bundle-ID (gruppiert die Positionen)
	     _mh_sh_role         'house' | 'extra'
	     _mh_sh_source_house base_product_id des Hauses (für Re-Auflösung)
	     _mh_sh_group        Gruppen-Index (für Re-Auflösung der Staffeln)
	   ══════════════════════════════════════════════════════════════════════ */

	public function ajax_add_to_cart() {
		check_ajax_referer( 'mh_stv_sh_add_to_cart', 'nonce' );

		// Rate-Limit (analog Spielturm): max 12 Add-to-Cart / Minute / Session.
		$rate_key = 'mh_stv_sh_atc_' . substr( md5( wp_get_session_token() . ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 12 );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 12 ) {
			wp_send_json_error( array( 'message' => 'Zu viele Anfragen. Bitte einen Moment warten.' ), 429 );
		}
		set_transient( $rate_key, $count + 1, 60 );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => 'Warenkorb nicht verfügbar.' ), 500 );
		}

		$group_index = isset( $_POST['group'] ) ? absint( $_POST['group'] ) : 0;
		$house_id    = isset( $_POST['house_id'] ) ? absint( $_POST['house_id'] ) : 0;

		// Gewünschte Extras [{id, qty}] einlesen (dedupe per id).
		$req_extras = array();
		if ( isset( $_POST['extras'] ) && is_array( $_POST['extras'] ) ) {
			foreach ( wp_unslash( $_POST['extras'] ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$id  = absint( $row['id'] ?? 0 );
				$qty = max( 1, absint( $row['qty'] ?? 1 ) );
				if ( $id ) {
					$req_extras[ $id ] = $qty; // gleiche ID → letzter Wert gewinnt
				}
			}
		}

		// ── Autoritative Re-Auflösung aus der Option (Frontend-Werten NIE vertrauen) ──
		$house_def = MH_STV_Spielhaus_Data_Provider::get_house_def( $group_index, $house_id );
		if ( ! $house_def ) {
			wp_send_json_error( array( 'message' => 'Konfiguration nicht gefunden.' ), 404 );
		}

		$house_product = wc_get_product( $house_id );
		if ( ! $house_product || ! $house_product->is_purchasable() || ! $house_product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => 'Das gewählte Spielhaus ist derzeit nicht verfügbar.' ), 409 );
		}

		// Gültige (nicht-inkludierte) Add-ons dieses Hauses → Map product_id => def.
		$valid_addons = array();
		if ( ! empty( $house_def['addons'] ) && is_array( $house_def['addons'] ) ) {
			foreach ( $house_def['addons'] as $addon ) {
				$pid = absint( $addon['product_id'] ?? 0 );
				if ( $pid && empty( $addon['included'] ) ) {
					$valid_addons[ $pid ] = $addon;
				}
			}
		}

		// Extras validieren (Whitelist + Kaufbarkeit + Lager + allow_qty).
		$to_add  = array();
		$notices = array();
		foreach ( $req_extras as $id => $qty ) {
			if ( ! isset( $valid_addons[ $id ] ) ) {
				continue; // kein gültiges Extra dieses Hauses → still verwerfen
			}
			$addon       = $valid_addons[ $id ];
			$allow_qty   = ! empty( $addon['allow_qty'] );
			$default_qty = max( 1, absint( $addon['default_qty'] ?? 1 ) );
			if ( ! $allow_qty ) {
				$qty = $default_qty; // feste Menge erzwingen, wenn nicht änderbar
			}

			$p = wc_get_product( $id );
			if ( ! $p || ! $p->is_purchasable() || ! $p->is_in_stock() ) {
				$notices[] = sprintf( '%s ist nicht verfügbar und wurde ausgelassen.', $p ? $p->get_name() : ( '#' . $id ) );
				continue;
			}
			if ( $p->managing_stock() && ! $p->backorders_allowed() ) {
				$avail = max( 0, (int) $p->get_stock_quantity() );
				if ( $avail < 1 ) {
					$notices[] = sprintf( '%s ist ausverkauft und wurde ausgelassen.', $p->get_name() );
					continue;
				}
				if ( $qty > $avail ) {
					$qty       = $avail;
					$notices[] = sprintf( '%s: nur %d auf Lager, Menge angepasst.', $p->get_name(), $avail );
				}
			}
			if ( $qty > 99 ) {
				$qty = 99;
			}
			$to_add[] = array( 'id' => $id, 'qty' => $qty );
		}

		// Eindeutige Bundle-ID.
		$bundle_id = 'mhsh_' . $house_id . '_' . time() . '_' . wp_generate_password( 4, false, false );

		$common = array(
			'_mh_sh_bundle_id'    => $bundle_id,
			'_mh_sh_source_house' => $house_id,
			'_mh_sh_group'        => $group_index,
		);

		$added_keys = array();

		// Haus zuerst (Menge 1, role=house) — Pflicht; ohne Haus kein Bundle.
		$house_key = WC()->cart->add_to_cart( $house_id, 1, 0, array(), array_merge( $common, array( '_mh_sh_role' => 'house' ) ) );
		if ( ! $house_key ) {
			wp_send_json_error( array( 'message' => 'Das Spielhaus konnte nicht hinzugefügt werden.' ), 500 );
		}
		$added_keys[] = $house_key;

		// Dann die Extras (role=extra).
		foreach ( $to_add as $ex ) {
			$k = WC()->cart->add_to_cart( $ex['id'], $ex['qty'], 0, array(), array_merge( $common, array( '_mh_sh_role' => 'extra' ) ) );
			if ( $k ) {
				$added_keys[] = $k;
			}
		}

		// Mini-Cart-Fragmente fürs sofortige UI-Update.
		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		wp_send_json_success( array(
			'message'    => 'Konfiguration zum Warenkorb hinzugefügt.',
			'added'      => count( $added_keys ),
			'bundle_id'  => $bundle_id,
			'cart_keys'  => $added_keys,
			'cart_count' => WC()->cart->get_cart_contents_count(),
			'cart_url'   => wc_get_cart_url(),
			'notices'    => $notices,
			'fragments'  => apply_filters( 'woocommerce_add_to_cart_fragments', array(
				'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
			) ),
			'cart_hash'  => WC()->cart->get_cart_hash(),
		) );
	}

	/* ══════════════════════════════════════════════════════════════════════
	   SCHRITT 5 — Rabatt-Engine als Positionspreis
	   ----------------------------------------------------------------------
	   Läuft an `woocommerce_before_calculate_totals`. KEINE Fee — der Rabatt
	   wird direkt auf den Stückpreis der Extra-Positionen gerechnet
	   (set_price), WooCommerce multipliziert mit der Menge.

	   Regel: D = Anzahl VERSCHIEDENER Extra-Positionen desselben Bundles, die
	   aktuell noch im Warenkorb liegen. Pro Extra greift die höchste Staffel
	   mit from_distinct <= D (aus der Option, nicht aus dem Frontend). Haus &
	   inkludierte Positionen werden nie angefasst.

	   - Idempotent: der Basispreis wird einmal pro Request (vor der ersten
	     Mutation) je Cart-Item-Key festgehalten → kein „Aufschaukeln", auch
	     wenn der Hook mehrfach feuert.
	   - Reagiert aufs Entfernen: D wird bei jedem Lauf frisch aus dem Cart
	     gezählt; fehlt das Haus des Bundles, entfällt der Rabatt komplett
	     (Extras zurück auf vollen Preis).
	   ══════════════════════════════════════════════════════════════════════ */

	public function apply_bundle_pricing( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart || ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		$items = $cart->get_cart();

		// 1) Nach Bundle gruppieren: Haus vorhanden? + Extra-Positionen sammeln.
		$bundles = array();
		foreach ( $items as $key => $ci ) {
			if ( empty( $ci['_mh_sh_bundle_id'] ) || empty( $ci['_mh_sh_role'] ) ) {
				continue;
			}
			if ( empty( $ci['data'] ) || ! is_a( $ci['data'], 'WC_Product' ) ) {
				continue;
			}
			$bid = (string) $ci['_mh_sh_bundle_id'];
			if ( ! isset( $bundles[ $bid ] ) ) {
				$bundles[ $bid ] = array( 'has_house' => false, 'extra_keys' => array() );
			}
			if ( 'house' === $ci['_mh_sh_role'] ) {
				$bundles[ $bid ]['has_house'] = true;
			} elseif ( 'extra' === $ci['_mh_sh_role'] ) {
				$bundles[ $bid ]['extra_keys'][] = $key;
			}
		}

		if ( empty( $bundles ) ) {
			return;
		}

		// 2) Pro Bundle Rabatt rechnen und Positionspreis setzen.
		foreach ( $bundles as $b ) {
			$distinct = count( $b['extra_keys'] ); // D = verschiedene Extras im Cart

			foreach ( $b['extra_keys'] as $key ) {
				if ( ! isset( $items[ $key ] ) ) {
					continue;
				}
				$ci      = $items[ $key ];
				$product = $ci['data'];

				// Basispreis idempotent festhalten (vor jeder Mutation, je Key).
				$base = self::base_price( $key, $product->get_price() );

				$pct = 0.0;
				if ( $b['has_house'] ) {
					$tiers = MH_STV_Spielhaus_Data_Provider::get_addon_tiers(
						absint( $ci['_mh_sh_group'] ?? 0 ),
						absint( $ci['_mh_sh_source_house'] ?? 0 ),
						absint( $ci['product_id'] ?? $product->get_id() )
					);
					$pct = MH_STV_Spielhaus_Data_Provider::tier_percent( $tiers, $distinct );
				}

				$new = ( $pct > 0 ) ? round( $base * ( 1 - $pct / 100 ), wc_get_price_decimals() ) : $base;
				$product->set_price( $new );

				// Angewendeten % für Anzeige/Bestellung am Cart-Item hinterlegen (Runtime).
				$cart->cart_contents[ $key ]['_mh_sh_applied_pct'] = $pct;
			}
		}
	}

	/**
	 * Idempotenter Basispreis-Speicher (statisch pro Request, je Cart-Item-Key).
	 * Erster Aufruf hält den unmutierten Katalogpreis fest; spätere Aufrufe
	 * (mehrfaches Feuern des Hooks) liefern denselben Basiswert zurück.
	 */
	private static function base_price( $key, $current ) {
		static $store = array();
		if ( ! array_key_exists( $key, $store ) ) {
			$store[ $key ] = (float) $current;
		}
		return $store[ $key ];
	}

	/* ── Bundle-Meta aus der Session wiederherstellen (sonst Verlust bei Reload) ── */

	public function restore_cart_item( $cart_item, $values, $key ) {
		foreach ( array( '_mh_sh_bundle_id', '_mh_sh_role', '_mh_sh_source_house', '_mh_sh_group' ) as $mk ) {
			if ( isset( $values[ $mk ] ) ) {
				$cart_item[ $mk ] = $values[ $mk ];
			}
		}
		return $cart_item;
	}

	/* ── Sichtbarer Hinweis pro Warenkorb-/Checkout-Zeile ── */

	public function cart_item_meta_display( $item_data, $cart_item ) {
		if ( empty( $cart_item['_mh_sh_bundle_id'] ) || empty( $cart_item['_mh_sh_role'] ) ) {
			return $item_data;
		}

		if ( 'house' === $cart_item['_mh_sh_role'] ) {
			$item_data[] = array(
				'key'     => 'Konfiguration',
				'value'   => 'Spielhaus-Set',
				'display' => 'Spielhaus-Set',
			);
		} elseif ( 'extra' === $cart_item['_mh_sh_role'] ) {
			$pct = isset( $cart_item['_mh_sh_applied_pct'] ) ? floatval( $cart_item['_mh_sh_applied_pct'] ) : 0;
			if ( $pct > 0 ) {
				$val = '&minus;' . self::fmt_pct( $pct ) . '&nbsp;% Set-Vorteil';
				$item_data[] = array(
					'key'     => 'Set-Rabatt',
					'value'   => wp_strip_all_tags( $val ),
					'display' => $val,
				);
			} else {
				$item_data[] = array(
					'key'     => 'Konfiguration',
					'value'   => 'Spielhaus-Zubehör',
					'display' => 'Spielhaus-Zubehör',
				);
			}
		}
		return $item_data;
	}

	/** Prozent hübsch formatieren (10.00 → "10", 7.50 → "7,5"). */
	private static function fmt_pct( $pct ) {
		$s = number_format( (float) $pct, 2, ',', '' );
		$s = rtrim( $s, '0' );
		$s = rtrim( $s, ',' );
		return $s === '' ? '0' : $s;
	}

	/* ── Bundle-Meta an die Bestellpositionen schreiben ── */

	public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['_mh_sh_bundle_id'] ) ) {
			return;
		}
		// Technische Meta (versteckt — Underscore-Prefix).
		$item->add_meta_data( '_mh_sh_bundle_id', sanitize_text_field( $values['_mh_sh_bundle_id'] ), true );
		if ( ! empty( $values['_mh_sh_role'] ) ) {
			$item->add_meta_data( '_mh_sh_role', sanitize_key( $values['_mh_sh_role'] ), true );
		}
		if ( ! empty( $values['_mh_sh_source_house'] ) ) {
			$item->add_meta_data( '_mh_sh_source_house', absint( $values['_mh_sh_source_house'] ), true );
		}

		// Angewendeten Rabatt autoritativ neu berechnen (nicht der Runtime-Wert).
		if ( ! empty( $values['_mh_sh_role'] ) && 'extra' === $values['_mh_sh_role'] && function_exists( 'WC' ) && WC()->cart ) {
			list( $distinct, $has_house ) = $this->bundle_state( WC()->cart, $values['_mh_sh_bundle_id'] );
			$pct = 0.0;
			if ( $has_house ) {
				$tiers = MH_STV_Spielhaus_Data_Provider::get_addon_tiers(
					absint( $values['_mh_sh_group'] ?? 0 ),
					absint( $values['_mh_sh_source_house'] ?? 0 ),
					absint( $values['product_id'] ?? 0 )
				);
				$pct = MH_STV_Spielhaus_Data_Provider::tier_percent( $tiers, $distinct );
			}
			if ( $pct > 0 ) {
				// Sichtbare Meta für Support/Bestellübersicht.
				$item->add_meta_data( 'Set-Rabatt', '−' . self::fmt_pct( $pct ) . ' %', true );
				$item->add_meta_data( '_mh_sh_applied_pct', round( $pct, 2 ), true );
			}
		}
	}

	/**
	 * Zählt für ein Bundle: D (verschiedene Extras im Cart) + ob das Haus noch da ist.
	 *
	 * @return array [ int $distinct, bool $has_house ]
	 */
	private function bundle_state( $cart, $bundle_id ) {
		$distinct  = 0;
		$has_house = false;
		foreach ( $cart->get_cart() as $ci ) {
			if ( empty( $ci['_mh_sh_bundle_id'] ) || (string) $ci['_mh_sh_bundle_id'] !== (string) $bundle_id ) {
				continue;
			}
			$role = $ci['_mh_sh_role'] ?? '';
			if ( 'house' === $role ) {
				$has_house = true;
			} elseif ( 'extra' === $role ) {
				$distinct++;
			}
		}
		return array( $distinct, $has_house );
	}

	/* ── Schema (aktuelles Haus) ── */

	private function build_schema_json_ld( $data ) {
		$current = null;
		foreach ( $data['houses'] as $h ) {
			if ( ! empty( $h['is_current'] ) ) {
				$current = $h;
				break;
			}
		}
		if ( ! $current ) {
			return '';
		}

		$avail_map = array(
			'instock'     => 'https://schema.org/InStock',
			'onbackorder' => 'https://schema.org/PreOrder',
			'outofstock'  => 'https://schema.org/OutOfStock',
		);
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $current['name'],
			'url'         => $current['url'],
			'description' => wp_strip_all_tags( $current['short_description'] ),
			'offers'      => array(
				'@type'         => 'Offer',
				'price'         => number_format( $current['price'], 2, '.', '' ),
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => $avail_map[ $current['stock_status'] ] ?? 'https://schema.org/InStock',
				'url'           => $current['url'],
			),
		);
		if ( ! empty( $current['image'] ) ) {
			$schema['image'] = $current['image'];
		}
		if ( $current['average_rating'] > 0 && $current['review_count'] > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => number_format( $current['average_rating'], 1, '.', '' ),
				'reviewCount' => $current['review_count'],
			);
		}

		return '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>';
	}

	/* ── Skeleton ── */

	private function build_skeleton_html() {
		$pulse = 'background:#e5e3df;border-radius:10px;animation:mhShPulse 1.5s ease-in-out infinite;';
		return '<div class="mh-sh-skeleton" aria-hidden="true">'
			. '<style>@keyframes mhShPulse{0%,100%{opacity:.4}50%{opacity:.8}}</style>'
			. '<div style="' . $pulse . 'height:64px;width:100%;margin-bottom:20px"></div>'
			. '<div style="display:grid;grid-template-columns:1fr 1fr;gap:32px">'
			. '<div style="' . $pulse . 'aspect-ratio:4/3;width:100%"></div>'
			. '<div>'
			. '<div style="' . $pulse . 'height:30px;width:60%;margin-bottom:16px"></div>'
			. '<div style="' . $pulse . 'height:80px;width:100%;margin-bottom:12px"></div>'
			. '<div style="' . $pulse . 'height:80px;width:100%;margin-bottom:12px"></div>'
			. '<div style="' . $pulse . 'height:52px;width:100%"></div>'
			. '</div></div></div>';
	}

	/* ── aktuelles Produkt erkennen ── */
	private function get_current_product_id() {
		global $product;
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$id = $product->get_id();
			if ( $id > 0 ) {
				return $id;
			}
		}
		global $post;
		if ( is_object( $post ) && $post->post_type === 'product' && $post->ID > 0 ) {
			return $post->ID;
		}
		$queried = get_queried_object_id();
		if ( $queried > 0 && get_post_type( $queried ) === 'product' ) {
			return $queried;
		}
		return 0;
	}
}

MH_STV_SH_Frontend::boot();
