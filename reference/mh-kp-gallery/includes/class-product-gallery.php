<?php
/**
 * MH Shop the Look — Product Page Gallery
 *
 * Shortcode: [mh_kundenprojekte_produkt]
 * Shows Kundenprojekte that feature the current (or specified) product as a pin.
 * Designed as an "Inspirations-Kacheln" masonry grid for WooCommerce product pages.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_Product_Gallery {

    /**
     * Render the shortcode.
     */
    public static function render( $atts ) {
        $atts = shortcode_atts( [
            'product_id' => 0,
            'per_page'   => 6,
            'columns'    => 3,
            'headline'   => '',
            'show_count' => 'true',
        ], $atts );

        // Determine product ID
        $product_id = absint( $atts['product_id'] );
        if ( ! $product_id ) {
            global $post;
            if ( $post && $post->post_type === 'product' ) {
                $product_id = $post->ID;
            }
        }
        if ( ! $product_id ) {
            return '<!-- mh_kundenprojekte_produkt: kein Produkt erkannt -->';
        }

        $per_page = max( 1, absint( $atts['per_page'] ) );

        // Enqueue assets
        wp_enqueue_style( 'mh-stl-opensans', 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap', [], null );
        wp_enqueue_style( 'mh-stl-front', MH_STL_URL . 'assets/css/frontend.css', [ 'mh-stl-opensans' ], MH_STL_VERSION );
        wp_enqueue_script( 'mh-stl-front', MH_STL_URL . 'assets/js/frontend.js', [], MH_STL_VERSION, true );
        wp_localize_script( 'mh-stl-front', 'mhSTLProduct', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'mh_stl_product_gallery' ),
            'product_id' => $product_id,
            'per_page'   => $per_page,
        ] );

        // Find ALL matching post IDs (for total count), then slice first page
        $all_ids  = self::find_project_ids_for_product( $product_id, 200 );
        $total    = count( $all_ids );

        if ( $total === 0 ) {
            return '<!-- mh_kundenprojekte_produkt: keine Projekte gefunden -->';
        }

        $page_ids = array_slice( $all_ids, 0, $per_page );
        $projects = array_filter( array_map( 'get_post', $page_ids ) );
        $has_more = $total > $per_page;

        $columns    = max( 1, min( 3, absint( $atts['columns'] ) ) );
        $show_count = filter_var( $atts['show_count'], FILTER_VALIDATE_BOOLEAN );
        $headline   = $atts['headline'] ?: 'So setzen unsere Kunden dieses Produkt ein';

        ob_start(); ?>
        <div class="mh-stl-wrapper mh-stl-product-wrapper" data-columns="<?php echo esc_attr( $columns ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>">

            <!-- Header -->
            <div class="mh-pg-header">
                <div class="mh-pg-header-text">
                    <div class="mh-pg-accent-line"></div>
                    <h3 class="mh-pg-headline"><?php echo esc_html( $headline ); ?></h3>
                    <?php if ( $show_count ) : ?>
                        <span class="mh-pg-count" id="mh-pg-count">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <?php
                            printf(
                                esc_html( _n(
                                    'In %d Kundenprojekt verbaut',
                                    'In %d Kundenprojekten verbaut',
                                    $total,
                                    'mh-stl'
                                ) ),
                                $total
                            );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Masonry Grid -->
            <div class="mh-pg-grid" id="mh-pg-grid">
                <?php echo self::render_cards( $projects ); ?>
            </div>

            <!-- Load More -->
            <?php if ( $has_more ) : ?>
            <div class="mh-pg-loadmore-wrap" id="mh-pg-loadmore-wrap">
                <button class="mh-pg-loadmore" id="mh-pg-loadmore" data-page="2">
                    Mehr Kundenprojekte anzeigen
                    <span class="mh-pg-loadmore-count">(<?php echo ( $total - $per_page ); ?> weitere)</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Lightbox -->
            <?php if ( ! did_action( 'mh_stl_lightbox_rendered' ) ) : ?>
                <div id="mh-stl-lightbox" class="mh-stl-lightbox" style="display:none;" aria-hidden="true"></div>
                <?php do_action( 'mh_stl_lightbox_rendered' ); ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render card HTML for a list of post objects.
     */
    public static function render_cards( $projects ) {
        $html = '';
        foreach ( $projects as $post_obj ) {
            $data  = MH_STL_Frontend::get_data( $post_obj->ID );
            $image = $data['image'] ?? '';
            if ( ! $image ) continue;

            $customer  = get_the_title( $post_obj->ID );
            $pin_count = count( $data['pins'] ?? [] );
            $has_pins  = $pin_count > 0;
            $json      = esc_attr( wp_json_encode( $data ) );

            $html .= '<div class="mh-pg-card mh-grid-card-reveal' . ( $has_pins ? ' has-pins' : '' ) . '" data-project=\'' . $json . '\'>';
            $html .= '  <div class="mh-pg-card-image">';
            $html .= '    <img src="' . esc_url( $image ) . '" alt="' . esc_attr( $customer ) . '" loading="lazy">';
            $html .= '    <div class="mh-pg-card-bottom">';
            $html .= '      <div class="mh-pg-card-title">' . esc_html( $customer ) . '</div>';
            $html .= '    </div>';
            $html .= '    <div class="mh-pg-card-overlay">';
            if ( $has_pins ) {
                $html .= '    <span class="mh-pg-card-badge">';
                $html .= '      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>';
                $html .= '      ' . $pin_count . ' Produkt' . ( $pin_count !== 1 ? 'e' : '' ) . ' entdecken';
                $html .= '    </span>';
            } else {
                $html .= '    <span class="mh-pg-card-badge mh-pg-card-badge-view">';
                $html .= '      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>';
                $html .= '      Projekt ansehen';
                $html .= '    </span>';
            }
            $html .= '    </div>';
            $html .= '  </div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * AJAX: Load more projects for a product.
     */
    public static function ajax_load_more() {
        check_ajax_referer( 'mh_stl_product_gallery', 'nonce' );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        $page       = absint( $_POST['page'] ?? 1 );
        $per_page   = absint( $_POST['per_page'] ?? 6 );

        if ( ! $product_id ) {
            wp_send_json_error( 'Missing product_id' );
        }

        $all_ids   = self::find_project_ids_for_product( $product_id, 200 );
        $total     = count( $all_ids );
        $offset    = ( $page - 1 ) * $per_page;
        $page_ids  = array_slice( $all_ids, $offset, $per_page );
        $projects  = array_filter( array_map( 'get_post', $page_ids ) );
        $has_more  = ( $offset + $per_page ) < $total;
        $remaining = max( 0, $total - $offset - $per_page );

        wp_send_json_success( [
            'html'      => self::render_cards( $projects ),
            'has_more'  => $has_more,
            'remaining' => $remaining,
            'total'     => $total,
        ] );
    }

    /**
     * Find kundenprojekt post IDs that have a specific product pinned.
     * Returns verified array of post IDs, ordered by date DESC.
     */
    public static function find_project_ids_for_product( $product_id, $limit = 200 ) {
        global $wpdb;

        $like_int = '%"product_id";i:' . absint( $product_id ) . ';%';

        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
               AND p.post_status = 'publish'
               AND pm.meta_key = '_mh_stl_pins'
               AND pm.meta_value LIKE %s
             ORDER BY p.post_date DESC
             LIMIT %d",
            MH_STL_POST_TYPE,
            $like_int,
            $limit
        ) );

        if ( empty( $post_ids ) ) return [];

        $verified = [];
        foreach ( $post_ids as $pid ) {
            $pins = get_post_meta( $pid, '_mh_stl_pins', true );
            if ( ! is_array( $pins ) ) continue;

            foreach ( $pins as $pin ) {
                if ( absint( $pin['product_id'] ?? 0 ) === absint( $product_id ) ) {
                    $verified[] = absint( $pid );
                    break;
                }
            }
        }

        return $verified;
    }
}
