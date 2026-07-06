<?php
/**
 * 360° Metabox — WooCommerce product data tab for managing 360° image sequences.
 *
 * Adds a "360° Ansicht" tab to the WC product data panel where admins can
 * upload/reorder frames from the Media Library. Stores attachment IDs in
 * post meta `_mh_stv_360_frames`.
 *
 * @package MH_Spielturm_Vergleich
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MH_STV_360_Metabox {

	private static $instance = null;

	public static function boot() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
	}

	private function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add "360° Ansicht" tab to WooCommerce product data.
	 */
	public function add_product_tab( $tabs ) {
		$tabs['mh_stv_360'] = array(
			'label'    => '360° Ansicht',
			'target'   => 'mh_stv_360_panel',
			'class'    => array(),
			'priority' => 80,
		);
		return $tabs;
	}

	/**
	 * Render the 360° panel content.
	 */
	public function render_panel() {
		global $post;
		$frame_ids = get_post_meta( $post->ID, '_mh_stv_360_frames', true );
		if ( ! is_array( $frame_ids ) ) {
			$frame_ids = array();
		}
		wp_nonce_field( 'mh_stv_360_save', 'mh_stv_360_nonce' );
		?>
		<div id="mh_stv_360_panel" class="panel woocommerce_options_panel">
			<div class="options_group" style="padding:12px 12px 0;">
				<p class="form-field">
					<strong>360° Bildsequenz</strong><br>
					<span class="description" style="margin-bottom:10px;display:block;">
						Lade die Einzelbilder deiner 360°-Drehung hoch (empfohlen: 36–72 Frames als WebP).<br>
						Reihenfolge = Drehrichtung. Bilder per Drag &amp; Drop sortieren.
					</span>
				</p>

				<div id="mh-stv-360-frames-wrap" style="display:flex;flex-wrap:wrap;gap:6px;min-height:40px;padding:8px;border:1px dashed #c3c4c7;border-radius:4px;background:#f9f9f9;">
					<?php foreach ( $frame_ids as $att_id ) :
						$att_id = absint( $att_id );
						$thumb  = wp_get_attachment_image_url( $att_id, 'thumbnail' );
						if ( ! $thumb ) continue;
					?>
						<div class="mh-stv-360-frame" data-id="<?php echo esc_attr( $att_id ); ?>" style="position:relative;width:60px;height:60px;border-radius:4px;overflow:hidden;cursor:grab;border:2px solid #ddd;">
							<img src="<?php echo esc_url( $thumb ); ?>" style="width:100%;height:100%;object-fit:cover;" />
							<input type="hidden" name="mh_stv_360_frames[]" value="<?php echo esc_attr( $att_id ); ?>" />
							<button type="button" class="mh-stv-360-remove" style="position:absolute;top:0;right:0;background:rgba(0,0,0,.7);color:#fff;border:none;width:18px;height:18px;font-size:12px;line-height:18px;text-align:center;cursor:pointer;border-radius:0 0 0 4px;">&times;</button>
						</div>
					<?php endforeach; ?>
				</div>

				<p style="margin-top:10px;">
					<button type="button" id="mh-stv-360-add-btn" class="button">
						Frames hinzufügen
					</button>
					<button type="button" id="mh-stv-360-clear-btn" class="button" style="color:#b32d2e;">
						Alle entfernen
					</button>
					<span id="mh-stv-360-count" style="margin-left:12px;color:#666;">
						<?php echo count( $frame_ids ); ?> Frame(s)
					</span>
				</p>

				<p class="form-field" style="margin-top:16px;">
					<strong>FFmpeg-Tipp:</strong> <code style="font-size:12px;">ffmpeg -i video.mp4 -vf "select=not(mod(n\,10))" -vsync vfn -q:v 85 frame_%03d.webp</code>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save 360° frame IDs on product save.
	 */
	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['mh_stv_360_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mh_stv_360_nonce'] ) ), 'mh_stv_360_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['mh_stv_360_frames'] ) && is_array( $_POST['mh_stv_360_frames'] ) ) {
			$ids = array_map( 'absint', $_POST['mh_stv_360_frames'] );
			$ids = array_filter( $ids );
			update_post_meta( $post_id, '_mh_stv_360_frames', $ids );
		} else {
			delete_post_meta( $post_id, '_mh_stv_360_frames' );
		}
	}

	/**
	 * Enqueue admin scripts on product edit screens.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'product' ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		// WooCommerce product data tab icon.
		wp_add_inline_style( 'woocommerce_admin_styles',
			'#woocommerce-product-data ul.wc-tabs li.mh_stv_360_options a::before { content: "\f463"; font-family: dashicons; }'
		);

		$inline_js = "
		jQuery(function($) {
			var wrap = $('#mh-stv-360-frames-wrap');
			var countEl = $('#mh-stv-360-count');

			function updateCount() {
				var n = wrap.find('.mh-stv-360-frame').length;
				countEl.text(n + ' Frame(s)');
			}

			// Sortable
			wrap.sortable({
				items: '.mh-stv-360-frame',
				cursor: 'grabbing',
				tolerance: 'pointer',
				placeholder: 'mh-stv-360-placeholder',
				start: function(e, ui) {
					ui.placeholder.css({ width: '60px', height: '60px', border: '2px dashed #e8910c', borderRadius: '4px', background: '#fff8ef' });
				}
			});

			// Add frames
			$('#mh-stv-360-add-btn').on('click', function(e) {
				e.preventDefault();
				var frame = wp.media({
					title: '360° Frames auswählen',
					button: { text: 'Frames hinzufügen' },
					library: { type: 'image' },
					multiple: true
				});
				frame.on('select', function() {
					var selection = frame.state().get('selection');
					selection.each(function(attachment) {
						var data = attachment.toJSON();
						var thumb = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
						var html = '<div class=\"mh-stv-360-frame\" data-id=\"' + data.id + '\" style=\"position:relative;width:60px;height:60px;border-radius:4px;overflow:hidden;cursor:grab;border:2px solid #ddd;\">'
							+ '<img src=\"' + thumb + '\" style=\"width:100%;height:100%;object-fit:cover;\" />'
							+ '<input type=\"hidden\" name=\"mh_stv_360_frames[]\" value=\"' + data.id + '\" />'
							+ '<button type=\"button\" class=\"mh-stv-360-remove\" style=\"position:absolute;top:0;right:0;background:rgba(0,0,0,.7);color:#fff;border:none;width:18px;height:18px;font-size:12px;line-height:18px;text-align:center;cursor:pointer;border-radius:0 0 0 4px;\">&times;</button>'
							+ '</div>';
						wrap.append(html);
					});
					updateCount();
				});
				frame.open();
			});

			// Remove single frame
			wrap.on('click', '.mh-stv-360-remove', function(e) {
				e.preventDefault();
				$(this).closest('.mh-stv-360-frame').remove();
				updateCount();
			});

			// Clear all
			$('#mh-stv-360-clear-btn').on('click', function(e) {
				e.preventDefault();
				if (confirm('Alle 360° Frames entfernen?')) {
					wrap.find('.mh-stv-360-frame').remove();
					updateCount();
				}
			});
		});
		";
		wp_add_inline_script( 'jquery-ui-sortable', $inline_js );
	}

	/**
	 * Static helper: get 360° frame URLs for a product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $size       Image size (default: 'large').
	 * @return array Array of image URLs.
	 */
	public static function get_frame_urls( $product_id, $size = 'large' ) {
		$ids = get_post_meta( absint( $product_id ), '_mh_stv_360_frames', true );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return array();
		}

		$urls = array();
		foreach ( $ids as $att_id ) {
			$url = wp_get_attachment_image_url( absint( $att_id ), $size );
			if ( $url ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * Static helper: get 360° frame thumbnail URLs for a product.
	 */
	public static function get_frame_thumb_url( $product_id ) {
		$ids = get_post_meta( absint( $product_id ), '_mh_stv_360_frames', true );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return '';
		}
		// Return the first frame as the 360° thumbnail preview.
		$first = absint( $ids[0] );
		$url   = wp_get_attachment_image_url( $first, 'woocommerce_gallery_thumbnail' );
		return $url ? $url : '';
	}
}
