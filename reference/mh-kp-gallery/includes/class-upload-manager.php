<?php
/**
 * MH STL Upload Manager — Admin page for external project upload with pin editor
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_Upload_Manager {

    /* ── Admin Menu ───────────────────────────────────────── */
    public static function register_menu() {
        // Upload Manager (under Kundenprojekte)
        add_submenu_page(
            'edit.php?post_type=' . MH_STL_POST_TYPE,
            'Upload Manager',
            '📤 Upload Manager',
            'edit_posts',
            'mh-stl-upload-manager',
            [ __CLASS__, 'render_page' ]
        );

        // FTP Settings
        add_submenu_page(
            'edit.php?post_type=' . MH_STL_POST_TYPE,
            'FTP-Einstellungen',
            '⚙️ FTP-Einstellungen',
            'manage_options',
            'mh-stl-ftp-settings',
            [ __CLASS__, 'render_settings' ]
        );
    }

    /* ── Enqueue Assets ───────────────────────────────────── */
    public static function enqueue( $hook ) {
        // Upload Manager page
        if ( strpos( $hook, 'mh-stl-upload-manager' ) !== false ) {
            wp_enqueue_media();

            wp_enqueue_style(
                'mh-stl-upload-manager',
                MH_STL_URL . 'assets/css/upload-manager.css',
                [],
                MH_STL_VERSION
            );

            wp_enqueue_script(
                'mh-stl-upload-manager',
                MH_STL_URL . 'assets/js/upload-manager.js',
                [ 'jquery', 'jquery-ui-draggable' ],
                MH_STL_VERSION,
                true
            );

            wp_localize_script( 'mh-stl-upload-manager', 'mhUpload', [
                'rest_url'   => esc_url_raw( rest_url( 'mh-stl/v1/' ) ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'ftp_active' => self::is_ftp_configured(),
            ] );
        }

        // Settings page
        if ( strpos( $hook, 'mh-stl-ftp-settings' ) !== false ) {
            wp_enqueue_style(
                'mh-stl-settings',
                MH_STL_URL . 'assets/css/upload-manager.css',
                [],
                MH_STL_VERSION
            );
        }
    }

    /* ══════════════════════════════════════════════════════════
       UPLOAD MANAGER PAGE
       ══════════════════════════════════════════════════════════ */
    public static function render_page() {
        ?>
        <div class="wrap mh-um-wrap">
            <div id="mh-upload-manager-app">
                <!-- Rendered by JS -->
                <div class="mh-um-loading">
                    <div class="mh-um-spinner"></div>
                    <p>Upload Manager wird geladen…</p>
                </div>
            </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════════
       FTP SETTINGS PAGE
       ══════════════════════════════════════════════════════════ */
    public static function render_settings() {
        $saved = false;
        $test_result = null;

        // Handle save
        if ( isset( $_POST['mh_stl_ftp_save'] ) ) {
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_mh_ftp_nonce'] ?? '' ) ), 'mh_stl_ftp_save' ) ) {
                wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'mh-stl' ) );
            }
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Keine Berechtigung.', 'mh-stl' ) );
            }

            $settings = [
                'host' => sanitize_text_field( wp_unslash( $_POST['ftp_host'] ?? '' ) ),
                'port' => absint( $_POST['ftp_port'] ?? 21 ),
                'user' => sanitize_text_field( wp_unslash( $_POST['ftp_user'] ?? '' ) ),
                'pass' => wp_unslash( $_POST['ftp_pass'] ?? '' ),
                'path' => sanitize_text_field( wp_unslash( $_POST['ftp_path'] ?? '/' ) ),
                'ssl'  => isset( $_POST['ftp_ssl'] ),
            ];
            update_option( 'mh_stl_ftp_settings', $settings );
            $saved = true;

            // Save team password if provided
            if ( class_exists( 'MH_STL_Upload_Portal' ) ) {
                MH_STL_Upload_Portal::save_team_password();
            }
        }

        // Handle test
        if ( isset( $_POST['mh_stl_ftp_test'] ) ) {
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_mh_ftp_nonce'] ?? '' ) ), 'mh_stl_ftp_save' ) ) {
                $settings = get_option( 'mh_stl_ftp_settings', [] );
                $test_result = MH_STL_FTP_Handler::test_connection( $settings );
            }
        }

        $s = get_option( 'mh_stl_ftp_settings', [
            'host' => '', 'port' => 21, 'user' => '', 'pass' => '', 'path' => '/', 'ssl' => false,
        ] );
        ?>
        <div class="wrap mh-um-wrap">
            <h1 class="mh-um-page-title">
                <span class="mh-um-icon">⚙️</span>
                FTP-Einstellungen
            </h1>
            <p class="mh-um-subtitle">Verbindungsdaten für den FTP-Server, auf den Bilder hochgeladen werden.</p>

            <?php if ( $saved ) : ?>
                <div class="mh-um-notice mh-um-notice-success">✅ Einstellungen gespeichert.</div>
            <?php endif; ?>

            <?php if ( $test_result === true ) : ?>
                <div class="mh-um-notice mh-um-notice-success">✅ FTP-Verbindung erfolgreich!</div>
            <?php elseif ( is_wp_error( $test_result ) ) : ?>
                <div class="mh-um-notice mh-um-notice-error">❌ <?php echo esc_html( $test_result->get_error_message() ); ?></div>
            <?php endif; ?>

            <form method="post" class="mh-um-settings-form">
                <?php wp_nonce_field( 'mh_stl_ftp_save', '_mh_ftp_nonce' ); ?>

                <div class="mh-um-settings-grid">
                    <div class="mh-um-field">
                        <label for="ftp_host">FTP-Host</label>
                        <input type="text" id="ftp_host" name="ftp_host"
                               value="<?php echo esc_attr( $s['host'] ?? '' ); ?>"
                               placeholder="ftp.mega-holz.de">
                    </div>
                    <div class="mh-um-field">
                        <label for="ftp_port">Port</label>
                        <input type="number" id="ftp_port" name="ftp_port"
                               value="<?php echo esc_attr( $s['port'] ?? 21 ); ?>"
                               min="1" max="65535">
                    </div>
                    <div class="mh-um-field">
                        <label for="ftp_user">Benutzername</label>
                        <input type="text" id="ftp_user" name="ftp_user"
                               value="<?php echo esc_attr( $s['user'] ?? '' ); ?>"
                               autocomplete="off">
                    </div>
                    <div class="mh-um-field">
                        <label for="ftp_pass">Passwort</label>
                        <input type="password" id="ftp_pass" name="ftp_pass"
                               value="<?php echo esc_attr( $s['pass'] ?? '' ); ?>"
                               autocomplete="new-password">
                    </div>
                    <div class="mh-um-field mh-um-field-wide">
                        <label for="ftp_path">Upload-Pfad</label>
                        <input type="text" id="ftp_path" name="ftp_path"
                               value="<?php echo esc_attr( $s['path'] ?? '/' ); ?>"
                               placeholder="/kundenprojekte/">
                        <p class="mh-um-field-hint">Verzeichnis auf dem FTP-Server, in das Bilder hochgeladen werden.</p>
                    </div>
                    <div class="mh-um-field mh-um-field-wide">
                        <label class="mh-um-checkbox">
                            <input type="checkbox" name="ftp_ssl" <?php checked( $s['ssl'] ?? false ); ?>>
                            <span>FTPS (SSL/TLS) verwenden</span>
                        </label>
                    </div>
                </div>

                <div class="mh-um-settings-actions">
                    <button type="submit" name="mh_stl_ftp_save" class="mh-um-btn mh-um-btn-primary">
                        💾 Speichern
                    </button>
                    <button type="submit" name="mh_stl_ftp_test" class="mh-um-btn mh-um-btn-secondary">
                        🔌 Verbindung testen
                    </button>
                </div>
            </form>

            <?php if ( class_exists( 'MH_STL_Upload_Portal' ) ) : ?>
            <form method="post" style="max-width:640px;margin-top:20px;">
                <?php wp_nonce_field( 'mh_stl_ftp_save', '_mh_ftp_nonce' ); ?>
                <?php MH_STL_Upload_Portal::render_team_password_field(); ?>
                <div style="margin-top:16px;">
                    <button type="submit" name="mh_stl_ftp_save" class="mh-um-btn mh-um-btn-primary">
                        💾 Passwort speichern
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ── Helpers ──────────────────────────────────────────── */
    public static function is_ftp_configured() {
        $s = get_option( 'mh_stl_ftp_settings', [] );
        return ! empty( $s['host'] ) && ! empty( $s['user'] );
    }
}
