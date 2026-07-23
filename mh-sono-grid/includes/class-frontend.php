<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend: Asset-Registrierung (lazy Enqueue im Shortcode), Shortcodes
 * [mh_sono_grid] + [mh_sono_count], Renderer für Filterleiste, Sektionen
 * und Modell-Karten. Alle Varianten werden server-gerendert; JS macht nur
 * Sichtbarkeit (Filter) und den Oberflächen-Umschalter.
 */
final class MH_SONO_Frontend {

	private static $instance = null;

	private $assets_enqueued = false;

	public static function boot() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'wp_enqueue_scripts', array( self::$instance, 'register_assets' ) );
			add_action( 'wp_footer', array( self::$instance, 'print_late_styles' ), 1 );
			add_shortcode( 'mh_sono_grid', array( self::$instance, 'shortcode_grid' ) );
			add_shortcode( 'mh_sono_count', array( self::$instance, 'shortcode_count' ) );
		}
		return self::$instance;
	}

	/* ── Assets (Oxygen/WP-Rocket-sicher) ─────────────────────────────── */

	/** Content-Hash-Version (Cache-Bust nur bei echter Dateiänderung). */
	private function asset_version( $relative_path ) {
		$file = MH_SONO_PATH . $relative_path;
		if ( file_exists( $file ) ) {
			return substr( md5_file( $file ), 0, 8 );
		}
		return MH_SONO_VERSION;
	}

	private function resolve_asset( $relative_path ) {
		if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			$min_path = preg_replace( '/\.(js|css)$/', '.min.$1', $relative_path );
			if ( file_exists( MH_SONO_PATH . $min_path ) && filesize( MH_SONO_PATH . $min_path ) > 0 ) {
				return array( MH_SONO_URL . $min_path, $this->asset_version( $min_path ) );
			}
		}
		return array( MH_SONO_URL . $relative_path, $this->asset_version( $relative_path ) );
	}

	public function register_assets() {
		if ( is_admin() ) {
			return;
		}
		/* Serif-Akzent für die Modellnamen — nicht auf die Landingpage verlassen. */
		wp_register_style( 'mh-sono-serif', 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&display=swap', array(), null );

		list( $css_url, $css_ver ) = $this->resolve_asset( 'assets/css/mh-sono.css' );
		wp_register_style( 'mh-sono-front', $css_url, array( 'mh-sono-serif' ), $css_ver );

		list( $js_url, $js_ver ) = $this->resolve_asset( 'assets/js/mh-sono.js' );
		wp_register_script( 'mh-sono-front', $js_url, array(), $js_ver, true );
	}

	private function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}
		wp_enqueue_style( 'mh-sono-serif' );
		wp_enqueue_style( 'mh-sono-front' );
		wp_enqueue_script( 'mh-sono-front' );
		$this->assets_enqueued = true;
	}

	/**
	 * Oxygen rendert Shortcodes nach dem <head>: Styles dann im Footer
	 * nachdrucken (Muster mh-spielturm-vergleich v5.25.1).
	 */
	public function print_late_styles() {
		if ( ! $this->assets_enqueued || ! did_action( 'wp_head' ) ) {
			return;
		}
		if ( wp_style_is( 'mh-sono-front', 'enqueued' ) && ! wp_style_is( 'mh-sono-front', 'done' ) ) {
			wp_print_styles( array( 'mh-sono-serif', 'mh-sono-front' ) );
		}
	}

	/* ── Shortcode: [mh_sono_count what="models|heights|products|finishes"] ── */

	public function shortcode_count( $atts ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$atts = shortcode_atts( array(
			'what'     => 'models',
			'category' => 'sono',
		), $atts, 'mh_sono_count' );

		$data = MH_SONO_Data_Provider::get_grid_data( $atts['category'] );
		if ( empty( $data['ok'] ) ) {
			return '';
		}
		$what = in_array( $atts['what'], array( 'models', 'heights', 'products', 'finishes' ), true ) ? $atts['what'] : 'models';
		return esc_html( (string) $data['counts'][ $what ] );
	}

	/* ── Shortcode: [mh_sono_grid category="sono"] ─────────────────────── */

	public function shortcode_grid( $atts ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '<!-- MH SONO: WooCommerce nicht geladen -->';
		}

		$atts = shortcode_atts( array(
			'category'     => 'sono',
			'show_filters' => '1',
			'button_text'  => 'Zum Modell',
		), $atts, 'mh_sono_grid' );

		$data = MH_SONO_Data_Provider::get_grid_data( $atts['category'] );

		if ( empty( $data['ok'] ) ) {
			$reason = isset( $data['error'] ) ? $data['error'] : 'unbekannt';
			return '<!-- MH SONO: keine Daten (' . esc_html( $reason ) . ', Kategorie "' . esc_html( $atts['category'] ) . '") -->';
		}
		if ( empty( $data['sections'] ) ) {
			return '<!-- MH SONO: Kategorie "' . esc_html( $atts['category'] ) . '" enthaelt keine sichtbaren Produkte -->';
		}

		$this->enqueue_assets();

		ob_start();

		if ( ! empty( $data['incomplete'] ) ) {
			echo '<!-- MH SONO: ' . count( $data['incomplete'] ) . ' Produkt(e) ohne vollstaendige Attribute (Details im WP-Admin) -->' . "\n";
		}
		?>
<div class="mhsono" data-mhsono>
	<?php if ( '1' === $atts['show_filters'] ) { $this->render_filterbar( $data ); } ?>
	<div class="mhsono-sections">
	<?php foreach ( $data['sections'] as $section ) { $this->render_section( $section, $atts ); } ?>
	</div>
	<div class="mhsono-emptymsg" data-mhsono-empty hidden>
		<p>Keine Modelle f&uuml;r diese Auswahl.</p>
		<button type="button" class="mhsono-reset" data-mhsono-reset>Filter zur&uuml;cksetzen</button>
	</div>
</div>
		<?php
		return ob_get_clean();
	}

	/* ── Filterleiste ──────────────────────────────────────────────────── */

	private function render_filterbar( $data ) {
		$f = $data['filters'];

		$has_types    = count( $f['types'] ) >= 2;
		$has_heights  = count( $f['heights'] ) >= 2;
		$has_finishes = count( $f['finishes'] ) >= 2;

		if ( ! $has_types && ! $has_heights && ! $has_finishes ) {
			return;
		}
		?>
	<div class="mhsono-filters" data-mhsono-filters>
		<?php if ( $has_types ) : ?>
		<div class="mhsono-fgroup" data-mhsono-group="type">
			<span class="mhsono-flabel">Produkttyp</span>
			<div class="mhsono-frow" role="tablist" aria-label="Nach Produkttyp filtern">
				<button type="button" class="mhsono-pill is-active" role="tab" aria-selected="true" data-mhsono-filter="type" data-mhsono-value="">Alle</button>
				<?php foreach ( $f['types'] as $slug => $t ) :
					$label = '' === $slug ? $this->default_type_label() : $t['label']; ?>
				<button type="button" class="mhsono-pill" role="tab" aria-selected="false" data-mhsono-filter="type" data-mhsono-value="<?php echo esc_attr( '' === $slug ? '_default' : $slug ); ?>"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		<?php if ( $has_heights ) : ?>
		<div class="mhsono-fgroup" data-mhsono-group="height">
			<span class="mhsono-flabel">H&ouml;he</span>
			<div class="mhsono-frow" role="group" aria-label="Nach H&ouml;he filtern">
				<button type="button" class="mhsono-pill is-active" data-mhsono-filter="height" data-mhsono-value="" aria-pressed="true">Alle</button>
				<?php foreach ( $f['heights'] as $h => $hd ) : ?>
				<button type="button" class="mhsono-pill" data-mhsono-filter="height" data-mhsono-value="<?php echo esc_attr( $h ); ?>" aria-pressed="false"><?php echo esc_html( $hd['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		<?php if ( $has_finishes ) : ?>
		<div class="mhsono-fgroup" data-mhsono-group="finish">
			<span class="mhsono-flabel">Oberfl&auml;che</span>
			<div class="mhsono-frow" role="group" aria-label="Nach Oberfl&auml;che filtern">
				<button type="button" class="mhsono-pill is-active" data-mhsono-filter="finish" data-mhsono-value="" aria-pressed="true">Alle</button>
				<?php foreach ( $f['finishes'] as $slug => $fd ) : ?>
				<button type="button" class="mhsono-pill" data-mhsono-filter="finish" data-mhsono-value="<?php echo esc_attr( $slug ); ?>" aria-pressed="false"><i class="mhsono-dotmini mhsono-finish--<?php echo esc_attr( $slug ); ?>"></i><?php echo esc_html( $fd['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
		<?php
	}

	private function default_type_label() {
		return apply_filters( 'mh_sono_default_type_label', 'Sichtschutz' );
	}

	/* ── Sektion + Karten ──────────────────────────────────────────────── */

	private function render_section( $section, $atts ) {
		?>
	<div class="mhsono-sec" id="<?php echo esc_attr( $section['id'] ); ?>" data-mhsono-sec>
		<div class="mhsono-sec-head"><h3><?php echo esc_html( $section['title'] ); ?></h3></div>
		<div class="mhsono-grid">
		<?php foreach ( $section['models'] as $model ) { $this->render_card( $model, $section, $atts ); } ?>
		</div>
	</div>
		<?php
	}

	private function render_card( $model, $section, $atts ) {
		$variants = $model['variants'];
		if ( empty( $variants ) ) {
			return;
		}

		/* Start-Variante: erste lieferbare, sonst die erste. */
		$active = 0;
		foreach ( $variants as $i => $v ) {
			if ( $v['in_stock'] ) {
				$active = $i;
				break;
			}
		}
		$first = $variants[ $active ];

		$finishes_attr = array();
		foreach ( $variants as $v ) {
			if ( '' !== $v['finish'] ) {
				$finishes_attr[] = $v['finish'];
			}
		}

		$type_val = '' === $model['type'] ? '_default' : $model['type'];
		?>
		<article class="mhsono-card"
			data-mhsono-card
			data-mhsono-type="<?php echo esc_attr( $type_val ); ?>"
			data-mhsono-height="<?php echo esc_attr( $model['height'] ? $model['height'] : '' ); ?>"
			data-mhsono-finishes="<?php echo esc_attr( implode( ' ', $finishes_attr ) ); ?>">
			<a class="mhsono-card-img mhsono-jslink" href="<?php echo esc_url( $first['url'] ); ?>">
				<?php foreach ( $variants as $i => $v ) : ?>
				<span class="mhsono-var<?php echo $i === $active ? ' is-active' : ''; ?>" data-mhsono-var="<?php echo esc_attr( $v['finish'] ); ?>">
					<?php echo $v['img'] ? $v['img'] : '<span class="mhsono-noimg">Kein Produktbild</span>'; ?>
				</span>
				<?php endforeach; ?>
			</a>
			<div class="mhsono-card-body">
				<h4 class="mhsono-card-name"><?php echo esc_html( $model['name'] ); ?></h4>
				<?php if ( 'height' !== $section['kind'] && ( $model['height_label'] || '' !== $model['type_label'] ) ) : ?>
				<p class="mhsono-card-meta">
					<?php
					$meta = array_filter( array( $model['height_label'], $model['type_label'] ) );
					echo esc_html( implode( ' · ', $meta ) );
					?>
				</p>
				<?php endif; ?>
				<?php if ( count( $variants ) > 1 ) : ?>
				<div class="mhsono-finish" role="group" aria-label="Oberfl&auml;che w&auml;hlen">
					<?php foreach ( $variants as $i => $v ) : ?>
					<button type="button"
						class="mhsono-dot mhsono-finish--<?php echo esc_attr( $v['finish'] ? $v['finish'] : 'unknown' ); ?><?php echo $i === $active ? ' is-active' : ''; ?><?php echo $v['in_stock'] ? '' : ' is-oos'; ?>"
						data-mhsono-var="<?php echo esc_attr( $v['finish'] ); ?>"
						data-mhsono-href="<?php echo esc_url( $v['url'] ); ?>"
						aria-pressed="<?php echo $i === $active ? 'true' : 'false'; ?>"
						title="<?php echo esc_attr( $v['finish_label'] . ( $v['in_stock'] ? '' : ' – zurzeit nicht lieferbar' ) ); ?>">
						<span class="mhsono-sr"><?php echo esc_html( $v['finish_label'] ); ?></span>
					</button>
					<?php endforeach; ?>
					<span class="mhsono-finish-name" data-mhsono-finishname><?php echo esc_html( $first['finish_label'] ); ?></span>
				</div>
				<?php endif; ?>
				<div class="mhsono-card-foot">
					<div class="mhsono-price-wrap">
						<?php foreach ( $variants as $i => $v ) : ?>
						<div class="mhsono-var<?php echo $i === $active ? ' is-active' : ''; ?>" data-mhsono-var="<?php echo esc_attr( $v['finish'] ); ?>">
							<div class="mhsono-price<?php echo $v['price_html'] ? '' : ' mhsono-price--na'; ?>">
								<?php echo $v['price_html'] ? wp_kses_post( $v['price_html'] ) : 'Preis auf Produktseite'; ?>
							</div>
							<?php if ( ! $v['in_stock'] ) : ?>
							<span class="mhsono-stock">Zurzeit nicht lieferbar</span>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div>
					<a class="mhsono-btn mhsono-jslink" href="<?php echo esc_url( $first['url'] ); ?>"><?php echo esc_html( $atts['button_text'] ); ?></a>
				</div>
			</div>
		</article>
		<?php
	}
}
