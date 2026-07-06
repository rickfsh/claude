<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package MH_Bought_Together
 */

// If uninstall not called from WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

// Remove plugin options.
delete_option( 'mh_bt_options' );

// Remove all product meta.
global $wpdb;
$wpdb->delete(
    $wpdb->postmeta,
    array( 'meta_key' => '_mh_bt_linked_products' ),
    array( '%s' )
);
$wpdb->delete(
    $wpdb->postmeta,
    array( 'meta_key' => '_mh_bt_included_products' ),
    array( '%s' )
);
