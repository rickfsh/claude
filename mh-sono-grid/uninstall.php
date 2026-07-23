<?php
/**
 * MH SONO Grid — Uninstall.
 * Räumt alle Transients und User-Metas des Plugins auf.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Alle mh_sono_*-Transients (+ Timeouts).
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_mh\_sono\_%'
	    OR option_name LIKE '\_transient\_timeout\_mh\_sono\_%'"
);

// Dismiss-Metas der Admin-Notice.
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'mh_sono_notice_dismissed'"
);
