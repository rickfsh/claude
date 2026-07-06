<?php
/**
 * Frontend — shortcode-based 2-column configurator (v4).
 *
 * @package MH_Spielturm_Vergleich
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MH_STV_Frontend {

	private static $instance = null;
	private $assets_registered = false;
	private $already_enqueued = false;

	public static function boot() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'mh_spielturm_vergleich', array( $this, 'shortcode_output' ) );
		add_shortcode( 'mh_360_viewer', array( $this, 'shortcode_360_viewer' ) );

		// AJAX add-to-cart handler.
		add_action( 'wp_ajax_mh_stv_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_mh_stv_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

		// AJAX bought-together refresh handler.
		add_action( 'wp_ajax_mh_stv_refresh_bt', array( $this, 'ajax_refresh_bt' ) );
		add_action( 'wp_ajax_nopriv_mh_stv_refresh_bt', array( $this, 'ajax_refresh_bt' ) );

		// AJAX undo (remove from cart) handler — v5.4.0.
		add_action( 'wp_ajax_mh_stv_undo_cart', array( $this, 'ajax_undo_cart' ) );
		add_action( 'wp_ajax_nopriv_mh_stv_undo_cart', array( $this, 'ajax_undo_cart' ) );

		// #2: AJAX product detail (lazy-loaded tab/lightbox data).
		add_action( 'wp_ajax_mh_stv_product_detail', array( $this, 'ajax_product_detail' ) );
		add_action( 'wp_ajax_nopriv_mh_stv_product_detail', array( $this, 'ajax_product_detail' ) );
	}

	/**
	 * #4: Get content-hash version for a file (immutable caching).
	 * Returns first 8 chars of md5 hash, falls back to plugin version.
	 */
	private function asset_version( $relative_path ) {
		$file = MH_STV_PATH . $relative_path;
		if ( file_exists( $file ) ) {
			return substr( md5_file( $file ), 0, 8 );
		}
		return MH_STV_VERSION;
	}

	/**
	 * #1: Resolve asset path — use .min version if available and not debugging.
	 */
	private function asset_url( $relative_path ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return MH_STV_URL . $relative_path;
		}
		$min_path = preg_replace( '/\.(js|css)$/', '.min.$1', $relative_path );
		if ( file_exists( MH_STV_PATH . $min_path ) ) {
			return MH_STV_URL . $min_path;
		}
		return MH_STV_URL . $relative_path;
	}

	/**
	 * #1 + #4: Resolve asset version for the chosen file (min or unminified).
	 */
	private function resolve_asset( $relative_path ) {
		if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			$min_path = preg_replace( '/\.(js|css)$/', '.min.$1', $relative_path );
			if ( file_exists( MH_STV_PATH . $min_path ) ) {
				return array( MH_STV_URL . $min_path, $this->asset_version( $min_path ) );
			}
		}
		return array( MH_STV_URL . $relative_path, $this->asset_version( $relative_path ) );
	}

	public function register_assets() {
		if ( is_admin() ) {
			return;
		}

		list( $css_url, $css_ver ) = $this->resolve_asset( 'public/css/spielturm-vergleich.css' );
		wp_register_style( 'mh-stv-front', $css_url, array(), $css_ver );

		list( $lb_url, $lb_ver ) = $this->resolve_asset( 'public/css/spielturm-lightbox.css' );
		wp_register_style( 'mh-stv-lightbox', $lb_url, array( 'mh-stv-front' ), $lb_ver );

		list( $js_url, $js_ver ) = $this->resolve_asset( 'public/js/spielturm-vergleich.js' );
		wp_register_script( 'mh-stv-front', $js_url, array( 'mh-stv-360' ), $js_ver, true );

		// 360° Spinner assets.
		list( $s360_css_url, $s360_css_ver ) = $this->resolve_asset( 'public/css/spielturm-360.css' );
		wp_register_style( 'mh-stv-360', $s360_css_url, array(), $s360_css_ver );

		list( $s360_js_url, $s360_js_ver ) = $this->resolve_asset( 'public/js/spielturm-360.js' );
		wp_register_script( 'mh-stv-360', $s360_js_url, array(), $s360_js_ver, true );

		$this->assets_registered = true;
	}

	/**
	 * Shortcode: [mh_spielturm_vergleich] or [mh_spielturm_vergleich id="123"]
	 */
	public function shortcode_output( $atts ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '<!-- MH STV: WooCommerce not loaded -->';
		}

		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'mh_spielturm_vergleich' );
		$product_id = absint( $atts['id'] );

		if ( $product_id < 1 ) {
			$product_id = $this->get_current_product_id();
		}

		if ( $product_id < 1 ) {
			return '<!-- MH STV: No product ID -->';
		}

		$match = MH_STV_Data_Provider::get_group_for_product( $product_id );
		if ( ! $match ) {
			return '<!-- MH STV: Product not in any group -->';
		}

		$data = MH_STV_Data_Provider::build_comparison_data(
			$match['group'],
			$match['current_serie'],
			$match['current_level']
		);

		if ( empty( $data['products'] ) || count( $data['products'] ) < 2 ) {
			return '<!-- MH STV: Less than 2 products in group -->';
		}

		$settings = get_option( 'mh_stv_settings', array() );
		$accent   = '#e8910c';
		if ( ! empty( $settings['accent_color'] ) ) {
			$sanitized = sanitize_hex_color( $settings['accent_color'] );
			if ( $sanitized ) {
				$accent = $sanitized;
			}
		}

		if ( ! $this->already_enqueued ) {
			if ( ! $this->assets_registered ) {
				$this->register_assets();
			}
			wp_enqueue_style( 'mh-stv-front' );
			wp_enqueue_style( 'mh-stv-lightbox' );
			wp_enqueue_style( 'mh-stv-360' );
			wp_enqueue_script( 'mh-stv-360' );
			wp_enqueue_script( 'mh-stv-front' );

			wp_localize_script( 'mh-stv-front', 'mhStvData', array(
				'comparison'       => $data,
				'accentColor'      => $accent,
				'showStock'        => ! empty( $settings['show_stock'] ),
				'paymentSelector'  => ! empty( $settings['payment_selector'] ) ? trim( $settings['payment_selector'] ) : '',
				'manufacturerSelector' => ! empty( $settings['manufacturer_selector'] ) ? trim( $settings['manufacturer_selector'] ) : '',
				'embedSelectors'   => $this->parse_embed_selectors( $settings ),
				'belowGridSelectors' => $this->parse_embed_selectors( $settings, 'below_grid_selectors' ),
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'cartNonce'   => wp_create_nonce( 'mh_stv_add_to_cart' ),
				'undoNonce'   => wp_create_nonce( 'mh_stv_undo_cart' ),
				'btNonce'     => wp_create_nonce( 'mh_stv_refresh_bt' ),
				'detailNonce' => wp_create_nonce( 'mh_stv_product_detail' ),
				'trackNonce'  => wp_create_nonce( 'mh_stv_track' ),
				'priceFormat' => array(
					'thousand'  => wc_get_price_thousand_separator(),
					'decimal'   => wc_get_price_decimal_separator(),
					'decimals'  => absint( wc_get_price_decimals() ),
					'symbol'    => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'position'  => get_option( 'woocommerce_currency_pos', 'left' ),
				),
				'i18n'        => array(
					'step1'          => 'Design w&auml;hlen',
					'step2'          => 'Ausstattung konfigurieren',
					'currentProduct' => 'Das ist das aktuelle Produkt',
					'ctaText'        => 'Diese Variante ansehen',
					'addToCart'      => 'In den Warenkorb',
					'priceSuffix'    => 'inkl. MwSt, zzgl. Versand',
					'swingLabel'     => 'Schaukel',
					'swingDesc'      => 'Schaukelanbau mit Schaukelsitz + Picknicktisch',
					'wallLabel'      => 'Kletterwand',
					'wallDesc'       => 'Kletterwand mit 5 Klettersteinen + Seil',
					'wallDisabled'   => 'Schaukel wird ben&ouml;tigt',
					'included'       => 'Inklusive',
					'available'      => 'Verf&uuml;gbar',
					'backorder'      => 'Auf Bestellung',
					'outOfStock'     => 'Zurzeit nicht auf Lager',
					'tabDescription' => 'Beschreibung',
					'tabAttributes'  => 'Zus&auml;tzliche Informationen',
					'tabProductData' => 'Produktdaten',
					'tabReviews'     => 'Bewertungen',
					'trust1'         => ! empty( $settings['trust_1'] ) ? $settings['trust_1'] : '',
					'trust2'         => ! empty( $settings['trust_2'] ) ? $settings['trust_2'] : '',
					'trust3'         => ! empty( $settings['trust_3'] ) ? $settings['trust_3'] : '',
				),
			) );

			$this->already_enqueued = true;
		}

		// Hide default WC gallery/summary via CSS.
		// Fix v5.4.0: Whitelist CSS selector characters to prevent CSS injection.
		$hide_css = '';
		if ( ! empty( $settings['hide_selector'] ) ) {
			$selector = $settings['hide_selector'];
			if ( preg_match( '/^[a-zA-Z0-9\s\.\#\-\>\,\:\[\]\=\_\+\~\(\)\*]+$/', $selector ) ) {
				$hide_css = '<style>' . esc_html( $selector ) . '{display:none!important}</style>';
			}
		}

		// Build JSON-LD Schema.org Product markup (Fix: schema lost when WC gallery hidden).
		$schema = $this->build_schema_json_ld( $data, $product_id );

		// Build skeleton placeholder (visible until JS renders).
		$skeleton = $this->build_skeleton_html();

		// Embed mh-bought-together shortcode output (if plugin is active).
		$bt_html = '';
		if ( shortcode_exists( 'mh_bought_together' ) ) {
			$bt_output = do_shortcode( '[mh_bought_together]' );

			// Set dedup flag AFTER rendering — suppresses the standalone auto-placement
			// but lets our embedded call succeed first.
			global $mh_bt_widget_rendered;
			$mh_bt_widget_rendered = true;

			if ( ! empty( trim( strip_tags( $bt_output ) ) ) ) {
				// Hidden source — JS will move this into the right column during render.
				$bt_html = '<div id="mh-stv-bt-source" style="display:none">' . $bt_output . '</div>';
			}
		}

		return $hide_css
			. $schema
			. '<div id="mh-stv-configurator" style="--mh-stv-accent:' . esc_attr( $accent ) . '">'
			. $skeleton
			. '</div>'
			. $bt_html;
	}

	/**
	 * Build JSON-LD Schema.org Product markup.
	 */
	private function build_schema_json_ld( $data, $product_id ) {
		$current_product = null;
		foreach ( $data['products'] as $p ) {
			if ( isset( $p['is_current'] ) && $p['is_current'] ) {
				$current_product = $p;
				break;
			}
		}
		if ( ! $current_product ) {
			return '';
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $current_product['name'],
			'url'         => $current_product['url'],
			'description' => wp_strip_all_tags( $current_product['short_description'] ),
		);

		if ( ! empty( $current_product['image'] ) ) {
			$schema['image'] = $current_product['image'];
		}

		// Offer.
		$availability_map = array(
			'instock'     => 'https://schema.org/InStock',
			'onbackorder' => 'https://schema.org/PreOrder',
			'outofstock'  => 'https://schema.org/OutOfStock',
		);
		$schema['offers'] = array(
			'@type'         => 'Offer',
			'price'         => number_format( $current_product['price'], 2, '.', '' ),
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => isset( $availability_map[ $current_product['stock_status'] ] )
				? $availability_map[ $current_product['stock_status'] ]
				: 'https://schema.org/InStock',
			'url'           => $current_product['url'],
		);

		// AggregateRating.
		if ( $current_product['average_rating'] > 0 && $current_product['review_count'] > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => number_format( $current_product['average_rating'], 1, '.', '' ),
				'reviewCount' => $current_product['review_count'],
			);
		}

		return '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>';
	}

	/**
	 * Build skeleton placeholder HTML (shown while JS loads).
	 */
	private function build_skeleton_html() {
		$pulse = 'background:#e5e3df;border-radius:8px;animation:mhStvPulse 1.5s ease-in-out infinite;';
		return '<div class="mh-stv-skeleton" aria-hidden="true">'
			. '<style>@keyframes mhStvPulse{0%,100%{opacity:.4}50%{opacity:.8}}'
			. '.mh-stv-skeleton{display:grid;grid-template-columns:1fr 1fr;gap:32px;}'
			. '@media(max-width:768px){.mh-stv-skeleton{display:flex;flex-direction:column;gap:12px;}'
			. '.mh-stv-sk-left,.mh-stv-sk-right{display:contents;}'
			. '.mh-stv-sk-name{order:1}.mh-stv-sk-gallery{order:2}.mh-stv-sk-thumbrow{order:3}'
			. '.mh-stv-sk-action{order:4}}</style>'
			. '<div class="mh-stv-sk-left">'
			. '<div class="mh-stv-sk-gallery" style="' . $pulse . 'aspect-ratio:4/3;width:100%;margin-bottom:12px"></div>'
			. '<div class="mh-stv-sk-thumbrow" style="display:flex;gap:8px">'
			. '<div style="' . $pulse . 'width:72px;height:72px"></div>'
			. '<div style="' . $pulse . 'width:72px;height:72px"></div>'
			. '<div style="' . $pulse . 'width:72px;height:72px"></div>'
			. '</div>'
			. '</div>'
			. '<div class="mh-stv-sk-right">'
			. '<div class="mh-stv-sk-name" style="' . $pulse . 'height:28px;width:70%;margin-bottom:16px"></div>'
			. '<div class="mh-stv-sk-action">'
			. '<div style="' . $pulse . 'height:40px;width:50%;margin-bottom:20px"></div>'
			. '<div style="' . $pulse . 'height:130px;width:100%;margin-bottom:14px"></div>'
			. '<div style="' . $pulse . 'height:52px;width:100%;border-radius:10px"></div>'
			. '</div>'
			. '</div>'
			. '</div>';
	}

	/**
	 * AJAX add-to-cart handler.
	 * v5.4.0: Rate-limiting, qty max enforcement, cart_item_key for undo.
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'mh_stv_add_to_cart', 'nonce' );

		// Fix #3: Rate-limiting — max 12 add-to-cart requests per minute per session.
		$rate_key = 'mh_stv_atc_' . substr( md5( wp_get_session_token() . $_SERVER['REMOTE_ADDR'] ), 0, 12 );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 12 ) {
			wp_send_json_error( array( 'message' => 'Zu viele Anfragen. Bitte warte einen Moment.' ), 429 );
		}
		set_transient( $rate_key, $count + 1, 60 );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

		if ( $product_id < 1 ) {
			wp_send_json_error( array( 'message' => 'Ungültige Produkt-ID.' ), 400 );
		}

		if ( $quantity < 1 ) {
			$quantity = 1;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Produkt nicht gefunden.' ), 404 );
		}

		// Fix #6: Enforce quantity max from stock.
		if ( $product->managing_stock() ) {
			$stock_qty = $product->get_stock_quantity();
			if ( $stock_qty > 0 && $quantity > $stock_qty ) {
				$quantity = $stock_qty;
			}
		}
		if ( $quantity > 99 ) {
			$quantity = 99;
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );

		if ( $cart_item_key ) {
			// Trigger WC fragments refresh.
			ob_start();
			woocommerce_mini_cart();
			$mini_cart = ob_get_clean();

			$data = array(
				'message'        => 'Produkt zum Warenkorb hinzugefügt.',
				'cart_count'     => WC()->cart->get_cart_contents_count(),
				'cart_total'     => WC()->cart->get_cart_total(),
				'cart_url'       => wc_get_cart_url(),
				'cart_item_key'  => $cart_item_key, // Fix #5: for undo
				'fragments'      => apply_filters( 'woocommerce_add_to_cart_fragments', array() ),
			);
			wp_send_json_success( $data );
		} else {
			wp_send_json_error( array( 'message' => 'Produkt konnte nicht hinzugefügt werden.' ), 500 );
		}
	}

	/**
	 * AJAX refresh bought-together widget for a given product ID.
	 *
	 * Called from JS when the configurator switches to a different product.
	 * Sets up global $post + $product so [mh_bought_together] renders correctly.
	 */
	public function ajax_refresh_bt() {
		check_ajax_referer( 'mh_stv_refresh_bt', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( $product_id < 1 || ! shortcode_exists( 'mh_bought_together' ) ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		// Set up WC product context for the shortcode.
		global $post, $product;
		$original_post    = $post;
		$original_product = $product;

		$post    = get_post( $product_id );
		setup_postdata( $post );
		$product = wc_get_product( $product_id );

		// Reset the dedup flag so the shortcode actually renders.
		global $mh_bt_widget_rendered;
		$mh_bt_widget_rendered = false;

		$html = do_shortcode( '[mh_bought_together]' );

		// Restore original context.
		$post    = $original_post;
		$product = $original_product;
		if ( $post ) {
			setup_postdata( $post );
		}
		wp_reset_postdata();

		$clean = trim( strip_tags( $html ) );
		if ( empty( $clean ) ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX undo — remove a specific cart item by key.
	 * v5.4.0: Enables "undo" after add-to-cart.
	 */
	public function ajax_undo_cart() {
		check_ajax_referer( 'mh_stv_undo_cart', 'nonce' );

		$cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';

		if ( empty( $cart_item_key ) ) {
			wp_send_json_error( array( 'message' => 'Kein Warenkorb-Eintrag.' ), 400 );
		}

		$removed = WC()->cart->remove_cart_item( $cart_item_key );

		if ( $removed ) {
			wp_send_json_success( array(
				'message'    => 'Produkt entfernt.',
				'cart_count' => WC()->cart->get_cart_contents_count(),
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Eintrag nicht gefunden.' ), 404 );
		}
	}

	/**
	 * #2: AJAX product detail — lazy-loads description, attributes, meta, reviews, gallery_full.
	 * Called from JS when user opens a tab or lightbox for the first time.
	 */
	public function ajax_product_detail() {
		check_ajax_referer( 'mh_stv_product_detail', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( $product_id < 1 ) {
			wp_send_json_error( array( 'message' => 'Ungültige Produkt-ID.' ), 400 );
		}

		$detail = MH_STV_Data_Provider::get_product_detail( $product_id );

		if ( ! $detail ) {
			wp_send_json_error( array( 'message' => 'Produkt nicht gefunden.' ), 404 );
		}

		wp_send_json_success( $detail );
	}

	/**
	 * Standalone Shortcode: [mh_360_viewer id="123"]
	 *
	 * Renders a 360° spinner for any WooCommerce product that has frames uploaded.
	 * Can be placed anywhere via Oxygen Builder or other page builders.
	 */
	public function shortcode_360_viewer( $atts ) {
		if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'MH_STV_360_Metabox' ) ) {
			return '<!-- MH 360: WooCommerce or 360 Metabox not loaded -->';
		}

		$atts = shortcode_atts( array( 'id' => 0, 'autoplay' => 'true' ), $atts, 'mh_360_viewer' );
		$product_id = absint( $atts['id'] );

		if ( $product_id < 1 ) {
			$product_id = $this->get_current_product_id();
		}

		if ( $product_id < 1 ) {
			return '<!-- MH 360: No product ID -->';
		}

		$frames = MH_STV_360_Metabox::get_frame_urls( $product_id );
		if ( empty( $frames ) ) {
			return '<!-- MH 360: No frames for product ' . esc_html( $product_id ) . ' -->';
		}

		$settings = get_option( 'mh_stv_settings', array() );
		$accent   = '#e8910c';
		if ( ! empty( $settings['accent_color'] ) ) {
			$sanitized = sanitize_hex_color( $settings['accent_color'] );
			if ( $sanitized ) {
				$accent = $sanitized;
			}
		}

		// Enqueue 360° assets.
		if ( ! $this->assets_registered ) {
			$this->register_assets();
		}
		wp_enqueue_style( 'mh-stv-360' );
		wp_enqueue_script( 'mh-stv-360' );

		$container_id = 'mh-360-standalone-' . $product_id;
		$autoplay     = ( $atts['autoplay'] !== 'false' ) ? 'true' : 'false';

		$inline_js = sprintf(
			'(function(){' .
			'var c=document.getElementById(%s);' .
			'if(!c||typeof MH360==="undefined")return;' .
			'MH360.create(c,{frames:%s,accentColor:%s,autoplay:%s});' .
			'})();',
			wp_json_encode( $container_id ),
			wp_json_encode( array_values( $frames ) ),
			wp_json_encode( $accent ),
			$autoplay
		);

		// Use wp_add_inline_script to run after MH360 is loaded.
		wp_add_inline_script( 'mh-stv-360', $inline_js );

		return '<div id="' . esc_attr( $container_id ) . '" class="mh-360-standalone"></div>';
	}

	/**
	 * Parse embed selectors from settings textarea (one per line).
	 */
	private function parse_embed_selectors( $settings, $key = 'embed_selectors' ) {
		if ( empty( $settings[ $key ] ) ) {
			return array();
		}
		$raw   = $settings[ $key ];
		$lines = preg_split( '/\r?\n/', $raw );
		$out   = array();
		foreach ( $lines as $line ) {
			$sel = trim( $line );
			if ( $sel !== '' ) {
				$out[] = $sel;
			}
		}
		return $out;
	}

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

MH_STV_Frontend::boot();
