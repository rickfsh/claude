<?php
/**
 * Plugin Name: Mega-Holz Shop-Galerie
 * Description: Produktbild-Galerie im Spielhaus-Look als Shortcode [mh_shopgalerie] — Hero mit Swipe/Pfeilen, Thumb-Streifen mit Laufleiste, Lightbox mit Zoom & Pan. Bilder kommen automatisch aus dem Produkt der aktuellen Seite.
 * Version: 1.2.9
 * Author: Mega-Holz
 *
 * VERWENDUNG:
 *   [mh_shopgalerie]                  -> Bilder des aktuellen Produkts
 *   [mh_shopgalerie product_id="123"] -> Bilder eines bestimmten Produkts
 *
 * v1.1.0: CSS + JS werden INLINE mit der Shortcode-Ausgabe gerendert (einmal pro
 * Seite). Grund: In Oxygen rendert der Code-Block nach wp_head, wodurch normales
 * wp_enqueue unzuverlaessig ist und WP Rocket "Remove Unused CSS" zugreifen kann.
 * Inline umgeht beides.
 *
 * v1.2.6: ECHTER FIX fuer "Bild laedt kurz, verschwindet wieder" — CSS-Spezifitaets-
 * fehler: "display:block" in der gemeinsamen Hero/Peek-Regel liess das weisse Peek-
 * Bild ueber dem Hero liegen. "display:block" jetzt nur noch auf dem Hero-Bild.
 *
 * v1.2.7: SCOPING. Ohne explizite product_id rendert die Galerie nur noch auf einer
 * einzelnen Produktseite (is_singular('product')). Damit kann sie nicht mehr auf
 * Archiv-/Kategorie- oder anderen Seiten auftauchen, falls das Oxygen-Element breiter
 * greift als gedacht. Zusaetzliches Sicherheitsnetz: aufgeloeste ID muss Post-Typ
 * "product" sein (verhindert Term-ID/Post-ID-Kollision auf Archivseiten).
 *
 * v1.2.8: UMBENENNUNG (zur Abgrenzung von einem aehnlich benannten Plugin).
 *   - Plugin/Ordner/Datei: mh-produkt-galerie -> mh-shop-galerie
 *   - Shortcode:           [mh_produktgalerie] -> [mh_shopgalerie]
 *   - Klasse/Konstanten:   MH_Produkt_Galerie/MH_PG_* -> MH_Shop_Galerie/MHSG_*
 *   - CSS-Prefix:          .mh-pg-* -> .mhsg-*
 *   - Inline-Asset-IDs:    mh-pg-css/mh-pg-js -> mhsg-css/mhsg-js
 *   NACH DEM UPDATE ANPASSEN: (1) Shortcode im Oxygen-Template auf [mh_shopgalerie],
 *   (2) WP-Rocket "Delay JavaScript Execution"-Ausschluss von "mh-pg-js" auf "mhsg-js".
 *
 * v1.2.9: PERFORMANCE (Bild-Ladezeit).
 *   (1) Hero-<img> bekommt fetchpriority="high" — LCP-Bild wird priorisiert geladen.
 *   (2) Neuer wp_head-Preload-Link fuer das Hauptbild des aktuellen Produkts. Startet
 *       den Download im <head> statt erst beim spaeten Body-Render des Oxygen-Blocks.
 *       (Nur Preload-Link, keine Asset-Enqueues -> keine RUCSS-/Timing-Probleme.)
 *   (3) Inline-Galerie preloadet jetzt die Nachbarbilder (wie die Lightbox schon) ->
 *       Thumbnail-/Pfeil-Klick wechselt ohne Flackern. (Aenderung in der JS-Datei.)
 *
 * WP ROCKET: Das Hero-Bild ist serverseitig da und zeigt sich auch ohne JS. Fuer die
 * Interaktivitaet (Wischen, Lightbox, Thumbnail-Klick) sollte der Marker "mhsg-js"
 * unter "Delay JavaScript Execution" ausgeschlossen sein.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MHSG_VERSION', '1.2.9' );
define( 'MHSG_PATH', plugin_dir_path( __FILE__ ) );

final class MH_Shop_Galerie {

	public static function init(): void {
		add_shortcode( 'mh_shopgalerie', [ __CLASS__, 'shortcode' ] );
		add_action( 'wp_head', [ __CLASS__, 'preload_hero' ], 1 );
	}

	/**
	 * v1.2.9: Preload-Link fuer das Produkt-Hauptbild im <head>.
	 * Startet den Hero-Download frueh (statt erst beim spaeten Body-Render des
	 * Oxygen-Code-Blocks). Bildauswahl identisch zur Shortcode-Galerie
	 * (woocommerce_single -> large), damit Preload und tatsaechliches Bild matchen.
	 * Nur ein Preload-Link, kein Asset-Enqueue -> keine RUCSS-/Timing-Probleme.
	 */
	public static function preload_hero(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$pid = (int) get_queried_object_id();
		if ( ! $pid || 'product' !== get_post_type( $pid ) ) {
			return;
		}
		$product = wc_get_product( $pid );
		if ( ! $product ) {
			return;
		}
		$aid = (int) $product->get_image_id();
		if ( ! $aid ) {
			return;
		}
		$src = wp_get_attachment_image_url( $aid, 'woocommerce_single' );
		if ( ! $src ) {
			$src = wp_get_attachment_image_url( $aid, 'large' );
		}
		if ( ! $src ) {
			return;
		}
		$srcset = (string) wp_get_attachment_image_srcset( $aid, 'woocommerce_single' );
		echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url( $src ) . '"';
		if ( '' !== $srcset ) {
			echo ' imagesrcset="' . esc_attr( $srcset ) . '" imagesizes="(max-width: 980px) 92vw, 720px"';
		}
		echo ">\n";
	}

	/** Datei-Inhalt (mit statischem Cache pro Request). */
	private static function file( string $rel ): string {
		static $cache = [];
		if ( ! isset( $cache[ $rel ] ) ) {
			$p = MHSG_PATH . $rel;
			$cache[ $rel ] = is_readable( $p ) ? (string) file_get_contents( $p ) : '';
		}
		return $cache[ $rel ];
	}

	/** CSS + JS einmal pro Request inline ausgeben. */
	private static function inline_assets(): string {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;
		$out  = '';
		$css  = self::file( 'public/css/mh-shop-galerie.css' );
		$js   = self::file( 'public/js/mh-shop-galerie.js' );
		if ( '' !== $css ) {
			$out .= '<style id="mhsg-css">' . $css . '</style>';
		}
		if ( '' !== $js ) {
			$out .= '<script id="mhsg-js">' . $js . '</script>';
		}
		return $out;
	}

	/**
	 * Bild-Daten eines Produkts: Hauptbild + Galerie-Bilder.
	 * thumb -> Thumbnail (Streifen), single -> Anzeige (Hero), full -> Lightbox.
	 */
	private static function collect_images( \WC_Product $product ): array {
		$ids  = [];
		$main = (int) $product->get_image_id();
		if ( $main ) {
			$ids[] = $main;
		}
		foreach ( (array) $product->get_gallery_image_ids() as $gid ) {
			$ids[] = (int) $gid;
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );

		$images = [];
		foreach ( $ids as $aid ) {
			$single = wp_get_attachment_image_url( $aid, 'woocommerce_single' );
			if ( ! $single ) {
				$single = wp_get_attachment_image_url( $aid, 'large' );
			}
			if ( ! $single ) {
				continue;
			}
			$images[] = [
				'thumb'  => wp_get_attachment_image_url( $aid, 'woocommerce_gallery_thumbnail' ) ?: $single,
				'single' => $single,
				'full'   => wp_get_attachment_image_url( $aid, 'full' ) ?: $single,
				'srcset' => (string) wp_get_attachment_image_srcset( $aid, 'woocommerce_single' ),
				'alt'    => trim( wp_strip_all_tags( (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) ) ),
			];
		}
		return $images;
	}

	public static function shortcode( $atts ): string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$atts = shortcode_atts( [ 'product_id' => 0 ], $atts, 'mh_shopgalerie' );

		$pid = absint( $atts['product_id'] );

		// v1.2.7: Ohne explizite product_id NUR auf einer einzelnen Produktseite rendern.
		// Verhindert, dass die Galerie (inkl. inline CSS/JS) auf anderen Seiten auftaucht,
		// falls das Oxygen-Element breiter greift als gedacht. Schliesst zudem die seltene
		// Term-ID/Post-ID-Kollision aus: auf Archiv-/Kategorieseiten liefert
		// get_queried_object_id() eine Term-ID, die zufaellig der Post-ID eines Produkts
		// entsprechen kann -> wc_get_product() gaebe dann ein fremdes Produkt zurueck.
		$on_product_page = is_singular( 'product' )
			|| ( function_exists( 'is_product' ) && is_product() );
		if ( ! $pid && ! $on_product_page ) {
			return '';
		}

		if ( ! $pid ) {
			$pid = (int) get_queried_object_id();
		}
		if ( ! $pid ) {
			$pid = (int) get_the_ID();
		}

		// Sicherheitsnetz: nur fortfahren, wenn das aufgeloeste Objekt wirklich ein Produkt ist.
		if ( $pid && 'product' !== get_post_type( $pid ) ) {
			return '';
		}

		$product = $pid ? wc_get_product( $pid ) : null;
		if ( ! $product ) {
			return '';
		}

		$images = self::collect_images( $product );
		if ( empty( $images ) ) {
			return '';
		}

		$name  = $product->get_name();
		$first = $images[0];
		$n     = count( $images );
		$uid   = 'mhsg-' . $pid . '-' . wp_unique_id();

		ob_start();
		?>
		<div class="mhsg" id="<?php echo esc_attr( $uid ); ?>" data-mhsg>

			<?php
			/* Hero-Bild serverseitig gerendert → liegt im Quelltext (gut für SEO, da die
			   WooCommerce-Standardgalerie auf dieser Seite deaktiviert ist) und ist sofort
			   sichtbar (LCP). Das frühere "Bild lädt kurz, verschwindet wieder" lag NICHT
			   am Lazyload, sondern an einem CSS-Spezifitätsfehler: das weiße Peek-Bild lag
			   über dem Hero. Behoben in CSS v1.2.6. */
			?>
			<div class="mhsg-hero<?php echo $n > 1 ? ' is-multi' : ''; ?>" role="button" tabindex="0"
				 aria-label="<?php echo esc_attr( $name ); ?> — Bild vergrößern">
				<img class="mhsg-img skip-lazy"
					 data-skip-lazy="true"
					 src="<?php echo esc_url( $first['single'] ); ?>"
					 <?php if ( $first['srcset'] ) : ?>
					 srcset="<?php echo esc_attr( $first['srcset'] ); ?>"
					 sizes="(max-width: 980px) 92vw, 720px"
					 <?php endif; ?>
					 alt="<?php echo esc_attr( $first['alt'] ?: $name ); ?>"
					 loading="eager" fetchpriority="high" decoding="async" draggable="false">
			</div>

			<?php if ( $n > 1 ) : ?>
			<div class="mhsg-thumbstrip">
				<div class="mhsg-thumbs-scroll">
					<?php foreach ( $images as $i => $img ) : ?>
					<button type="button"
							class="mhsg-thumb<?php echo 0 === $i ? ' is-active' : ''; ?>"
							aria-label="Bild <?php echo (int) ( $i + 1 ); ?>"
							aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>"
							data-i="<?php echo (int) $i; ?>">
						<img src="<?php echo esc_url( $img['thumb'] ); ?>"
							 alt="<?php echo esc_attr( ( $img['alt'] ?: $name ) . ' ' . ( $i + 1 ) ); ?>"
							 loading="lazy" draggable="false">
					</button>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<script type="application/json" class="mhsg-data"><?php
				echo wp_json_encode(
					[ 'name' => $name, 'images' => $images ],
					JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
				);
			?></script>
		</div>
		<?php
		$html = (string) ob_get_clean();

		return self::inline_assets() . $html;
	}
}

MH_Shop_Galerie::init();
