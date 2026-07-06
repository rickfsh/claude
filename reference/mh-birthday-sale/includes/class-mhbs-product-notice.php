<?php
/**
 * Set-Rabatt-Hinweis auf der Produktseite (PDP), Variante B.
 *
 * Zwei Wege, damit es in JEDEM Template funktioniert:
 *   1. Automatischer Hook 'woocommerce_single_product_summary' (Standard-Themes).
 *   2. Shortcode [mhbs_birthday_hint] – fuer Page-Builder wie Oxygen, deren
 *      eigene Produkt-Templates die Standard-Hooks NICHT feuern. Einfach dort
 *      platzieren, wo der Hinweis hin soll (z.B. direkt unter dem Preisblock).
 *
 * Erscheint nur im Aktionszeitraum und nur auf rabattfaehigen Zaunfeldern.
 * Doppel-Ausgabe (Hook + Shortcode auf derselben Seite) wird verhindert.
 *
 * @package mh-birthday-sale
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MHBS_Product_Notice {

	private static $rendered = false;
	private static $css_done = false;

	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_hook' ), 11 );
		add_shortcode( 'mhbs_birthday_hint', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_head', array( __CLASS__, 'maybe_head_css' ) );
	}

	/** Hook-Ausgabe (Standard-Templates). */
	public static function render_hook() {
		echo self::get_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- intern aufgebaut/escaped.
	}

	/** Shortcode-Ausgabe (Oxygen & Co.). */
	public static function shortcode() {
		return self::get_html();
	}

	/**
	 * Baut das Markup oder '' (wenn nicht anwendbar). Gibt pro Request nur
	 * einmal etwas aus (Hook ODER Shortcode).
	 *
	 * @return string
	 */
	public static function get_html() {
		if ( self::$rendered || ! mhbs_is_active_window() ) {
			return '';
		}

		$product = self::resolve_product();
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$cfg = mhbs_get_config();
		if ( ! self::should_show( $product, $cfg ) ) {
			return '';
		}

		self::$rendered = true;

		$rate_pct = (int) round( (float) $cfg['rate'] * 100 );
		$rate     = (float) $cfg['rate'];

		$gift = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></svg>';

		// Aktuellen WooCommerce-Preis ermitteln und die 22 % einrechnen.
		// Bei variablen Produkten den (ggf. "ab") Mindestpreis nehmen.
		if ( $product->is_type( 'variable' ) ) {
			$raw     = (float) $product->get_variation_price( 'min' );
			$is_from = ( $product->get_variation_price( 'min' ) !== $product->get_variation_price( 'max' ) );
		} else {
			$raw     = (float) $product->get_price();
			$is_from = false;
		}

		$base       = function_exists( 'wc_get_price_to_display' ) ? (float) wc_get_price_to_display( $product, array( 'price' => $raw ) ) : $raw;
		$decimals   = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$discounted = round( $base * ( 1 - $rate ), $decimals );

		$mega = '<strong>MEGA FLEX Pfosten</strong>';

		if ( $discounted > 0 ) {
			$price_html = ( $is_from ? esc_html__( 'ab ', 'mh-birthday-sale' ) : '' ) . wc_price( $discounted );
			$price_html = '<strong class="mhbs-pdp-hint__price">' . $price_html . '</strong>';
			$text       = sprintf(
				/* translators: 1: Rabattsatz, 2: rabattierter Preis, 3: Produktname */
				esc_html__( 'Du erhältst dieses Produkt mit zusätzlichen %1$d%% Geburtstagsrabatt für nur %2$s, wenn der Zaun im Set mit den passenden %3$s bestellt wird! Der Rabatt wird automatisch im Warenkorb abgezogen.', 'mh-birthday-sale' ),
				$rate_pct,
				$price_html,
				$mega
			);
		} else {
			$text = sprintf(
				/* translators: 1: Rabattsatz, 2: Produktname */
				esc_html__( 'Du erhältst dieses Produkt mit zusätzlichen %1$d%% Geburtstagsrabatt, wenn der Zaun im Set mit den passenden %2$s bestellt wird! Der Rabatt wird automatisch im Warenkorb abgezogen.', 'mh-birthday-sale' ),
				$rate_pct,
				$mega
			);
		}

		$html  = '<div class="mhbs-pdp-hint" role="note">';
		$html .= '<span class="mhbs-pdp-hint__icon">' . $gift . '</span>';
		$html .= '<div class="mhbs-pdp-hint__body">';
		$html .= '<span class="mhbs-pdp-hint__text">' . wp_kses_post( $text ) . '</span>';
		$html .= '</div></div>';

		return self::inline_css() . $html;
	}

	/** Aktuelles Produkt ermitteln (Hook-Kontext oder Shortcode im Content). */
	private static function resolve_product() {
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		$id = get_the_ID();
		if ( $id ) {
			$p = wc_get_product( $id );
			if ( $p instanceof WC_Product ) {
				return $p;
			}
		}
		return null;
	}

	/** Rabattfaehiges Zaunfeld? (auch wenn nur eine Variation gelistet ist) */
	private static function should_show( $product, $cfg ) {
		if ( MHBS_Cart_Discount::is_panel( $product, $cfg ) ) {
			return true;
		}
		if ( $product->is_type( 'variable' ) && ! empty( $cfg['panel_product_ids'] ) ) {
			$ids = array_map( 'absint', (array) $cfg['panel_product_ids'] );
			foreach ( $product->get_children() as $child_id ) {
				if ( in_array( (int) $child_id, $ids, true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** CSS einmal pro Request ausgeben (egal ob via head oder Shortcode). */
	private static function inline_css() {
		if ( self::$css_done ) {
			return '';
		}
		self::$css_done = true;
		return '<style id="mhbs-pdp-hint-css">'
			. '.mhbs-pdp-hint{display:flex;gap:14px;align-items:flex-start;margin:18px 0;padding:16px 18px;background:#FFF9F0;border:1px solid #F4DDB0;border-radius:8px;font-family:"Open Sans",sans-serif;}'
			. '.mhbs-pdp-hint__icon{flex:0 0 auto;color:#FAA41A;line-height:0;}'
			. '.mhbs-pdp-hint__icon svg{width:24px;height:24px;display:block;}'
			. '.mhbs-pdp-hint__body{display:flex;flex-direction:column;}'
			. '.mhbs-pdp-hint__head{font-size:15px;font-weight:600;color:#111111;margin-bottom:3px;}'
			. '.mhbs-pdp-hint__text{font-size:13.5px;line-height:1.6;color:#333333;}'
			. '.mhbs-pdp-hint__text strong{font-weight:600;color:#111111;}'
			. '.mhbs-pdp-hint__price{font-weight:700;color:#111111;white-space:nowrap;}'
			. '.mhbs-pdp-hint__price .woocommerce-Price-amount{font-weight:700;}'
			. '</style>';
	}

	/** CSS auf Produktseiten via head laden (Fallback fuer den Hook-Weg). */
	public static function maybe_head_css() {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! mhbs_is_active_window() ) {
			return;
		}
		echo self::inline_css(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
