<?php
/**
 * MH STL REST API — Endpoints for the Upload Manager
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_REST_API {

    const NAMESPACE = 'mh-stl/v1';

    /* ── Register Routes ──────────────────────────────────── */
    public static function register_routes() {

        /* Projects CRUD */
        register_rest_route( self::NAMESPACE, '/projects', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'list_projects' ],
                'permission_callback' => [ __CLASS__, 'can_edit' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create_project' ],
                'permission_callback' => [ __CLASS__, 'can_edit' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/projects/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_project' ],
                'permission_callback' => [ __CLASS__, 'can_edit' ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update_project' ],
                'permission_callback' => [ __CLASS__, 'can_edit' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete_project' ],
                'permission_callback' => [ __CLASS__, 'can_edit' ],
            ],
        ] );

        /* Image Upload (to FTP + WP Media) */
        register_rest_route( self::NAMESPACE, '/upload', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'upload_image' ],
            'permission_callback' => [ __CLASS__, 'can_edit' ],
        ] );

        /* Product Search */
        register_rest_route( self::NAMESPACE, '/products', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'search_products' ],
            'permission_callback' => [ __CLASS__, 'can_edit' ],
        ] );

        /* Categories */
        register_rest_route( self::NAMESPACE, '/categories', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_categories' ],
            'permission_callback' => [ __CLASS__, 'can_edit' ],
        ] );
    }

    /* ── Permission ───────────────────────────────────────── */
    public static function can_edit() {
        // WP user with permissions
        if ( current_user_can( 'edit_posts' ) ) return true;

        // Team token (from Upload Portal)
        if ( class_exists( 'MH_STL_Upload_Portal' ) ) {
            return MH_STL_Upload_Portal::can_upload();
        }

        return false;
    }

    /* ══════════════════════════════════════════════════════════
       PROJECTS
       ══════════════════════════════════════════════════════════ */

    public static function list_projects( $request ) {
        $args = [
            'post_type'      => MH_STL_POST_TYPE,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => intval( $request->get_param( 'per_page' ) ?: 50 ),
            'paged'          => intval( $request->get_param( 'page' ) ?: 1 ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $cat = sanitize_text_field( $request->get_param( 'category' ) ?: '' );
        if ( $cat ) {
            $args['tax_query'] = [ [
                'taxonomy' => 'mh_projekt_kategorie',
                'field'    => 'slug',
                'terms'    => $cat,
            ] ];
        }

        // Lightweight mode: only id, title, date, status, thumbnail (fast!)
        $lite = $request->get_param( 'lite' ) === '1';

        $query = new WP_Query( $args );
        $items = [];

        foreach ( $query->posts as $post ) {
            $items[] = $lite ? self::format_project_lite( $post ) : self::format_project( $post->ID );
        }

        return rest_ensure_response( [
            'items' => $items,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ] );
    }

    public static function get_project( $request ) {
        $id   = absint( $request['id'] );
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== MH_STL_POST_TYPE ) {
            return new WP_Error( 'not_found', 'Projekt nicht gefunden.', [ 'status' => 404 ] );
        }

        return rest_ensure_response( self::format_project( $id ) );
    }

    public static function create_project( $request ) {
        $body = $request->get_json_params();

        $post_id = wp_insert_post( [
            'post_type'   => MH_STL_POST_TYPE,
            'post_title'  => sanitize_text_field( $body['title'] ?? 'Neues Kundenprojekt' ),
            'post_status' => sanitize_text_field( $body['status'] ?? 'draft' ),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        self::save_project_meta( $post_id, $body );

        return rest_ensure_response( self::format_project( $post_id ) );
    }

    public static function update_project( $request ) {
        $id   = absint( $request['id'] );
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== MH_STL_POST_TYPE ) {
            return new WP_Error( 'not_found', 'Projekt nicht gefunden.', [ 'status' => 404 ] );
        }

        $body = $request->get_json_params();

        $update = [ 'ID' => $id ];
        if ( isset( $body['title'] ) ) {
            $update['post_title'] = sanitize_text_field( $body['title'] );
        }
        if ( isset( $body['status'] ) ) {
            $update['post_status'] = sanitize_text_field( $body['status'] );
        }
        wp_update_post( $update );

        self::save_project_meta( $id, $body );

        return rest_ensure_response( self::format_project( $id ) );
    }

    public static function delete_project( $request ) {
        $id   = absint( $request['id'] );
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== MH_STL_POST_TYPE ) {
            return new WP_Error( 'not_found', 'Projekt nicht gefunden.', [ 'status' => 404 ] );
        }

        wp_trash_post( $id );
        return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
    }

    /* ══════════════════════════════════════════════════════════
       IMAGE UPLOAD
       ══════════════════════════════════════════════════════════ */

    public static function upload_image( $request ) {
        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', 'Keine Datei hochgeladen.', [ 'status' => 400 ] );
        }

        $file      = $files['file'];
        $project_id = absint( $request->get_param( 'project_id' ) ?: 0 );

        // Validate file type
        $allowed = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
        if ( ! in_array( $file['type'], $allowed, true ) ) {
            return new WP_Error( 'invalid_type', 'Nur Bilder (JPG, PNG, WebP, GIF) erlaubt.', [ 'status' => 400 ] );
        }

        // ── Auto-convert to WebP (only for files under 2 MB — larger files are too slow) ──
        $converted = false;
        $max_convert_size = 2 * 1024 * 1024; // 2 MB
        if ( $file['type'] !== 'image/webp'
             && $file['size'] <= $max_convert_size
             && self::can_convert_webp()
        ) {
            $webp_result = self::convert_to_webp( $file['tmp_name'], $file['type'], 78 );
            if ( $webp_result ) {
                $file['tmp_name'] = $webp_result;
                $file['type']     = 'image/webp';
                $file['name']     = pathinfo( $file['name'], PATHINFO_FILENAME ) . '.webp';
                $converted        = true;
            }
        }

        // 1) Upload to FTP if configured
        $ftp_result = null;
        $ftp_settings = get_option( 'mh_stl_ftp_settings', [] );
        if ( ! empty( $ftp_settings['host'] ) && ! empty( $ftp_settings['user'] ) ) {
            $ftp_result = MH_STL_FTP_Handler::upload( $file['tmp_name'], $file['name'], $ftp_settings );
        }

        // 2) Also add to WP Media Library
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload( $file, [ 'test_form' => false ] );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'upload_error', $upload['error'], [ 'status' => 500 ] );
        }

        $attachment_id = wp_insert_attachment( [
            'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
            'post_mime_type' => $upload['type'],
            'post_status'    => 'inherit',
        ], $upload['file'], $project_id );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        // If project_id given, set as thumbnail
        if ( $project_id > 0 ) {
            set_post_thumbnail( $project_id, $attachment_id );
        }

        return rest_ensure_response( [
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_url( $attachment_id ),
            'thumb'         => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
            'large'         => wp_get_attachment_image_url( $attachment_id, 'large' ),
            'webp_converted' => $converted,
            'ftp_uploaded'  => $ftp_result ? true : false,
            'ftp_path'      => $ftp_result['remote_path'] ?? null,
        ] );
    }

    /* ══════════════════════════════════════════════════════════
       PRODUCT SEARCH
       ══════════════════════════════════════════════════════════ */

    public static function search_products( $request ) {
        $term = sanitize_text_field( $request->get_param( 'term' ) ?: '' );
        if ( strlen( $term ) < 2 ) {
            return rest_ensure_response( [] );
        }

        global $wpdb;
        $results = [];

        // Search by ID
        if ( is_numeric( $term ) ) {
            $p = get_post( absint( $term ) );
            if ( $p && $p->post_type === 'product' && $p->post_status === 'publish' ) {
                $results[] = self::format_product( $p );
            }
        }

        // Search by title
        $like  = '%' . $wpdb->esc_like( $term ) . '%';
        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type='product' AND post_status='publish' AND post_title LIKE %s
             ORDER BY post_title LIMIT 15", $like
        ) );

        $ids = array_column( $results, 'id' );
        foreach ( $posts as $p ) {
            if ( in_array( $p->ID, $ids ) ) continue;
            $results[] = self::format_product( get_post( $p->ID ) );
        }

        return rest_ensure_response( $results );
    }

    /* ══════════════════════════════════════════════════════════
       CATEGORIES
       ══════════════════════════════════════════════════════════ */

    public static function get_categories( $request ) {
        $terms = get_terms( [
            'taxonomy'   => 'mh_projekt_kategorie',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) ) return rest_ensure_response( [] );

        $items = [];
        foreach ( $terms as $t ) {
            $items[] = [
                'id'    => $t->term_id,
                'name'  => $t->name,
                'slug'  => $t->slug,
                'count' => $t->count,
            ];
        }

        return rest_ensure_response( $items );
    }

    /* ══════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════ */

    /**
     * Lightweight project data for list view.
     * Only 2-3 DB queries per project instead of 20+.
     */
    private static function format_project_lite( $post ) {
        $thumb = '';
        $tid   = get_post_thumbnail_id( $post->ID );
        if ( $tid ) {
            $thumb = wp_get_attachment_image_url( $tid, 'thumbnail' ) ?: '';
        }

        $pin_count = 0;
        $pins = get_post_meta( $post->ID, '_mh_stl_pins', true );
        if ( is_array( $pins ) ) $pin_count = count( $pins );

        return [
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'status'    => $post->post_status,
            'date'      => $post->post_date,
            'thumb'     => $thumb,
            'pin_count' => $pin_count,
        ];
    }

    private static function format_project( $post_id ) {
        $post = get_post( $post_id );

        $image_url = '';
        $image_id  = get_post_thumbnail_id( $post_id );
        if ( $image_id ) {
            $image_url = wp_get_attachment_image_url( $image_id, 'large' );
        }

        // Try ACF gallery
        $gallery_images = [];
        if ( function_exists( 'get_field' ) ) {
            $gallery = get_field( 'referenz-galerie', $post_id );
            if ( empty( $gallery ) ) $gallery = get_field( 'referenz_galerie', $post_id );
            if ( empty( $gallery ) ) $gallery = get_field( 'galerie', $post_id );
            if ( ! empty( $gallery ) && is_array( $gallery ) ) {
                foreach ( $gallery as $item ) {
                    $url = '';
                    $aid = 0;
                    if ( is_array( $item ) ) {
                        $url = $item['url'] ?? ( $item['sizes']['large'] ?? '' );
                        $aid = $item['ID'] ?? 0;
                    } elseif ( is_numeric( $item ) ) {
                        $url = wp_get_attachment_image_url( $item, 'large' ) ?: '';
                        $aid = $item;
                    }
                    if ( $url ) {
                        $gallery_images[] = [ 'id' => $aid, 'url' => $url ];
                    }
                }
                if ( ! $image_url && ! empty( $gallery_images ) ) {
                    $image_url = $gallery_images[0]['url'];
                }
            }
        }

        // Fallback: custom gallery meta (from Upload Portal)
        if ( empty( $gallery_images ) ) {
            $meta_gallery = get_post_meta( $post_id, '_mh_stl_gallery', true );
            if ( is_array( $meta_gallery ) && ! empty( $meta_gallery ) ) {
                foreach ( $meta_gallery as $aid ) {
                    $aid = absint( $aid );
                    $url = wp_get_attachment_image_url( $aid, 'large' );
                    if ( $url ) {
                        $gallery_images[] = [ 'id' => $aid, 'url' => $url ];
                    }
                }
                if ( ! $image_url && ! empty( $gallery_images ) ) {
                    $image_url = $gallery_images[0]['url'];
                }
            }
        }

        $pins    = get_post_meta( $post_id, '_mh_stl_pins', true ) ?: [];
        $buttons = get_post_meta( $post_id, '_mh_stl_buttons', true ) ?: [];

        // Enrich pins with product data
        $enriched_pins = [];
        foreach ( $pins as $pin ) {
            $pid = absint( $pin['product_id'] ?? 0 );
            $pin_data = [
                'x'           => floatval( $pin['x'] ?? 0 ),
                'y'           => floatval( $pin['y'] ?? 0 ),
                'product_id'  => $pid,
                'image_index' => absint( $pin['image_index'] ?? 0 ),
            ];
            if ( $pid ) {
                $p = get_post( $pid );
                $pin_data['product_name']  = $p ? $p->post_title : '';
                $pin_data['product_thumb'] = '';
                $tid = get_post_thumbnail_id( $pid );
                if ( $tid ) $pin_data['product_thumb'] = wp_get_attachment_image_url( $tid, 'thumbnail' ) ?: '';
            }
            $enriched_pins[] = $pin_data;
        }

        // Categories
        $terms = wp_get_post_terms( $post_id, 'mh_projekt_kategorie', [ 'fields' => 'all' ] );
        $cats  = [];
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $cats[] = [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ];
            }
        }

        return [
            'id'              => $post_id,
            'title'           => $post->post_title,
            'status'          => $post->post_status,
            'date'            => $post->post_date,
            'stl_title'       => get_post_meta( $post_id, '_mh_stl_title', true ) ?: '',
            'stl_desc'        => get_post_meta( $post_id, '_mh_stl_desc', true ) ?: '',
            'show_in_slider'  => get_post_meta( $post_id, '_mh_stl_show_in_slider', true ) === '1',
            'hero_grid'       => get_post_meta( $post_id, '_mh_stl_hero_grid', true ) === '1',
            'image_url'       => $image_url ?: '',
            'image_id'        => $image_id ?: 0,
            'gallery_images'  => $gallery_images,
            'pins'            => $enriched_pins,
            'buttons'         => $buttons,
            'categories'      => $cats,
        ];
    }

    private static function save_project_meta( $post_id, $body ) {
        if ( isset( $body['stl_title'] ) ) {
            update_post_meta( $post_id, '_mh_stl_title', sanitize_text_field( $body['stl_title'] ) );
        }
        if ( isset( $body['stl_desc'] ) ) {
            update_post_meta( $post_id, '_mh_stl_desc', sanitize_textarea_field( $body['stl_desc'] ) );
        }
        if ( isset( $body['show_in_slider'] ) ) {
            update_post_meta( $post_id, '_mh_stl_show_in_slider', $body['show_in_slider'] ? '1' : '' );
        }
        if ( isset( $body['hero_grid'] ) ) {
            update_post_meta( $post_id, '_mh_stl_hero_grid', $body['hero_grid'] ? '1' : '' );
        }

        // Buttons
        if ( isset( $body['buttons'] ) && is_array( $body['buttons'] ) ) {
            $buttons = [];
            foreach ( $body['buttons'] as $i => $btn ) {
                $buttons[] = [
                    'text'  => sanitize_text_field( $btn['text'] ?? '' ),
                    'url'   => esc_url_raw( $btn['url'] ?? '' ),
                    'style' => $i === 0 ? 'primary' : 'secondary',
                ];
            }
            update_post_meta( $post_id, '_mh_stl_buttons', $buttons );
        }

        // Pins
        if ( isset( $body['pins'] ) && is_array( $body['pins'] ) ) {
            $pins = [];
            foreach ( $body['pins'] as $pin ) {
                $pins[] = [
                    'x'           => floatval( $pin['x'] ?? 0 ),
                    'y'           => floatval( $pin['y'] ?? 0 ),
                    'product_id'  => absint( $pin['product_id'] ?? 0 ),
                    'image_index' => absint( $pin['image_index'] ?? 0 ),
                ];
            }
            update_post_meta( $post_id, '_mh_stl_pins', $pins );
        }

        // Categories
        if ( isset( $body['categories'] ) && is_array( $body['categories'] ) ) {
            $term_ids = array_map( 'absint', $body['categories'] );
            wp_set_post_terms( $post_id, $term_ids, 'mh_projekt_kategorie' );
        }

        // Thumbnail
        if ( isset( $body['image_id'] ) && absint( $body['image_id'] ) > 0 ) {
            set_post_thumbnail( $post_id, absint( $body['image_id'] ) );
        }

        // Gallery images (stored as array of attachment IDs)
        if ( isset( $body['gallery_image_ids'] ) && is_array( $body['gallery_image_ids'] ) ) {
            $ids = array_map( 'absint', $body['gallery_image_ids'] );
            $ids = array_filter( $ids );
            update_post_meta( $post_id, '_mh_stl_gallery', $ids );

            // Also set first image as thumbnail if not set
            if ( ! empty( $ids ) && ! has_post_thumbnail( $post_id ) ) {
                set_post_thumbnail( $post_id, $ids[0] );
            }

            // If ACF is active, also update the gallery field
            if ( function_exists( 'update_field' ) ) {
                update_field( 'referenz-galerie', $ids, $post_id );
            }
        }
    }

    private static function format_product( $post ) {
        $data = [
            'id'    => $post->ID,
            'name'  => $post->post_title,
            'thumb' => '',
            'price' => '',
        ];

        $tid = get_post_thumbnail_id( $post->ID );
        if ( $tid ) $data['thumb'] = wp_get_attachment_image_url( $tid, 'thumbnail' ) ?: '';

        if ( function_exists( 'wc_get_product' ) ) {
            $wc = wc_get_product( $post->ID );
            if ( $wc ) {
                if ( $wc->is_type( 'variable' ) ) {
                    $min_price = $wc->get_variation_price( 'min', true );
                    $max_price = $wc->get_variation_price( 'max', true );
                    if ( $min_price !== $max_price ) {
                        $data['price'] = 'ab ' . strip_tags( wc_price( $min_price ) );
                    } else {
                        $data['price'] = strip_tags( wc_price( $min_price ) );
                    }
                } else {
                    $data['price'] = strip_tags( wc_price( wc_get_price_to_display( $wc ) ) );
                }
            }
        }

        return $data;
    }

    /* ══════════════════════════════════════════════════════════
       WEBP CONVERSION
       ══════════════════════════════════════════════════════════ */

    /**
     * Check if the server can convert images to WebP.
     */
    private static function can_convert_webp() {
        // GD with WebP support
        if ( function_exists( 'imagewebp' ) && function_exists( 'imagecreatefromjpeg' ) ) {
            return true;
        }
        // Imagick with WebP support
        if ( class_exists( 'Imagick' ) ) {
            $formats = \Imagick::queryFormats( 'WEBP' );
            if ( ! empty( $formats ) ) return true;
        }
        return false;
    }

    /**
     * Convert an image file to WebP. Returns new temp file path or false.
     *
     * @param string $source_path Path to the source image file.
     * @param string $mime_type   Original MIME type.
     * @param int    $quality     WebP quality (1-100).
     * @return string|false       Path to WebP temp file, or false on failure.
     */
    private static function convert_to_webp( $source_path, $mime_type, $quality = 82 ) {
        $webp_path = $source_path . '.webp';

        // Try GD first (faster, less memory)
        if ( function_exists( 'imagewebp' ) ) {
            $image = null;
            switch ( $mime_type ) {
                case 'image/jpeg':
                    $image = @imagecreatefromjpeg( $source_path );
                    break;
                case 'image/png':
                    $image = @imagecreatefrompng( $source_path );
                    if ( $image ) {
                        // Preserve transparency
                        imagepalettetotruecolor( $image );
                        imagealphablending( $image, true );
                        imagesavealpha( $image, true );
                    }
                    break;
                case 'image/gif':
                    $image = @imagecreatefromgif( $source_path );
                    break;
            }

            if ( $image && @imagewebp( $image, $webp_path, $quality ) ) {
                imagedestroy( $image );
                // Verify the WebP is actually smaller or reasonable
                $orig_size = filesize( $source_path );
                $webp_size = filesize( $webp_path );
                if ( $webp_size > 0 && $webp_size < $orig_size * 1.1 ) {
                    return $webp_path;
                }
                // WebP is larger — still use it for consistency
                if ( $webp_size > 0 ) {
                    return $webp_path;
                }
                @unlink( $webp_path );
            }
            if ( $image ) imagedestroy( $image );
        }

        // Fallback: Imagick
        if ( class_exists( 'Imagick' ) ) {
            try {
                $im = new \Imagick( $source_path );
                $im->setImageFormat( 'webp' );
                $im->setImageCompressionQuality( $quality );

                // Strip metadata to save bytes
                $im->stripImage();

                if ( $im->writeImage( $webp_path ) ) {
                    $im->destroy();
                    if ( filesize( $webp_path ) > 0 ) {
                        return $webp_path;
                    }
                    @unlink( $webp_path );
                }
                $im->destroy();
            } catch ( \Exception $e ) {
                // Silently fail — original file will be used
            }
        }

        return false;
    }
}
