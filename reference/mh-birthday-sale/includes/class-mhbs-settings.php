<?php
/**
 * Admin-Einstellungsseite der Geburtstagsaktion.
 *
 * Einstellungen -> MH Birthday Sale: Aktionszeitraum (Start/Ende),
 * Mindestanzahl Pfosten und ein Ein/Aus-Schalter – ohne Code-Aenderung.
 *
 * Gespeichert wird die Option 'mhbs_settings'. mhbs_get_config() liest diese
 * Werte und ueberschreibt damit die Code-Defaults (ein 'mhbs_config'-Filter
 * kann weiterhin alles ueberschreiben – Reihenfolge: Default < Admin < Filter).
 *
 * @package mh-birthday-sale
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MHBS_Settings {

	const OPTION = 'mhbs_settings';
	const GROUP  = 'mhbs_settings_group';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/** Default-Form der gespeicherten Option. */
	public static function defaults() {
		return array(
			'enabled'      => 1,
			'active_from'  => '',
			'active_until' => '',
			'min_posts'    => 2,
		);
	}

	public static function menu() {
		add_options_page(
			__( 'MH Birthday Sale', 'mh-birthday-sale' ),
			__( 'MH Birthday Sale', 'mh-birthday-sale' ),
			'manage_options',
			'mhbs-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Eingaben pruefen/normalisieren.
	 *
	 * @param mixed $input Rohdaten aus dem Formular.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = self::defaults();
		if ( ! is_array( $input ) ) {
			return $out;
		}

		$out['enabled']      = empty( $input['enabled'] ) ? 0 : 1;
		$out['active_from']  = self::normalize_datetime( isset( $input['active_from'] ) ? $input['active_from'] : '' );
		$out['active_until'] = self::normalize_datetime( isset( $input['active_until'] ) ? $input['active_until'] : '' );
		$out['min_posts']    = max( 0, (int) ( isset( $input['min_posts'] ) ? $input['min_posts'] : 2 ) );

		if ( $out['active_from'] && $out['active_until'] && $out['active_until'] < $out['active_from'] ) {
			add_settings_error(
				self::OPTION,
				'mhbs_dates',
				esc_html__( 'Das Enddatum liegt vor dem Startdatum. Bitte korrigieren – die Aktion bleibt sonst inaktiv.', 'mh-birthday-sale' ),
				'error'
			);
		}

		return $out;
	}

	/** datetime-local ("Y-m-d\TH:i") -> Speicherformat ("Y-m-d H:i:s"). */
	protected static function normalize_datetime( $val ) {
		$val = sanitize_text_field( (string) $val );
		if ( '' === $val ) {
			return '';
		}
		try {
			$dt = new DateTimeImmutable( str_replace( 'T', ' ', $val ), wp_timezone() );
		} catch ( Exception $e ) {
			return '';
		}
		return $dt->format( 'Y-m-d H:i:s' );
	}

	/** Speicherformat ("Y-m-d H:i:s") -> Feldwert ("Y-m-d\TH:i"). */
	protected static function to_input( $val ) {
		if ( empty( $val ) ) {
			return '';
		}
		try {
			$dt = new DateTimeImmutable( $val, wp_timezone() );
		} catch ( Exception $e ) {
			return '';
		}
		return $dt->format( 'Y-m-d\TH:i' );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'mh-birthday-sale' ) );
		}

		$cfg      = mhbs_get_config(); // effektive Werte inkl. Admin-Overrides.
		$from_in  = self::to_input( $cfg['active_from'] );
		$until_in = self::to_input( $cfg['active_until'] );
		$min      = (int) $cfg['min_posts'];
		$enabled  = ! empty( $cfg['enabled'] );
		$opt      = self::OPTION;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MH Birthday Sale', 'mh-birthday-sale' ); ?></h1>

			<?php settings_errors( self::OPTION ); ?>
			<?php self::render_status( $cfg, $enabled ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Aktion aktiv', 'mh-birthday-sale' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> />
								<?php esc_html_e( 'Geburtstagsaktion einschalten (innerhalb des Zeitraums unten).', 'mh-birthday-sale' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Haken raus = sofort pausiert, unabhaengig vom Datum.', 'mh-birthday-sale' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mhbs_from"><?php esc_html_e( 'Start', 'mh-birthday-sale' ); ?></label></th>
						<td><input type="datetime-local" id="mhbs_from" name="<?php echo esc_attr( $opt ); ?>[active_from]" value="<?php echo esc_attr( $from_in ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="mhbs_until"><?php esc_html_e( 'Ende', 'mh-birthday-sale' ); ?></label></th>
						<td><input type="datetime-local" id="mhbs_until" name="<?php echo esc_attr( $opt ); ?>[active_until]" value="<?php echo esc_attr( $until_in ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="mhbs_min"><?php esc_html_e( 'Mindestanzahl Pfosten', 'mh-birthday-sale' ); ?></label></th>
						<td>
							<input type="number" min="0" step="1" id="mhbs_min" class="small-text" name="<?php echo esc_attr( $opt ); ?>[min_posts]" value="<?php echo esc_attr( $min ); ?>" />
							<p class="description"><?php esc_html_e( '1 = ein Mega-Flex-Pfosten reicht fuer den Rabatt.', 'mh-birthday-sale' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="description">
					<?php
					printf(
						/* translators: %s = Zeitzone der Website */
						esc_html__( 'Alle Zeiten in der Zeitzone der Website: %s.', 'mh-birthday-sale' ),
						'<code>' . esc_html( wp_timezone_string() ) . '</code>'
					);
					?>
				</p>

				<?php submit_button( __( 'Speichern', 'mh-birthday-sale' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Statusbanner: laeuft / startet / beendet / pausiert. */
	protected static function render_status( $cfg, $enabled ) {
		$tz   = wp_timezone();
		$from = null;
		$until = null;
		try {
			if ( ! empty( $cfg['active_from'] ) ) {
				$from = new DateTimeImmutable( $cfg['active_from'], $tz );
			}
			if ( ! empty( $cfg['active_until'] ) ) {
				$until = new DateTimeImmutable( $cfg['active_until'], $tz );
			}
		} catch ( Exception $e ) {
			$from = null;
			$until = null;
		}

		$fmt = static function ( $dt ) {
			return $dt ? wp_date( 'd.m.Y, H:i', $dt->getTimestamp() ) . ' Uhr' : '';
		};

		if ( ! $enabled ) {
			$bg = '#f0f0f1'; $bd = '#c3c4c7'; $tc = '#3c434a';
			$msg = esc_html__( 'Aktion pausiert (Schalter aus).', 'mh-birthday-sale' );
		} elseif ( ! $from || ! $until ) {
			$bg = '#fcf9e8'; $bd = '#dba617'; $tc = '#674e00';
			$msg = esc_html__( 'Kein vollstaendiger Zeitraum gesetzt – die Aktion ist inaktiv.', 'mh-birthday-sale' );
		} elseif ( mhbs_is_active_window() ) {
			$bg = '#edfaef'; $bd = '#00a32a'; $tc = '#00500f';
			$msg = sprintf(
				/* translators: %s = Enddatum */
				esc_html__( 'Aktion laeuft – endet am %s.', 'mh-birthday-sale' ),
				esc_html( $fmt( $until ) )
			);
		} else {
			$now = new DateTimeImmutable( 'now', $tz );
			if ( $now < $from ) {
				$bg = '#e7f3fb'; $bd = '#2271b1'; $tc = '#0a4b78';
				$msg = sprintf(
					/* translators: %s = Startdatum */
					esc_html__( 'Aktion startet am %s.', 'mh-birthday-sale' ),
					esc_html( $fmt( $from ) )
				);
			} else {
				$bg = '#f0f0f1'; $bd = '#c3c4c7'; $tc = '#3c434a';
				$msg = sprintf(
					/* translators: %s = Enddatum */
					esc_html__( 'Aktion beendet (lief bis %s).', 'mh-birthday-sale' ),
					esc_html( $fmt( $until ) )
				);
			}
		}

		printf(
			'<div style="margin:14px 0;padding:12px 16px;background:%1$s;border-left:4px solid %2$s;color:%3$s;border-radius:0 4px 4px 0;font-size:14px;font-weight:600;">%4$s</div>',
			esc_attr( $bg ),
			esc_attr( $bd ),
			esc_attr( $tc ),
			$msg // bereits via esc_html/sprintf aufbereitet.
		);
	}
}
