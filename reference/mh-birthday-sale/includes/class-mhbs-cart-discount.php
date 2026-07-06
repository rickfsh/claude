<?php
/**
 * Warenkorb-Rabattlogik der Geburtstagsaktion.
 *
 * Ansatz: negative Fee via woocommerce_cart_calculate_fees.
 * Vorteile: Produkt-/Positionspreise bleiben unangetastet (kompatibel mit
 * allen Einstiegswegen inkl. 3D-Zaunplaner), der Kunde sieht eine klar
 * benannte Rabattzeile, Mengenaenderungen werden automatisch neu berechnet.
 *
 * Erkennung ist ID-basiert (Source of Truth). Getroffen wird die eigene ODER
 * die Eltern-ID, d.h. es ist egal, ob in der Whitelist Einzelprodukte,
 * Varianten oder Eltern-IDs stehen. Namens-Pattern sind nur Fallback.
 *
 * @package mh-birthday-sale
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MHBS_Cart_Discount {

	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_discount' ), 20 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'render_notices' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_notices' ), 5 );
	}

	/**
	 * Warenkorb klassifizieren: Zaunfelder, qualifizierende Pfosten, LED-Leisten.
	 *
	 * 'net'-Summen (line_subtotal) sind die Fee-Basis, 'gross'-Summen
	 * (inkl. Steuer) dienen nur der Anzeige in Notices.
	 *
	 * @return array
	 */
	private static function classify_cart() {
		$cfg = mhbs_get_config();
		$out = array(
			'panel_qty'   => 0,
			'panel_net'   => 0.0,
			'panel_gross' => 0.0,
			'pfosten_qty' => 0,
			'led_qty'     => 0,
			'led_net'     => 0.0,
			'led_gross'   => 0.0,
			'tax_class'   => '',
		);

		if ( ! WC()->cart ) {
			return $out;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$name  = $product->get_name();
			$qty   = (int) $item['quantity'];
			// line_total = Position NACH bereits angewandten Gutscheinen. So gibt
			// es keinen Doppelrabatt, falls ein Coupon denselben Artikel trifft;
			// trifft der Coupon den Zaun nicht, ist line_total == line_subtotal.
			$net   = (float) $item['line_total'];
			$gross = (float) $item['line_total'] + (float) $item['line_tax'];

			if ( self::matches( $product, $name, $cfg['panel_product_ids'], $cfg['panel_pattern'] ) ) {
				$out['panel_qty']   += $qty;
				$out['panel_net']   += $net;
				$out['panel_gross'] += $gross;
				if ( '' === $out['tax_class'] ) {
					$out['tax_class'] = $product->get_tax_class();
				}
			} elseif ( self::matches( $product, $name, $cfg['pfosten_product_ids'], $cfg['pfosten_pattern'], $cfg['pfosten_exclude'] ) ) {
				$out['pfosten_qty'] += $qty;
			} elseif ( self::matches( $product, $name, $cfg['led_product_ids'], $cfg['led_pattern'] ) ) {
				$out['led_qty']   += $qty;
				$out['led_net']   += $net;
				$out['led_gross'] += $gross;
			}
		}

		return $out;
	}

	/**
	 * Produkt-Matching: bevorzugt per ID-Whitelist (eigene ODER Eltern-ID),
	 * sonst Namens-Pattern.
	 *
	 * @param WC_Product $product Produkt.
	 * @param string     $name    Produktname.
	 * @param array      $ids     ID-Whitelist (leer = Pattern-Fallback).
	 * @param string     $pattern PCRE-Pattern.
	 * @param string     $exclude Optionales Exclude-Pattern (nur im Pattern-Modus).
	 * @return bool
	 */
	private static function matches( $product, $name, $ids, $pattern, $exclude = '' ) {
		if ( ! empty( $ids ) ) {
			$ids = array_map( 'absint', (array) $ids );
			$own = (int) $product->get_id();
			$par = (int) $product->get_parent_id();
			return in_array( $own, $ids, true ) || ( $par && in_array( $par, $ids, true ) );
		}
		if ( '' !== $exclude && preg_match( $exclude, $name ) ) {
			return false;
		}
		return (bool) preg_match( $pattern, $name );
	}

	/**
	 * Oeffentlicher Helfer fuer den Produktseiten-Hinweis: ist dieses Produkt
	 * ein rabattfaehiges Zaunfeld?
	 *
	 * @param WC_Product $product Produkt.
	 * @param array|null $cfg     Optionale Config.
	 * @return bool
	 */
	public static function is_panel( $product, $cfg = null ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		if ( null === $cfg ) {
			$cfg = mhbs_get_config();
		}
		return self::matches( $product, $product->get_name(), $cfg['panel_product_ids'], $cfg['panel_pattern'] );
	}

	/**
	 * Set-Bedingung erfuellt?
	 *
	 * @param array $c   Ergebnis von classify_cart().
	 * @param array $cfg Konfiguration.
	 * @return bool
	 */
	private static function is_qualified( $c, $cfg ) {
		return ( $c['panel_qty'] >= 1 && $c['pfosten_qty'] >= (int) $cfg['min_posts'] );
	}

	/**
	 * Rabatt als negative Fee anwenden.
	 *
	 * @param WC_Cart $cart Warenkorb.
	 */
	public static function apply_discount( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! mhbs_is_active_window() ) {
			return;
		}

		$cfg = mhbs_get_config();

		if ( $cfg['block_with_coupons'] && count( $cart->get_applied_coupons() ) > 0 ) {
			return;
		}

		$c = self::classify_cart();
		if ( ! self::is_qualified( $c, $cfg ) ) {
			return;
		}

		$pct            = (int) round( $cfg['rate'] * 100 );
		$panel_discount = round( $c['panel_net'] * $cfg['rate'], 2 );

		if ( $panel_discount > 0 ) {
			$cart->add_fee(
				sprintf(
					/* translators: %d: Rabatt in Prozent */
					__( 'Geburtstagsrabatt −%d%% (Zaunfeld)', 'mh-birthday-sale' ),
					$pct
				),
				-$panel_discount,
				true,
				$c['tax_class']
			);
		}

		if ( $c['led_qty'] > 0 ) {
			$led_discount = round( $c['led_net'] * $cfg['rate'], 2 );
			if ( $led_discount > 0 ) {
				$cart->add_fee(
					sprintf(
						/* translators: %d: Rabatt in Prozent */
						__( 'Geburtstagsbonus −%d%% (LED-Abdeckleiste)', 'mh-birthday-sale' ),
						$pct
					),
					-$led_discount,
					true,
					$c['tax_class']
				);
			}
		}
	}

	/**
	 * Notices im Cart/Checkout: Unlock-Hinweis, Erfolgsmeldung, LED-Upsell,
	 * Gutschein-Konflikt. (Classic Cart/Checkout; Cart-Blocks siehe CLAUDE.md.)
	 */
	public static function render_notices() {
		if ( ! mhbs_is_active_window() || ! function_exists( 'wc_print_notice' ) ) {
			return;
		}

		$cfg = mhbs_get_config();
		$c   = self::classify_cart();
		$pct = (int) round( $cfg['rate'] * 100 );

		if ( $c['panel_qty'] < 1 ) {
			return;
		}

		if ( $cfg['block_with_coupons'] && WC()->cart && count( WC()->cart->get_applied_coupons() ) > 0 ) {
			wc_print_notice(
				esc_html__( 'Hinweis: Der Geburtstagsrabatt ist nicht mit Gutscheinen kombinierbar.', 'mh-birthday-sale' ),
				'notice'
			);
			return;
		}

		if ( ! self::is_qualified( $c, $cfg ) ) {
			$missing   = max( 0, (int) $cfg['min_posts'] - $c['pfosten_qty'] );
			$potential = wc_price( $c['panel_gross'] * $cfg['rate'] );
			wc_print_notice(
				wp_kses_post(
					sprintf(
						/* translators: 1: fehlende Pfosten, 2: Ersparnis, 3: Prozent */
						_n(
							'Fast geschafft: Lege noch %1$d Mega-Flex-Pfosten in den Warenkorb und schalte %2$s Geburtstagsrabatt (−%3$d%% auf die Zaunfelder) frei.',
							'Fast geschafft: Lege noch %1$d Mega-Flex-Pfosten in den Warenkorb und schalte %2$s Geburtstagsrabatt (−%3$d%% auf die Zaunfelder) frei.',
							$missing,
							'mh-birthday-sale'
						),
						$missing,
						$potential,
						$pct
					)
				),
				'notice'
			);
			return;
		}

		$saved = wc_price( $c['panel_gross'] * $cfg['rate'] + $c['led_gross'] * $cfg['rate'] );
		wc_print_notice(
			wp_kses_post(
				sprintf(
					/* translators: 1: Ersparnis, 2: Prozent */
					__( 'Geburtstagsrabatt aktiv – du sparst %1$s (−%2$d%% auf die Zaunfelder).', 'mh-birthday-sale' ),
					$saved,
					$pct
				)
			),
			'success'
		);

		if ( 0 === $c['led_qty'] ) {
			wc_print_notice(
				wp_kses_post(
					sprintf(
						/* translators: %d: Prozent */
						__( 'Geburtstags-Bonus: Lege die LED-Abdeckleiste dazu – sie wird ebenfalls um %d%% reduziert.', 'mh-birthday-sale' ),
						$pct
					)
				),
				'notice'
			);
		}
	}
}
