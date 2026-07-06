<?php
/**
 * Gutschein-Sperre fuer die Zaunfelder.
 *
 * Die rabattfaehigen Zaunfelder sollen KEINE Gutscheine zulassen (der
 * Geburtstags-Set-Rabatt ist ihr einziger Nachlass). Andere Produkte im
 * Warenkorb bekommen Gutscheine ganz normal.
 *
 * Umsetzung ueber WooCommerces eigenen Ausschluss-Mechanismus: Die Panel-IDs
 * werden per Filter zu den "ausgeschlossenen Produkten" JEDES Gutscheins
 * hinzugefuegt – identisch zur nativen "Produkte ausschliessen"-Einstellung
 * im Coupon, nur automatisch und ohne dass du es pro Coupon pflegen musst.
 *
 * Greift nur im Aktionszeitraum. Soll der Zaun dauerhaft (auch ausserhalb der
 * Aktion) keine Coupons zulassen, die mhbs_is_active_window()-Pruefung in
 * active() entfernen.
 *
 * @package mh-birthday-sale
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MHBS_Coupon_Exclusion {

	public static function init() {
		add_filter( 'woocommerce_coupon_get_excluded_product_ids', array( __CLASS__, 'excluded_ids' ), 20, 2 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( __CLASS__, 'invalid_for_panels' ), 20, 4 );
	}

	private static function active() {
		return mhbs_is_active_window();
	}

	/**
	 * Panel-IDs an die Ausschlussliste jedes Gutscheins anhaengen.
	 *
	 * @param array     $ids    Bestehende ausgeschlossene Produkt-IDs.
	 * @param WC_Coupon $coupon Coupon.
	 * @return array
	 */
	public static function excluded_ids( $ids, $coupon ) {
		if ( ! self::active() ) {
			return $ids;
		}
		$cfg   = mhbs_get_config();
		$panel = array_map( 'absint', (array) $cfg['panel_product_ids'] );
		if ( empty( $panel ) ) {
			return $ids;
		}
		return array_values( array_unique( array_merge( array_map( 'absint', (array) $ids ), $panel ) ) );
	}

	/**
	 * Sicherheitsnetz: Gutschein fuer Panel-Produkte als ungueltig melden.
	 *
	 * @param bool       $valid   Bisherige Gueltigkeit.
	 * @param WC_Product $product Produkt.
	 * @param WC_Coupon  $coupon  Coupon.
	 * @param array      $values  Cart-Item-Daten.
	 * @return bool
	 */
	public static function invalid_for_panels( $valid, $product, $coupon, $values ) {
		if ( ! self::active() ) {
			return $valid;
		}
		if ( $product instanceof WC_Product && MHBS_Cart_Discount::is_panel( $product, mhbs_get_config() ) ) {
			return false;
		}
		return $valid;
	}
}
