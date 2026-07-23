<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-Notice für unvollständig gepflegte SONO-Produkte (fehlendes
 * Modell-/Höhen-/Oberflächen-Attribut). Dismissbar pro Nutzer; taucht
 * wieder auf, sobald sich die Liste der betroffenen Produkte ändert.
 */
final class MH_SONO_Admin_Notice {

	const META_KEY = 'mh_sono_notice_dismissed';

	public static function boot() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
	}

	public static function maybe_dismiss() {
		if ( ! isset( $_GET['mh_sono_dismiss'], $_GET['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'mh_sono_dismiss' ) ) {
			return;
		}
		update_user_meta( get_current_user_id(), self::META_KEY, sanitize_key( wp_unslash( $_GET['mh_sono_dismiss'] ) ) );
	}

	public static function maybe_render() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$category = apply_filters( 'mh_sono_notice_category', 'sono' );
		$data     = MH_SONO_Data_Provider::get_grid_data( $category );
		if ( empty( $data['ok'] ) || empty( $data['incomplete'] ) ) {
			return;
		}

		/* Fingerprint der Problem-Liste: Dismiss gilt nur für genau diesen Stand. */
		$fingerprint = substr( md5( wp_json_encode( wp_list_pluck( $data['incomplete'], 'missing', 'id' ) ) ), 0, 12 );
		if ( get_user_meta( get_current_user_id(), self::META_KEY, true ) === $fingerprint ) {
			return;
		}

		$labels = array(
			'modell'      => 'Modell',
			'hoehe'       => 'H&ouml;he',
			'oberflaeche' => 'Oberfl&auml;che',
		);

		$dismiss_url = wp_nonce_url(
			add_query_arg( 'mh_sono_dismiss', $fingerprint ),
			'mh_sono_dismiss'
		);
		?>
		<div class="notice notice-warning">
			<p><strong>MH SONO Grid:</strong> <?php echo (int) count( $data['incomplete'] ); ?> Produkt(e) in der SONO-Kategorie ohne vollst&auml;ndige Attribute &mdash; sie erscheinen als Einzel-Karte unter &bdquo;Weitere Modelle&ldquo; statt gruppiert:</p>
			<ul style="list-style:disc;margin-left:20px;">
				<?php foreach ( array_slice( $data['incomplete'], 0, 15 ) as $p ) :
					$miss = array();
					foreach ( $p['missing'] as $key ) {
						$miss[] = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
					}
					?>
				<li>
					<a href="<?php echo esc_url( get_edit_post_link( $p['id'] ) ); ?>"><?php echo esc_html( $p['title'] ); ?></a>
					&mdash; fehlt: <?php echo implode( ', ', $miss ); /* Labels sind Plugin-eigene Strings */ ?>
				</li>
				<?php endforeach; ?>
				<?php if ( count( $data['incomplete'] ) > 15 ) : ?>
				<li>&hellip; und <?php echo (int) ( count( $data['incomplete'] ) - 15 ); ?> weitere</li>
				<?php endif; ?>
			</ul>
			<p><a href="<?php echo esc_url( $dismiss_url ); ?>">Hinweis ausblenden</a> (erscheint wieder, wenn sich die Liste &auml;ndert)</p>
		</div>
		<?php
	}
}
