<?php
/**
 * Plugin Name:       MH Birthday Sale
 * Plugin URI:        https://mega-holz.de
 * Description:       22-Jahre-Geburtstagsaktion: Warenkorb-Rabatt mit Set-Bedingung (rabattfaehige Zaunfelder + Mega-Flex-Pfosten, optional LED-Abdeckleiste), Produktseiten-Hinweis und visuelles Planer-Override (Unlock-Tracker, Ersparnis-Anzeige, rabattierter Gesamtpreis).
 * Version:           1.7.0
 * Author:            Mega-Holz / Rick
 * Text Domain:       mh-birthday-sale
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MHBS_VERSION', '1.7.0' );
define( 'MHBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MHBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Zentrale Konfiguration. Alles hier ist per Filter 'mhbs_config' ueberschreibbar,
 * z.B. aus dem Child-Theme oder einem Site-Specific-Plugin.
 *
 * WICHTIG (TODO vor Livegang):
 * - Aktionszeitraum setzen (active_from / active_until, Site-Zeitzone).
 * - override_page_slugs auf die echte Sale-Seite anpassen.
 *
 * @return array
 */
function mhbs_get_config() {
	$defaults = array(
		// Master-Schalter (per Admin schaltbar).
		'enabled'            => true,

		// Rabattlogik.
		'rate'               => 0.22,
		'min_posts'          => 2, // Schwelle qualifizierender Pfosten. 1 = ein Pfosten reicht.
		'block_with_coupons' => false, // false = Set-Rabatt bleibt, auch wenn ein Gutschein (z.B. auf ein anderes Produkt) im Korb liegt. true = jeder Gutschein deaktiviert den Set-Rabatt komplett.

		// Aktionszeitraum (Site-Zeitzone). TODO: echte Daten eintragen.
		'active_from'        => '2026-06-15 00:00:00',
		'active_until'       => '2026-06-22 23:59:59',

		// Rabattfaehige Zaunfelder (Source of Truth = IDs). Eigene ODER Eltern-ID
		// wird getroffen, d.h. Einzelprodukte, Varianten und Eltern-IDs gehen.
		'panel_product_ids'   => array(
			92812,   // Steckzaun Taiga Laerche
			92813,   // Steckzaun Taiga Laerche 90 cm
			136062,  // Massivo Anthrazit
			5302,    // Alu Rhombus
			212202,  // Alu Rhombus (weitere Version)
		),

		// Qualifizierende Pfosten = Mega Flex.
		'pfosten_product_ids' => array(
			222815,
			222814,
			6083,
			137794,
		),

		'led_product_ids'     => array(),

		// Namens-Pattern (Fallback, PCRE) – greifen nur, wenn die jeweilige
		// ID-Liste leer ist. Exclude verhindert, dass "Pfostentraeger" /
		// "Abdeckung" als Pfosten zaehlen.
		'panel_pattern'       => '/steckzaun\s+taiga|massivo|rhombus/i',
		'pfosten_pattern'     => '/mega\s*flex|pfosten/i',
		'pfosten_exclude'     => '/pfostentr|abdeckung/i',
		'led_pattern'         => '/led[\s\S]{0,30}leiste|leiste[\s\S]{0,30}led/i',

		// Seiten (Slugs), auf denen das Planer-Override geladen wird.
		'override_page_slugs' => array( 'geburtstag', 'birthday-sale' ),

		// Ziel des "Im 3D-Zaunplaner planen"-Links im Produktseiten-Hinweis.
		'planner_url'         => '',
	);

	// Admin-Einstellungen (Backend) ueberschreiben die Code-Defaults.
	// Reihenfolge der Praezedenz: Default < Admin-Option < 'mhbs_config'-Filter.
	$saved = get_option( 'mhbs_settings', array() );
	if ( is_array( $saved ) ) {
		if ( isset( $saved['enabled'] ) ) {
			$defaults['enabled'] = (bool) $saved['enabled'];
		}
		if ( ! empty( $saved['active_from'] ) ) {
			$defaults['active_from'] = $saved['active_from'];
		}
		if ( ! empty( $saved['active_until'] ) ) {
			$defaults['active_until'] = $saved['active_until'];
		}
		if ( isset( $saved['min_posts'] ) && '' !== $saved['min_posts'] ) {
			$defaults['min_posts'] = (int) $saved['min_posts'];
		}
	}

	return apply_filters( 'mhbs_config', $defaults );
}

/**
 * Laeuft die Aktion gerade? (Site-Zeitzone via wp_timezone)
 *
 * @return bool
 */
function mhbs_is_active_window() {
	$cfg = mhbs_get_config();

	try {
		$tz    = wp_timezone();
		$now   = new DateTimeImmutable( 'now', $tz );
		$from  = new DateTimeImmutable( $cfg['active_from'], $tz );
		$until = new DateTimeImmutable( $cfg['active_until'], $tz );
	} catch ( Exception $e ) {
		return false;
	}

	$active = ! empty( $cfg['enabled'] ) && ( $now >= $from && $now <= $until );

	return (bool) apply_filters( 'mhbs_is_active', $active );
}

require_once MHBS_PLUGIN_DIR . 'includes/class-mhbs-cart-discount.php';
require_once MHBS_PLUGIN_DIR . 'includes/class-mhbs-coupon-exclusion.php';
require_once MHBS_PLUGIN_DIR . 'includes/class-mhbs-product-notice.php';
require_once MHBS_PLUGIN_DIR . 'includes/class-mhbs-settings.php';
require_once MHBS_PLUGIN_DIR . 'includes/class-mhbs-assets.php';

add_action(
	'plugins_loaded',
	function () {
		// Admin-Einstellungen immer laden (auch ohne aktives WooCommerce).
		if ( is_admin() ) {
			MHBS_Settings::init();
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		MHBS_Cart_Discount::init();
		MHBS_Coupon_Exclusion::init();
		MHBS_Product_Notice::init();
		MHBS_Assets::init();
	}
);
