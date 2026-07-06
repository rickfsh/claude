<?php
/**
 * Asset-Handling: Planer-Override (JS/CSS) nur auf der Sale-Seite laden.
 *
 * @package mh-birthday-sale
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MHBS_Assets {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		$cfg = mhbs_get_config();

		// Warenkorb-/Checkout-Styling der Rabattzeile (automatisch, ohne Shortcode).
		if ( mhbs_is_active_window() && function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			wp_enqueue_style(
				'mhbs-cart-style',
				MHBS_PLUGIN_URL . 'assets/css/cart-style.css',
				array(),
				MHBS_VERSION
			);
			wp_enqueue_script(
				'mhbs-cart-style',
				MHBS_PLUGIN_URL . 'assets/js/cart-style.js',
				array(),
				MHBS_VERSION,
				true
			);
			wp_localize_script(
				'mhbs-cart-style',
				'MHBS_CART',
				apply_filters(
					'mhbs_cart_js_config',
					array(
						'labels' => array(
							__( 'Geburtstagsrabatt', 'mh-birthday-sale' ),
							__( 'Geburtstagsbonus', 'mh-birthday-sale' ),
						),
					)
				)
			);
		}

		$load = is_page( $cfg['override_page_slugs'] );

		/**
		 * Override-Assets auch auf anderen Seiten laden (oder unterdrücken),
		 * z.B. wenn der Planer per Shortcode woanders eingebunden ist.
		 */
		$load = apply_filters( 'mhbs_load_planner_override', $load );

		if ( ! $load || ! mhbs_is_active_window() ) {
			return;
		}

		wp_enqueue_style(
			'mhbs-birthday',
			MHBS_PLUGIN_URL . 'assets/css/birthday.css',
			array(),
			MHBS_VERSION
		);

		wp_enqueue_script(
			'mhbs-planner-override',
			MHBS_PLUGIN_URL . 'assets/js/planner-override.js',
			array(),
			MHBS_VERSION,
			true
		);

		/**
		 * Frontend-Konfiguration. Selektoren per Filter 'mhbs_js_config'
		 * an den echten Planer-DOM anpassen (TODO vor Livegang!).
		 */
		wp_localize_script(
			'mhbs-planner-override',
			'MHBS_CFG',
			apply_filters(
				'mhbs_js_config',
				array(
					'rate'     => (float) $cfg['rate'],
					'minPosts' => (int) $cfg['min_posts'],
					'patterns' => array(
						// Pattern als Strings ohne Delimiter, JS baut RegExp mit 'i'.
						'taiga'          => 'steckzaun\\s+taiga',
						'pfosten'        => 'pfosten',
						'pfostenExclude' => 'pfostentr|abdeckung',
						'led'            => 'led[\\s\\S]{0,30}leiste|leiste[\\s\\S]{0,30}led',
					),
					'selectors' => array(
						// TODO: an echten Planer-DOM anpassen.
						'root'          => '#zaunplaner, .zaunplaner, .mh-planer',
						'row'           => 'tr',
						'total'         => '',
						'trackerAnchor' => '',
						'savingsAnchor' => '',
					),
					'flags' => array(
						'rewriteTotal' => true,
						'confetti'     => true,
					),
					'labels' => array(
						'trackerTitle' => __( '22 Jahre Mega-Holz – dein Set-Rabatt', 'mh-birthday-sale' ),
						'note'         => __( 'Der Rabatt wird im Warenkorb automatisch abgezogen.', 'mh-birthday-sale' ),
					),
				)
			)
		);
	}
}
