<?php
/**
 * MH STL FTP Handler — Uploads images to an external FTP server
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_FTP_Handler {

    /**
     * Upload a file to the FTP server.
     *
     * @param string $local_path  Path to the local temp file.
     * @param string $filename    Original filename.
     * @param array  $settings    FTP settings (host, user, pass, path, port, ssl).
     * @return array|WP_Error     Result with remote_path on success.
     */
    public static function upload( $local_path, $filename, $settings ) {
        $host = sanitize_text_field( $settings['host'] ?? '' );
        $user = $settings['user'] ?? '';
        $pass = $settings['pass'] ?? '';
        $port = absint( $settings['port'] ?? 21 );
        $path = trailingslashit( $settings['path'] ?? '/' );
        $ssl  = ! empty( $settings['ssl'] );

        if ( ! $host || ! $user ) {
            return new WP_Error( 'ftp_config', 'FTP nicht konfiguriert.' );
        }

        // Sanitize filename
        $safe_name = self::safe_filename( $filename );

        // Organize by year/month
        $subdir     = date( 'Y/m' );
        $remote_dir = $path . $subdir;
        $remote_file = $remote_dir . '/' . $safe_name;

        // Connect
        $conn = $ssl ? @ftp_ssl_connect( $host, $port, 30 ) : @ftp_connect( $host, $port, 30 );

        if ( ! $conn ) {
            return new WP_Error( 'ftp_connect', sprintf(
                'FTP-Verbindung zu %s:%d fehlgeschlagen.',
                esc_html( $host ), $port
            ) );
        }

        // Login
        if ( ! @ftp_login( $conn, $user, $pass ) ) {
            ftp_close( $conn );
            return new WP_Error( 'ftp_login', 'FTP-Login fehlgeschlagen. Zugangsdaten prüfen.' );
        }

        // Passive mode
        ftp_pasv( $conn, true );

        // Create directory recursively
        self::ftp_mkdir_recursive( $conn, $remote_dir );

        // Upload
        $success = @ftp_put( $conn, $remote_file, $local_path, FTP_BINARY );
        ftp_close( $conn );

        if ( ! $success ) {
            return new WP_Error( 'ftp_upload', 'FTP-Upload fehlgeschlagen: ' . esc_html( $remote_file ) );
        }

        return [
            'remote_path' => $remote_file,
            'filename'    => $safe_name,
        ];
    }

    /**
     * Test FTP connection.
     *
     * @param array $settings FTP settings.
     * @return true|WP_Error
     */
    public static function test_connection( $settings ) {
        $host = sanitize_text_field( $settings['host'] ?? '' );
        $user = $settings['user'] ?? '';
        $pass = $settings['pass'] ?? '';
        $port = absint( $settings['port'] ?? 21 );
        $ssl  = ! empty( $settings['ssl'] );

        if ( ! $host || ! $user ) {
            return new WP_Error( 'ftp_config', 'Host und Benutzer sind erforderlich.' );
        }

        $conn = $ssl ? @ftp_ssl_connect( $host, $port, 15 ) : @ftp_connect( $host, $port, 15 );
        if ( ! $conn ) {
            return new WP_Error( 'ftp_connect', 'Verbindung fehlgeschlagen.' );
        }

        if ( ! @ftp_login( $conn, $user, $pass ) ) {
            ftp_close( $conn );
            return new WP_Error( 'ftp_login', 'Login fehlgeschlagen.' );
        }

        ftp_pasv( $conn, true );
        $pwd = ftp_pwd( $conn );
        ftp_close( $conn );

        return true;
    }

    /**
     * List files in a remote FTP directory.
     */
    public static function list_files( $settings, $remote_dir = '/' ) {
        $host = sanitize_text_field( $settings['host'] ?? '' );
        $user = $settings['user'] ?? '';
        $pass = $settings['pass'] ?? '';
        $port = absint( $settings['port'] ?? 21 );
        $ssl  = ! empty( $settings['ssl'] );

        $conn = $ssl ? @ftp_ssl_connect( $host, $port, 15 ) : @ftp_connect( $host, $port, 15 );
        if ( ! $conn ) return [];

        if ( ! @ftp_login( $conn, $user, $pass ) ) {
            ftp_close( $conn );
            return [];
        }

        ftp_pasv( $conn, true );
        $list = ftp_nlist( $conn, $remote_dir ) ?: [];
        ftp_close( $conn );

        return $list;
    }

    /* ── Helpers ──────────────────────────────────────────── */

    private static function safe_filename( $filename ) {
        $info = pathinfo( $filename );
        $name = sanitize_file_name( $info['filename'] );
        $ext  = strtolower( $info['extension'] ?? 'jpg' );

        // Prevent duplicates with timestamp
        return $name . '-' . time() . '.' . $ext;
    }

    private static function ftp_mkdir_recursive( $conn, $dir ) {
        if ( @ftp_chdir( $conn, $dir ) ) {
            ftp_chdir( $conn, '/' );
            return true;
        }

        $parts   = array_filter( explode( '/', $dir ) );
        $current = '';

        foreach ( $parts as $part ) {
            $current .= '/' . $part;
            if ( ! @ftp_chdir( $conn, $current ) ) {
                @ftp_mkdir( $conn, $current );
            }
        }

        ftp_chdir( $conn, '/' );
        return true;
    }
}
