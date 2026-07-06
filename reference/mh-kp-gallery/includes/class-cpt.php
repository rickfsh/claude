<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MH_STL_CPT {
    public static function register() {
        if ( ! post_type_exists( MH_STL_POST_TYPE ) ) {
            register_post_type( MH_STL_POST_TYPE, [
                'labels' => [
                    'name'               => 'Kundenprojekte',
                    'singular_name'      => 'Kundenprojekt',
                    'add_new'            => 'Neues Projekt',
                    'add_new_item'       => 'Neues Kundenprojekt anlegen',
                    'edit_item'          => 'Kundenprojekt bearbeiten',
                    'all_items'          => 'Alle Projekte',
                    'search_items'       => 'Projekte suchen',
                    'not_found'          => 'Keine Projekte gefunden',
                    'menu_name'          => 'Kundenprojekte',
                ],
                'public'          => true,
                'show_ui'         => true,
                'show_in_menu'    => true,
                'menu_icon'       => 'dashicons-camera',
                'menu_position'   => 25,
                'supports'        => [ 'title', 'thumbnail', 'custom-fields' ],
                'has_archive'     => false,
                'show_in_rest'    => true,
                'capability_type' => 'post',
            ] );
        }

        /* ── Taxonomy: Projektkategorie ── */
        if ( ! taxonomy_exists( 'mh_projekt_kategorie' ) ) {
            register_taxonomy( 'mh_projekt_kategorie', MH_STL_POST_TYPE, [
                'labels' => [
                    'name'          => 'Projektkategorien',
                    'singular_name' => 'Projektkategorie',
                    'add_new_item'  => 'Neue Kategorie',
                    'menu_name'     => 'Kategorien',
                ],
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'rewrite'           => false,
            ] );

            if ( ! get_option( 'mh_stl_cats_v3' ) ) {
                foreach ( [ 'Sichtschutz', 'Hochbeet', 'Terrasse', 'Gartenmöbel', 'Gartenbauten', 'Spielplatz', 'Gartenzaun' ] as $cat ) {
                    if ( ! term_exists( $cat, 'mh_projekt_kategorie' ) ) {
                        wp_insert_term( $cat, 'mh_projekt_kategorie' );
                    }
                }
                update_option( 'mh_stl_cats_v3', true );
            }
        }
    }
}
