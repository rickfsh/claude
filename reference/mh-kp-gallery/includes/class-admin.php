<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_Admin {

    /* ── Register Metaboxes ───────────────────────────────────── */
    public static function register_metabox() {
        add_meta_box( 'mh_stl_details', '✏️ Shop the Look — Details', [ __CLASS__, 'render_details' ], MH_STL_POST_TYPE, 'normal', 'high' );
        add_meta_box( 'mh_stl_pin_editor', '📌 Shop the Look — Pin-Editor', [ __CLASS__, 'render_pins' ], MH_STL_POST_TYPE, 'normal', 'high' );
    }

    /* ══════════════════════════════════════════════════════════
       DETAILS METABOX (Title, Description, Buttons)
       ══════════════════════════════════════════════════════════ */
    public static function render_details( $post ) {
        wp_nonce_field( 'mh_stl_save', 'mh_stl_nonce' );

        $stl_title = get_post_meta( $post->ID, '_mh_stl_title', true );
        $stl_desc  = get_post_meta( $post->ID, '_mh_stl_desc', true );
        $show_slider = get_post_meta( $post->ID, '_mh_stl_show_in_slider', true );
        $hero_grid   = get_post_meta( $post->ID, '_mh_stl_hero_grid', true );
        $hero_global = get_post_meta( $post->ID, '_mh_stl_hero_global', true );
        $buttons   = get_post_meta( $post->ID, '_mh_stl_buttons', true );
        $buttons   = is_array( $buttons ) ? $buttons : [];

        // Ensure at least 2 button slots
        while ( count( $buttons ) < 2 ) {
            $buttons[] = [ 'text' => '', 'url' => '', 'style' => 'primary' ];
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="mh_stl_show_in_slider">Im Slider anzeigen</label></th>
                <td>
                    <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="mh_stl_show_in_slider" name="mh_stl_show_in_slider" value="1"
                               <?php checked( $show_slider, '1' ); ?>>
                        <span>Dieses Projekt in der <code>[mh_shop_the_look]</code> Slide-Ansicht anzeigen</span>
                    </label>
                    <p class="description">Nur Projekte mit dieser Option werden im Slider dargestellt. Pins müssen trotzdem gesetzt sein.</p>
                </td>
            </tr>
            <tr>
                <th><label for="mh_stl_hero_grid">Hero-Projekt (Grid)</label></th>
                <td>
                    <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="mh_stl_hero_grid" name="mh_stl_hero_grid" value="1"
                               <?php checked( $hero_grid, '1' ); ?>>
                        <span>Als großes Hero-Projekt im Grid darstellen</span>
                    </label>
                    <div style="margin-top:10px;display:flex;align-items:center;gap:10px;">
                        <label for="mh_stl_hero_priority" style="font-weight:500;">Priorität:</label>
                        <input type="number" id="mh_stl_hero_priority" name="mh_stl_hero_priority"
                               value="<?php echo esc_attr( get_post_meta( $post->ID, '_mh_stl_hero_priority', true ) ?: '0' ); ?>"
                               min="0" max="100" step="1" style="width:80px;">
                        <span style="color:#999;font-size:12px;">Höchste Zahl = wird in der jeweiligen Kategorie als Hero angezeigt.</span>
                    </div>
                    <div style="margin-top:12px;padding:10px 14px;background:#fff8ee;border:1px solid #f0dbb8;border-radius:6px;">
                        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="mh_stl_hero_global" name="mh_stl_hero_global" value="1"
                                   <?php checked( $hero_global, '1' ); ?>>
                            <strong style="color:#b8860b;">⭐ Fester Global-Hero für „Alle"</strong>
                        </label>
                        <?php
                        // Show which project is currently the global hero
                        $current_global = get_posts( [
                            'post_type'      => MH_STL_POST_TYPE,
                            'post_status'    => 'publish',
                            'posts_per_page' => 1,
                            'meta_query'     => [ [ 'key' => '_mh_stl_hero_global', 'value' => '1' ] ],
                            'exclude'        => [ $post->ID ],
                        ] );
                        if ( ! empty( $current_global ) ) : ?>
                            <p style="margin:6px 0 0;font-size:12px;color:#999;">
                                Aktuell: <strong><?php echo esc_html( $current_global[0]->post_title ); ?></strong> — wird beim Speichern ersetzt.
                            </p>
                        <?php elseif ( $hero_global === '1' ) : ?>
                            <p style="margin:6px 0 0;font-size:12px;color:#5b8c5a;">✓ Dieses Projekt ist aktuell der Global-Hero.</p>
                        <?php else : ?>
                            <p style="margin:6px 0 0;font-size:12px;color:#999;">Kein Global-Hero gesetzt — bei „Alle" wird das Hero-Projekt mit der höchsten Priorität angezeigt.</p>
                        <?php endif; ?>
                    </div>
                    <p class="description" style="margin-top:8px;">Wird in der <code>[mh_kundenprojekte_grid]</code> Ansicht groß dargestellt (Text links, Bild rechts). <strong>Global-Hero</strong> überschreibt die Priorität bei „Alle" und zeigt immer genau dieses Projekt. In Kategorie-Filtern gilt weiter die Priorität. Titel und Beschreibung sollten ausgefüllt sein.</p>
                </td>
            </tr>
            <tr>
                <th><label for="mh_stl_title">Anzeige-Titel</label></th>
                <td>
                    <input type="text" id="mh_stl_title" name="mh_stl_title"
                           value="<?php echo esc_attr( $stl_title ); ?>"
                           class="large-text" placeholder="z.B. Ein minimalistischer Zaun im Einklang mit der Natur">
                    <p class="description">Wird rechts neben dem Bild angezeigt. Leer lassen = Beitragstitel wird verwendet.</p>
                </td>
            </tr>
            <tr>
                <th><label for="mh_stl_desc">Beschreibung</label></th>
                <td>
                    <textarea id="mh_stl_desc" name="mh_stl_desc" rows="4"
                              class="large-text" placeholder="Kurze Beschreibung des Projekts…"><?php echo esc_textarea( $stl_desc ); ?></textarea>
                </td>
            </tr>
            <tr>
                <th>Buttons</th>
                <td>
                    <?php for ( $i = 0; $i < 2; $i++ ) :
                        $btn = $buttons[ $i ];
                        $style_label = $i === 0 ? 'Primär (farbig)' : 'Sekundär (dunkel)';
                    ?>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                        <input type="text" name="mh_stl_btn_text[]" value="<?php echo esc_attr( $btn['text'] ); ?>"
                               placeholder="Button-Text" style="width:200px;">
                        <input type="url" name="mh_stl_btn_url[]" value="<?php echo esc_attr( $btn['url'] ); ?>"
                               placeholder="https://…" style="width:300px;">
                        <span style="color:#999;font-size:12px;"><?php echo $style_label; ?></span>
                    </div>
                    <?php endfor; ?>
                    <p class="description">Leer lassen = Button wird nicht angezeigt.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /* ══════════════════════════════════════════════════════════
       PIN EDITOR METABOX
       ══════════════════════════════════════════════════════════ */
    public static function render_pins( $post ) {
        $pins = get_post_meta( $post->ID, '_mh_stl_pins', true );
        $pins = is_array( $pins ) ? $pins : [];

        // Get ALL gallery images
        $gallery_images = [];
        if ( function_exists( 'get_field' ) ) {
            $gallery = get_field( 'referenz-galerie', $post->ID );
            if ( empty( $gallery ) ) $gallery = get_field( 'referenz_galerie', $post->ID );
            if ( empty( $gallery ) ) $gallery = get_field( 'galerie', $post->ID );
            if ( ! empty( $gallery ) && is_array( $gallery ) ) {
                foreach ( $gallery as $item ) {
                    $url   = '';
                    $thumb = '';
                    if ( is_array( $item ) ) {
                        $url   = $item['url'] ?? ( $item['sizes']['large'] ?? '' );
                        $thumb = $item['sizes']['thumbnail'] ?? ( $item['sizes']['medium'] ?? $url );
                    } elseif ( is_numeric( $item ) ) {
                        $url   = wp_get_attachment_image_url( $item, 'large' ) ?: '';
                        $thumb = wp_get_attachment_image_url( $item, 'thumbnail' ) ?: $url;
                    }
                    if ( $url ) {
                        $gallery_images[] = [ 'url' => $url, 'thumb' => $thumb ];
                    }
                }
            }
        }
        if ( empty( $gallery_images ) && has_post_thumbnail( $post->ID ) ) {
            $url   = get_the_post_thumbnail_url( $post->ID, 'large' );
            $thumb = get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: $url;
            if ( $url ) {
                $gallery_images[] = [ 'url' => $url, 'thumb' => $thumb ];
            }
        }

        $image_url = ! empty( $gallery_images ) ? $gallery_images[0]['url'] : '';

        // Enrich pins (ensure image_index exists for backward compat)
        $pins_enriched = [];
        foreach ( $pins as $pin ) {
            $pid = absint( $pin['product_id'] ?? 0 );
            $p = get_post( $pid );
            $pin['product_name']  = $p ? $p->post_title : 'Produkt #' . $pid;
            $pin['product_thumb'] = '';
            if ( ! isset( $pin['image_index'] ) ) {
                $pin['image_index'] = 0;
            }
            if ( $pid ) {
                $tid = get_post_thumbnail_id( $pid );
                if ( $tid ) $pin['product_thumb'] = wp_get_attachment_image_url( $tid, 'thumbnail' ) ?: '';
            }
            $pins_enriched[] = $pin;
        }
        ?>
        <div id="mh-stl-editor">
            <?php if ( $image_url ) : ?>
                <p class="mh-stl-instruction">
                    <strong>Klicke auf das Bild</strong> um einen Produkt-Pin zu platzieren. Dann wähle das Produkt aus.
                    Pins können per Drag & Drop verschoben werden.
                    <?php if ( count( $gallery_images ) > 1 ) : ?>
                        <br>Wechsle zwischen den Bildern um Pins auf verschiedenen Fotos zu platzieren.
                    <?php endif; ?>
                </p>
                <?php if ( count( $gallery_images ) > 1 ) : ?>
                <div id="mh-stl-gallery-strip">
                    <?php foreach ( $gallery_images as $idx => $gi ) : ?>
                        <div class="mh-stl-gallery-thumb-wrap<?php echo $idx === 0 ? ' active' : ''; ?>" data-gindex="<?php echo $idx; ?>">
                            <img src="<?php echo esc_url( $gi['thumb'] ); ?>" alt="Bild <?php echo $idx + 1; ?>">
                            <span class="mh-stl-gallery-thumb-num"><?php echo $idx + 1; ?></span>
                            <span class="mh-stl-gallery-pin-count" data-gindex="<?php echo $idx; ?>"></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div id="mh-stl-canvas-wrap">
                    <div id="mh-stl-canvas">
                        <img id="mh-stl-image" src="<?php echo esc_url( $image_url ); ?>" alt="Projektbild">
                    </div>
                </div>
                <div id="mh-stl-pin-list">
                    <h4>Platzierte Pins</h4>
                    <div id="mh-stl-pins-container"></div>
                </div>
                <input type="hidden" id="mh-stl-pins-data" name="mh_stl_pins"
                       value="<?php echo esc_attr( wp_json_encode( $pins_enriched ) ); ?>">
                <input type="hidden" id="mh-stl-gallery-data"
                       value="<?php echo esc_attr( wp_json_encode( $gallery_images ) ); ?>">
            <?php else : ?>
                <div class="mh-stl-no-image">
                    <p>⚠️ <strong>Kein Bild gefunden.</strong> Bitte zuerst ein Bild hochladen, dann speichern.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════════
       SAVE
       ══════════════════════════════════════════════════════════ */
    public static function save( $post_id ) {
        if ( ! isset( $_POST['mh_stl_nonce'] ) || ! wp_verify_nonce( $_POST['mh_stl_nonce'], 'mh_stl_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Title & Description
        if ( isset( $_POST['mh_stl_title'] ) ) {
            update_post_meta( $post_id, '_mh_stl_title', sanitize_text_field( $_POST['mh_stl_title'] ) );
        }
        if ( isset( $_POST['mh_stl_desc'] ) ) {
            update_post_meta( $post_id, '_mh_stl_desc', sanitize_textarea_field( $_POST['mh_stl_desc'] ) );
        }

        // Slider visibility
        update_post_meta( $post_id, '_mh_stl_show_in_slider', isset( $_POST['mh_stl_show_in_slider'] ) ? '1' : '' );

        // Hero grid
        update_post_meta( $post_id, '_mh_stl_hero_grid', isset( $_POST['mh_stl_hero_grid'] ) ? '1' : '' );

        // Hero priority (0-100)
        $hero_priority = isset( $_POST['mh_stl_hero_priority'] ) ? absint( $_POST['mh_stl_hero_priority'] ) : 0;
        $hero_priority = min( 100, $hero_priority );
        update_post_meta( $post_id, '_mh_stl_hero_priority', $hero_priority );

        // Global Hero — only one can be active at a time
        $is_global_hero = isset( $_POST['mh_stl_hero_global'] );
        if ( $is_global_hero ) {
            // Unset all other global heroes
            $existing = get_posts( [
                'post_type'      => MH_STL_POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'meta_query'     => [ [ 'key' => '_mh_stl_hero_global', 'value' => '1' ] ],
                'fields'         => 'ids',
                'exclude'        => [ $post_id ],
            ] );
            foreach ( $existing as $eid ) {
                update_post_meta( $eid, '_mh_stl_hero_global', '' );
            }
            // Also ensure hero_grid is on when global hero is set
            update_post_meta( $post_id, '_mh_stl_hero_grid', '1' );
        }
        update_post_meta( $post_id, '_mh_stl_hero_global', $is_global_hero ? '1' : '' );

        // Buttons
        $buttons = [];
        if ( ! empty( $_POST['mh_stl_btn_text'] ) && is_array( $_POST['mh_stl_btn_text'] ) ) {
            for ( $i = 0; $i < count( $_POST['mh_stl_btn_text'] ); $i++ ) {
                $buttons[] = [
                    'text'  => sanitize_text_field( $_POST['mh_stl_btn_text'][ $i ] ?? '' ),
                    'url'   => esc_url_raw( $_POST['mh_stl_btn_url'][ $i ] ?? '' ),
                    'style' => $i === 0 ? 'primary' : 'secondary',
                ];
            }
        }
        update_post_meta( $post_id, '_mh_stl_buttons', $buttons );

        // Pins
        if ( isset( $_POST['mh_stl_pins'] ) ) {
            $raw = json_decode( stripslashes( $_POST['mh_stl_pins'] ), true );
            $pins = [];
            if ( is_array( $raw ) ) {
                foreach ( $raw as $pin ) {
                    $pins[] = [
                        'x'           => floatval( $pin['x'] ?? 0 ),
                        'y'           => floatval( $pin['y'] ?? 0 ),
                        'product_id'  => absint( $pin['product_id'] ?? 0 ),
                        'image_index' => absint( $pin['image_index'] ?? 0 ),
                    ];
                }
            }
            update_post_meta( $post_id, '_mh_stl_pins', $pins );
        }
    }

    /* ── Enqueue ──────────────────────────────────────────────── */
    public static function enqueue( $hook ) {
        global $post_type;
        if ( $post_type !== MH_STL_POST_TYPE ) return;
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) return;

        wp_enqueue_style( 'mh-stl-admin', MH_STL_URL . 'assets/css/admin.css', [], MH_STL_VERSION );
        wp_enqueue_script( 'mh-stl-admin', MH_STL_URL . 'assets/js/admin.js', [ 'jquery', 'jquery-ui-draggable' ], MH_STL_VERSION, true );
        wp_localize_script( 'mh-stl-admin', 'mhSTL', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mh_stl_admin' ),
        ] );
    }

    /* ── Product Search AJAX ──────────────────────────────────── */
    public static function ajax_search() {
        check_ajax_referer( 'mh_stl_admin', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();

        $term = sanitize_text_field( $_POST['term'] ?? '' );
        if ( strlen( $term ) < 2 ) wp_send_json_success( [] );

        global $wpdb;
        $results = [];

        if ( is_numeric( $term ) ) {
            $p = get_post( absint( $term ) );
            if ( $p && $p->post_type === 'product' && $p->post_status === 'publish' ) {
                $tid = get_post_thumbnail_id( $p->ID );
                $results[] = [
                    'id'    => $p->ID,
                    'name'  => $p->post_title,
                    'thumb' => $tid ? wp_get_attachment_image_url( $tid, 'thumbnail' ) : '',
                ];
            }
        }

        $like = '%' . $wpdb->esc_like( $term ) . '%';
        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type='product' AND post_status='publish' AND post_title LIKE %s
             ORDER BY post_title LIMIT 10", $like
        ) );

        $ids = array_column( $results, 'id' );
        foreach ( $posts as $p ) {
            if ( in_array( $p->ID, $ids ) ) continue;
            $tid = get_post_thumbnail_id( $p->ID );
            $results[] = [
                'id'    => $p->ID,
                'name'  => $p->post_title,
                'thumb' => $tid ? wp_get_attachment_image_url( $tid, 'thumbnail' ) : '',
            ];
        }

        wp_send_json_success( $results );
    }
}
