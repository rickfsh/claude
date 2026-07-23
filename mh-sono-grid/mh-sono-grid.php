<?php
/**
 * Plugin Name: MH SONO Grid
 * Description: Automatisches, filterbares Modell-Karten-Grid für die MEGA FLEX SONO Produktreihe. Produkte erscheinen automatisch, sobald sie in der SONO-Kategorie liegen; Oberflächen-Varianten werden über das Modell-Attribut zu einer Karte gruppiert. Shortcodes: [mh_sono_grid category="sono"], [mh_sono_count what="models|heights|products"].
 * Version: 1.1.0
 * Author: Mega-Holz
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * Changelog:
 * 1.1.0 — Modell-Fallback aus dem Produkttitel: das letzte komplett GROSS
 *         geschriebene Wort (nur Buchstaben, ≥3 Zeichen, Stoppliste via Filter
 *         mh_sono_model_stopwords) gilt als Modellname — Namenskonvention
 *         "… 180 x 120 cm ALBA - Anthrazit". pa_modell gewinnt weiterhin.
 * 1.0.0 — Erstversion: dynamische Produktabfrage der SONO-Kategorie (WP_Query + tax_query),
 *         Gruppierung zu Modell-Karten über pa_modell/pa_hoehe/pa_oberflaeche (mit
 *         Titel-Parsing-Fallback für Höhe/Oberfläche), Sektionen nach Höhe (Zäune) und
 *         Unterkategorie (Tore), client-seitige Filter (Produkttyp/Höhe/Oberfläche),
 *         Transient-Cache mit Preis-/Lager-Re-Stamp pro Request, Admin-Notice für
 *         unvollständig gepflegte Produkte, Zähler-Shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MH_SONO_VERSION', '1.1.0' );
define( 'MH_SONO_FILE', __FILE__ );
define( 'MH_SONO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MH_SONO_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'mh_sono_init', 20 );

function mh_sono_init() {
	require_once MH_SONO_PATH . 'includes/class-data-provider.php';
	require_once MH_SONO_PATH . 'includes/class-frontend.php';

	MH_SONO_Frontend::boot();

	if ( is_admin() ) {
		require_once MH_SONO_PATH . 'includes/class-admin-notice.php';
		MH_SONO_Admin_Notice::boot();
	}

	/* Cache-Invalidierung: Produkt- und Kategorie-Änderungen leeren alle
	   mh_sono_*-Transients (Bulk-Delete, Muster mh-spielturm-vergleich). */
	$invalidate = array( 'MH_SONO_Data_Provider', 'invalidate_cache' );
	add_action( 'woocommerce_update_product', $invalidate );
	add_action( 'woocommerce_new_product', $invalidate );
	add_action( 'save_post_product', $invalidate );
	add_action( 'woocommerce_product_set_stock', $invalidate );
	add_action( 'woocommerce_variation_set_stock', $invalidate );
	add_action( 'created_product_cat', $invalidate );
	add_action( 'edited_product_cat', $invalidate );
	add_action( 'delete_product_cat', $invalidate );
}
