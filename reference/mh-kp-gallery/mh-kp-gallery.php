<?php
/**
 * Plugin Name: MH Shop the Look
 * Description: "Shop the Look" Galerie für Kundenprojekte. Produkt-Pins direkt auf dem Bild platzieren. Frontend Upload-Portal für Mitarbeiter. Shortcodes: [mh_shop_the_look], [mh_kundenprojekte_grid], [mh_kundenprojekte_produkt], [mh_upload_portal]
 * Version: 3.8.5
 * Author: Mega-Holz GmbH & Co. KG
 * Text Domain: mh-stl
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MH_STL_VERSION', '3.8.5' );
define( 'MH_STL_PATH', plugin_dir_path( __FILE__ ) );
define( 'MH_STL_URL', plugin_dir_url( __FILE__ ) );
define( 'MH_STL_POST_TYPE', 'kundenprojekt' );

/* ── Includes ─────────────────────────────────────────────────── */
require_once MH_STL_PATH . 'includes/class-cpt.php';
require_once MH_STL_PATH . 'includes/class-admin.php';
require_once MH_STL_PATH . 'includes/class-frontend.php';
require_once MH_STL_PATH . 'includes/class-grid.php';
require_once MH_STL_PATH . 'includes/class-product-gallery.php';
require_once MH_STL_PATH . 'includes/class-ftp-handler.php';
require_once MH_STL_PATH . 'includes/class-rest-api.php';
require_once MH_STL_PATH . 'includes/class-upload-manager.php';
require_once MH_STL_PATH . 'includes/class-upload-portal.php';

/* ── Hooks: Core ──────────────────────────────────────────────── */
add_action( 'init', [ 'MH_STL_CPT', 'register' ] );
add_action( 'add_meta_boxes', [ 'MH_STL_Admin', 'register_metabox' ] );
add_action( 'save_post_' . MH_STL_POST_TYPE, [ 'MH_STL_Admin', 'save' ] );
add_action( 'admin_enqueue_scripts', [ 'MH_STL_Admin', 'enqueue' ] );
add_action( 'wp_ajax_mh_stl_search_products', [ 'MH_STL_Admin', 'ajax_search' ] );

/* ── Hooks: Shortcodes & Frontend AJAX ────────────────────────── */
add_shortcode( 'mh_shop_the_look', [ 'MH_STL_Frontend', 'render' ] );
add_shortcode( 'mh_kundenprojekte_grid', [ 'MH_STL_Grid', 'render' ] );
add_shortcode( 'mh_kundenprojekte_produkt', [ 'MH_STL_Product_Gallery', 'render' ] );
add_action( 'wp_ajax_mh_stl_grid_load', [ 'MH_STL_Grid', 'ajax_load' ] );
add_action( 'wp_ajax_nopriv_mh_stl_grid_load', [ 'MH_STL_Grid', 'ajax_load' ] );
add_action( 'wp_ajax_mh_stl_product_load', [ 'MH_STL_Product_Gallery', 'ajax_load_more' ] );
add_action( 'wp_ajax_nopriv_mh_stl_product_load', [ 'MH_STL_Product_Gallery', 'ajax_load_more' ] );

/* ── Hooks: REST API ──────────────────────────────────────────── */
add_action( 'rest_api_init', [ 'MH_STL_REST_API', 'register_routes' ] );

/* ── Hooks: Upload Manager & FTP Settings ─────────────────────── */
add_action( 'admin_menu', [ 'MH_STL_Upload_Manager', 'register_menu' ] );
add_action( 'admin_enqueue_scripts', [ 'MH_STL_Upload_Manager', 'enqueue' ] );

/* ── Hooks: Frontend Upload Portal ────────────────────────────── */
MH_STL_Upload_Portal::init();

/* ── Ensure WebP uploads are allowed ─────────────────────────── */
add_filter( 'upload_mimes', function( $mimes ) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
} );

/* ── Activation ───────────────────────────────────────────────── */
register_activation_hook( __FILE__, function() {
    MH_STL_CPT::register();
    flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
