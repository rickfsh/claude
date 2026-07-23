<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Liest die SONO-Produkte aus WooCommerce und baut die gruppierte
 * Grid-Datenstruktur (Sektionen → Modelle → Oberflächen-Varianten).
 *
 * Daten-Vertrag pro Produkt (siehe README):
 *  - Kategorie: Basis-Kategorie (Default 'sono'); Tore zusätzlich in Unterkategorie.
 *  - Attribute: pa_modell (Gruppierschlüssel), pa_hoehe, pa_oberflaeche.
 *  - Fallback: Höhe/Oberfläche werden notfalls aus dem Produkttitel geparst;
 *    das Modell wird NIE geraten — ohne Modell-Attribut wird das Produkt als
 *    Einzel-Karte in der Sektion „Weitere Modelle" gezeigt und im Admin gemeldet.
 */
final class MH_SONO_Data_Provider {

	/** Per-Request-Cache (Kategorie-Slug → Struktur). */
	private static $memo = array();

	/* ── Konfiguration (per Filter überschreibbar) ─────────────────────── */

	public static function attr_map() {
		return apply_filters( 'mh_sono_attr_map', array(
			'modell'      => 'pa_modell',
			'hoehe'       => 'pa_hoehe',
			'oberflaeche' => 'pa_oberflaeche',
		) );
	}

	/** Fallback-Reihenfolge der Oberflächen, wenn Terme keine menu_order haben. */
	public static function finish_order() {
		return apply_filters( 'mh_sono_finish_order', array( 'anthrazit', 'silber', 'laerche' ) );
	}

	/** Editorial-Zusätze zu den Höhen-Sektionen (Bestand von der Landingpage). */
	public static function height_titles() {
		return apply_filters( 'mh_sono_height_titles', array(
			180 => 'Voller Sichtschutz',
			150 => 'Sichtschutz mit Luft',
			120 => 'Gartenzaun',
			90  => 'Vorgartenzaun',
		) );
	}

	/** Typ-Slugs, die nach Höhe (statt nach Kategorie) sektioniert werden. */
	public static function height_section_types() {
		return apply_filters( 'mh_sono_height_section_types', array( '', 'sichtschutz', 'sichtschutzzaeune' ) );
	}

	/* ── Öffentliche API ───────────────────────────────────────────────── */

	/**
	 * Komplette Grid-Struktur für eine Basis-Kategorie.
	 * Struktur ist transient-gecacht; Preis + Lagerstatus werden bei jedem
	 * Request frisch gestempelt (Sale-Start feuert keinen Produkt-Hook).
	 */
	public static function get_grid_data( $category_slug ) {
		$category_slug = sanitize_title( $category_slug );

		if ( isset( self::$memo[ $category_slug ] ) ) {
			return self::$memo[ $category_slug ];
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return array( 'ok' => false, 'error' => 'woocommerce_missing' );
		}
		if ( ! term_exists( $category_slug, 'product_cat' ) ) {
			return array( 'ok' => false, 'error' => 'category_not_found' );
		}

		$cache_key = 'mh_sono_grid_' . md5( $category_slug . '|' . MH_SONO_VERSION );
		$data      = get_transient( $cache_key );

		if ( ! is_array( $data ) || empty( $data['ok'] ) ) {
			$data = self::build_grid_data( $category_slug );
			if ( $data['ok'] ) {
				set_transient( $cache_key, $data, HOUR_IN_SECONDS );
			}
		}

		if ( $data['ok'] ) {
			self::restamp_live_fields( $data );
		}

		self::$memo[ $category_slug ] = $data;
		return $data;
	}

	/** Löscht alle Plugin-Transients (Bulk, inkl. Timeouts). */
	public static function invalidate_cache() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\_transient\_mh\_sono\_%'
			    OR option_name LIKE '\_transient\_timeout\_mh\_sono\_%'"
		);
		self::$memo = array();
	}

	/* ── Aufbau der Struktur ───────────────────────────────────────────── */

	private static function build_grid_data( $category_slug ) {
		$ids = self::query_product_ids( $category_slug );

		$resolved   = array();
		$incomplete = array();

		foreach ( $ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			$row = self::resolve_product( $product, $category_slug );
			if ( ! empty( $row['missing'] ) ) {
				$incomplete[] = array(
					'id'      => $pid,
					'title'   => $product->get_name(),
					'missing' => $row['missing'],
				);
			}
			$resolved[] = $row;
		}

		$sections = self::group_products( $resolved );
		$filters  = self::build_filters( $sections );

		return array(
			'ok'         => true,
			'category'   => $category_slug,
			'sections'   => $sections,
			'filters'    => $filters,
			'incomplete' => $incomplete,
			'counts'     => array(
				'products' => count( $resolved ),
				'models'   => array_sum( array_map( function ( $s ) { return count( $s['models'] ); }, $sections ) ),
				'heights'  => count( $filters['heights'] ),
				'finishes' => count( $filters['finishes'] ),
			),
		);
	}

	/** Produkt-IDs der Kategorie (inkl. Unterkategorien), katalog-sichtbar. */
	private static function query_product_ids( $category_slug ) {
		$tax_query = array(
			array(
				'taxonomy'         => 'product_cat',
				'field'            => 'slug',
				'terms'            => $category_slug,
				'include_children' => true,
			),
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
		);

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'outofstock' ),
				'operator' => 'NOT IN',
			);
		}

		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'tax_query'      => $tax_query,
			'no_found_rows'  => true,
		) );

		return array_map( 'intval', $q->posts );
	}

	/**
	 * Ein Produkt → aufgelöste Dimensionen + Anzeigedaten.
	 * Fallback-Kette: Attribut → Titel-Parsing → leer (+ missing-Meldung).
	 */
	private static function resolve_product( $product, $category_slug ) {
		$pid   = $product->get_id();
		$title = $product->get_name();
		$map   = self::attr_map();

		$missing = array();

		/* Modell — nur aus dem Attribut, nie geraten. */
		$model = self::first_term( $pid, $map['modell'] );
		if ( ! $model ) {
			$missing[] = 'modell';
		}

		/* Höhe — Attribut, sonst Titel-Parsing ("180 cm"). */
		$height_term = self::first_term( $pid, $map['hoehe'] );
		$height_raw  = $height_term ? $height_term['name'] : $title;
		$height      = 0;
		if ( preg_match( '/(\d{2,3})\s*cm/i', $height_raw, $m ) ) {
			$height = (int) $m[1];
		} elseif ( $height_term && preg_match( '/^(\d{2,3})$/', trim( $height_term['name'] ), $m ) ) {
			$height = (int) $m[1];
		}
		if ( ! $height && ! $height_term ) {
			$missing[] = 'hoehe';
		}

		/* Oberfläche — Attribut, sonst Keyword im Titel. */
		$finish_term = self::first_term( $pid, $map['oberflaeche'] );
		if ( $finish_term ) {
			$finish       = $finish_term['slug'];
			$finish_label = $finish_term['name'];
		} else {
			list( $finish, $finish_label ) = self::parse_finish( $title );
			if ( ! $finish ) {
				$missing[] = 'oberflaeche';
			}
		}

		/* Produkttyp — tiefste Unterkategorie unterhalb der Basis-Kategorie. */
		list( $type, $type_label ) = self::resolve_type( $pid, $category_slug );

		return array(
			'id'           => $pid,
			'title'        => $title,
			'menu_order'   => (int) get_post_field( 'menu_order', $pid ),
			'model'        => $model ? $model['name'] : '',
			'model_slug'   => $model ? $model['slug'] : '',
			'height'       => $height,
			'height_label' => $height ? $height . ' cm' : '',
			'finish'       => $finish,
			'finish_label' => $finish_label,
			'type'         => $type,
			'type_label'   => $type_label,
			'url'          => get_permalink( $pid ),
			'img'          => self::image_html( $product, $title ),
			'missing'      => $missing,
		);
	}

	/** Ersten Term einer Taxonomie am Produkt holen (oder null). */
	private static function first_term( $pid, $taxonomy ) {
		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		$terms = wp_get_post_terms( $pid, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}
		return array(
			'name'       => $terms[0]->name,
			'slug'       => $terms[0]->slug,
			'menu_order' => (int) get_term_meta( $terms[0]->term_id, 'order', true ),
		);
	}

	/** Oberfläche aus dem Titel parsen (nur bekannte Keywords, kein Raten). */
	private static function parse_finish( $title ) {
		$keywords = apply_filters( 'mh_sono_finish_keywords', array(
			'anthrazit' => 'Anthrazit RAL 7016',
			'silber'    => 'Silber',
			'laerche'   => 'Lärchenoptik',
			'lärche'    => 'Lärchenoptik',
		) );
		$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title );
		foreach ( $keywords as $needle => $label ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				$slug = ( 'lärche' === $needle ) ? 'laerche' : $needle;
				return array( $slug, $label );
			}
		}
		return array( '', '' );
	}

	/**
	 * Tiefste product_cat unterhalb der Basis-Kategorie bestimmt den Typ.
	 * Produkt nur in der Basis selbst → Default-Typ '' („Sichtschutz").
	 */
	private static function resolve_type( $pid, $category_slug ) {
		$base = get_term_by( 'slug', $category_slug, 'product_cat' );
		if ( ! $base ) {
			return array( '', '' );
		}
		$terms = wp_get_post_terms( $pid, 'product_cat' );
		if ( is_wp_error( $terms ) ) {
			return array( '', '' );
		}

		$best       = null;
		$best_depth = -1;
		foreach ( $terms as $t ) {
			if ( (int) $t->term_id === (int) $base->term_id ) {
				continue;
			}
			$depth    = 0;
			$ancestor = $t;
			$is_child = false;
			while ( $ancestor && $ancestor->parent ) {
				$depth++;
				if ( (int) $ancestor->parent === (int) $base->term_id ) {
					$is_child = true;
					break;
				}
				$ancestor = get_term( $ancestor->parent, 'product_cat' );
				if ( is_wp_error( $ancestor ) ) {
					$ancestor = null;
				}
			}
			if ( $is_child && ( $depth > $best_depth || ( $depth === $best_depth && $best && $t->term_id < $best->term_id ) ) ) {
				$best       = $t;
				$best_depth = $depth;
			}
		}

		if ( $best ) {
			return array( $best->slug, $best->name );
		}
		return array( '', '' );
	}

	/** Produktbild-HTML (gecacht; Bildwechsel feuert save_post_product). */
	private static function image_html( $product, $alt ) {
		$img_id = $product->get_image_id();
		if ( ! $img_id ) {
			return '';
		}
		return wp_get_attachment_image( $img_id, 'woocommerce_single', false, array(
			'class'   => 'mhsono-img',
			'alt'     => $alt,
			'loading' => 'lazy',
			/* Ohne 'sizes' wählt WP aus dem srcset zu kleine Dateien. */
			'sizes'   => '(max-width: 640px) 92vw, (max-width: 1024px) 45vw, 400px',
		) );
	}

	/**
	 * Aufgelöste Produkte → Sektionen mit Modell-Karten.
	 * Gruppenschlüssel: typ|modell|höhe (ein Modell existiert je Höhe als eigene Karte).
	 */
	private static function group_products( $resolved ) {
		$groups = array();

		foreach ( $resolved as $row ) {
			if ( '' === $row['model_slug'] ) {
				/* Ohne Modell: Einzel-Karte, Schlüssel = Produkt-ID (keine Fusion). */
				$key = 'single|' . $row['id'];
			} else {
				$key = $row['type'] . '|' . $row['model_slug'] . '|' . $row['height'];
			}

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'key'          => $key,
					'name'         => $row['model'] ? $row['model'] : $row['title'],
					'is_single'    => '' === $row['model_slug'],
					'type'         => $row['type'],
					'type_label'   => $row['type_label'],
					'height'       => $row['height'],
					'height_label' => $row['height_label'],
					'menu_order'   => $row['menu_order'],
					'incomplete'   => ! empty( $row['missing'] ),
					'variants'     => array(),
				);
			}

			$groups[ $key ]['menu_order'] = min( $groups[ $key ]['menu_order'], $row['menu_order'] );

			$groups[ $key ]['variants'][] = array(
				'product_id'   => $row['id'],
				'finish'       => $row['finish'],
				'finish_label' => '' !== $row['finish_label'] ? $row['finish_label'] : 'Standard',
				'url'          => $row['url'],
				'img'          => $row['img'],
				/* Live-Felder, pro Request gestempelt: */
				'price_html'   => '',
				'in_stock'     => true,
			);
		}

		/* Varianten je Karte sortieren (Term-Reihenfolge, Fallback feste Liste). */
		$order = array_flip( self::finish_order() );
		foreach ( $groups as &$g ) {
			usort( $g['variants'], function ( $a, $b ) use ( $order ) {
				$pa = isset( $order[ $a['finish'] ] ) ? $order[ $a['finish'] ] : 99;
				$pb = isset( $order[ $b['finish'] ] ) ? $order[ $b['finish'] ] : 99;
				if ( $pa !== $pb ) {
					return $pa - $pb;
				}
				return strcmp( $a['finish_label'], $b['finish_label'] );
			} );
		}
		unset( $g );

		return self::build_sections( $groups );
	}

	/** Karten → Sektionen: Höhen (Zäune) → Tore-Kategorien → „Weitere Modelle". */
	private static function build_sections( $groups ) {
		$height_types  = self::height_section_types();
		$height_titles = self::height_titles();

		$by_height = array();  // height (int) => models
		$by_type   = array();  // type slug   => models
		$rest      = array();

		foreach ( $groups as $g ) {
			$is_fence = in_array( $g['type'], $height_types, true );
			if ( $g['is_single'] && ( ! $g['height'] || ! $is_fence ) ) {
				$rest[] = $g;
			} elseif ( $is_fence && $g['height'] ) {
				$by_height[ $g['height'] ][] = $g;
			} elseif ( $is_fence ) {
				$rest[] = $g; // Zaun ohne Höhe → „Weitere Modelle"
			} else {
				$by_type[ $g['type'] ][] = $g;
			}
		}

		$sections = array();

		/* Höhen absteigend (180 → 90). */
		krsort( $by_height );
		foreach ( $by_height as $h => $models ) {
			$suffix   = isset( $height_titles[ $h ] ) ? ' – ' . $height_titles[ $h ] : '';
			$sections[] = array(
				'kind'   => 'height',
				'id'     => 'sono-h' . $h,
				'title'  => $h . ' cm' . $suffix,
				'type'   => '',
				'height' => $h,
				'models' => self::sort_models( $models ),
			);
		}

		/* Typ-Sektionen (Tore …) nach Kategorie-Reihenfolge (term meta 'order'). */
		$type_sections = array();
		foreach ( $by_type as $slug => $models ) {
			$term  = get_term_by( 'slug', $slug, 'product_cat' );
			$order = $term ? (int) get_term_meta( $term->term_id, 'order', true ) : 0;
			$type_sections[] = array(
				'kind'   => 'type',
				'id'     => 'sono-typ-' . $slug,
				'title'  => $models[0]['type_label'],
				'type'   => $slug,
				'height' => 0,
				'models' => self::sort_models( $models ),
				'_order' => $order,
			);
		}
		usort( $type_sections, function ( $a, $b ) {
			if ( $a['_order'] !== $b['_order'] ) {
				return $a['_order'] - $b['_order'];
			}
			return strcmp( $a['title'], $b['title'] );
		} );
		foreach ( $type_sections as $s ) {
			unset( $s['_order'] );
			$sections[] = $s;
		}

		if ( $rest ) {
			$sections[] = array(
				'kind'   => 'rest',
				'id'     => 'sono-weitere',
				'title'  => 'Weitere Modelle',
				'type'   => '',
				'height' => 0,
				'models' => self::sort_models( $rest ),
			);
		}

		return $sections;
	}

	/** Modelle innerhalb einer Sektion: menu_order der Produkte, dann Name. */
	private static function sort_models( $models ) {
		usort( $models, function ( $a, $b ) {
			if ( $a['menu_order'] !== $b['menu_order'] ) {
				return $a['menu_order'] - $b['menu_order'];
			}
			return strcasecmp( $a['name'], $b['name'] );
		} );
		return array_values( $models );
	}

	/** Filter-Werte (mit Modell-Zählern) aus den fertigen Sektionen ableiten. */
	private static function build_filters( $sections ) {
		$types    = array();
		$heights  = array();
		$finishes = array();

		foreach ( $sections as $sec ) {
			foreach ( $sec['models'] as $m ) {
				$tkey = $m['type'];
				if ( ! isset( $types[ $tkey ] ) ) {
					$types[ $tkey ] = array( 'label' => $m['type_label'], 'count' => 0 );
				}
				$types[ $tkey ]['count']++;

				if ( $m['height'] ) {
					$hkey = (string) $m['height'];
					if ( ! isset( $heights[ $hkey ] ) ) {
						$heights[ $hkey ] = array( 'label' => $m['height_label'], 'count' => 0 );
					}
					$heights[ $hkey ]['count']++;
				}

				foreach ( $m['variants'] as $v ) {
					if ( '' === $v['finish'] ) {
						continue;
					}
					if ( ! isset( $finishes[ $v['finish'] ] ) ) {
						$finishes[ $v['finish'] ] = array( 'label' => $v['finish_label'], 'count' => 0 );
					}
					$finishes[ $v['finish'] ]['count']++;
				}
			}
		}

		krsort( $heights, SORT_NUMERIC );

		/* Oberflächen in Standard-Reihenfolge. */
		$sorted_finishes = array();
		foreach ( self::finish_order() as $slug ) {
			if ( isset( $finishes[ $slug ] ) ) {
				$sorted_finishes[ $slug ] = $finishes[ $slug ];
				unset( $finishes[ $slug ] );
			}
		}
		$sorted_finishes = array_merge( $sorted_finishes, $finishes );

		return array(
			'types'    => $types,
			'heights'  => $heights,
			'finishes' => $sorted_finishes,
		);
	}

	/** Preis-HTML + Lagerstatus pro Request frisch stempeln (nie cachen). */
	private static function restamp_live_fields( &$data ) {
		foreach ( $data['sections'] as &$sec ) {
			foreach ( $sec['models'] as &$m ) {
				foreach ( $m['variants'] as &$v ) {
					$product = wc_get_product( $v['product_id'] );
					if ( $product ) {
						$v['price_html'] = $product->get_price_html();
						$v['in_stock']   = $product->is_in_stock();
					} else {
						$v['price_html'] = '';
						$v['in_stock']   = false;
					}
				}
				unset( $v );
			}
			unset( $m );
		}
		unset( $sec );
	}
}
