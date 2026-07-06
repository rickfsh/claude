<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_Frontend {

    public static function render( $atts ) {
        $atts = shortcode_atts( [ 'limit' => 20 ], $atts );

        wp_enqueue_style( 'mh-stl-opensans', 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap', [], null );
        wp_enqueue_style( 'mh-stl-front', MH_STL_URL . 'assets/css/frontend.css', [ 'mh-stl-opensans' ], MH_STL_VERSION );
        wp_enqueue_script( 'mh-stl-front', MH_STL_URL . 'assets/js/frontend.js', [], MH_STL_VERSION, true );

        $posts = get_posts( [
            'post_type'      => MH_STL_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => '_mh_stl_pins', 'compare' => 'EXISTS' ],
                [ 'key' => '_mh_stl_show_in_slider', 'value' => '1' ],
            ],
        ] );

        $slides = [];
        foreach ( $posts as $post ) {
            $data = self::get_data( $post->ID );
            if ( empty( $data['image'] ) || empty( $data['pins'] ) ) continue;
            $slides[] = $data;
        }

        if ( empty( $slides ) ) {
            return '<div class="mh-stl-wrapper"><p style="text-align:center;padding:40px;color:#999;">Noch keine Shop-the-Look Projekte vorhanden.</p></div>';
        }

        $slides_json = esc_attr( wp_json_encode( $slides ) );

        ob_start(); ?>
        <div class="mh-stl-wrapper" data-slides='<?php echo $slides_json; ?>'>
            <div class="mh-stl-slider">
                <div class="mh-stl-slide-content">
                    <div class="mh-stl-image-side" id="mh-stl-image-side">
                        <img id="mh-stl-main-img" src="" alt="">
                        <div id="mh-stl-pins-layer"></div>
                        <button class="mh-stl-expand-btn" id="mh-stl-expand" title="Bild vergrößern">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                        </button>
                    </div>
                    <div class="mh-stl-info-side" id="mh-stl-info-side">
                        <div class="mh-stl-info-top">
                            <div class="mh-stl-arrows">
                                <button class="mh-stl-arrow" id="mh-stl-prev" title="Vorheriges Projekt">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                                </button>
                                <button class="mh-stl-arrow" id="mh-stl-next" title="Nächstes Projekt">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                            <div class="mh-stl-counter-text" id="mh-stl-counter-text"></div>
                        </div>
                        <div class="mh-stl-info-body">
                            <h3 class="mh-stl-title" id="mh-stl-title"></h3>
                            <div class="mh-stl-desc" id="mh-stl-desc"></div>
                            <div class="mh-stl-separator" id="mh-stl-separator"></div>
                            <div class="mh-stl-products-list" id="mh-stl-products-list"></div>
                            <div class="mh-stl-buttons" id="mh-stl-buttons"></div>
                        </div>
                        <div class="mh-stl-progress" id="mh-stl-progress">
                            <div class="mh-stl-progress-bar" id="mh-stl-progress-bar"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Lightbox -->
            <div id="mh-stl-lightbox" class="mh-stl-lightbox" style="display:none;" aria-hidden="true"></div>
        </div>
        <?php return ob_get_clean();
    }

    public static function get_data( $post_id ) {
        // Title: custom field → fallback post title
        $stl_title = get_post_meta( $post_id, '_mh_stl_title', true );
        $title = $stl_title ?: get_the_title( $post_id );

        // Description
        $desc = get_post_meta( $post_id, '_mh_stl_desc', true ) ?: '';

        // Buttons
        $raw_buttons = get_post_meta( $post_id, '_mh_stl_buttons', true );
        $buttons = [];
        if ( is_array( $raw_buttons ) ) {
            foreach ( $raw_buttons as $btn ) {
                if ( ! empty( $btn['text'] ) && ! empty( $btn['url'] ) ) {
                    $buttons[] = $btn;
                }
            }
        }

        // Track image dimensions for CLS prevention
        $image_w = 0;
        $image_h = 0;

        // Images (all gallery images)
        $images = [];
        $image = '';
        if ( function_exists( 'get_field' ) ) {
            $gallery = get_field( 'referenz-galerie', $post_id );
            if ( empty( $gallery ) ) $gallery = get_field( 'referenz_galerie', $post_id );
            if ( empty( $gallery ) ) $gallery = get_field( 'galerie', $post_id );
            if ( ! empty( $gallery ) && is_array( $gallery ) ) {
                foreach ( $gallery as $idx => $item ) {
                    $url = '';
                    $thumb_url = '';
                    if ( is_array( $item ) ) {
                        $url = $item['url'] ?? ( $item['sizes']['large'] ?? '' );
                        $thumb_url = $item['sizes']['thumbnail'] ?? ( $item['sizes']['medium'] ?? $url );
                        if ( $idx === 0 && ! empty( $item['width'] ) && ! empty( $item['height'] ) ) {
                            $image_w = intval( $item['width'] );
                            $image_h = intval( $item['height'] );
                        }
                    } elseif ( is_numeric( $item ) ) {
                        $url = wp_get_attachment_image_url( $item, 'large' ) ?: '';
                        $thumb_url = wp_get_attachment_image_url( $item, 'thumbnail' ) ?: $url;
                        if ( $idx === 0 ) {
                            $src = wp_get_attachment_image_src( intval( $item ), 'large' );
                            if ( $src ) { $image_w = intval( $src[1] ); $image_h = intval( $src[2] ); }
                        }
                    }
                    if ( $url ) {
                        $images[] = [ 'url' => $url, 'thumb' => $thumb_url ];
                    }
                }
                if ( ! empty( $images ) ) {
                    $image = $images[0]['url'];
                }
            }
        }
        if ( ! $image && has_post_thumbnail( $post_id ) ) {
            $image = get_the_post_thumbnail_url( $post_id, 'large' );
            $thumb = get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ?: $image;
            $images[] = [ 'url' => $image, 'thumb' => $thumb ];
            $tid = get_post_thumbnail_id( $post_id );
            if ( $tid ) {
                $src = wp_get_attachment_image_src( $tid, 'large' );
                if ( $src ) { $image_w = intval( $src[1] ); $image_h = intval( $src[2] ); }
            }
        }

        // Pins
        $raw_pins = get_post_meta( $post_id, '_mh_stl_pins', true );
        $raw_pins = is_array( $raw_pins ) ? $raw_pins : [];

        $pins = [];
        foreach ( $raw_pins as $pin ) {
            $pid = absint( $pin['product_id'] ?? 0 );
            if ( ! $pid ) continue;
            $p = get_post( $pid );
            if ( ! $p || $p->post_status !== 'publish' ) continue;

            $name = $p->post_title;
            $price = '';
            $sale_price = '';
            $url = get_permalink( $pid );
            $thumb = '';

            if ( function_exists( 'wc_get_product' ) ) {
                $wc = wc_get_product( $pid );
                if ( $wc ) {
                    // Variable products: show "ab X€" with the display price
                    if ( $wc->is_type( 'variable' ) ) {
                        $min_price = $wc->get_variation_price( 'min', true );
                        $max_price = $wc->get_variation_price( 'max', true );
                        if ( $min_price !== $max_price ) {
                            $price = 'ab ' . strip_tags( wc_price( $min_price ) );
                        } else {
                            $price = strip_tags( wc_price( $min_price ) );
                        }
                        $sale_price = '';
                    // Simple/other products: use wc_get_price_to_display for tax-aware pricing
                    } elseif ( $wc->is_on_sale() && $wc->get_regular_price() ) {
                        $price = strip_tags( wc_price( wc_get_price_to_display( $wc, [ 'price' => $wc->get_regular_price() ] ) ) );
                        $sp = $wc->get_sale_price() ? $wc->get_sale_price() : $wc->get_price();
                        $sale_price = strip_tags( wc_price( wc_get_price_to_display( $wc, [ 'price' => $sp ] ) ) );
                    } else {
                        $price = strip_tags( wc_price( wc_get_price_to_display( $wc ) ) );
                        $sale_price = '';
                    }
                    $url = $wc->get_permalink();
                }
            }

            $tid = get_post_thumbnail_id( $pid );
            if ( $tid ) $thumb = wp_get_attachment_image_url( $tid, 'thumbnail' ) ?: '';

            $pins[] = [
                'x' => floatval( $pin['x'] ), 'y' => floatval( $pin['y'] ),
                'image_index' => absint( $pin['image_index'] ?? 0 ),
                'name' => $name, 'price' => $price, 'sale_price' => $sale_price, 'url' => $url, 'thumb' => $thumb,
            ];
        }

        return [
            'title'   => $title,
            'desc'    => $desc,
            'buttons' => $buttons,
            'image'   => $image,
            'image_w' => $image_w,
            'image_h' => $image_h,
            'images'  => $images,
            'pins'    => $pins,
        ];
    }
}
