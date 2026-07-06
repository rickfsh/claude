<?php
/**
 * Data Provider — Spielhaus-Konfigurator (Schritt 2).
 *
 * Liest WooCommerce-Produktdaten für ein Haus + dessen Add-ons und baut die
 * Datenstruktur fürs Frontend. Analog zu MH_STV_Data_Provider (Spielturm):
 * leichtgewichtige Basis-Daten beim Initial-Load, schwere Felder (Beschreibung,
 * Attribute, Reviews, Voll-Galerie) lazy via AJAX. Transient-Cache pro Gruppe.
 *
 * Bewusst eigenständig: die Galerie-/Stock-/360°-Helfer sind hier dupliziert,
 * damit der Spielturm-Provider unangetastet bleibt (Regressionssicherheit).
 *
 * @package MH_Spielturm_Vergleich
 * @since   5.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MH_STV_Spielhaus_Data_Provider {

	const OPTION = 'mh_stv_spielhaeuser';

	/** In-Memory-Cache für die Option (vermeidet wiederholte get_option-Calls). */
	private static $groups_cache = null;

	private static function get_groups() {
		if ( self::$groups_cache === null ) {
			$g = get_option( self::OPTION, array() );
			self::$groups_cache = is_array( $g ) ? $g : array();
		}
		return self::$groups_cache;
	}

	/* ───────────────────────── Auflösung Produkt → Gruppe ───────────────────────── */

	/**
	 * Findet die Gruppe, deren Häuser-Liste das gegebene Basis-Produkt enthält.
	 *
	 * @return array|null [ 'group' => array, 'group_index' => int, 'current_house_id' => int ]
	 */
	public static function get_group_for_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return null;
		}

		$groups = self::get_groups();
		foreach ( $groups as $gi => $group ) {
			if ( empty( $group['houses'] ) || ! is_array( $group['houses'] ) ) {
				continue;
			}
			foreach ( $group['houses'] as $house ) {
				if ( absint( $house['base_product_id'] ?? 0 ) === $product_id ) {
					return array(
						'group'            => $group,
						'group_index'      => (int) $gi,
						'current_house_id' => $product_id,
					);
				}
			}
		}
		return null;
	}

	/**
	 * Gibt eine Gruppe per Index zurück (für [mh_spielhaus_konfigurator group="N"]).
	 */
	public static function get_group_by_index( $index ) {
		$groups = self::get_groups();
		$index  = absint( $index );
		if ( isset( $groups[ $index ] ) && ! empty( $groups[ $index ]['houses'] ) ) {
			$first = $groups[ $index ]['houses'][0];
			return array(
				'group'            => $groups[ $index ],
				'group_index'      => $index,
				'current_house_id' => absint( $first['base_product_id'] ?? 0 ),
			);
		}
		return null;
	}

	/* ───────────────── Autoritative Auflösung für Warenkorb/Rabatt (Schritt 4/5) ───────────────── */

	/**
	 * Liefert die ROHE Haus-Definition (inkl. Add-ons + discount_tiers) aus der Option.
	 *
	 * Server-seitig „Source of Truth" — der Warenkorb-Handler und die Rabatt-Engine
	 * vertrauen NIE den Frontend-Werten, sondern lösen Preise/Staffeln immer hierüber auf.
	 *
	 * @param int $group_index   Gruppen-Index in der Option.
	 * @param int $house_base_id base_product_id des Hauses.
	 * @return array|null Haus-Definition oder null.
	 */
	public static function get_house_def( $group_index, $house_base_id ) {
		$groups       = self::get_groups();
		$group_index  = absint( $group_index );
		$house_base_id = absint( $house_base_id );

		if ( ! isset( $groups[ $group_index ]['houses'] ) || ! is_array( $groups[ $group_index ]['houses'] ) ) {
			return null;
		}
		foreach ( $groups[ $group_index ]['houses'] as $house ) {
			if ( absint( $house['base_product_id'] ?? 0 ) === $house_base_id ) {
				return $house;
			}
		}
		return null;
	}

	/**
	 * Normalisierte, aufsteigend sortierte Rabatt-Staffeln für ein konkretes Extra.
	 *
	 * Inkludierte Add-ons liefern bewusst ein leeres Array (nie rabattiert).
	 *
	 * @param int $group_index      Gruppen-Index.
	 * @param int $house_base_id    base_product_id des Hauses.
	 * @param int $addon_product_id product_id des Add-ons.
	 * @return array Liste [ ['from_distinct'=>int, 'percent'=>float], ... ] (aufsteigend).
	 */
	public static function get_addon_tiers( $group_index, $house_base_id, $addon_product_id ) {
		$house = self::get_house_def( $group_index, $house_base_id );
		if ( ! $house || empty( $house['addons'] ) || ! is_array( $house['addons'] ) ) {
			return array();
		}
		$addon_product_id = absint( $addon_product_id );

		foreach ( $house['addons'] as $addon ) {
			if ( absint( $addon['product_id'] ?? 0 ) !== $addon_product_id ) {
				continue;
			}
			// Inkludierte Add-ons: nie rabattiert.
			if ( ! empty( $addon['included'] ) ) {
				return array();
			}
			$tiers = array();
			if ( ! empty( $addon['discount_tiers'] ) && is_array( $addon['discount_tiers'] ) ) {
				foreach ( $addon['discount_tiers'] as $t ) {
					$from = absint( $t['from_distinct'] ?? 0 );
					$pct  = isset( $t['percent'] ) ? floatval( $t['percent'] ) : 0;
					if ( $from >= 2 && $pct > 0 ) {
						$tiers[] = array( 'from_distinct' => $from, 'percent' => round( $pct, 2 ) );
					}
				}
				usort( $tiers, function ( $a, $b ) { return $a['from_distinct'] <=> $b['from_distinct']; } );
			}
			return $tiers;
		}
		return array();
	}

	/**
	 * Höchste passende Staffel-% bei D verschiedenen gewählten Extras.
	 * Spiegelt 1:1 die Frontend-Logik (tierPercent in spielhaus-konfigurator.js):
	 * Staffeln aufsteigend sortiert → die letzte mit from_distinct <= D gewinnt.
	 *
	 * @param array $tiers    Aufsteigend sortierte Staffeln (aus get_addon_tiers()).
	 * @param int   $distinct Anzahl verschiedener gewählter Extras (D).
	 * @return float Prozentsatz (0 = kein Rabatt).
	 */
	public static function tier_percent( $tiers, $distinct ) {
		$pct = 0.0;
		$distinct = (int) $distinct;
		if ( empty( $tiers ) || ! is_array( $tiers ) ) {
			return 0.0;
		}
		foreach ( $tiers as $t ) {
			if ( (int) $t['from_distinct'] <= $distinct ) {
				$pct = (float) $t['percent'];
			}
		}
		return $pct;
	}

	/* ───────────────────────── Basis-Daten (leichtgewichtig) ───────────────────────── */

	/**
	 * Baut die komplette Konfigurator-Struktur (alle Häuser + Add-ons) für eine Gruppe.
	 * Schwere Haus-Felder sind null → werden per get_house_detail() nachgeladen.
	 *
	 * @param array $group            Gruppen-Config aus der Option.
	 * @param int   $current_house_id Basis-Produkt-ID des initial gewählten Hauses.
	 */
	public static function build_configurator_data( $group, $current_house_id ) {
		$current_house_id = absint( $current_house_id );

		// ── Transient-Cache (Key auf Gruppen-Hash) ──
		$cache_key = 'mh_stv_sh_' . md5( wp_json_encode( $group ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			$cached['current_house_id'] = self::resolve_current( $cached, $current_house_id );
			foreach ( $cached['houses'] as &$ch ) {
				$ch['is_current'] = ( (int) $ch['id'] === (int) $cached['current_house_id'] );
			}
			unset( $ch );
			return $cached;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return array( 'group_name' => '', 'houses' => array(), 'current_house_id' => 0 );
		}

		$houses_out = array();
		$house_defs = isset( $group['houses'] ) && is_array( $group['houses'] ) ? $group['houses'] : array();

		foreach ( $house_defs as $house ) {
			$base_id = absint( $house['base_product_id'] ?? 0 );
			if ( $base_id < 1 ) {
				continue;
			}
			$wc = wc_get_product( $base_id );
			if ( ! $wc || ! is_object( $wc ) ) {
				continue;
			}

			$house_entry = self::build_product_base( $wc, $base_id );
			$house_entry['label']  = sanitize_text_field( $house['label'] ?? $house_entry['name'] );
			$house_entry['layout'] = sanitize_key( $house['layout'] ?? 'standard' );
			// Frei pflegbare Modell-Details (Frontend-Karten).
			$house_entry['tag']      = sanitize_text_field( $house['tag'] ?? '' );
			$house_entry['size']     = sanitize_text_field( $house['size'] ?? '' );
			$house_entry['size_pct'] = isset( $house['size_pct'] ) ? min( 100, absint( $house['size_pct'] ) ) : 0;
			$house_entry['blurb']    = sanitize_textarea_field( $house['blurb'] ?? '' );

			// 360°-Frames (falls die Spielturm-Metabox aktiv ist — wird geteilt genutzt).
			if ( class_exists( 'MH_STV_360_Metabox' ) ) {
				$house_entry['spin_frames'] = MH_STV_360_Metabox::get_frame_urls( $base_id );
				$house_entry['spin_thumb']  = MH_STV_360_Metabox::get_frame_thumb_url( $base_id );
			} else {
				$house_entry['spin_frames'] = array();
				$house_entry['spin_thumb']  = '';
			}

			// Lazy-Platzhalter (wie Spielturm).
			$house_entry['description']  = null;
			$house_entry['attributes']   = null;
			$house_entry['reviews']      = null;
			$house_entry['gallery_full'] = null;

			// ── Add-ons ──
			$addons_out = array();
			$addon_defs = isset( $house['addons'] ) && is_array( $house['addons'] ) ? $house['addons'] : array();
			foreach ( $addon_defs as $addon ) {
				$pid = absint( $addon['product_id'] ?? 0 );
				if ( $pid < 1 ) {
					continue;
				}
				$awc = wc_get_product( $pid );
				if ( ! $awc || ! is_object( $awc ) ) {
					continue;
				}

				$addon_entry = self::build_product_base( $awc, $pid );

				// Config aus der Option durchreichen.
				$addon_entry['included']    = ! empty( $addon['included'] );
				$addon_entry['default_qty'] = max( 1, absint( $addon['default_qty'] ?? 1 ) );
				$addon_entry['allow_qty']   = ! empty( $addon['allow_qty'] );
				// Vorauswahl: nur für Extras (included sind ohnehin fix im Set).
				$addon_entry['default_selected'] = ! $addon_entry['included'] && ! empty( $addon['preselected'] );

				// Rabatt-Staffel (nur Extras; bei included leer). from_distinct aufsteigend.
				$tiers = array();
				if ( ! $addon_entry['included'] && ! empty( $addon['discount_tiers'] ) && is_array( $addon['discount_tiers'] ) ) {
					foreach ( $addon['discount_tiers'] as $t ) {
						$from = absint( $t['from_distinct'] ?? 0 );
						$pct  = isset( $t['percent'] ) ? floatval( $t['percent'] ) : 0;
						if ( $from >= 2 && $pct > 0 ) {
							$tiers[] = array( 'from_distinct' => $from, 'percent' => round( $pct, 2 ) );
						}
					}
					usort( $tiers, function ( $a, $b ) { return $a['from_distinct'] <=> $b['from_distinct']; } );
				}
				$addon_entry['discount_tiers'] = $tiers;

				$addons_out[] = $addon_entry;
			}
			$house_entry['addons'] = $addons_out;

			$houses_out[] = $house_entry;
		}

		$result = array(
			'group_name'       => sanitize_text_field( $group['group_name'] ?? '' ),
			'houses'           => $houses_out,
			'current_house_id' => 0,
		);

		$result['current_house_id'] = self::resolve_current( $result, $current_house_id );
		foreach ( $result['houses'] as &$h ) {
			$h['is_current'] = ( (int) $h['id'] === (int) $result['current_house_id'] );
		}
		unset( $h );

		set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Sorgt dafür, dass current_house_id auf ein vorhandenes Haus zeigt (sonst erstes).
	 */
	private static function resolve_current( $data, $requested ) {
		$requested = absint( $requested );
		if ( empty( $data['houses'] ) ) {
			return 0;
		}
		foreach ( $data['houses'] as $h ) {
			if ( (int) $h['id'] === $requested ) {
				return $requested;
			}
		}
		return (int) $data['houses'][0]['id'];
	}

	/**
	 * Gemeinsame leichtgewichtige Produkt-Felder (Haus ODER Add-on).
	 */
	private static function build_product_base( $wc, $pid ) {
		$gallery        = self::get_gallery_urls( $wc, 'woocommerce_single' );
		$thumbnails     = self::get_gallery_urls( $wc, 'woocommerce_gallery_thumbnail' );
		$gallery_srcset = self::get_gallery_srcset( $wc );
		$stock_status   = $wc->get_stock_status();

		return array(
			'id'                => (int) $pid,
			'name'              => $wc->get_name(),
			'price'             => floatval( wc_get_price_to_display( $wc ) ),
			'regular_price'     => floatval( wc_get_price_to_display( $wc, array( 'price' => $wc->get_regular_price() ) ) ),
			'on_sale'           => (bool) $wc->is_on_sale(),
			'url'               => get_permalink( $pid ),
			'image'             => ! empty( $gallery ) ? $gallery[0] : '',
			'gallery'           => $gallery,
			'gallery_srcset'    => $gallery_srcset,
			'thumbnails'        => $thumbnails,
			'stock_status'      => $stock_status,
			'stock_text'        => self::stock_text( $stock_status ),
			'stock_quantity'    => $wc->managing_stock() ? (int) $wc->get_stock_quantity() : 0,
			'short_description' => apply_filters( 'the_content', $wc->get_short_description() ),
			'average_rating'    => floatval( $wc->get_average_rating() ),
			'review_count'      => (int) $wc->get_review_count(),
		);
	}

	private static function stock_text( $status ) {
		if ( 'instock' === $status ) {
			return 'Verfügbar';
		}
		if ( 'onbackorder' === $status ) {
			return 'Auf Bestellung';
		}
		return 'Zurzeit nicht auf Lager';
	}

	/* ───────────────────────── Lazy-Detail (AJAX) ───────────────────────── */

	/**
	 * Schwere Felder für ein Haus (Beschreibung, Attribute, Reviews, Voll-Galerie).
	 */
	public static function get_house_detail( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$wc = wc_get_product( $product_id );
		if ( ! $wc || ! is_object( $wc ) ) {
			return null;
		}

		// Downloads: Montageanleitung (eigenes Produktfeld) zuerst, dann ggf.
		// WooCommerce-Downloadable-Files.
		$downloads = self::get_product_downloads( $wc );
		$manual    = self::get_manual( $wc );
		if ( $manual ) {
			array_unshift( $downloads, $manual );
		}

		return array(
			'id'           => $product_id,
			'description'  => apply_filters( 'the_content', $wc->get_description() ),
			'reviews'      => self::get_product_reviews( $product_id ),
			'gallery_full' => self::get_gallery_urls( $wc, 'full' ),
			// v5.14.0 — Felder fürs Produktinfo-Akkordeon (Montage/Lieferung/Hersteller).
			'sku'          => $wc->get_sku(),
			'categories'   => self::get_product_categories( $product_id ),
			'shipping'     => self::get_shipping_info( $wc ),
			'downloads'    => $downloads,
			'manufacturer' => self::get_manufacturer_block(),
		);
	}

	/**
	 * Liest die Montageanleitung aus dem produkteigenen Feld (kein WooCommerce-
	 * „Downloadable File", sondern ein Custom-/ACF-Feld in der „Produktzusatz"-Box).
	 *
	 * Quelle in dieser Reihenfolge:
	 *   1. Gepinnter Feldschlüssel aus Option `mh_stv_sh_manual_meta_key`
	 *      (bzw. Filter gleichen Namens) — für 100 % Eindeutigkeit.
	 *   2. Sonst gängige Schlüssel-Kandidaten (`montageanleitung`, …).
	 *
	 * Pro Kandidat wird ACF (`get_field`) bevorzugt; ohne ACF wird die Roh-Meta
	 * gelesen. Mögliche Werte: Anhang-ID, URL oder ACF-Array → wird auf eine URL
	 * normalisiert. Anzeigename ist bewusst fix „Montageanleitung" (nicht der
	 * interne Dateiname wie „104917-104918-MATTEO.pdf").
	 *
	 * @return array|null [ 'name' => 'Montageanleitung', 'url' => string ] oder null.
	 */
	private static function get_manual( $wc ) {
		$pinned = trim( (string) get_option( 'mh_stv_sh_manual_meta_key', '' ) );
		$pinned = (string) apply_filters( 'mh_stv_sh_manual_meta_key', $pinned, $wc->get_id() );

		$candidates = $pinned !== ''
			? array( $pinned )
			: array( 'montageanleitung', 'montageanleitung_pdf', 'montage_anleitung', 'assembly_manual', 'anleitung' );

		foreach ( $candidates as $key ) {
			$url = self::resolve_file_url( $wc, $key );
			if ( $url !== '' ) {
				return array( 'name' => 'Montageanleitung', 'url' => esc_url_raw( $url ) );
			}
		}
		return null;
	}

	/**
	 * Löst den Datei-Wert eines Feldes auf eine URL auf — robust gegen die drei
	 * üblichen Speicherformen: Anhang-ID (Integer), URL (String) oder ACF-Array
	 * (`['url' => …]`). HPOS-sicher via `$wc->get_meta()` für die Roh-Meta.
	 */
	private static function resolve_file_url( $wc, $key ) {
		// 1) ACF bevorzugen (kennt das Return-Format: Array/ID/URL).
		if ( function_exists( 'get_field' ) ) {
			$v = get_field( $key, $wc->get_id() );
			if ( is_array( $v ) && ! empty( $v['url'] ) ) {
				return (string) $v['url'];
			}
			if ( is_numeric( $v ) ) {
				$u = wp_get_attachment_url( (int) $v );
				if ( $u ) {
					return $u;
				}
			}
			if ( is_string( $v ) && filter_var( $v, FILTER_VALIDATE_URL ) ) {
				return $v;
			}
		}

		// 2) Roh-Meta (ACF-File-Felder speichern i. d. R. die Anhang-ID).
		$raw = $wc->get_meta( $key );
		if ( is_array( $raw ) && ! empty( $raw['url'] ) ) {
			return (string) $raw['url'];
		}
		if ( is_numeric( $raw ) ) {
			$u = wp_get_attachment_url( (int) $raw );
			if ( $u ) {
				return $u;
			}
		}
		if ( is_string( $raw ) && filter_var( $raw, FILTER_VALIDATE_URL ) ) {
			return $raw;
		}

		return '';
	}

	/**
	 * Kategorien als [{name, url}] (für „Produktdaten" → verlinkt zur Kategorie-Seite).
	 *
	 * Wichtig: `wp_get_post_terms()` liefert Namen HTML-kodiert zurück
	 * (z. B. „Spiel- &amp; Stelzenhäuser"). Das Frontend escaped beim Rendern erneut,
	 * wodurch das „&" doppelt kodiert als „&amp;" sichtbar würde. Darum hier einmal
	 * dekodieren → das Frontend kodiert dann sauber genau einmal.
	 */
	private static function get_product_categories( $product_id ) {
		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'all' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $term ) {
			$link = get_term_link( $term, 'product_cat' );
			$out[] = array(
				'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'url'  => is_wp_error( $link ) ? '' : $link,
			);
		}
		return $out;
	}

	/**
	 * Versandart als ['label' => string, 'url' => string].
	 *
	 * Die kundenseitige Versandinfo ist NICHT die WooCommerce-Versandklasse (intern
	 * oft „standard"), sondern die Produkt-Eigenschaft „Versandgruppe" (z. B.
	 * „Speditionsgut") — exakt wie auf der normalen Produktseite. Reihenfolge:
	 * Attribut „Versandgruppe" → Attribut „Versandart" → Versandklassen-Name (Fallback).
	 *
	 * Der Link-Ziel-URL kommt aus der Option `mh_stv_sh_shipping_url` (im Admin
	 * pflegbar); ist sie leer, wird die Versandart als reiner Text gerendert.
	 */
	private static function get_shipping_info( $wc ) {
		$label = '';

		// 1) Produkt-Attribute „Versandgruppe" / „Versandart" bevorzugen (= normale Seite).
		foreach ( array( 'versandgruppe', 'versandart' ) as $wanted ) {
			$val = self::get_attribute_value_by_label( $wc, $wanted );
			if ( $val !== '' ) {
				$label = $val;
				break;
			}
		}

		// 2) Fallback: Name der WooCommerce-Versandklasse.
		if ( $label === '' ) {
			$slug = $wc->get_shipping_class();
			if ( $slug ) {
				$term  = get_term_by( 'slug', $slug, 'product_shipping_class' );
				$label = ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
			}
		}

		if ( $label === '' ) {
			return array( 'label' => '', 'url' => '' );
		}

		$url = trim( (string) get_option( 'mh_stv_sh_shipping_url', '' ) );

		return array(
			'label' => html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ),
			'url'   => $url !== '' ? esc_url_raw( $url ) : '',
		);
	}

	/**
	 * Wert eines (sichtbaren oder unsichtbaren) Produkt-Attributs anhand seines
	 * normalisierten Labels (case-insensitive). Liefert '' wenn nicht gefunden.
	 */
	private static function get_attribute_value_by_label( $wc, $needle_label ) {
		$needle_label = self::normalize_attr_key( $needle_label );
		foreach ( $wc->get_attributes() as $attr ) {
			$label = self::normalize_attr_key( wc_attribute_label( $attr->get_name(), $wc ) );
			if ( $label !== $needle_label ) {
				continue;
			}
			if ( $attr->is_taxonomy() ) {
				$terms = wp_get_post_terms( $wc->get_id(), $attr->get_name(), array( 'fields' => 'names' ) );
				return is_wp_error( $terms ) ? '' : implode( ', ', $terms );
			}
			return implode( ', ', $attr->get_options() );
		}
		return '';
	}

	/** Downloadbare Dateien (z. B. Montageanleitung-PDF) als [{name, url}]. */
	private static function get_product_downloads( $wc ) {
		$out = array();
		if ( ! method_exists( $wc, 'get_downloads' ) ) {
			return $out;
		}
		foreach ( (array) $wc->get_downloads() as $dl ) {
			$file = is_object( $dl ) && method_exists( $dl, 'get_file' ) ? $dl->get_file() : '';
			$name = is_object( $dl ) && method_exists( $dl, 'get_name' ) ? $dl->get_name() : '';
			if ( $file ) {
				$out[] = array(
					'name' => sanitize_text_field( $name ? $name : 'Download' ),
					'url'  => esc_url_raw( $file ),
				);
			}
		}
		return $out;
	}

	/**
	 * Herstellerblock (GPSR/Impressum). Identisch für alle Produkte, daher
	 * aus der Option `mh_stv_sh_manufacturer` (im Admin pflegbar) mit
	 * Mega-Holz-Standard als Fallback. HTML erlaubt (wp_kses_post beim Render).
	 */
	private static function get_manufacturer_block() {
		$opt = get_option( 'mh_stv_sh_manufacturer', '' );
		if ( is_string( $opt ) && trim( $opt ) !== '' ) {
			return $opt;
		}
		return "Marke: MEGA HOLZ\n"
			. "Hersteller: Mega-Holz GmbH & Co. KG\n"
			. "Adresse: Löbauer Straße 1A, 02763 Zittau\n"
			. "E-Mail: gpsr@mega-holz.de";
	}

	/**
	 * Normalisiert ein Attribut-Label für Vergleiche (lowercase, ohne Umlaut-/
	 * Sonderzeichen-Rauschen). „Verfügbarkeit" == „verfuegbarkeit" == „Verfugbarkeit".
	 */
	private static function normalize_attr_key( $label ) {
		$s = html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' );
		$s = mb_strtolower( trim( $s ), 'UTF-8' );
		$s = strtr( $s, array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss' ) );
		return preg_replace( '/[^a-z0-9]/', '', $s );
	}

	private static function get_product_reviews( $product_id ) {
		$reviews  = array();
		$comments = get_comments( array(
			'post_id' => $product_id,
			'status'  => 'approve',
			'type'    => 'review',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'number'  => 20,
		) );
		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
			$reviews[] = array(
				'author'  => sanitize_text_field( $comment->comment_author ),
				'date'    => date_i18n( 'd.m.Y', strtotime( $comment->comment_date ) ),
				'rating'  => $rating > 0 ? $rating : 5,
				'content' => wp_strip_all_tags( $comment->comment_content ),
			);
		}
		return $reviews;
	}

	/* ───────────────────────── Galerie-Helfer ───────────────────────── */

	private static function get_gallery_urls( $wc, $size = 'woocommerce_single' ) {
		$urls    = array();
		$main_id = $wc->get_image_id();
		if ( $main_id ) {
			$url = wp_get_attachment_image_url( $main_id, $size );
			if ( $url ) {
				$urls[] = $url;
			}
		}
		$gallery_ids = $wc->get_gallery_image_ids();
		if ( ! empty( $gallery_ids ) && is_array( $gallery_ids ) ) {
			foreach ( $gallery_ids as $gid ) {
				$url = wp_get_attachment_image_url( absint( $gid ), $size );
				if ( $url ) {
					$urls[] = $url;
				}
			}
		}
		return $urls;
	}

	private static function get_gallery_srcset( $wc ) {
		$srcsets = array();
		$ids     = array();
		$main_id = $wc->get_image_id();
		if ( $main_id ) {
			$ids[] = $main_id;
		}
		$gallery_ids = $wc->get_gallery_image_ids();
		if ( ! empty( $gallery_ids ) && is_array( $gallery_ids ) ) {
			$ids = array_merge( $ids, array_map( 'absint', $gallery_ids ) );
		}
		foreach ( $ids as $att_id ) {
			$srcsets[] = array(
				'srcset' => wp_get_attachment_image_srcset( $att_id, 'woocommerce_single' ) ?: '',
				'sizes'  => wp_get_attachment_image_sizes( $att_id, 'woocommerce_single' ) ?: '',
			);
		}
		return $srcsets;
	}

	/* ───────────────────────── Cache-Invalidierung ───────────────────────── */

	/**
	 * Löscht alle Spielhaus-Transients (bei Produkt-/Options-Änderung).
	 */
	public static function invalidate_cache( $product_id = 0 ) {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mh_stv_sh_%' OR option_name LIKE '_transient_timeout_mh_stv_sh_%'"
		);
		self::$groups_cache = null;
	}
}
