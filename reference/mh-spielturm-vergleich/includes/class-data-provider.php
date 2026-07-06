<?php
/**
 * Data Provider — reads WooCommerce product data for the configurator.
 *
 * @package MH_Spielturm_Vergleich
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MH_STV_Data_Provider {

	/**
	 * Fix v5.4.1: In-memory cache for groups option (avoids repeat get_option calls).
	 */
	private static $groups_cache = null;

	/**
	 * Get groups from option with static cache.
	 */
	private static function get_groups() {
		if ( self::$groups_cache === null ) {
			self::$groups_cache = get_option( 'mh_stv_groups', array() );
		}
		return self::$groups_cache;
	}

	/**
	 * Find the group config that contains a given product ID.
	 */
	public static function get_group_for_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return null;
		}

		$groups = self::get_groups();
		if ( empty( $groups ) || ! is_array( $groups ) ) {
			return null;
		}

		foreach ( $groups as $group ) {
			if ( empty( $group['products'] ) || ! is_array( $group['products'] ) ) {
				continue;
			}
			foreach ( $group['products'] as $serie_key => $levels ) {
				if ( ! is_array( $levels ) ) {
					continue;
				}
				foreach ( $levels as $level_key => $pid ) {
					if ( absint( $pid ) === $product_id && absint( $pid ) > 0 ) {
						return array(
							'group'         => $group,
							'current_serie' => sanitize_key( $serie_key ),
							'current_level' => sanitize_key( $level_key ),
						);
					}
				}
			}
		}

		return null;
	}

	/**
	 * Get gallery image URLs for a product.
	 */
	private static function get_gallery_urls( $wc, $size = 'woocommerce_single' ) {
		$urls = array();

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

	/**
	 * Get thumbnail gallery URLs for a product.
	 */
	private static function get_thumbnail_urls( $wc ) {
		return self::get_gallery_urls( $wc, 'woocommerce_gallery_thumbnail' );
	}

	/**
	 * Get srcset data for gallery images (responsive images).
	 */
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
			$srcset = wp_get_attachment_image_srcset( $att_id, 'woocommerce_single' );
			$sizes  = wp_get_attachment_image_sizes( $att_id, 'woocommerce_single' );
			$srcsets[] = array(
				'srcset' => $srcset ? $srcset : '',
				'sizes'  => $sizes ? $sizes : '',
			);
		}

		return $srcsets;
	}

	/**
	 * Build BASE comparison data for initial page load (v5.8.0 — lightweight).
	 *
	 * Excludes heavy fields (description, attributes, product_meta, reviews, gallery_full)
	 * which are lazy-loaded via AJAX when the user opens a tab or the lightbox.
	 *
	 * Wrapped in a transient cache (#8) keyed on group config hash.
	 */
	public static function build_comparison_data( $group, $current_serie, $current_level ) {

		// ── #8: Transient cache ──
		$cache_key = 'mh_stv_base_' . md5( wp_json_encode( $group ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			// Re-stamp is_current for the requesting product (may differ per page).
			foreach ( $cached['products'] as &$cp ) {
				$cp['is_current'] = ( $cp['serie'] === $current_serie && $cp['level'] === $current_level );
			}
			unset( $cp );
			$cached['current_serie'] = $current_serie;
			$cached['current_level'] = $current_level;
			return $cached;
		}

		$products = array();

		if ( ! function_exists( 'wc_get_product' ) ) {
			return array(
				'series'        => array(),
				'levels'        => array(),
				'products'      => array(),
				'current_serie' => $current_serie,
				'current_level' => $current_level,
			);
		}

		$product_map = isset( $group['products'] ) ? $group['products'] : array();

		foreach ( $product_map as $serie_key => $levels ) {
			if ( ! is_array( $levels ) ) {
				continue;
			}
			foreach ( $levels as $level_key => $pid ) {
				$pid = absint( $pid );
				if ( $pid < 1 ) {
					continue;
				}

				$wc = wc_get_product( $pid );
				if ( ! $wc || ! is_object( $wc ) ) {
					continue;
				}

				$gallery    = self::get_gallery_urls( $wc );
				$thumbnails = self::get_thumbnail_urls( $wc );
				$gallery_srcset = self::get_gallery_srcset( $wc );
				$main_image = ! empty( $gallery ) ? $gallery[0] : '';

				// Stock info.
				$stock_status = $wc->get_stock_status();
				$stock_text   = '';
				if ( 'instock' === $stock_status ) {
					$stock_text = 'Verfügbar';
				} elseif ( 'onbackorder' === $stock_status ) {
					$stock_text = 'Auf Bestellung';
				} else {
					$stock_text = 'Zurzeit nicht auf Lager';
				}

				// 360° frame data.
				$spin_frames = array();
				$spin_thumb  = '';
				if ( class_exists( 'MH_STV_360_Metabox' ) ) {
					$spin_frames = MH_STV_360_Metabox::get_frame_urls( $pid );
					$spin_thumb  = MH_STV_360_Metabox::get_frame_thumb_url( $pid );
				}

				// Reviews: only count + average for base data (schema + inline stars).
				$product_obj    = wc_get_product( $pid );
				$average_rating = $product_obj ? floatval( $product_obj->get_average_rating() ) : 0;
				$review_count   = $product_obj ? intval( $product_obj->get_review_count() ) : 0;

				$products[] = array(
					'id'                => $pid,
					'serie'             => sanitize_key( $serie_key ),
					'level'             => sanitize_key( $level_key ),
					'name'              => $wc->get_name(),
					'price'             => floatval( wc_get_price_to_display( $wc ) ),
					'url'               => get_permalink( $pid ),
					'image'             => $main_image,
					'gallery'           => $gallery,
					'gallery_srcset'    => $gallery_srcset,
					'thumbnails'        => $thumbnails,
					'spin_frames'       => $spin_frames,
					'spin_thumb'        => $spin_thumb,
					'stock_status'      => $stock_status,
					'stock_text'        => $stock_text,
					'stock_quantity'    => $wc->managing_stock() ? intval( $wc->get_stock_quantity() ) : 0,
					'short_description' => apply_filters( 'the_content', $wc->get_short_description() ),
					'review_count'      => $review_count,
					'average_rating'    => $average_rating,
					'add_to_cart_url'   => $wc->add_to_cart_url(),
					'is_current'        => ( $serie_key === $current_serie && $level_key === $current_level ),
					// Lazy-loaded fields: null signals "not yet loaded" to JS.
					'description'       => null,
					'attributes'        => null,
					'product_meta'      => null,
					'reviews'           => null,
					'gallery_full'      => null,
				);
			}
		}

		// ── v5.7.0: Per-serie features matrix ──
		// features_matrix[serie_key][level_key] = comma-separated features.
		$raw_matrix = isset( $group['features_matrix'] ) && is_array( $group['features_matrix'] )
			? $group['features_matrix']
			: array();

		$features_matrix = array();
		foreach ( $raw_matrix as $sk => $level_map ) {
			$sk = sanitize_key( $sk );
			if ( ! is_array( $level_map ) ) {
				continue;
			}
			$features_matrix[ $sk ] = array();
			foreach ( $level_map as $lk => $features_str ) {
				$features_matrix[ $sk ][ sanitize_key( $lk ) ] = array_values(
					array_filter( array_map( 'trim', explode( ',', (string) $features_str ) ) )
				);
			}
		}

		// Fallback: if no features_matrix exists, build from old levels[].extras (same for all series).
		if ( empty( $features_matrix ) ) {
			$group_levels  = isset( $group['levels'] ) ? $group['levels'] : array();
			$group_series2 = isset( $group['series'] ) ? $group['series'] : array();
			foreach ( $group_series2 as $serie_def ) {
				$sk = isset( $serie_def['key'] ) ? $serie_def['key'] : '';
				if ( empty( $sk ) ) continue;
				$features_matrix[ $sk ] = array();
				foreach ( $group_levels as $level_def ) {
					$lk = isset( $level_def['key'] ) ? $level_def['key'] : '';
					if ( empty( $lk ) ) continue;
					$extras_str = isset( $level_def['extras'] ) ? $level_def['extras'] : '';
					$features_matrix[ $sk ][ $lk ] = array_values(
						array_filter( array_map( 'trim', explode( ',', $extras_str ) ) )
					);
				}
			}
		}

		// Parse base features from group config.
		$base_features_str = isset( $group['base_features'] ) ? $group['base_features'] : '';
		$base_features = array_filter( array_map( 'trim', explode( ',', $base_features_str ) ) );

		// Parse per-series features (always-on features for that design).
		$series_features = array();
		$group_series = isset( $group['series'] ) ? $group['series'] : array();
		foreach ( $group_series as $serie_def ) {
			$sk = isset( $serie_def['key'] ) ? $serie_def['key'] : '';
			$sf_str = isset( $serie_def['features'] ) ? $serie_def['features'] : '';
			$series_features[ $sk ] = array_filter( array_map( 'trim', explode( ',', $sf_str ) ) );
		}

		$result = array(
			'group_name'       => isset( $group['group_name'] ) ? $group['group_name'] : '',
			'series'           => isset( $group['series'] ) ? array_values( $group['series'] ) : array(),
			'levels'           => isset( $group['levels'] ) ? array_values( $group['levels'] ) : array(),
			'products'         => $products,
			'current_serie'    => $current_serie,
			'current_level'    => $current_level,
			'base_features'    => array_values( $base_features ),
			'features_matrix'  => $features_matrix,
			'series_features'  => $series_features,
		);

		// #8: Store in transient (1 hour TTL, invalidated on product save).
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * #8: Invalidate transient cache when any product is saved.
	 */
	public static function invalidate_cache( $product_id ) {
		global $wpdb;
		// Delete all mh_stv_base_ transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mh_stv_base_%' OR option_name LIKE '_transient_timeout_mh_stv_base_%'"
		);
	}

	/**
	 * #2 + #9: Get detail data for a single product (lazy-loaded via AJAX).
	 *
	 * Returns: description, attributes, product_meta, reviews, gallery_full.
	 */
	public static function get_product_detail( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$wc = wc_get_product( $product_id );
		if ( ! $wc || ! is_object( $wc ) ) {
			return null;
		}

		$reviews_data = self::get_product_reviews( $product_id );

		return array(
			'id'            => $product_id,
			'description'   => apply_filters( 'the_content', $wc->get_description() ),
			'attributes'    => self::get_product_attributes( $wc ),
			'product_meta'  => self::get_product_meta( $wc, $product_id ),
			'reviews'       => $reviews_data['reviews'],
			'gallery_full'  => self::get_gallery_urls( $wc, 'full' ),
		);
	}

	/**
	 * Get product attributes, weight, and dimensions.
	 */
	private static function get_product_attributes( $wc ) {
		$attrs = array();

		// Weight.
		$weight = $wc->get_weight();
		if ( $weight ) {
			$attrs[] = array(
				'label' => 'Gewicht',
				'value' => $weight . ' ' . get_option( 'woocommerce_weight_unit', 'kg' ),
			);
		}

		// Dimensions.
		$length = $wc->get_length();
		$width  = $wc->get_width();
		$height = $wc->get_height();
		if ( $length && $width && $height ) {
			$unit = get_option( 'woocommerce_dimension_unit', 'cm' );
			$attrs[] = array(
				'label' => 'Versandmaße (L × B × H)',
				'value' => $length . ' × ' . $width . ' × ' . $height . ' ' . $unit,
			);
		}

		// Product attributes (custom + WC taxonomy).
		$product_attrs = $wc->get_attributes();
		if ( ! empty( $product_attrs ) ) {
			foreach ( $product_attrs as $attr ) {
				if ( ! $attr->get_visible() ) {
					continue;
				}
				$label = wc_attribute_label( $attr->get_name(), $wc );
				$value = '';

				if ( $attr->is_taxonomy() ) {
					$terms = wp_get_post_terms( $wc->get_id(), $attr->get_name(), array( 'fields' => 'names' ) );
					if ( ! is_wp_error( $terms ) ) {
						$value = implode( ', ', $terms );
					}
				} else {
					$value = implode( ', ', $attr->get_options() );
				}

				if ( $value ) {
					$attrs[] = array(
						'label' => sanitize_text_field( $label ),
						'value' => sanitize_text_field( $value ),
					);
				}
			}
		}

		return $attrs;
	}

	/**
	 * Get product meta: SKU, shipping class, categories, manufacturer (GPSR).
	 */
	private static function get_product_meta( $wc, $product_id ) {
		$meta = array();

		// SKU / Artikelnummer.
		$sku = $wc->get_sku();
		if ( $sku ) {
			$meta[] = array( 'label' => 'Artikelnummer', 'value' => sanitize_text_field( $sku ) );
		}

		// Categories.
		$cats = wc_get_product_category_list( $product_id, ', ' );
		if ( $cats ) {
			$meta[] = array( 'label' => 'Kategorie', 'value' => $cats, 'html' => true );
		}

		// v5.7.1: Removed Versandart — redundant with Versandgruppe in Zusätzliche Informationen.

		// ── Manufacturer / GPSR data (v5.7.1: HPOS-compatible via $wc->get_meta()) ──
		$gpsr_label_map = array(
			'_wc_gpsr_brand'                           => 'Marke',
			'_wc_gpsr_manufacturer_name'               => 'Hersteller',
			'_wc_gpsr_manufacturer_address'            => 'Adresse',
			'_wc_gpsr_manufacturer_email'              => 'E-Mail',
			'_wc_gpsr_manufacturer_url'                => 'Website',
			'_wc_gpsr_eu_responsible_person_name'       => 'EU-Verantwortlicher',
			'_wc_gpsr_eu_responsible_person_address'    => 'Adresse (EU-Verantwortlicher)',
			'_wc_gpsr_eu_responsible_person_email'      => 'E-Mail (EU-Verantwortlicher)',
			'_wc_gpsr_eu_responsible_person_url'        => 'Website (EU-Verantwortlicher)',
			'_wc_gpsr_importer_name'                    => 'Importeur',
			'_wc_gpsr_importer_address'                 => 'Adresse (Importeur)',
			'_wc_gpsr_safety_information'               => 'Sicherheitshinweise',
		);

		// v5.7.1: Use $wc->get_meta() for HPOS compatibility.
		$read_meta = method_exists( $wc, 'get_meta' )
			? function( $key ) use ( $wc ) { return $wc->get_meta( $key, true ); }
			: function( $key ) use ( $product_id ) { return get_post_meta( $product_id, $key, true ); };

		foreach ( $gpsr_label_map as $gpsr_key => $gpsr_label ) {
			$val = $read_meta( $gpsr_key );
			if ( $val ) {
				$meta[] = array(
					'label'   => $gpsr_label,
					'value'   => sanitize_text_field( $val ),
					'section' => 'manufacturer',
				);
			}
		}

		// Fallback brand detection.
		$has_brand = false;
		foreach ( $meta as $m ) {
			if ( $m['label'] === 'Marke' ) { $has_brand = true; break; }
		}
		if ( ! $has_brand ) {
			$brand_keys = array( '_brand', 'brand', '_manufacturer_brand' );
			foreach ( $brand_keys as $bk ) {
				$brand = $read_meta( $bk );
				if ( $brand ) {
					array_unshift( $meta, array(
						'label'   => 'Marke',
						'value'   => sanitize_text_field( $brand ),
						'section' => 'manufacturer',
					) );
					break;
				}
			}
		}

		// Catch additional _wc_gpsr_* fields via HPOS-safe get_meta_data().
		$all_meta_objects = method_exists( $wc, 'get_meta_data' ) ? $wc->get_meta_data() : array();
		if ( ! empty( $all_meta_objects ) ) {
			foreach ( $all_meta_objects as $meta_obj ) {
				$mk = $meta_obj->key;
				if ( strpos( $mk, '_wc_gpsr_' ) === 0 && ! isset( $gpsr_label_map[ $mk ] ) ) {
					$val = $meta_obj->value;
					if ( $val ) {
						$auto_label = ucfirst( str_replace( '_', ' ', str_replace( '_wc_gpsr_', '', $mk ) ) );
						$meta[] = array(
							'label'   => sanitize_text_field( $auto_label ),
							'value'   => sanitize_text_field( $val ),
							'section' => 'manufacturer',
						);
					}
				}
			}
		} else {
			// Legacy fallback for pre-HPOS.
			$all_meta = get_post_meta( $product_id );
			if ( is_array( $all_meta ) ) {
				foreach ( $all_meta as $mk => $mv ) {
					if ( strpos( $mk, '_wc_gpsr_' ) === 0 && ! isset( $gpsr_label_map[ $mk ] ) ) {
						$val = is_array( $mv ) ? $mv[0] : $mv;
						if ( $val ) {
							$auto_label = ucfirst( str_replace( '_', ' ', str_replace( '_wc_gpsr_', '', $mk ) ) );
							$meta[] = array(
								'label'   => sanitize_text_field( $auto_label ),
								'value'   => sanitize_text_field( $val ),
								'section' => 'manufacturer',
							);
						}
					}
				}
			}
		}

		return $meta;
	}

	/**
	 * Get product reviews.
	 */
	private static function get_product_reviews( $product_id ) {
		$reviews = array();

		$comments = get_comments( array(
			'post_id' => $product_id,
			'status'  => 'approve',
			'type'    => 'review',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'number'  => 20,
		) );

		if ( ! empty( $comments ) ) {
			foreach ( $comments as $comment ) {
				$rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );
				$reviews[] = array(
					'author'  => sanitize_text_field( $comment->comment_author ),
					'date'    => date_i18n( 'd.m.Y', strtotime( $comment->comment_date ) ),
					'rating'  => $rating > 0 ? $rating : 5,
					'content' => wp_strip_all_tags( $comment->comment_content ),
				);
			}
		}

		$product = wc_get_product( $product_id );
		$average = $product ? floatval( $product->get_average_rating() ) : 0;

		return array(
			'reviews' => $reviews,
			'count'   => count( $reviews ),
			'average' => $average,
		);
	}
}
