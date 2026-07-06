<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_Grid {

    /* ══ SHORTCODE ════════════════════════════════════════════ */
    public static function render( $atts ) {
        $atts = shortcode_atts( [ 'per_page' => 12, 'columns' => 3 ], $atts );

        wp_enqueue_style( 'mh-stl-opensans', 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap', [], null );
        wp_enqueue_style( 'mh-stl-front', MH_STL_URL . 'assets/css/frontend.css', [ 'mh-stl-opensans' ], MH_STL_VERSION );
        wp_enqueue_script( 'mh-stl-front', MH_STL_URL . 'assets/js/frontend.js', [], MH_STL_VERSION, true );
        wp_localize_script( 'mh-stl-front', 'mhSTLGrid', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mh_stl_grid' ),
            'per_page' => intval( $atts['per_page'] ),
        ] );

        // Categories
        $categories = [];
        if ( taxonomy_exists( 'mh_projekt_kategorie' ) ) {
            $terms = get_terms( [ 'taxonomy' => 'mh_projekt_kategorie', 'hide_empty' => true ] );
            if ( ! is_wp_error( $terms ) ) $categories = $terms;
        }

        // Initial load
        $result = self::query_projects( 1, '', intval( $atts['per_page'] ) );
        $hero = self::find_hero( '' );

        ob_start(); ?>
        <div class="mh-stl-wrapper mh-stl-grid-wrapper" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">

            <!-- Filters -->
            <?php if ( ! empty( $categories ) ) : ?>
            <div class="mh-grid-filters" id="mh-grid-filters" role="tablist" aria-label="Projektkategorien filtern">
                <button class="mh-grid-filter active" data-category="" role="tab" aria-selected="true">Alle</button>
                <?php foreach ( $categories as $cat ) : ?>
                    <button class="mh-grid-filter" data-category="<?php echo esc_attr( $cat->slug ); ?>" role="tab" aria-selected="false">
                        <?php echo esc_html( $cat->name ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Grid -->
            <div class="mh-grid" id="mh-grid">
                <div class="mh-grid-chunk">
                <?php echo self::render_cards( $result['posts'], $hero ); ?>
                </div>
            </div>

            <!-- Load More -->
            <?php if ( $result['has_more'] ) : ?>
            <div class="mh-grid-loadmore-wrap" id="mh-grid-loadmore-wrap">
                <button class="mh-grid-loadmore" id="mh-grid-loadmore" data-page="2">
                    Mehr Projekte laden
                </button>
            </div>
            <?php endif; ?>

            <!-- Lightbox (reused) -->
            <div id="mh-stl-lightbox" class="mh-stl-lightbox" style="display:none;" aria-hidden="true"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ══ QUERY ════════════════════════════════════════════════ */
    public static function query_projects( $page = 1, $category = '', $per_page = 12 ) {
        $args = [
            'post_type'      => MH_STL_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $category ) && taxonomy_exists( 'mh_projekt_kategorie' ) ) {
            $args['tax_query'] = [ [
                'taxonomy' => 'mh_projekt_kategorie',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $category ),
            ] ];
        }

        $q = new WP_Query( $args );
        return [
            'posts'    => $q->posts,
            'has_more' => $q->max_num_pages > $page,
            'total'    => $q->found_posts,
        ];
    }

    /* ══ FIND HERO POST ═══════════════════════════════════════ */
    public static function find_hero( $category = '' ) {

        // ── Global Hero: explicit override for "Alle" (no category) ──
        if ( empty( $category ) ) {
            $global = get_posts( [
                'post_type'      => MH_STL_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => [ [ 'key' => '_mh_stl_hero_global', 'value' => '1' ] ],
            ] );
            if ( ! empty( $global ) ) {
                return $global[0];
            }
        }

        // ── Fallback: priority-based hero ──
        $args = [
            'post_type'      => MH_STL_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [ [ 'key' => '_mh_stl_hero_grid', 'value' => '1' ] ],
        ];

        if ( ! empty( $category ) && taxonomy_exists( 'mh_projekt_kategorie' ) ) {
            $args['tax_query'] = [ [
                'taxonomy' => 'mh_projekt_kategorie',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $category ),
            ] ];
        }

        $q = new WP_Query( $args );
        if ( empty( $q->posts ) ) return null;

        // Sort: highest _mh_stl_hero_priority first, then newest date
        usort( $q->posts, function( $a, $b ) {
            $pa = intval( get_post_meta( $a->ID, '_mh_stl_hero_priority', true ) );
            $pb = intval( get_post_meta( $b->ID, '_mh_stl_hero_priority', true ) );
            if ( $pa !== $pb ) return $pb - $pa;
            return strtotime( $b->post_date ) - strtotime( $a->post_date );
        } );

        return $q->posts[0];
    }

    /* ══ RENDER CARDS ════════════════════════════════════════ */
    public static function render_cards( $posts, $hero_post = null ) {
        if ( empty( $posts ) && ! $hero_post ) {
            return '<div class="mh-grid-empty">Keine Projekte in dieser Kategorie gefunden.</div>';
        }

        // Category color map (based on slug)
        $cat_colors = [
            'sichtschutz'   => '#5b8c5a',
            'hochbeet'      => '#d4883a',
            'terrasse'      => '#8b6f4e',
            'gartenmoebel'  => '#b07d4f',
            'gartenbauten'  => '#6a7b4e',
            'spielplatz'    => '#c25b56',
            'gartenzaun'    => '#5a7d8c',
        ];

        $hero_id = $hero_post ? $hero_post->ID : 0;

        $html = '';
        $card_index = 0;

        // Render hero first if provided
        if ( $hero_post ) {
            $html .= self::render_single_card( $hero_post, $cat_colors, true, $card_index );
            $card_index++;
        }

        // Render remaining cards (skip hero if it's in the list)
        foreach ( $posts as $post ) {
            if ( $hero_id && $post->ID === $hero_id ) continue;
            $html .= self::render_single_card( $post, $cat_colors, false, $card_index );
            $card_index++;
        }

        if ( ! $html ) {
            return '<div class="mh-grid-empty">Keine Projekte in dieser Kategorie gefunden.</div>';
        }

        return $html;
    }

    /* ══ RENDER SINGLE CARD ══════════════════════════════════ */
    private static function render_single_card( $post, $cat_colors, $is_hero, $card_index = 0 ) {
        $data = MH_STL_Frontend::get_data( $post->ID );
        $image = $data['image'] ?? '';
        if ( ! $image ) return '';

        $customer = get_the_title( $post->ID );
        $pin_count = count( $data['pins'] ?? [] );
        $has_pins = $pin_count > 0;

        // First 3 cards: eager load for LCP, rest: lazy
        $loading = $card_index < 3 ? 'eager' : 'lazy';
        $decoding = $card_index < 3 ? 'auto' : 'async';

        // Image dimensions (CLS prevention — only when real values are known)
        // No fallback: missing dimensions render organically (preserves masonry look)
        $img_w = intval( $data['image_w'] ?? 0 );
        $img_h = intval( $data['image_h'] ?? 0 );
        $img_dims = ( $img_w > 0 && $img_h > 0 )
            ? ' width="' . $img_w . '" height="' . $img_h . '"'
            : '';

        // Per-card aspect ratio for layout balance.
        // Cards keep their natural ratio, but clamped so extreme smartphone
        // portraits (~9:16) and panoramas don't break the masonry rhythm.
        $wrap_style = '';
        if ( $img_w > 0 && $img_h > 0 ) {
            $natural = $img_w / $img_h;
            $clamped = max( 0.85, min( 2.0, $natural ) );
            $wrap_style = ' style="--card-aspect: ' . round( $clamped, 3 ) . ';"';
        }

        $cat_name = '';
        $cat_slug = '';
        if ( taxonomy_exists( 'mh_projekt_kategorie' ) ) {
            $terms = wp_get_post_terms( $post->ID, 'mh_projekt_kategorie' );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $cat_name = $terms[0]->name;
                $cat_slug = $terms[0]->slug;
            }
        }
        $cat_color = $cat_colors[ $cat_slug ] ?? '#f7af4a';
        $json = esc_attr( wp_json_encode( $data ) );

        $html = '';

        if ( $is_hero ) {
            $desc = $data['desc'] ?? '';
            $buttons = $data['buttons'] ?? [];

            $html .= '<div class="mh-grid-card mh-grid-card-hero mh-grid-card-reveal' . ( $has_pins ? ' has-pins' : '' ) . '" data-project=\'' . $json . '\' tabindex="0" role="button">';
            $html .= '  <div class="mh-grid-hero-text">';
            if ( $cat_name ) {
                $html .= '    <span class="mh-grid-card-tag" style="--tag-color:' . esc_attr( $cat_color ) . '">';
                $html .= '      <span class="mh-grid-card-tag-dot"></span>' . esc_html( $cat_name );
                $html .= '    </span>';
            }
            $html .= '    <h3 class="mh-grid-hero-title">' . esc_html( $customer ) . '</h3>';
            if ( $desc ) {
                $html .= '    <p class="mh-grid-hero-desc">' . esc_html( $desc ) . '</p>';
            }

            // Custom buttons from meta (if defined), otherwise fallback
            if ( ! empty( $buttons ) ) {
                $html .= '    <div class="mh-grid-hero-buttons">';
                foreach ( $buttons as $i => $btn ) {
                    if ( empty( $btn['text'] ) ) continue;
                    $btn_url   = ! empty( $btn['url'] ) ? esc_url( $btn['url'] ) : '#';
                    $btn_class = $i === 0 ? 'mh-grid-hero-cta' : 'mh-grid-hero-cta mh-grid-hero-cta-secondary';
                    $html .= '    <a href="' . $btn_url . '" class="' . $btn_class . '" onclick="event.stopPropagation();">';
                    if ( $i === 0 ) {
                        $html .= '      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>';
                    }
                    $html .= '      ' . esc_html( $btn['text'] );
                    $html .= '    </a>';
                }
                $html .= '    </div>';
            } elseif ( $has_pins ) {
                $html .= '    <span class="mh-grid-hero-cta">';
                $html .= '      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>';
                $html .= '      ' . $pin_count . ' Produkt' . ( $pin_count !== 1 ? 'e' : '' ) . ' entdecken';
                $html .= '    </span>';
            } else {
                $html .= '    <span class="mh-grid-hero-cta mh-grid-hero-cta-view">';
                $html .= '      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>';
                $html .= '      Projekt ansehen';
                $html .= '    </span>';
            }
            $html .= '  </div>';
            $html .= '  <div class="mh-grid-hero-image">';
            $html .= '    <img src="' . esc_url( $image ) . '" alt="' . esc_attr( $customer ) . '"' . $img_dims . ' loading="' . $loading . '" decoding="' . $decoding . '">';
            $html .= '  </div>';
            $html .= '</div>';

        } else {
            $card_classes = 'mh-grid-card mh-grid-card-reveal';
            if ( $has_pins ) $card_classes .= ' has-pins';

            $html .= '<div class="' . $card_classes . '" data-project=\'' . $json . '\' tabindex="0" role="button">';
            $html .= '  <div class="mh-grid-card-image"' . $wrap_style . '>';
            $html .= '    <img src="' . esc_url( $image ) . '" alt="' . esc_attr( $customer ) . '"' . $img_dims . ' loading="' . $loading . '" decoding="' . $decoding . '">';
            $html .= '    <div class="mh-grid-card-bottom">';
            $html .= '      <div class="mh-grid-card-title">' . esc_html( $customer ) . '</div>';
            if ( $cat_name ) {
                $html .= '      <span class="mh-grid-card-tag mh-grid-card-tag-overlay" style="--tag-color:' . esc_attr( $cat_color ) . '">';
                $html .= '        <span class="mh-grid-card-tag-dot"></span>' . esc_html( $cat_name );
                $html .= '      </span>';
            }
            $html .= '    </div>';
            $html .= '    <div class="mh-grid-card-overlay">';
            if ( $has_pins ) {
                $html .= '      <span class="mh-grid-card-badge">';
                $html .= '        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>';
                $html .= '        ' . $pin_count . ' Produkt' . ( $pin_count !== 1 ? 'e' : '' ) . ' entdecken';
                $html .= '      </span>';
            } else {
                $html .= '      <span class="mh-grid-card-badge mh-grid-card-badge-view">';
                $html .= '        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>';
                $html .= '        Bild vergrößern';
                $html .= '      </span>';
            }
            $html .= '    </div>';
            $html .= '  </div>';
            $html .= '</div>';
        }

        return $html;
    }

    /* ══ AJAX ════════════════════════════════════════════════ */
    public static function ajax_load() {
        check_ajax_referer( 'mh_stl_grid', 'nonce' );

        $page     = absint( $_POST['page'] ?? 1 );
        $category = sanitize_text_field( $_POST['category'] ?? '' );
        $per_page = absint( $_POST['per_page'] ?? 12 );

        $result = self::query_projects( $page, $category, $per_page );
        $hero = ( $page === 1 ) ? self::find_hero( $category ) : null;

        wp_send_json_success( [
            'html'     => self::render_cards( $result['posts'], $hero ),
            'has_more' => $result['has_more'],
            'total'    => $result['total'],
        ] );
    }
}
