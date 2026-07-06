<?php
/**
 * MH STL Upload Portal — Password-protected frontend upload page
 * Shortcode: [mh_upload_portal]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_Upload_Portal {

    const COOKIE_NAME = 'mh_stl_team_token';
    const TOKEN_LIFETIME = DAY_IN_SECONDS;

    /* ── Register ────────────────────────────────────────── */
    public static function init() {
        add_shortcode( 'mh_upload_portal', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ] );
    }

    /* ── REST Routes ─────────────────────────────────────── */
    public static function register_routes() {
        // Auth endpoint (public — validates team password)
        register_rest_route( 'mh-stl/v1', '/auth', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_auth' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /* ── Auth Handler ────────────────────────────────────── */
    public static function handle_auth( $request ) {
        $body = $request->get_json_params();
        $password = $body['password'] ?? '';

        $stored = get_option( 'mh_stl_team_password', '' );
        if ( ! $stored || ! $password ) {
            return new WP_Error( 'no_password', 'Kein Team-Passwort konfiguriert.', [ 'status' => 403 ] );
        }

        if ( ! wp_check_password( $password, $stored ) ) {
            return new WP_Error( 'wrong_password', 'Falsches Passwort.', [ 'status' => 403 ] );
        }

        // Generate token
        $token = self::generate_token();

        // Store in transient
        set_transient( 'mh_stl_token_' . $token, true, self::TOKEN_LIFETIME );

        return rest_ensure_response( [
            'success' => true,
            'token'   => $token,
        ] );
    }

    /* ── Token Validation ────────────────────────────────── */
    public static function validate_token( $token = null ) {
        if ( ! $token ) {
            $token = $_COOKIE[ self::COOKIE_NAME ] ?? '';
        }
        if ( ! $token || strlen( $token ) < 20 ) return false;

        return (bool) get_transient( 'mh_stl_token_' . $token );
    }

    /**
     * Permission callback for REST endpoints:
     * Either WP logged-in user with edit_posts, or valid team token.
     */
    public static function can_upload() {
        // WP user with permissions
        if ( current_user_can( 'edit_posts' ) ) return true;

        // Team token from header or cookie
        $token = '';
        if ( ! empty( $_SERVER['HTTP_X_MH_TEAM_TOKEN'] ) ) {
            $token = sanitize_text_field( $_SERVER['HTTP_X_MH_TEAM_TOKEN'] );
        } elseif ( ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $token = sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] );
        }

        return self::validate_token( $token );
    }

    /* ── Generate Token ──────────────────────────────────── */
    private static function generate_token() {
        return wp_generate_password( 40, false, false );
    }

    /* ── Enqueue (only on pages with shortcode) ──────────── */
    public static function maybe_enqueue() {
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'mh_upload_portal' ) ) return;

        wp_enqueue_style(
            'mh-stl-portal',
            MH_STL_URL . 'assets/css/upload-portal.css',
            [],
            MH_STL_VERSION
        );

        wp_enqueue_script(
            'mh-stl-portal',
            MH_STL_URL . 'assets/js/upload-portal.js',
            [ 'jquery', 'jquery-ui-draggable' ],
            MH_STL_VERSION,
            true
        );

        wp_localize_script( 'mh-stl-portal', 'mhPortal', [
            'rest_url'      => esc_url_raw( rest_url( 'mh-stl/v1/' ) ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'authenticated' => self::validate_token() ? 'yes' : 'no',
        ] );
    }

    /* ══════════════════════════════════════════════════════════
       SHORTCODE RENDER
       ══════════════════════════════════════════════════════════ */
    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'title' => 'Kundenprojekte hochladen',
        ], $atts );

        ob_start();
        ?>
        <div id="mh-upload-portal" class="mh-portal" data-title="<?php echo esc_attr( $atts['title'] ); ?>">
            <div class="mh-portal-loading">
                <div class="mh-portal-spinner"></div>
                <p>Wird geladen…</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ══════════════════════════════════════════════════════════
       ADMIN: Team Password Setting
       ══════════════════════════════════════════════════════════ */
    public static function render_team_password_field() {
        $has_password = (bool) get_option( 'mh_stl_team_password', '' );
        ?>
        <div class="mh-um-card" style="margin-top:24px;">
            <div class="mh-um-card-header"><h3>🔑 Upload-Portal — Team-Passwort</h3></div>
            <div class="mh-um-card-body">
                <p style="margin:0 0 12px;color:#50575e;font-size:14px;">
                    Shortcode <code>[mh_upload_portal]</code> auf einer Seite platzieren.
                    Mitarbeiter geben dieses Passwort ein, um Projekte hochzuladen — ohne WordPress-Login.
                </p>
                <div class="mh-um-form-grid">
                    <div class="mh-um-field">
                        <label for="mh_team_pw">Neues Passwort setzen</label>
                        <input type="password" id="mh_team_pw" name="mh_team_password"
                               placeholder="<?php echo $has_password ? '••••• (gesetzt)' : 'Passwort eingeben…'; ?>"
                               autocomplete="new-password">
                    </div>
                    <div class="mh-um-field">
                        <label>Status</label>
                        <p style="margin:8px 0 0;font-size:14px;">
                            <?php if ( $has_password ) : ?>
                                <span style="color:#1e7e34;">✅ Passwort ist gesetzt</span>
                            <?php else : ?>
                                <span style="color:#dc3232;">❌ Kein Passwort — Portal ist deaktiviert</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <p class="mh-um-field-hint">Leer lassen = bestehendes Passwort bleibt. Alle Teammitglieder nutzen dasselbe Passwort.</p>
            </div>
        </div>
        <?php
    }

    public static function save_team_password() {
        if ( ! empty( $_POST['mh_team_password'] ) ) {
            $pw = wp_unslash( $_POST['mh_team_password'] );
            update_option( 'mh_stl_team_password', wp_hash_password( $pw ) );
        }
    }
}
