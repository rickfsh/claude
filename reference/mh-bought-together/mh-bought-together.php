<?php
/**
 * Plugin Name:       MH Häufig zusammen gekauft
 * Plugin URI:        https://mega-holz.de
 * Description:       Zeigt "Wird oft zusammen gekauft"-Empfehlungen mit intelligenter Mengenberechnung (Multiplikator + Offset) auf WooCommerce-Produktseiten. Neu: "Im Lieferumfang enthalten"-Widget zeigt inklusive Produkte. Speziell für Mega-Holz.
 * Version:           3.2.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mega-Holz GmbH & Co. KG
 * Author URI:        https://mega-holz.de
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mh-bought-together
 * Domain Path:       /languages
 *
 * @package MH_Bought_Together
 */

defined( 'ABSPATH' ) || exit;

define( 'MH_BT_VERSION', '3.2.6' );
define( 'MH_BT_FILE', __FILE__ );
define( 'MH_BT_PATH', plugin_dir_path( __FILE__ ) );
define( 'MH_BT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active before initializing.
 */
function mh_bt_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'mh_bt_woocommerce_missing_notice' );
        return;
    }
    mh_bt_init();
}
add_action( 'plugins_loaded', 'mh_bt_check_woocommerce' );

function mh_bt_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'MH Häufig zusammen gekauft benötigt WooCommerce. Bitte WooCommerce installieren und aktivieren.', 'mh-bought-together' ); ?></p>
    </div>
    <?php
}

/**
 * Initialize all plugin functionality.
 */
function mh_bt_init() {
    // Admin.
    add_action( 'add_meta_boxes', 'mh_bt_add_meta_box' );
    add_action( 'add_meta_boxes', 'mh_ip_add_meta_box' );
    add_action( 'save_post_product', 'mh_bt_save_meta_box', 10, 2 );
    add_action( 'save_post_product', 'mh_ip_save_meta_box', 10, 2 );
    add_action( 'admin_enqueue_scripts', 'mh_bt_admin_scripts' );

    // Frontend: Hook into add-to-cart area (only if auto-placement is enabled).
    $opts = mh_bt_get_options();
    if ( ! empty( $opts['auto_placement'] ) ) {
        add_action( 'woocommerce_after_add_to_cart_form', 'mh_bt_display_widget_once', 15 );
        add_action( 'woocommerce_after_add_to_cart_button', 'mh_bt_display_widget_once', 20 );
        add_action( 'woocommerce_after_single_product_summary', 'mh_bt_display_widget_once', 5 );
        add_filter( 'the_content', 'mh_bt_content_fallback', 99 );
    }

    // Shortcode always available for manual placement.
    add_shortcode( 'mh_bought_together', 'mh_bt_shortcode' );
    add_shortcode( 'mh_included_products', 'mh_ip_shortcode' );

    // Included Products: display on product pages.
    $opts_ip = mh_bt_get_options();
    if ( ! empty( $opts_ip['auto_placement'] ) ) {
        add_action( 'woocommerce_before_add_to_cart_form', 'mh_ip_display_widget_once', 5 );
        add_action( 'woocommerce_after_add_to_cart_form', 'mh_ip_display_widget_once', 5 );
    }

    // Frontend scripts.
    add_action( 'wp_enqueue_scripts', 'mh_bt_frontend_scripts' );

    // AJAX.
    add_action( 'wp_ajax_mh_bt_add_to_cart', 'mh_bt_ajax_add_to_cart' );
    add_action( 'wp_ajax_nopriv_mh_bt_add_to_cart', 'mh_bt_ajax_add_to_cart' );

    // AJAX: Tracking events (impressions, deselections).
    add_action( 'wp_ajax_mh_bt_track', 'mh_bt_ajax_track' );
    add_action( 'wp_ajax_nopriv_mh_bt_track', 'mh_bt_ajax_track' );

    // Cart: Apply bundle discount as negative fee.
    add_action( 'woocommerce_cart_calculate_fees', 'mh_bt_apply_bundle_discount' );
    // Cart: Restore bundle data from session (critical for page reloads).
    add_filter( 'woocommerce_get_cart_item_from_session', 'mh_bt_restore_cart_item_from_session', 10, 3 );
    // Cart: Inject bundle badges via footer script (theme-proof).
    add_action( 'wp_footer', 'mh_bt_cart_bundle_data_script', 5 );
    // Cart: Style fee row in cart/checkout totals.
    add_filter( 'woocommerce_cart_totals_fee_html', 'mh_bt_style_fee_html', 10, 2 );
    // Order: Save bundle meta to order items.
    add_action( 'woocommerce_checkout_create_order_line_item', 'mh_bt_save_order_item_meta', 10, 4 );

    // Settings.
    add_action( 'admin_menu', 'mh_bt_add_settings_page' );
    add_action( 'admin_init', 'mh_bt_register_settings' );
}

/* ==========================================================================
   DATA FORMAT — stored in post_meta '_mh_bt_linked_products':
   [
       [ 'product_id' => 123, 'multiplier' => 1.0, 'offset' => 1 ],
       [ 'product_id' => 456, 'multiplier' => 0.5, 'offset' => 0 ],
   ]
   Formula: qty = ceil( main_qty × multiplier + offset )
   ========================================================================== */

/* ==========================================================================
   ADMIN: META BOX — Repeater with product search + multiplier + offset
   ========================================================================== */

function mh_bt_add_meta_box() {
    add_meta_box(
        'mh_bt_linked_products',
        __( 'Häufig zusammen gekauft', 'mh-bought-together' ),
        'mh_bt_meta_box_callback',
        'product',
        'normal',
        'default'
    );
}

function mh_bt_meta_box_callback( $post ) {
    wp_nonce_field( 'mh_bt_save_linked', 'mh_bt_nonce' );

    $linked = get_post_meta( $post->ID, '_mh_bt_linked_products', true );
    if ( ! is_array( $linked ) ) {
        $linked = array();
    }
    // Backward compat: convert flat ID array to new format.
    if ( ! empty( $linked ) && isset( $linked[0] ) && ! is_array( $linked[0] ) ) {
        $linked = array_map( function( $id ) {
            return array( 'product_id' => absint( $id ), 'multiplier' => 1, 'offset' => 0, 'reason' => '', 'box_type' => 'primary', 'qty_label' => '', 'checked_default' => '' );
        }, $linked );
    }
    ?>
    <style>
        .mh-bt-admin-table { width:100%; border-collapse:collapse; }
        .mh-bt-admin-table th { text-align:left; padding:8px 6px; font-size:13px; border-bottom:1px solid #ddd; }
        .mh-bt-admin-table td { padding:6px; vertical-align:top; }
        .mh-bt-admin-table .col-product { width:22%; }
        .mh-bt-admin-table .col-box { width:9%; }
        .mh-bt-admin-table .col-checked { width:8%; }
        .mh-bt-admin-table .col-reason { width:19%; }
        .mh-bt-admin-table .col-num { width:8%; }
        .mh-bt-admin-table .col-actions { width:24%; }
        .mh-bt-admin-table input[type="number"] { width:70px; }
        .mh-bt-admin-table input[type="text"].mh-bt-reason-input { width:100%; font-size:12px; }
        .mh-bt-admin-table select.mh-bt-box-select { width:100%; font-size:12px; }
        .mh-bt-admin-table select { width:100%; }
        .mh-bt-row-example { color:#666; font-style:italic; font-size:12px; margin-top:2px; }
        #mh-bt-add-row { margin-top:10px; }
    </style>

    <table class="mh-bt-admin-table" id="mh-bt-repeater">
        <thead>
            <tr>
                <th class="col-product"><?php esc_html_e( 'Produkt', 'mh-bought-together' ); ?></th>
                <th class="col-box"><?php esc_html_e( 'Box', 'mh-bought-together' ); ?></th>
                <th class="col-checked"><?php esc_html_e( 'Aktiv', 'mh-bought-together' ); ?></th>
                <th class="col-reason"><?php esc_html_e( 'Erklärung', 'mh-bought-together' ); ?></th>
                <th class="col-num"><?php esc_html_e( '× Mult.', 'mh-bought-together' ); ?></th>
                <th class="col-num"><?php esc_html_e( '+ Offset', 'mh-bought-together' ); ?></th>
                <th class="col-actions"><?php esc_html_e( 'Mengenhinweis / Aktion', 'mh-bought-together' ); ?></th>
            </tr>
        </thead>
        <tbody id="mh-bt-rows">
            <?php
            $row_index = 0;
            foreach ( $linked as $item ) :
                $pid  = absint( $item['product_id'] ?? 0 );
                $mult = floatval( $item['multiplier'] ?? 1 );
                $off  = intval( $item['offset'] ?? 0 );
                $reason = sanitize_text_field( $item['reason'] ?? '' );
                $box_type = sanitize_text_field( $item['box_type'] ?? ( $item['group'] ?? 'primary' ) );
                $qty_label = sanitize_text_field( $item['qty_label'] ?? '' );
                $checked_default = sanitize_text_field( $item['checked_default'] ?? '' );
                if ( ! in_array( $box_type, array( 'primary', 'optional' ), true ) ) { $box_type = 'primary'; }
                $prod = wc_get_product( $pid );
                if ( ! $prod ) { continue; }
                ?>
                <tr class="mh-bt-row">
                    <td>
                        <select name="mh_bt_linked[<?php echo intval( $row_index ); ?>][product_id]"
                                class="mh-bt-product-search wc-product-search"
                                data-placeholder="<?php esc_attr_e( 'Produkt suchen...', 'mh-bought-together' ); ?>"
                                data-action="woocommerce_json_search_products"
                                data-exclude="<?php echo intval( $post->ID ); ?>">
                            <option value="<?php echo intval( $pid ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $prod->get_formatted_name() ) ); ?></option>
                        </select>
                    </td>
                    <td>
                        <select class="mh-bt-box-select" name="mh_bt_linked[<?php echo intval( $row_index ); ?>][box_type]">
                            <option value="primary" <?php selected( $box_type, 'primary' ); ?>><?php esc_html_e( 'Empfohlen', 'mh-bought-together' ); ?></option>
                            <option value="optional" <?php selected( $box_type, 'optional' ); ?>><?php esc_html_e( 'Optional', 'mh-bought-together' ); ?></option>
                        </select>
                    </td>
                    <td>
                        <select name="mh_bt_linked[<?php echo intval( $row_index ); ?>][checked_default]" style="width:100%; font-size:12px;">
                            <option value="" <?php selected( $checked_default, '' ); ?>><?php esc_html_e( 'Global', 'mh-bought-together' ); ?></option>
                            <option value="1" <?php selected( $checked_default, '1' ); ?>><?php esc_html_e( 'Ja', 'mh-bought-together' ); ?></option>
                            <option value="0" <?php selected( $checked_default, '0' ); ?>><?php esc_html_e( 'Nein', 'mh-bought-together' ); ?></option>
                        </select>
                    </td>
                    <td><input type="text" class="mh-bt-reason-input" name="mh_bt_linked[<?php echo intval( $row_index ); ?>][reason]" value="<?php echo esc_attr( $reason ); ?>" placeholder="<?php esc_attr_e( 'z.B. Schützt das Holz von innen', 'mh-bought-together' ); ?>" /></td>
                    <td><input type="number" name="mh_bt_linked[<?php echo intval( $row_index ); ?>][multiplier]" value="<?php echo esc_attr( $mult ); ?>" step="0.1" min="0" /></td>
                    <td><input type="number" name="mh_bt_linked[<?php echo intval( $row_index ); ?>][offset]" value="<?php echo intval( $off ); ?>" step="1" min="0" /></td>
                    <td>
                        <input type="text" class="mh-bt-qty-label-input" name="mh_bt_linked[<?php echo intval( $row_index ); ?>][qty_label]" value="<?php echo esc_attr( $qty_label ); ?>" placeholder="<?php esc_attr_e( 'z.B. 1 Dose reicht für 3 Felder', 'mh-bought-together' ); ?>" style="width:100%; font-size:12px; margin-bottom:3px;" />
                        <div class="mh-bt-row-example"><?php printf( 'Auto: 2 Stk → %s Stk', intval( ceil( 2 * $mult + $off ) ) ); ?></div>
                        <button type="button" class="button button-small mh-bt-remove-row"><?php esc_html_e( 'Entfernen', 'mh-bought-together' ); ?></button>
                    </td>
                </tr>
                <?php
                $row_index++;
            endforeach;
            ?>
        </tbody>
    </table>

    <button type="button" class="button" id="mh-bt-add-row">+ <?php esc_html_e( 'Produkt hinzufügen', 'mh-bought-together' ); ?></button>

    <hr style="margin:16px 0 12px;" />
    <h4 style="margin:0 0 6px;"><?php esc_html_e( 'Bundle-Rabatt (pro Produkt)', 'mh-bought-together' ); ?></h4>
    <?php
    $product_discount = get_post_meta( $post->ID, '_mh_bt_bundle_discount', true );
    $product_min_items = get_post_meta( $post->ID, '_mh_bt_discount_min_items', true );
    $global_opts = mh_bt_get_options();
    ?>
    <table class="form-table" style="margin:0;">
        <tr>
            <td style="padding:4px 6px;">
                <label>
                    <input type="number" name="mh_bt_product_discount" value="<?php echo esc_attr( $product_discount ); ?>" min="0" max="50" step="1" style="width:80px;" placeholder="<?php echo intval( $global_opts['bundle_discount'] ); ?>" />
                    <?php esc_html_e( '% Bundle-Rabatt', 'mh-bought-together' ); ?>
                </label>
                <p class="description"><?php printf( esc_html__( 'Leer = globaler Fallback (%d%%). 0 = kein Rabatt für dieses Produkt.', 'mh-bought-together' ), intval( $global_opts['bundle_discount'] ) ); ?></p>
            </td>
            <td style="padding:4px 6px;">
                <label>
                    <input type="number" name="mh_bt_product_min_items" value="<?php echo esc_attr( $product_min_items ); ?>" min="2" max="10" step="1" style="width:80px;" placeholder="<?php echo intval( $global_opts['discount_min_items'] ); ?>" />
                    <?php esc_html_e( 'Mindestanzahl Produkte', 'mh-bought-together' ); ?>
                </label>
                <p class="description"><?php printf( esc_html__( 'Leer = globaler Fallback (%d).', 'mh-bought-together' ), intval( $global_opts['discount_min_items'] ) ); ?></p>
            </td>
        </tr>
    </table>

    <hr style="margin:16px 0 12px;" />
    <h4 style="margin:0 0 6px;"><?php esc_html_e( 'Social Proof', 'mh-bought-together' ); ?></h4>
    <?php
    $purchase_count = absint( get_post_meta( $post->ID, '_mh_bt_purchase_count', true ) );
    $global_opts_sp = mh_bt_get_options();
    $sp_mode = $global_opts_sp['purchase_count_mode'] ?? 'manual';
    if ( 'auto' === $sp_mode ) :
        $total_sales = absint( get_post_meta( $post->ID, 'total_sales', true ) );
    ?>
        <div style="background:#f0faf4; border:1px solid #c3e6cb; border-radius:4px; padding:10px 14px; margin-bottom:8px;">
            <strong style="color:#1a7a42;">&#x2713; <?php esc_html_e( 'Automatischer Modus aktiv', 'mh-bought-together' ); ?></strong><br>
            <span style="font-size:12px; color:#555;">
                <?php printf(
                    esc_html__( 'Die Verkaufszahl des Hauptproduktes wird automatisch verwendet. Aktuell: %d Verkäufe.', 'mh-bought-together' ),
                    intval( $total_sales )
                ); ?>
            </span>
        </div>
        <input type="hidden" name="mh_bt_purchase_count" value="<?php echo intval( $purchase_count ); ?>" />
        <p class="description"><?php
            printf(
                wp_kses(
                    __( 'Modus ändern unter <a href="%s">WooCommerce → Zusammen gekauft → Einstellungen</a>.', 'mh-bought-together' ),
                    array( 'a' => array( 'href' => array() ) )
                ),
                esc_url( admin_url( 'admin.php?page=mh-bought-together' ) )
            );
        ?></p>
    <?php else : ?>
        <label>
            <input type="number" name="mh_bt_purchase_count" value="<?php echo intval( $purchase_count ); ?>" min="0" step="1" style="width:80px;" />
            <?php esc_html_e( 'Kunden kauften diese Kombination', 'mh-bought-together' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Wird unter der Überschrift angezeigt. 0 = ausgeblendet. Manuell pflegen.', 'mh-bought-together' ); ?></p>
    <?php endif; ?>

    <hr style="margin:16px 0 12px;" />
    <h4 style="margin:0 0 6px;"><?php esc_html_e( 'Anzeige-Optionen', 'mh-bought-together' ); ?></h4>
    <?php $show_stock = get_post_meta( $post->ID, '_mh_bt_show_stock', true ); if ( '' === $show_stock ) { $show_stock = '1'; } ?>
    <label>
        <input type="checkbox" name="mh_bt_show_stock" value="1" <?php checked( $show_stock, '1' ); ?> />
        <?php esc_html_e( 'Lagerstatus anzeigen', 'mh-bought-together' ); ?>
    </label>
    <p class="description"><?php esc_html_e( 'Zeigt "Auf Lager", "Nur noch X auf Lager" oder "In Produktion" bei jedem Produkt im Widget.', 'mh-bought-together' ); ?></p>

    <p class="description" style="margin-top:12px;">
        <strong><?php esc_html_e( 'Formel:', 'mh-bought-together' ); ?></strong>
        <?php esc_html_e( 'Menge = aufrunden( Hauptprodukt-Menge × Multiplikator + Offset )', 'mh-bought-together' ); ?><br>
        <strong><?php esc_html_e( 'Typische Werte:', 'mh-bought-together' ); ?></strong>
        <?php esc_html_e( 'Zaun→Pfosten: ×1 +1 (3 Felder = 4 Pfosten) · Zaun→Lasur: ×0.5 +0 (4 Felder = 2 Dosen) · Hochbeet→Vlies: ×1 +0 (1:1)', 'mh-bought-together' ); ?><br>
        <strong><?php esc_html_e( 'Box:', 'mh-bought-together' ); ?></strong>
        <?php esc_html_e( 'Empfohlen = primäre Box mit Hauptprodukt, standardmäßig angehakt, mit Bundle-Rabatt. Optional = separate zweite Box für Extras (z.B. Türen, Deko), standardmäßig nicht angehakt.', 'mh-bought-together' ); ?><br>
        <strong><?php esc_html_e( 'Erklärung:', 'mh-bought-together' ); ?></strong>
        <?php esc_html_e( 'Optionaler Kurztext, der dem Kunden erklärt warum dieses Zubehör empfohlen wird. Z.B. "Schützt das Hochbeet von innen vor Feuchtigkeit".', 'mh-bought-together' ); ?><br>
        <strong><?php esc_html_e( 'Mengenhinweis:', 'mh-bought-together' ); ?></strong>
        <?php esc_html_e( 'Optionaler Text, der unter der Mengenberechnung angezeigt wird. Z.B. "1 Dose reicht für 3 Felder". Leer = automatischer Hinweis aus Formel (z.B. "Bei 2 Stk oben → 2 Stk empfohlen"). Komplett leer lassen wenn kein Hinweis gewünscht und Multiplikator 1 / Offset 0 ist.', 'mh-bought-together' ); ?>
    </p>

    <script>
    jQuery(function($) {
        var postId = <?php echo intval( $post->ID ); ?>;
        var rowIdx = <?php echo intval( $row_index ); ?>;

        function initSelect2(el) {
            $(el).filter(':not(.select2-hidden-accessible)').each(function() {
                $(this).selectWoo({
                    allowClear: true,
                    placeholder: $(this).data('placeholder') || '',
                    minimumInputLength: 3,
                    ajax: {
                        url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return { term: params.term, action: 'woocommerce_json_search_products', security: '<?php echo esc_js( wp_create_nonce( 'search-products' ) ); ?>', exclude: [postId] };
                        },
                        processResults: function(data) {
                            var r = [];
                            if (data) $.each(data, function(id, text) { r.push({id:id, text:text}); });
                            return {results: r};
                        },
                        cache: true
                    }
                });
            });
        }

        function updateExample(row) {
            var mRaw = row.find('input[name$="[multiplier]"]').val();
            var m = (mRaw === '' || mRaw === undefined) ? 1 : parseFloat(mRaw);
            if (isNaN(m)) m = 1;
            var o = parseInt(row.find('input[name$="[offset]"]').val()) || 0;
            row.find('.mh-bt-row-example').text('Auto: 2 Stk → ' + Math.ceil(2*m+o) + ' Stk');
        }

        initSelect2('.mh-bt-product-search');

        $('#mh-bt-add-row').on('click', function() {
            var h = '<tr class="mh-bt-row"><td><select name="mh_bt_linked['+rowIdx+'][product_id]" class="mh-bt-product-search wc-product-search" data-placeholder="Produkt suchen..." data-action="woocommerce_json_search_products" data-exclude="'+postId+'"></select></td>' +
                '<td><select class="mh-bt-box-select" name="mh_bt_linked['+rowIdx+'][box_type]"><option value="primary">Empfohlen</option><option value="optional">Optional</option></select></td>' +
                '<td><select name="mh_bt_linked['+rowIdx+'][checked_default]" style="width:100%; font-size:12px;"><option value="">Global</option><option value="1">Ja</option><option value="0">Nein</option></select></td>' +
                '<td><input type="text" class="mh-bt-reason-input" name="mh_bt_linked['+rowIdx+'][reason]" value="" placeholder="z.B. Schützt das Holz von innen" /></td>' +
                '<td><input type="number" name="mh_bt_linked['+rowIdx+'][multiplier]" value="1" step="0.1" min="0" /></td>' +
                '<td><input type="number" name="mh_bt_linked['+rowIdx+'][offset]" value="0" step="1" min="0" /></td>' +
                '<td><input type="text" class="mh-bt-qty-label-input" name="mh_bt_linked['+rowIdx+'][qty_label]" value="" placeholder="z.B. 1 Dose reicht für 3 Felder" style="width:100%; font-size:12px; margin-bottom:3px;" /><div class="mh-bt-row-example">Auto: 2 Stk → 2 Stk</div><button type="button" class="button button-small mh-bt-remove-row">Entfernen</button></td></tr>';
            $('#mh-bt-rows').append(h);
            initSelect2('#mh-bt-rows tr:last-child .mh-bt-product-search');
            rowIdx++;
        });

        $('#mh-bt-repeater').on('click', '.mh-bt-remove-row', function() { $(this).closest('tr').remove(); });
        $('#mh-bt-repeater').on('input change', 'input[type="number"]', function() { updateExample($(this).closest('tr')); });
    });
    </script>
    <?php
}

function mh_bt_save_meta_box( $post_id, $post ) {
    if ( ! isset( $_POST['mh_bt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mh_bt_nonce'] ) ), 'mh_bt_save_linked' ) ) { return; }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_product', $post_id ) ) { return; }

    $linked = array();
    if ( isset( $_POST['mh_bt_linked'] ) && is_array( $_POST['mh_bt_linked'] ) ) {
        foreach ( wp_unslash( $_POST['mh_bt_linked'] ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $pid    = absint( $row['product_id'] ?? 0 );
            $mult   = max( 0, round( floatval( $row['multiplier'] ?? 1 ), 2 ) );
            $off    = max( 0, intval( $row['offset'] ?? 0 ) );
            $reason   = sanitize_text_field( $row['reason'] ?? '' );
            $box_type = in_array( ( $row['box_type'] ?? 'primary' ), array( 'primary', 'optional' ), true ) ? $row['box_type'] : 'primary';
            $qty_label = sanitize_text_field( $row['qty_label'] ?? '' );
            $checked_default = '';
            if ( isset( $row['checked_default'] ) && in_array( $row['checked_default'], array( '0', '1' ), true ) ) {
                $checked_default = $row['checked_default'];
            }
            if ( $pid && $pid !== $post_id ) {
                $linked[] = array( 'product_id' => $pid, 'multiplier' => $mult, 'offset' => $off, 'reason' => $reason, 'box_type' => $box_type, 'qty_label' => $qty_label, 'checked_default' => $checked_default );
            }
        }
    }
    update_post_meta( $post_id, '_mh_bt_linked_products', $linked );

    // Save social proof count.
    if ( isset( $_POST['mh_bt_purchase_count'] ) ) {
        update_post_meta( $post_id, '_mh_bt_purchase_count', absint( $_POST['mh_bt_purchase_count'] ) );
    }

    // Save stock indicator toggle.
    update_post_meta( $post_id, '_mh_bt_show_stock', isset( $_POST['mh_bt_show_stock'] ) ? '1' : '0' );

    // Save per-product bundle discount.
    if ( isset( $_POST['mh_bt_product_discount'] ) && '' !== $_POST['mh_bt_product_discount'] ) {
        update_post_meta( $post_id, '_mh_bt_bundle_discount', min( 50, absint( $_POST['mh_bt_product_discount'] ) ) );
    } else {
        delete_post_meta( $post_id, '_mh_bt_bundle_discount' );
    }
    if ( isset( $_POST['mh_bt_product_min_items'] ) && '' !== $_POST['mh_bt_product_min_items'] ) {
        update_post_meta( $post_id, '_mh_bt_discount_min_items', max( 2, absint( $_POST['mh_bt_product_min_items'] ) ) );
    } else {
        delete_post_meta( $post_id, '_mh_bt_discount_min_items' );
    }
}

function mh_bt_admin_scripts( $hook_suffix ) {
    global $post_type;
    if ( 'product' !== $post_type || ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) { return; }
    wp_enqueue_script( 'wc-enhanced-select' );
    wp_enqueue_style( 'woocommerce_admin_styles' );
}

/* ==========================================================================
   ADMIN: META BOX — "Im Lieferumfang enthalten" (Included Products)
   ========================================================================== */

function mh_ip_add_meta_box() {
    add_meta_box(
        'mh_ip_included_products',
        __( 'Im Lieferumfang enthalten', 'mh-bought-together' ),
        'mh_ip_meta_box_callback',
        'product',
        'normal',
        'default'
    );
}

function mh_ip_meta_box_callback( $post ) {
    wp_nonce_field( 'mh_ip_save_included', 'mh_ip_nonce' );

    $included = get_post_meta( $post->ID, '_mh_bt_included_products', true );
    if ( ! is_array( $included ) ) {
        $included = array();
    }
    ?>
    <style>
        /* Meta-box must not clip select2 dropdowns */
        #mh_ip_included_products .inside { overflow:visible; }
        .mh-ip-admin-table { width:100%; border-collapse:collapse; }
        .mh-ip-admin-table th { text-align:left; padding:8px 6px; font-size:13px; border-bottom:1px solid #ddd; }
        .mh-ip-admin-table td { padding:6px; vertical-align:top; overflow:visible; }
        .mh-ip-admin-table .col-product { width:35%; }
        .mh-ip-admin-table .col-label { width:20%; }
        .mh-ip-admin-table .col-actions { width:10%; }
        .mh-ip-admin-table input[type="text"] { width:100%; font-size:12px; }
        /* Force select2 container to fill column */
        .mh-ip-admin-table .select2-container { width:100% !important; min-width:200px; }
        /* Ensure dropdown renders above other meta boxes */
        .mh-ip-admin-table .select2-container--open { z-index:999999; }
        #mh-ip-add-row { margin-top:10px; }
    </style>

    <p class="description" style="margin-bottom:10px;">
        <?php esc_html_e( 'Produkte die bereits im Lieferumfang dieses Produktes enthalten sind (z.B. Rutsche, Kletterwand, Schaukel bei einem Spielturm). Diese werden dem Kunden als "inklusive" angezeigt — kein Warenkorb, rein informativ.', 'mh-bought-together' ); ?>
    </p>

    <table class="mh-ip-admin-table" id="mh-ip-repeater">
        <thead>
            <tr>
                <th class="col-product"><?php esc_html_e( 'Produkt', 'mh-bought-together' ); ?></th>
                <th class="col-label"><?php esc_html_e( 'Kategorie-Label (optional)', 'mh-bought-together' ); ?></th>
                <th class="col-label"><?php esc_html_e( 'Beschreibung (optional)', 'mh-bought-together' ); ?></th>
                <th class="col-actions"><?php esc_html_e( 'Aktion', 'mh-bought-together' ); ?></th>
            </tr>
        </thead>
        <tbody id="mh-ip-rows">
            <?php
            $ip_row_index = 0;
            foreach ( $included as $item ) :
                $pid   = absint( $item['product_id'] ?? 0 );
                $label = sanitize_text_field( $item['label'] ?? '' );
                $desc  = sanitize_text_field( $item['description'] ?? '' );
                $prod  = wc_get_product( $pid );
                if ( ! $prod ) { continue; }
                ?>
                <tr class="mh-ip-row">
                    <td>
                        <select name="mh_ip_included[<?php echo intval( $ip_row_index ); ?>][product_id]"
                                class="mh-ip-product-search wc-product-search"
                                data-placeholder="<?php esc_attr_e( 'Produkt suchen...', 'mh-bought-together' ); ?>"
                                data-action="woocommerce_json_search_products"
                                data-exclude="<?php echo intval( $post->ID ); ?>">
                            <option value="<?php echo intval( $pid ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $prod->get_formatted_name() ) ); ?></option>
                        </select>
                    </td>
                    <td><input type="text" name="mh_ip_included[<?php echo intval( $ip_row_index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'z.B. Rutsche, Spielzeug', 'mh-bought-together' ); ?>" /></td>
                    <td><input type="text" name="mh_ip_included[<?php echo intval( $ip_row_index ); ?>][description]" value="<?php echo esc_attr( $desc ); ?>" placeholder="<?php esc_attr_e( 'z.B. 300cm, gelb', 'mh-bought-together' ); ?>" /></td>
                    <td><button type="button" class="button button-small mh-ip-remove-row"><?php esc_html_e( 'Entfernen', 'mh-bought-together' ); ?></button></td>
                </tr>
                <?php
                $ip_row_index++;
            endforeach;
            ?>
        </tbody>
    </table>

    <button type="button" class="button" id="mh-ip-add-row">+ <?php esc_html_e( 'Enthaltenes Produkt hinzufügen', 'mh-bought-together' ); ?></button>

    <script>
    jQuery(function($) {
        var postId = <?php echo intval( $post->ID ); ?>;
        var ipRowIdx = <?php echo intval( $ip_row_index ); ?>;

        function initIpSelect2(el) {
            $(el).filter(':not(.select2-hidden-accessible)').each(function() {
                $(this).selectWoo({
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).data('placeholder') || '',
                    minimumInputLength: 3,
                    dropdownParent: $('body'),
                    ajax: {
                        url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return { term: params.term, action: 'woocommerce_json_search_products', security: '<?php echo esc_js( wp_create_nonce( 'search-products' ) ); ?>', exclude: [postId] };
                        },
                        processResults: function(data) {
                            var r = [];
                            if (data) $.each(data, function(id, text) { r.push({id:id, text:text}); });
                            return {results: r};
                        },
                        cache: true
                    }
                });
            });
        }

        initIpSelect2('.mh-ip-product-search');

        $('#mh-ip-add-row').on('click', function() {
            var h = '<tr class="mh-ip-row"><td><select name="mh_ip_included['+ipRowIdx+'][product_id]" class="mh-ip-product-search wc-product-search" data-placeholder="Produkt suchen..." data-action="woocommerce_json_search_products" data-exclude="'+postId+'"></select></td>' +
                '<td><input type="text" name="mh_ip_included['+ipRowIdx+'][label]" value="" placeholder="z.B. Rutsche, Spielzeug" /></td>' +
                '<td><input type="text" name="mh_ip_included['+ipRowIdx+'][description]" value="" placeholder="z.B. 300cm, gelb" /></td>' +
                '<td><button type="button" class="button button-small mh-ip-remove-row">Entfernen</button></td></tr>';
            $('#mh-ip-rows').append(h);
            initIpSelect2('#mh-ip-rows tr:last-child .mh-ip-product-search');
            ipRowIdx++;
        });

        $('#mh-ip-repeater').on('click', '.mh-ip-remove-row', function() { $(this).closest('tr').remove(); });
    });
    </script>
    <?php
}

function mh_ip_save_meta_box( $post_id, $post ) {
    if ( ! isset( $_POST['mh_ip_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mh_ip_nonce'] ) ), 'mh_ip_save_included' ) ) { return; }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_product', $post_id ) ) { return; }

    $included = array();
    if ( isset( $_POST['mh_ip_included'] ) && is_array( $_POST['mh_ip_included'] ) ) {
        foreach ( wp_unslash( $_POST['mh_ip_included'] ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $pid   = absint( $row['product_id'] ?? 0 );
            $label = sanitize_text_field( $row['label'] ?? '' );
            $desc  = sanitize_text_field( $row['description'] ?? '' );
            if ( $pid && $pid !== $post_id ) {
                $included[] = array( 'product_id' => $pid, 'label' => $label, 'description' => $desc );
            }
        }
    }
    update_post_meta( $post_id, '_mh_bt_included_products', $included );
}

/* ==========================================================================
   SETTINGS PAGE
   ========================================================================== */

function mh_bt_add_settings_page() {
    add_submenu_page( 'woocommerce', __( 'Häufig zusammen gekauft', 'mh-bought-together' ), __( 'Zusammen gekauft', 'mh-bought-together' ), 'manage_woocommerce', 'mh-bought-together', 'mh_bt_settings_page_html' );
    add_submenu_page( 'woocommerce', __( 'Bundle-Statistiken', 'mh-bought-together' ), __( 'Bundle-Statistiken', 'mh-bought-together' ), 'manage_woocommerce', 'mh-bt-stats', 'mh_bt_stats_page_html' );
}
function mh_bt_register_settings() {
    register_setting( 'mh_bt_settings', 'mh_bt_options', array( 'type' => 'array', 'sanitize_callback' => 'mh_bt_sanitize_options', 'default' => mh_bt_default_options() ) );
    add_settings_section( 'mh_bt_general', __( 'Allgemeine Einstellungen', 'mh-bought-together' ), '__return_null', 'mh-bought-together' );
    add_settings_field( 'heading', __( 'Widget-Überschrift (Empfohlen)', 'mh-bought-together' ), 'mh_bt_field_heading', 'mh-bought-together', 'mh_bt_general' );
    add_settings_field( 'heading_optional', __( 'Widget-Überschrift (Optional)', 'mh-bought-together' ), 'mh_bt_field_heading_optional', 'mh-bought-together', 'mh_bt_general' );
    add_settings_field( 'discount', __( 'Bundle-Rabatt (%)', 'mh-bought-together' ), 'mh_bt_field_discount', 'mh-bought-together', 'mh_bt_general' );
    add_settings_field( 'min_items', __( 'Mindestanzahl für Rabatt', 'mh-bought-together' ), 'mh_bt_field_min_items', 'mh-bought-together', 'mh_bt_general' );
    add_settings_field( 'max', __( 'Max. angezeigte Produkte', 'mh-bought-together' ), 'mh_bt_field_max_products', 'mh-bought-together', 'mh_bt_general' );
    add_settings_field( 'placement', __( 'Automatische Platzierung', 'mh-bought-together' ), 'mh_bt_field_auto_placement', 'mh-bought-together', 'mh_bt_general' );
    add_settings_field( 'default_checked', __( 'Produkte vorausgewählt', 'mh-bought-together' ), 'mh_bt_field_default_checked', 'mh-bought-together', 'mh_bt_general' );
    add_settings_field( 'purchase_count_mode', __( 'Social Proof Modus', 'mh-bought-together' ), 'mh_bt_field_purchase_count_mode', 'mh-bought-together', 'mh_bt_general' );

    // Included Products settings.
    add_settings_section( 'mh_ip_section', __( 'Lieferumfang-Widget', 'mh-bought-together' ), 'mh_ip_section_description', 'mh-bought-together' );
    add_settings_field( 'ip_heading', __( 'Widget-Überschrift', 'mh-bought-together' ), 'mh_ip_field_heading', 'mh-bought-together', 'mh_ip_section' );
    add_settings_field( 'ip_display_mode', __( 'Anzeigemodus', 'mh-bought-together' ), 'mh_ip_field_display_mode', 'mh-bought-together', 'mh_ip_section' );
    add_settings_field( 'ip_collapse_after', __( 'Aufklappen nach X Produkten', 'mh-bought-together' ), 'mh_ip_field_collapse_after', 'mh-bought-together', 'mh_ip_section' );
    add_settings_field( 'ip_show_prices', __( 'Einzelpreise anzeigen', 'mh-bought-together' ), 'mh_ip_field_show_prices', 'mh-bought-together', 'mh_ip_section' );
    add_settings_field( 'ip_show_total', __( 'Inklusiv-Wert anzeigen', 'mh-bought-together' ), 'mh_ip_field_show_total', 'mh-bought-together', 'mh_ip_section' );
}
function mh_bt_default_options() { return array( 'heading' => 'Wird oft zusammen gekauft', 'heading_optional' => 'Passend dazu', 'bundle_discount' => 0, 'discount_min_items' => 2, 'max_products' => 6, 'auto_placement' => 0, 'default_checked' => 1, 'purchase_count_mode' => 'manual', 'ip_heading' => 'Im Lieferumfang enthalten', 'ip_display_mode' => 'collapsible', 'ip_collapse_after' => 3, 'ip_show_prices' => 1, 'ip_show_total' => 1 ); }
function mh_bt_sanitize_options( $i ) {
    $d = mh_bt_default_options();
    return array(
        'heading' => sanitize_text_field( $i['heading'] ?? $d['heading'] ),
        'heading_optional' => sanitize_text_field( $i['heading_optional'] ?? $d['heading_optional'] ),
        'bundle_discount' => min( 50, absint( $i['bundle_discount'] ?? $d['bundle_discount'] ) ),
        'discount_min_items' => max( 2, absint( $i['discount_min_items'] ?? $d['discount_min_items'] ) ),
        'max_products' => max( 1, min( 10, absint( $i['max_products'] ?? $d['max_products'] ) ) ),
        'auto_placement' => absint( $i['auto_placement'] ?? $d['auto_placement'] ) ? 1 : 0,
        'default_checked' => absint( $i['default_checked'] ?? $d['default_checked'] ) ? 1 : 0,
        'purchase_count_mode' => in_array( ( $i['purchase_count_mode'] ?? 'manual' ), array( 'manual', 'auto' ), true ) ? $i['purchase_count_mode'] : 'manual',
        'ip_heading' => sanitize_text_field( $i['ip_heading'] ?? $d['ip_heading'] ),
        'ip_display_mode' => in_array( ( $i['ip_display_mode'] ?? 'collapsible' ), array( 'collapsible', 'expanded' ), true ) ? $i['ip_display_mode'] : 'collapsible',
        'ip_collapse_after' => max( 1, min( 20, absint( $i['ip_collapse_after'] ?? $d['ip_collapse_after'] ) ) ),
        'ip_show_prices' => absint( $i['ip_show_prices'] ?? $d['ip_show_prices'] ) ? 1 : 0,
        'ip_show_total' => absint( $i['ip_show_total'] ?? $d['ip_show_total'] ) ? 1 : 0,
    );
}
function mh_bt_get_options() {
    static $cached = null;
    if ( null === $cached ) {
        $cached = wp_parse_args( get_option( 'mh_bt_options', array() ), mh_bt_default_options() );
    }
    return $cached;
}
function mh_bt_field_heading() { $o = mh_bt_get_options(); printf( '<input type="text" name="mh_bt_options[heading]" value="%s" class="regular-text" />', esc_attr( $o['heading'] ) ); }
function mh_bt_field_heading_optional() { $o = mh_bt_get_options(); printf( '<input type="text" name="mh_bt_options[heading_optional]" value="%s" class="regular-text" />', esc_attr( $o['heading_optional'] ) ); echo '<p class="description">' . esc_html__( 'Überschrift der zweiten Box für optionales Zubehör (z.B. Türen). Wird nur angezeigt wenn es optionale Produkte gibt.', 'mh-bought-together' ) . '</p>'; }
function mh_bt_field_discount() { $o = mh_bt_get_options(); printf( '<input type="number" name="mh_bt_options[bundle_discount]" value="%d" min="0" max="50" class="small-text" /> %%', intval( $o['bundle_discount'] ) ); echo '<p class="description">' . esc_html__( 'Globaler Fallback. Kann pro Produkt in der Produkt-Meta-Box überschrieben werden. 0 = kein Rabatt.', 'mh-bought-together' ) . '</p>'; }
function mh_bt_field_min_items() { $o = mh_bt_get_options(); printf( '<input type="number" name="mh_bt_options[discount_min_items]" value="%d" min="2" max="10" class="small-text" />', intval( $o['discount_min_items'] ) ); echo '<p class="description">' . esc_html__( 'Globaler Fallback. Kann pro Produkt überschrieben werden.', 'mh-bought-together' ) . '</p>'; }
function mh_bt_field_max_products() { $o = mh_bt_get_options(); printf( '<input type="number" name="mh_bt_options[max_products]" value="%d" min="1" max="10" class="small-text" />', intval( $o['max_products'] ) ); }
function mh_bt_field_auto_placement() {
    $o = mh_bt_get_options();
    printf( '<label><input type="checkbox" name="mh_bt_options[auto_placement]" value="1" %s /> %s</label>',
        checked( $o['auto_placement'], 1, false ),
        esc_html__( 'Widget automatisch auf Produktseiten anzeigen', 'mh-bought-together' )
    );
    echo '<p class="description">' . esc_html__( 'Deaktivieren für Oxygen Builder — dann den Shortcode [mh_bought_together] manuell platzieren.', 'mh-bought-together' ) . '</p>';
}
function mh_bt_field_default_checked() {
    $o = mh_bt_get_options();
    printf( '<label><input type="checkbox" name="mh_bt_options[default_checked]" value="1" %s /> %s</label>',
        checked( $o['default_checked'], 1, false ),
        esc_html__( 'Zusätzliche Produkte sind standardmäßig ausgewählt', 'mh-bought-together' )
    );
    echo '<p class="description">' . esc_html__( 'Wenn aktiviert, sind alle empfohlenen Produkte im Widget bereits angehakt. Wenn deaktiviert, muss der Kunde sie manuell auswählen.', 'mh-bought-together' ) . '</p>';
}
function mh_bt_field_purchase_count_mode() {
    $o = mh_bt_get_options();
    echo '<fieldset>';
    printf( '<label><input type="radio" name="mh_bt_options[purchase_count_mode]" value="manual" %s /> %s</label><br>',
        checked( $o['purchase_count_mode'], 'manual', false ),
        esc_html__( 'Manuell — Zahl pro Produkt im Metabox eingeben', 'mh-bought-together' )
    );
    printf( '<label><input type="radio" name="mh_bt_options[purchase_count_mode]" value="auto" %s /> %s</label>',
        checked( $o['purchase_count_mode'], 'auto', false ),
        esc_html__( 'Automatisch — Verkaufszahl des Hauptproduktes (WooCommerce total_sales) verwenden', 'mh-bought-together' )
    );
    echo '<p class="description">' . esc_html__( 'Im Modus "Automatisch" wird die tatsächliche Verkaufszahl des Hauptproduktes als Social-Proof-Zähler angezeigt. Das manuelle Feld im Produkt-Editor wird dann ignoriert.', 'mh-bought-together' ) . '</p>';
    echo '</fieldset>';
}
function mh_bt_settings_page_html() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
    echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1><form action="options.php" method="post">';
    settings_fields( 'mh_bt_settings' ); do_settings_sections( 'mh-bought-together' ); submit_button();
    echo '</form></div>';
}

/* --- Included Products Settings Fields --- */

function mh_ip_section_description() {
    echo '<p>' . esc_html__( 'Einstellungen für das "Im Lieferumfang enthalten"-Widget. Zeigt dem Kunden welche Produkte bereits im Hauptprodukt inklusive sind.', 'mh-bought-together' ) . '</p>';
}
function mh_ip_field_heading() {
    $o = mh_bt_get_options();
    printf( '<input type="text" name="mh_bt_options[ip_heading]" value="%s" class="regular-text" />', esc_attr( $o['ip_heading'] ) );
}
function mh_ip_field_display_mode() {
    $o = mh_bt_get_options();
    echo '<fieldset>';
    printf( '<label><input type="radio" name="mh_bt_options[ip_display_mode]" value="collapsible" %s /> %s</label><br>',
        checked( $o['ip_display_mode'], 'collapsible', false ),
        esc_html__( 'Aufklappbar — zeigt erst X Produkte, Rest per Klick', 'mh-bought-together' )
    );
    printf( '<label><input type="radio" name="mh_bt_options[ip_display_mode]" value="expanded" %s /> %s</label>',
        checked( $o['ip_display_mode'], 'expanded', false ),
        esc_html__( 'Immer aufgeklappt — alle Produkte sofort sichtbar', 'mh-bought-together' )
    );
    echo '</fieldset>';
}
function mh_ip_field_collapse_after() {
    $o = mh_bt_get_options();
    printf( '<input type="number" name="mh_bt_options[ip_collapse_after]" value="%d" min="1" max="20" class="small-text" />', intval( $o['ip_collapse_after'] ) );
    echo '<p class="description">' . esc_html__( 'Nur relevant im Modus "Aufklappbar". Nach dieser Anzahl wird der Rest eingeklappt.', 'mh-bought-together' ) . '</p>';
}
function mh_ip_field_show_prices() {
    $o = mh_bt_get_options();
    printf( '<label><input type="checkbox" name="mh_bt_options[ip_show_prices]" value="1" %s /> %s</label>',
        checked( $o['ip_show_prices'], 1, false ),
        esc_html__( 'Einzelpreise der enthaltenen Produkte anzeigen', 'mh-bought-together' )
    );
    echo '<p class="description">' . esc_html__( 'Zeigt "Einzelwert: XX €" bei jedem Produkt. Stärkt den wahrgenommenen Wert.', 'mh-bought-together' ) . '</p>';
}
function mh_ip_field_show_total() {
    $o = mh_bt_get_options();
    printf( '<label><input type="checkbox" name="mh_bt_options[ip_show_total]" value="1" %s /> %s</label>',
        checked( $o['ip_show_total'], 1, false ),
        esc_html__( 'Inklusiv-Wert-Summe unten anzeigen', 'mh-bought-together' )
    );
    echo '<p class="description">' . esc_html__( 'Zeigt die Summe aller enthaltenen Einzelpreise als "Inklusiv-Wert gesamt: XXX €".', 'mh-bought-together' ) . '</p>';
}

/* ==========================================================================
   FRONTEND: DISPLAY WIDGET
   ========================================================================== */

/**
 * Get effective bundle discount settings for a product.
 * Per-product overrides global. Empty per-product = use global fallback.
 */
function mh_bt_get_product_discount( $product_id ) {
    $options = mh_bt_get_options();

    $discount  = get_post_meta( $product_id, '_mh_bt_bundle_discount', true );
    $min_items = get_post_meta( $product_id, '_mh_bt_discount_min_items', true );

    return array(
        'discount'  => ( '' !== $discount ) ? intval( $discount ) : intval( $options['bundle_discount'] ),
        'min_items' => ( '' !== $min_items ) ? intval( $min_items ) : intval( $options['discount_min_items'] ),
    );
}

function mh_bt_display_widget_once() {
    global $mh_bt_widget_rendered;
    if ( ! empty( $mh_bt_widget_rendered ) ) { return; }
    $mh_bt_widget_rendered = true;
    mh_bt_display_widget();
}

function mh_bt_display_widget() {
    global $product;
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return; }

    $linked_data = get_post_meta( $product->get_id(), '_mh_bt_linked_products', true );
    if ( ! is_array( $linked_data ) || empty( $linked_data ) ) { return; }

    // Backward compat: flat ID array → new format.
    if ( isset( $linked_data[0] ) && ! is_array( $linked_data[0] ) ) {
        $linked_data = array_map( function( $id ) {
            return array( 'product_id' => absint( $id ), 'multiplier' => 1, 'offset' => 0, 'reason' => '', 'box_type' => 'primary', 'qty_label' => '', 'checked_default' => '' );
        }, $linked_data );
    }

    $options      = mh_bt_get_options();
    $max_products = intval( $options['max_products'] );

    // Split into primary + optional.
    $primary_products  = array();
    $optional_products = array();
    foreach ( $linked_data as $item ) {
        if ( count( $primary_products ) + count( $optional_products ) >= $max_products ) { break; }
        $pid      = absint( $item['product_id'] ?? 0 );
        $mult     = floatval( $item['multiplier'] ?? 1 );
        $off      = intval( $item['offset'] ?? 0 );
        $reason   = sanitize_text_field( $item['reason'] ?? '' );
        $box_type = sanitize_text_field( $item['box_type'] ?? ( $item['group'] ?? 'primary' ) );
        $qty_label = sanitize_text_field( $item['qty_label'] ?? '' );
        if ( ! in_array( $box_type, array( 'primary', 'optional' ), true ) ) { $box_type = 'primary'; }
        $lp       = wc_get_product( $pid );
        if ( ! $lp || 'publish' !== get_post_status( $pid ) || ! $lp->is_purchasable() || ! $lp->is_in_stock() || 'simple' !== $lp->get_type() ) { continue; }
        $entry = array( 'product' => $lp, 'multiplier' => $mult, 'offset' => $off, 'reason' => $reason, 'qty_label' => $qty_label, 'checked_default' => sanitize_text_field( $item['checked_default'] ?? '' ) );
        if ( 'optional' === $box_type ) {
            $optional_products[] = $entry;
        } else {
            $primary_products[] = $entry;
        }
    }
    if ( empty( $primary_products ) && empty( $optional_products ) ) { return; }

    $disc_settings = mh_bt_get_product_discount( $product->get_id() );
    $discount      = intval( $disc_settings['discount'] );
    $min_items     = intval( $disc_settings['min_items'] );
    $main_product  = $product;
    // Social Proof: manual or auto mode.
    if ( 'auto' === $options['purchase_count_mode'] ) {
        $purchase_count = absint( $product->get_total_sales() );
    } else {
        $purchase_count = absint( get_post_meta( $product->get_id(), '_mh_bt_purchase_count', true ) );
    }
    $default_checked = ! empty( $options['default_checked'] );
    $show_stock_raw = get_post_meta( $product->get_id(), '_mh_bt_show_stock', true );
    $show_stock     = ( '' === $show_stock_raw ) ? true : ( '1' === $show_stock_raw );

    // === PRIMARY BOX ===
    if ( ! empty( $primary_products ) ) {
        $heading = $options['heading'];
        ?>
        <div class="mh-bt-widget" data-discount="<?php echo intval( $discount ); ?>" data-min-items="<?php echo intval( $min_items ); ?>" role="group" aria-label="<?php esc_attr_e( 'Häufig zusammen gekauft – Bundle', 'mh-bought-together' ); ?>">
            <h2 class="mh-bt-heading"><?php echo esc_html( $heading ); ?> <span class="mh-bt-heading__count"></span></h2>
            <?php if ( $purchase_count > 0 ) : ?>
                <div class="mh-bt-social-proof"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><?php printf( esc_html__( '%d Kunden kauften diese Kombination', 'mh-bought-together' ), intval( $purchase_count ) ); ?></div>
            <?php endif; ?>
            <div class="mh-bt-qty-sync-hint">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <span><?php esc_html_e( 'Mengen passen sich automatisch an, wenn Sie die Stückzahl oben ändern.', 'mh-bought-together' ); ?></span>
            </div>
            <?php if ( $discount > 0 ) : ?>
                <div class="mh-bt-bundle-progress" style="display:none;" aria-live="polite">
                    <div class="mh-bt-bundle-progress__track"><div class="mh-bt-bundle-progress__fill"></div><div class="mh-bt-bundle-progress__milestone"><svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="2.5 6 5 8.5 9.5 4"/></svg></div></div>
                    <div class="mh-bt-bundle-progress__info"><div class="mh-bt-bundle-progress__text"></div><div class="mh-bt-bundle-progress__badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span></span></div></div>
                </div>
            <?php endif; ?>
            <div class="mh-bt-products">
                <div class="mh-bt-product mh-bt-product--main">
                    <div class="mh-bt-product__label">
                        <input type="hidden" class="mh-bt-checkbox"
                               data-product-id="<?php echo intval( $main_product->get_id() ); ?>"
                               data-price="<?php echo esc_attr( wc_get_price_to_display( $main_product ) ); ?>"
                               data-regular-price="<?php
                                   $rp = $main_product->get_regular_price();
                                   echo esc_attr( $rp ? wc_get_price_to_display( $main_product, array( 'price' => $rp ) ) : wc_get_price_to_display( $main_product ) );
                               ?>"
                               data-multiplier="1" data-offset="0" value="1" />
                        <span class="mh-bt-main-pin" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                        <span class="mh-bt-product__image"><?php echo wp_kses_post( $main_product->get_image( 'woocommerce_gallery_thumbnail' ) ); ?></span>
                        <span class="mh-bt-product__info">
                            <span class="mh-bt-product__name">
                                <?php echo esc_html( $main_product->get_name() ); ?>
                            </span>
                            <span class="mh-bt-product__price"><?php echo wp_kses_post( $main_product->get_price_html() ); ?></span>
                            <?php if ( $show_stock ) { mh_bt_render_stock_indicator( $main_product ); } ?>
                        </span>
                        <span class="mh-bt-product__qty-calc" data-is-main="1"></span>
                    </div>
                </div>
                <?php
                $lp_index = 0;
                $lp_total = count( $primary_products );
                foreach ( $primary_products as $lp_data ) :
                    $lp = $lp_data['product']; $mult = $lp_data['multiplier']; $off = $lp_data['offset']; $reason = $lp_data['reason'] ?? ''; $qty_label = $lp_data['qty_label'] ?? '';
                    $item_checked = ( '' !== ( $lp_data['checked_default'] ?? '' ) ) ? ( '1' === $lp_data['checked_default'] ) : $default_checked;
                    // After first linked product, open collapsible wrapper if there are 2+ linked
                    if ( 1 === $lp_index && $lp_total > 1 ) : ?>
                        <div class="mh-bt-collapsible" style="display:none;">
                    <?php endif; ?>
                    <div class="mh-bt-plus-separator"><span class="mh-bt-plus-separator__icon">+</span></div>
                    <?php mh_bt_render_product_row( $lp, $mult, $off, $reason, $item_checked, $show_stock, $qty_label );
                    $lp_index++;
                endforeach;
                // Close collapsible wrapper + add toggle button
                if ( $lp_total > 1 ) : ?>
                    </div>
                    <button type="button" class="mh-bt-show-more" onclick="event.stopPropagation();">
                        <svg class="mh-bt-show-more__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        <span class="mh-bt-show-more__text"><?php printf( esc_html__( '%d weitere Produkte anzeigen', 'mh-bought-together' ), $lp_total - 1 ); ?></span>
                    </button>
                <?php endif; ?>
            </div>
            <div class="mh-bt-footer">
                <div class="mh-bt-total" aria-live="polite" aria-atomic="true">
                    <span class="mh-bt-total__label"><?php esc_html_e( 'Gesamtpreis:', 'mh-bought-together' ); ?></span>
                    <span class="mh-bt-total__original" style="display:none;"></span>
                    <span class="mh-bt-total__price"></span>
                    <span class="mh-bt-savings-pill">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        <span class="mh-bt-savings-pill__text"></span>
                    </span>
                </div>
                <button type="button" class="button mh-bt-add-all" data-main-product="<?php echo intval( $main_product->get_id() ); ?>" aria-label="<?php esc_attr_e( 'Alle ausgewählten Produkte in den Warenkorb legen', 'mh-bought-together' ); ?>">
                    <svg class="mh-bt-add-all__icon-cart" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <svg class="mh-bt-add-all__icon-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M20 6L9 17l-5-5"/></svg>
                    <span class="mh-bt-add-all__text"><?php esc_html_e( 'Alle ausgewählten in den Warenkorb', 'mh-bought-together' ); ?></span>
                </button>
                <div class="mh-bt-notice" style="display:none;"></div>
            </div>
        </div>
    <?php
    }

    // === OPTIONAL BOX ===
    if ( ! empty( $optional_products ) ) {
        $heading_opt = $options['heading_optional'];
        ?>
        <div class="mh-bt-widget mh-bt-widget--optional" data-discount="0" data-min-items="99" data-no-main="1" role="group" aria-label="<?php esc_attr_e( 'Passend dazu – optionales Zubehör', 'mh-bought-together' ); ?>">
            <h2 class="mh-bt-heading"><?php echo esc_html( $heading_opt ); ?> <span class="mh-bt-heading__count"></span></h2>
            <div class="mh-bt-products">
                <?php $first_opt = true;
                foreach ( $optional_products as $lp_data ) :
                    $lp = $lp_data['product']; $mult = $lp_data['multiplier']; $off = $lp_data['offset']; $reason = $lp_data['reason'] ?? ''; $qty_label = $lp_data['qty_label'] ?? '';
                    $item_checked = ( '' !== ( $lp_data['checked_default'] ?? '' ) ) ? ( '1' === $lp_data['checked_default'] ) : $default_checked;
                    if ( ! $first_opt ) : ?>
                        <div class="mh-bt-plus-separator"><span class="mh-bt-plus-separator__icon">+</span></div>
                    <?php endif; $first_opt = false;
                    mh_bt_render_product_row( $lp, $mult, $off, $reason, $item_checked, $show_stock, $qty_label ); ?>
                <?php endforeach; ?>
            </div>
            <div class="mh-bt-footer">
                <div class="mh-bt-total">
                    <span class="mh-bt-total__label"><?php esc_html_e( 'Gesamtpreis:', 'mh-bought-together' ); ?></span>
                    <span class="mh-bt-total__price"></span>
                </div>
                <button type="button" class="button mh-bt-add-all" data-main-product="0">
                    <svg class="mh-bt-add-all__icon-cart" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <svg class="mh-bt-add-all__icon-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M20 6L9 17l-5-5"/></svg>
                    <span class="mh-bt-add-all__text"><?php esc_html_e( 'Ausgewählte hinzufügen', 'mh-bought-together' ); ?></span>
                </button>
                <div class="mh-bt-notice" style="display:none;"></div>
            </div>
        </div>
    <?php
    }
}

/**
 * Render a single linked product row (used by both primary and optional boxes).
 */
function mh_bt_render_product_row( $lp, $mult, $off, $reason, $checked = false, $show_stock = true, $qty_label = '' ) {
    ?>
    <div class="mh-bt-product">
        <label class="mh-bt-product__label">
            <input type="checkbox" <?php checked( $checked ); ?> class="mh-bt-checkbox mh-bt-checkbox--linked"
                   name="mh_bt_products[]" value="<?php echo intval( $lp->get_id() ); ?>"
                   data-product-id="<?php echo intval( $lp->get_id() ); ?>"
                   data-price="<?php echo esc_attr( wc_get_price_to_display( $lp ) ); ?>"
                   data-regular-price="<?php
                       $lp_rp = $lp->get_regular_price();
                       echo esc_attr( $lp_rp ? wc_get_price_to_display( $lp, array( 'price' => $lp_rp ) ) : wc_get_price_to_display( $lp ) );
                   ?>"
                   data-multiplier="<?php echo esc_attr( $mult ); ?>"
                   data-offset="<?php echo intval( $off ); ?>"
                   data-qty-label="<?php echo esc_attr( $qty_label ); ?>"
                   aria-label="<?php echo esc_attr( sprintf( __( 'Produkt %s auswählen', 'mh-bought-together' ), $lp->get_name() ) ); ?>" />
            <span class="mh-bt-checkbox-custom"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
            <span class="mh-bt-product__image" data-preview-img="<?php echo esc_url( wp_get_attachment_image_url( $lp->get_image_id(), 'woocommerce_single' ) ?: '' ); ?>" data-preview-name="<?php echo esc_attr( $lp->get_name() ); ?>">
                <a href="<?php echo esc_url( $lp->get_permalink() ); ?>" target="_blank" rel="noopener" onclick="event.stopPropagation();">
                    <?php echo wp_kses_post( $lp->get_image( 'woocommerce_gallery_thumbnail' ) ); ?>
                    <span class="mh-bt-image-hint"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></span>
                </a>
            </span>
            <span class="mh-bt-product__info">
                <span class="mh-bt-product__name">
                    <a href="<?php echo esc_url( $lp->get_permalink() ); ?>" target="_blank" rel="noopener" onclick="event.stopPropagation();">
                        <?php echo esc_html( $lp->get_name() ); ?>
                    </a>
                </span>
                <?php if ( ! empty( $reason ) ) : ?>
                    <span class="mh-bt-product__reason"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg><?php echo esc_html( $reason ); ?></span>
                <?php endif; ?>
                <span class="mh-bt-product__price"><?php echo wp_kses_post( $lp->get_price_html() ); ?></span>
                <span class="mh-bt-product__qty-explain"></span>
                <?php if ( $show_stock ) { mh_bt_render_stock_indicator( $lp ); } ?>
            </span>
            <span class="mh-bt-product__qty-calc"></span>
        </label>
    </div>
    <?php
}

/* ==========================================================================
   FRONTEND: "IM LIEFERUMFANG ENTHALTEN" WIDGET
   ========================================================================== */

function mh_ip_display_widget_once() {
    global $mh_ip_widget_rendered;
    if ( ! empty( $mh_ip_widget_rendered ) ) { return; }
    $mh_ip_widget_rendered = true;
    mh_ip_display_widget();
}

function mh_ip_display_widget() {
    global $product;
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) { return; }

    $included_data = get_post_meta( $product->get_id(), '_mh_bt_included_products', true );
    if ( ! is_array( $included_data ) || empty( $included_data ) ) { return; }

    $options        = mh_bt_get_options();
    $heading        = $options['ip_heading'];
    $display_mode   = $options['ip_display_mode'];
    $collapse_after = intval( $options['ip_collapse_after'] );
    $show_prices    = ! empty( $options['ip_show_prices'] );
    $show_total     = ! empty( $options['ip_show_total'] );
    $is_collapsible = ( 'collapsible' === $display_mode );

    // Build valid product list.
    $products = array();
    foreach ( $included_data as $item ) {
        $pid  = absint( $item['product_id'] ?? 0 );
        $lp   = wc_get_product( $pid );
        if ( ! $lp || 'publish' !== get_post_status( $pid ) ) { continue; }
        $products[] = array(
            'product'     => $lp,
            'label'       => sanitize_text_field( $item['label'] ?? '' ),
            'description' => sanitize_text_field( $item['description'] ?? '' ),
        );
    }
    if ( empty( $products ) ) { return; }

    $total_count = count( $products );
    $total_value = 0;
    foreach ( $products as $p ) {
        $total_value += floatval( wc_get_price_to_display( $p['product'] ) );
    }

    // Ensure styles are loaded.
    mh_bt_frontend_scripts_force();
    ?>
    <div class="mh-ip-widget" role="complementary" aria-label="<?php esc_attr_e( 'Im Lieferumfang enthalten', 'mh-bought-together' ); ?>">
        <h2 class="mh-ip-heading"><?php echo esc_html( $heading ); ?> <span class="mh-ip-heading__count"><?php echo intval( $total_count ); ?> <?php echo esc_html( _n( 'Produkt inklusive', 'Produkte inklusive', $total_count, 'mh-bought-together' ) ); ?></span></h2>

        <div class="mh-ip-products">
            <?php
            $index = 0;
            $needs_collapse = $is_collapsible && $total_count > $collapse_after;

            foreach ( $products as $p_data ) :
                $lp    = $p_data['product'];
                $label = $p_data['label'];
                $desc  = $p_data['description'];

                // Open collapsible wrapper after visible items.
                if ( $needs_collapse && $index === $collapse_after ) : ?>
                    <div class="mh-ip-collapsible mh-ip-collapsible--hidden">
                <?php endif; ?>

                <div class="mh-ip-product">
                    <span class="mh-ip-product__check"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></span>
                    <span class="mh-ip-product__image">
                        <a href="<?php echo esc_url( $lp->get_permalink() ); ?>" target="_blank" rel="noopener" onclick="event.stopPropagation();">
                            <?php echo wp_kses_post( $lp->get_image( 'woocommerce_gallery_thumbnail' ) ); ?>
                        </a>
                    </span>
                    <span class="mh-ip-product__info">
                        <span class="mh-ip-product__name">
                            <a href="<?php echo esc_url( $lp->get_permalink() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $lp->get_name() ); ?></a>
                        </span>
                        <span class="mh-ip-product__bottom">
                            <?php if ( $show_prices ) : ?>
                                <span class="mh-ip-product__price"><?php printf( esc_html__( 'Einzelwert: %s', 'mh-bought-together' ), wp_kses_post( $lp->get_price_html() ) ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $label ) ) : ?>
                                <?php if ( $show_prices ) : ?><span class="mh-ip-product__sep">·</span><?php endif; ?>
                                <span class="mh-ip-product__label"><?php echo esc_html( $label ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $desc ) ) : ?>
                                <?php if ( $show_prices || ! empty( $label ) ) : ?><span class="mh-ip-product__sep">·</span><?php endif; ?>
                                <span class="mh-ip-product__desc"><?php echo esc_html( $desc ); ?></span>
                            <?php endif; ?>
                        </span>
                    </span>
                </div>

                <?php
                $index++;
            endforeach;

            // Close collapsible wrapper.
            if ( $needs_collapse ) : ?>
                </div>
                <button type="button" class="mh-ip-show-more" aria-expanded="false">
                    <svg class="mh-ip-show-more__icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    <span class="mh-ip-show-more__text"><?php printf( esc_html__( '%d weitere Produkte anzeigen', 'mh-bought-together' ), $total_count - $collapse_after ); ?></span>
                </button>
            <?php endif; ?>
        </div>

        <?php if ( $show_total && $total_value > 0 ) : ?>
            <div class="mh-ip-total">
                <span class="mh-ip-total__label"><?php esc_html_e( 'Inklusiv-Wert gesamt', 'mh-bought-together' ); ?></span>
                <span class="mh-ip-total__price"><?php echo wp_kses_post( wc_price( $total_value ) ); ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Shortcode: [mh_included_products product_id="123"]
 */
function mh_ip_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'product_id' => 0 ), $atts, 'mh_included_products' );
    $product_id = absint( $atts['product_id'] );
    if ( ! $product_id ) {
        global $product;
        if ( $product && is_a( $product, 'WC_Product' ) ) { $product_id = $product->get_id(); }
    }
    if ( ! $product_id ) {
        global $post;
        if ( $post && 'product' === get_post_type( $post ) ) { $product_id = $post->ID; $GLOBALS['product'] = wc_get_product( $product_id ); }
    }
    if ( ! $product_id ) { return ''; }

    $orig = $GLOBALS['product'] ?? null;
    $GLOBALS['product'] = wc_get_product( $product_id );
    if ( ! $GLOBALS['product'] ) { $GLOBALS['product'] = $orig; return ''; }

    mh_bt_frontend_scripts_force();
    global $mh_ip_widget_rendered;
    $mh_ip_widget_rendered = true;

    ob_start();
    mh_ip_display_widget();
    $output = ob_get_clean();
    $GLOBALS['product'] = $orig;
    return $output;
}

/* ==========================================================================
   SHORTCODE & OXYGEN FALLBACK
   ========================================================================== */

function mh_bt_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'product_id' => 0 ), $atts, 'mh_bought_together' );
    $product_id = absint( $atts['product_id'] );
    if ( ! $product_id ) {
        global $product;
        if ( $product && is_a( $product, 'WC_Product' ) ) { $product_id = $product->get_id(); }
    }
    if ( ! $product_id ) {
        global $post;
        if ( $post && 'product' === get_post_type( $post ) ) { $product_id = $post->ID; $GLOBALS['product'] = wc_get_product( $product_id ); }
    }
    if ( ! $product_id ) { return ''; }

    $orig = $GLOBALS['product'] ?? null;
    $GLOBALS['product'] = wc_get_product( $product_id );
    if ( ! $GLOBALS['product'] ) { $GLOBALS['product'] = $orig; return ''; }

    mh_bt_frontend_scripts_force();
    global $mh_bt_widget_rendered;
    $mh_bt_widget_rendered = true;

    ob_start();
    mh_bt_display_widget();
    $output = ob_get_clean();
    $GLOBALS['product'] = $orig;
    return $output;
}

function mh_bt_content_fallback( $content ) {
    if ( is_admin() || ! is_singular( 'product' ) || ! is_main_query() ) { return $content; }
    global $mh_bt_widget_rendered;
    if ( ! empty( $mh_bt_widget_rendered ) ) { return $content; }
    global $post;
    if ( $post && has_shortcode( $post->post_content, 'mh_bought_together' ) ) { return $content; }
    $html = mh_bt_shortcode( array() );
    return empty( $html ) ? $content : $content . $html;
}

/* ==========================================================================
   FRONTEND: SCRIPTS & STYLES
   ========================================================================== */

function mh_bt_frontend_scripts_force() {
    static $loaded = false;
    if ( $loaded ) { return; }
    $loaded = true;

    // v2.9 F1: File-based CSS — enables browser caching.
    $css_file = MH_BT_PATH . 'assets/css/mh-bt-frontend.css';
    if ( file_exists( $css_file ) ) {
        wp_enqueue_style( 'mh-bt-style', MH_BT_URL . 'assets/css/mh-bt-frontend.css', array(), MH_BT_VERSION );
    } else {
        // Fallback to inline if file missing.
        wp_register_style( 'mh-bt-style', false, array(), MH_BT_VERSION );
        wp_enqueue_style( 'mh-bt-style' );
        wp_add_inline_style( 'mh-bt-style', mh_bt_get_frontend_css() );
    }

    // v2.9 F1: File-based JS — enables browser caching + F2 lazy init.
    $js_file = MH_BT_PATH . 'assets/js/mh-bt-frontend.js';
    if ( file_exists( $js_file ) ) {
        wp_enqueue_script( 'mh-bt-script', MH_BT_URL . 'assets/js/mh-bt-frontend.js', array( 'jquery' ), MH_BT_VERSION, true );
        wp_localize_script( 'mh-bt-script', 'mhBtConfig', mh_bt_get_js_config() );
    } else {
        // Fallback to inline if file missing.
        wp_enqueue_script( 'jquery' );
        add_action( 'wp_footer', 'mh_bt_print_footer_script', 99 );
    }

    // Ensure cart fragments script is loaded for live cart counter updates.
    if ( function_exists( 'WC' ) ) {
        wp_enqueue_script( 'wc-cart-fragments' );
    }
}

function mh_bt_frontend_scripts() {
    if ( ! is_product() ) {
        // Load cart/checkout styles on cart and checkout pages.
        if ( is_cart() || is_checkout() ) {
            mh_bt_enqueue_cart_styles();
        }
        return;
    }
    mh_bt_frontend_scripts_force();
}

/**
 * Enqueue cart/checkout styles for bundle display.
 * v2.9 F1: File-based assets with inline fallback.
 */
function mh_bt_enqueue_cart_styles() {
    static $cart_loaded = false;
    if ( $cart_loaded ) { return; }
    $cart_loaded = true;

    $css_file = MH_BT_PATH . 'assets/css/mh-bt-cart.css';
    if ( file_exists( $css_file ) ) {
        wp_enqueue_style( 'mh-bt-cart-style', MH_BT_URL . 'assets/css/mh-bt-cart.css', array(), MH_BT_VERSION );
    } else {
        wp_register_style( 'mh-bt-cart-style', false, array(), MH_BT_VERSION );
        wp_enqueue_style( 'mh-bt-cart-style' );
        wp_add_inline_style( 'mh-bt-cart-style', mh_bt_get_cart_css() );
    }

    $js_file = MH_BT_PATH . 'assets/js/mh-bt-cart.js';
    if ( file_exists( $js_file ) ) {
        wp_enqueue_script( 'mh-bt-cart-script', MH_BT_URL . 'assets/js/mh-bt-cart.js', array( 'jquery' ), MH_BT_VERSION, true );
    } else {
        add_action( 'wp_footer', 'mh_bt_cart_footer_script', 99 );
    }
}

/**
 * Small JS to enhance fee row display in cart/checkout.
 */
function mh_bt_cart_footer_script() {
    /* v3.1: Badge injection is handled by mh_bt_cart_bundle_data_script().
       This fallback only styles fee rows when the external JS file is missing. */
    ?>
    <script type="text/javascript">
    (function(){
        function styleFeeRows() {
            document.querySelectorAll('tr.fee th, tr.fee td, .fee-label').forEach(function(el) {
                if (el.dataset.mhBtStyled) return;
                var text = el.textContent || '';
                if (text.indexOf('Bundle-Rabatt') === -1) return;
                el.dataset.mhBtStyled = '1';
                el.style.color = '#1a7a42';
                el.style.fontWeight = '600';
            });
        }
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', styleFeeRows); }
        else { styleFeeRows(); }
        jQuery(document.body).on('updated_cart_totals updated_checkout', styleFeeRows);
    })();
    </script>
    <?php
}

/**
 * Cart/Checkout CSS for bundle items and fee display.
 */
function mh_bt_get_cart_css() {
    return '
/* v3.1: Bundle display in cart/checkout */
.mh-bt-cart-bundle-wrap {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 4px;
}
.mh-bt-cart-bundle-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f0faf4;
    color: #1a7a42;
    font-size: 0.82em;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 4px;
    border: 1px solid rgba(39,174,96,0.18);
    line-height: 1.4;
    white-space: nowrap;
    letter-spacing: 0.01em;
    width: fit-content;
}
.mh-bt-cart-bundle-badge svg {
    flex-shrink: 0;
    color: #27ae60;
}
.mh-bt-cart-bundle-savings {
    font-size: 0.78em;
    color: #1a7a42;
    font-weight: 500;
    padding-left: 2px;
}
.mh-bt-cart-bundle-savings .woocommerce-Price-amount {
    font-weight: 700;
}
.mh-bt-cart-bundle-item {
    border-left: 3px solid #27ae60 !important;
}
.mh-bt-cart-bundle-item td:first-child {
    padding-left: 12px !important;
}
.mh-bt-fee-original {
    text-decoration: line-through;
    color: #999 !important;
    font-weight: 400 !important;
    font-size: 0.9em;
    margin-right: 6px;
}
tr.fee .mh-bt-fee-label {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #1a7a42;
    font-weight: 600;
}
tr.fee .mh-bt-fee-label .mh-bt-fee-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    background: rgba(39,174,96,0.1);
    border-radius: 50%;
    flex-shrink: 0;
}
tr.fee .mh-bt-fee-label .mh-bt-fee-icon svg {
    color: #27ae60;
}
.mh-bt-fee-amount {
    color: #1a7a42 !important;
    font-weight: 600 !important;
}
';
}

/**
 * Get JS configuration array for wp_localize_script.
 * v2.9 F1: Extracted from mh_bt_print_footer_script for file-based JS.
 *
 * @return array Configuration data.
 */
function mh_bt_get_js_config() {
    return array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mh_bt_add_to_cart' ),
        'sym'     => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
        'cartUrl' => wc_get_cart_url(),
        'i18n'    => array(
            'adding'  => __( 'Wird hinzugefügt...', 'mh-bought-together' ),
            'added'   => __( 'Zum Warenkorb hinzugefügt!', 'mh-bought-together' ),
            'error'   => __( 'Fehler beim Hinzufügen. Bitte versuche es erneut.', 'mh-bought-together' ),
            'cart'    => __( 'Warenkorb ansehen', 'mh-bought-together' ),
            'noItems' => __( 'Bitte mindestens ein zusätzliches Produkt auswählen.', 'mh-bought-together' ),
            'btn'     => __( 'Alle ausgewählten in den Warenkorb', 'mh-bought-together' ),
            'btnOk'   => __( 'Hinzugefügt!', 'mh-bought-together' ),
            'pcs'     => __( 'Stk', 'mh-bought-together' ),
            'save'    => __( 'Sie sparen %s', 'mh-bought-together' ),
            'savePct' => __( '-%d% Bundle · Sie sparen %s', 'mh-bought-together' ),
            'products'=> __( 'Produkte', 'mh-bought-together' ),
            'mainProd'=> __( 'Hauptprodukt', 'mh-bought-together' ),
            'formula' => __( '%d × %s + %s = %d', 'mh-bought-together' ),
            'btnDyn'  => __( '%d Stk in den Warenkorb · %s', 'mh-bought-together' ),
            'bundleNeed'  => __( 'Noch %d Produkt(e) für -%d% Bundle-Rabatt', 'mh-bought-together' ),
            'bundleDone'  => __( 'Bundle-Rabatt aktiv! -%d%', 'mh-bought-together' ),
            'bundleProg'  => __( '%d von %d Produkten', 'mh-bought-together' ),
            'qtyExplain'  => __( 'Bei %d Stk oben → %d Stk empfohlen', 'mh-bought-together' ),
            'showLess'    => __( 'Weniger anzeigen', 'mh-bought-together' ),
        ),
    );
}

/**
 * Inline footer script — FALLBACK only when assets/js/mh-bt-frontend.js is missing.
 */
function mh_bt_print_footer_script() {
    $C = array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mh_bt_add_to_cart' ),
        'sym'     => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
        'cartUrl' => wc_get_cart_url(),
        'i18n'    => array(
            'adding'  => __( 'Wird hinzugefügt...', 'mh-bought-together' ),
            'added'   => __( 'Zum Warenkorb hinzugefügt!', 'mh-bought-together' ),
            'error'   => __( 'Fehler beim Hinzufügen. Bitte versuche es erneut.', 'mh-bought-together' ),
            'cart'    => __( 'Warenkorb ansehen', 'mh-bought-together' ),
            'noItems' => __( 'Bitte mindestens ein zusätzliches Produkt auswählen.', 'mh-bought-together' ),
            'btn'     => __( 'Alle ausgewählten in den Warenkorb', 'mh-bought-together' ),
            'btnOk'   => __( 'Hinzugefügt!', 'mh-bought-together' ),
            'pcs'     => __( 'Stk', 'mh-bought-together' ),
            'save'    => __( 'Sie sparen %s', 'mh-bought-together' ),
            'savePct' => __( '-%d% Bundle · Sie sparen %s', 'mh-bought-together' ),
            'products'=> __( 'Produkte', 'mh-bought-together' ),
            'mainProd'=> __( 'Hauptprodukt', 'mh-bought-together' ),
            'formula' => __( '%d × %s + %s = %d', 'mh-bought-together' ),
            'btnDyn'  => __( '%d Stk in den Warenkorb · %s', 'mh-bought-together' ),
            'bundleNeed'  => __( 'Noch %d Produkt(e) für -%d% Bundle-Rabatt', 'mh-bought-together' ),
            'bundleDone'  => __( 'Bundle-Rabatt aktiv! -%d%', 'mh-bought-together' ),
            'bundleProg'  => __( '%d von %d Produkten', 'mh-bought-together' ),
            'qtyExplain'  => __( 'Bei %d Stk oben → %d Stk empfohlen', 'mh-bought-together' ),
            'showLess'    => __( 'Weniger anzeigen', 'mh-bought-together' ),
        ),
    );
    ?>
    <script type="text/javascript">
    (function($){
        'use strict';
        var C = <?php echo wp_json_encode( $C ); ?>;

        function mhBtInitWidget(w) {
            if (w.data('mh-bt-init')) return;
            w.data('mh-bt-init', true);

            var discount = parseInt(w.data('discount'))||0;
            var minItems = parseInt(w.data('min-items'))||2;
            var noMain   = !!w.data('no-main');

            // Find WooCommerce qty input.
            var qtyInput = $('form.cart input[name="quantity"]');
            if (!qtyInput.length) qtyInput = $('input[name="quantity"]').first();

            function mainQty() { return Math.max(1, parseInt(qtyInput.val())||1); }
            function calcQty(m,o,mq) { return Math.max(1, Math.ceil(mq*m+o)); }
            function fmt(p) { var parts=p.toFixed(2).split('.'); parts[0]=parts[0].replace(/\B(?=(\d{3})+(?!\d))/g,'.'); return parts.join(',') + ' ' + C.sym; }

            // Animated Price Counter (per widget instance)
            var currentPrice = 0;
            var priceRaf = null;
            function animatePrice(el, from, to, duration) {
                if (priceRaf) cancelAnimationFrame(priceRaf);
                var start = performance.now();
                duration = duration || 350;
                (function tick(now) {
                    var t = Math.min((now - start) / duration, 1);
                    t = t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t + 2, 3) / 2;
                    el.text(fmt(from + (to - from) * t));
                    if (t < 1) { priceRaf = requestAnimationFrame(tick); }
                })(start);
            }

            // v2.7.1: Editable qty inputs — track state
            var prevQtyMap = {};
            var lastMainQty = 0;
            var inputsCreated = false;
            var prevDisc = false;

            function createQtyInputs() {
                if (inputsCreated) return;
                inputsCreated = true;
                var inpStyle = 'color:#000!important;-webkit-text-fill-color:#000!important;background:#fff!important;font-weight:600!important;font-size:13px!important;text-align:center!important;opacity:1!important;border:none!important;border-left:1px solid #e0e0e0!important;border-right:1px solid #e0e0e0!important;border-radius:0!important;padding:0!important;width:2.4em!important;height:100%!important;margin:0!important;box-shadow:none!important;line-height:2em!important;-webkit-appearance:none!important;-moz-appearance:textfield!important;';
                w.find('.mh-bt-product__qty-calc').each(function(){
                    var el = $(this);
                    var isMain = !!el.data('is-main');
                    var cls = isMain ? 'mh-bt-qty-input mh-bt-qty-input--main' : 'mh-bt-qty-input mh-bt-qty-input--linked';
                    el.html(
                        '<div class="mh-bt-qty-stepper">' +
                            '<button type="button" class="mh-bt-qty-btn mh-bt-qty-minus" aria-label="' + C.i18n.pcs + ' -">−</button>' +
                            '<input type="number" class="' + cls + '" value="1" min="1" step="1" style="' + inpStyle + '">' +
                            '<button type="button" class="mh-bt-qty-btn mh-bt-qty-plus" aria-label="' + C.i18n.pcs + ' +">+</button>' +
                        '</div>'
                    );
                });
            }

            function update() {
                var mq = mainQty(), total = 0, cnt = 0, sumRegular = 0, totalQty = 0;
                var prevPrice = currentPrice;
                var hasMultiplier = false;
                var mainChanged = (mq !== lastMainQty);
                lastMainQty = mq;

                // Ensure inputs exist.
                createQtyInputs();

                // Main product (only in primary box).
                if (!noMain) {
                    var mainCb = w.find('.mh-bt-product--main .mh-bt-checkbox');
                    var mp = parseFloat(mainCb.data('price'))||0;
                    var mrp = parseFloat(mainCb.data('regular-price'))||mp;
                    total += mp * mq;
                    sumRegular += mrp * mq;
                    cnt++;
                    totalQty += mq;
                    var mainQtyEl = w.find('.mh-bt-product--main .mh-bt-product__qty-calc');
                    var mainInput = mainQtyEl.find('.mh-bt-qty-input');
                    var prevMainQ = prevQtyMap['main']||0;
                    mainInput.val(mq);
                    // v2.7 #9: Qty-pop animation
                    if (prevMainQ && prevMainQ !== mq) {
                        mainQtyEl.removeClass('mh-bt-qty-pop');
                        void mainQtyEl[0].offsetWidth;
                        mainQtyEl.addClass('mh-bt-qty-pop');
                    }
                    prevQtyMap['main'] = mq;
                }

                // Linked products.
                w.find('.mh-bt-checkbox--linked').each(function(){
                    var cb = $(this);
                    var pid = cb.data('product-id');
                    var mRaw = cb.data('multiplier');
                    var m = (mRaw === '' || mRaw === undefined || mRaw === null) ? 1 : parseFloat(mRaw);
                    if (isNaN(m)) m = 1;
                    var o = parseInt(cb.data('offset'))||0;
                    var formulaQ = noMain ? 1 : calcQty(m, o, mq);
                    var p = parseFloat(cb.data('price'))||0;
                    var rp = parseFloat(cb.data('regular-price'))||p;
                    if (m !== 1 || o !== 0) hasMultiplier = true;

                    var qtyEl = cb.closest('.mh-bt-product').find('.mh-bt-product__qty-calc');
                    var input = qtyEl.find('.mh-bt-qty-input');
                    var prevQ = prevQtyMap[pid]||0;

                    // Set input value: recalculate from formula when main qty changes, otherwise keep user edit
                    if (mainChanged || prevQ === 0) {
                        input.val(formulaQ);
                    }

                    // Read actual value from input (user may have edited)
                    var actualQ = Math.max(1, parseInt(input.val())||1);
                    cb.data('calc-qty', actualQ);

                    // v2.7 #9: Qty-pop animation (only when formula changes)
                    if (prevQ && prevQ !== actualQ) {
                        qtyEl.removeClass('mh-bt-qty-pop');
                        void qtyEl[0].offsetWidth;
                        qtyEl.addClass('mh-bt-qty-pop');
                        // v2.9 A2: Highlight entire product row on qty-sync change
                        if (mainChanged) {
                            var hlProd = cb.closest('.mh-bt-product');
                            hlProd.removeClass('mh-bt-qty-highlight');
                            void hlProd[0].offsetWidth;
                            hlProd.addClass('mh-bt-qty-highlight');
                        }
                    }
                    prevQtyMap[pid] = actualQ;

                    // v3.1: Qty-Explain — only show custom label from admin, no auto text
                    var explainEl = cb.closest('.mh-bt-product').find('.mh-bt-product__qty-explain');
                    var customLabel = cb.data('qty-label') || '';
                    if (customLabel) {
                        var explainSvg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 10 4 15 9 20"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/></svg>';
                        explainEl.html(explainSvg + '<span>' + customLabel + '</span>').addClass('mh-bt-qty-explain--visible');
                    } else {
                        explainEl.removeClass('mh-bt-qty-explain--visible');
                    }

                    if (cb.is(':checked')) { total += p*actualQ; sumRegular += rp*actualQ; cnt++; totalQty += actualQ; }

                    // v2.6 #1+#6: Unchecked dimming on product + preceding separator
                    var prodEl = cb.closest('.mh-bt-product');
                    var prevSep = prodEl.prev('.mh-bt-plus-separator');
                    if (cb.is(':checked')) {
                        prodEl.removeClass('mh-bt-unchecked').addClass('mh-bt-checked');
                        prevSep.removeClass('mh-bt-dimmed');
                    } else {
                        prodEl.addClass('mh-bt-unchecked').removeClass('mh-bt-checked');
                        prevSep.addClass('mh-bt-dimmed');
                    }
                });

                // v2.7: Show/hide qty-sync hint (only relevant when multipliers exist)
                var hintEl = w.find('.mh-bt-qty-sync-hint');
                if (hasMultiplier && !noMain) { hintEl.show(); } else { hintEl.hide(); }

                var disc = discount>0 && cnt>=minItems;
                var fin = disc ? total*(1-discount/100) : total;

                // v3.0: Milestone progress bar
                var progressEl = w.find('.mh-bt-bundle-progress');
                if (discount > 0 && progressEl.length) {
                    progressEl.show();
                    var pctFill = Math.min(100, Math.round((cnt / minItems) * 100));
                    progressEl.find('.mh-bt-bundle-progress__fill').css('width', pctFill + '%');
                    var textEl = progressEl.find('.mh-bt-bundle-progress__text');
                    var fillEl = progressEl.find('.mh-bt-bundle-progress__fill');
                    var msEl = progressEl.find('.mh-bt-bundle-progress__milestone');
                    var badgeEl = progressEl.find('.mh-bt-bundle-progress__badge');
                    if (cnt >= minItems) {
                        textEl.text(C.i18n.bundleDone.replace('%d', discount)).addClass('mh-bt-bundle-progress--reached');
                        fillEl.addClass('mh-bt-bundle-progress--reached');
                        if (!msEl.hasClass('mh-bt-bundle-progress--reached')) {
                            msEl.addClass('mh-bt-bundle-progress--reached');
                        }
                        badgeEl.addClass('mh-bt-bundle-progress--reached').find('span').text(C.i18n.bundleDone.replace('%d', discount));
                    } else {
                        var need = minItems - cnt;
                        textEl.text(C.i18n.bundleNeed.replace('%d', need).replace('%d', discount)).removeClass('mh-bt-bundle-progress--reached');
                        fillEl.removeClass('mh-bt-bundle-progress--reached');
                        msEl.removeClass('mh-bt-bundle-progress--reached');
                        badgeEl.removeClass('mh-bt-bundle-progress--reached');
                    }
                }

                // Animated price counter — v2.9.1: green ONLY for bundle discount, not for deselecting
                var priceEl = w.find('.mh-bt-total__price');
                var priceChanged = Math.abs(currentPrice - fin) > 0.005;
                var discJustActivated = disc && !prevDisc;
                var discJustDeactivated = !disc && prevDisc;

                if (priceChanged) {
                    if (discJustActivated && total > fin) {
                        // Two-step: count up to full total, pause, then green count down to discounted price
                        animatePrice(priceEl, currentPrice, total, 250);
                        currentPrice = total;
                        setTimeout(function(){
                            priceEl.addClass('mh-bt-price--down');
                            animatePrice(priceEl, total, fin, 400);
                            currentPrice = fin;
                            setTimeout(function(){ priceEl.removeClass('mh-bt-price--down'); }, 600);
                        }, 350);
                    } else {
                        // Neutral animation — no green flash (deselecting, qty change, etc.)
                        animatePrice(priceEl, currentPrice, fin, 350);
                        currentPrice = fin;
                    }
                }
                prevDisc = disc;

                // Strikethrough original price: show when any savings exist (sale or bundle)
                var origEl = w.find('.mh-bt-total__original');
                var hasSavings = sumRegular - fin > 0.01;
                if (hasSavings) {
                    origEl.text(fmt(sumRegular)).show();
                } else {
                    origEl.hide();
                }

                // v2.9 E2: Bundle discount — glow only, badge removed (consolidated into savings pill)
                if (disc) {
                    w.find('.mh-bt-add-all').addClass('mh-bt-add-all--bundle-glow');
                } else {
                    w.find('.mh-bt-add-all').removeClass('mh-bt-add-all--bundle-glow');
                }

                // v2.9 B1+E2: Consolidated Savings Pill — one element with bundle % + euro savings
                var totalSavings = sumRegular - fin;
                var savingsPill = w.find('.mh-bt-savings-pill');
                if (totalSavings > 0.01) {
                    var savingsText;
                    if (disc) {
                        savingsText = C.i18n.savePct.replace('%d', discount).replace('%s', fmt(totalSavings));
                    } else {
                        savingsText = C.i18n.save.replace('%s', fmt(totalSavings));
                    }
                    w.find('.mh-bt-savings-pill__text').text(savingsText);
                    savingsPill.addClass('mh-bt-savings-pill--visible');
                } else {
                    savingsPill.removeClass('mh-bt-savings-pill--visible');
                }

                // Counter badge
                var countEl = w.find('.mh-bt-heading__count');
                countEl.text(cnt + ' ' + C.i18n.products);
                countEl.addClass('mh-bt-pop');
                setTimeout(function(){ countEl.removeClass('mh-bt-pop'); }, 200);

                // v2.7 #2: Dynamic CTA button text with total pieces + price
                var btnText = w.find('.mh-bt-add-all__text');
                if (!w.data('mh-bt-success')) {
                    btnText.text(C.i18n.btnDyn.replace('%d', totalQty).replace('%s', fmt(fin)));
                }
            }

            w.on('change', '.mh-bt-checkbox--linked', update);
            if (!noMain) {
                qtyInput.on('input change', update);
                $(document).on('click', '.quantity .plus, .quantity .minus', function(){ setTimeout(update,50); });
            }
            update();

            // v2.9.5: Tracking — impression (once per widget init)
            var mainId = parseInt(w.find('.mh-bt-add-all').data('main-product'))||0;
            if (mainId && C.ajaxUrl) {
                $.post(C.ajaxUrl, { action:'mh_bt_track', type:'impression', product_id:mainId, nonce:C.nonce });
            }
            // v2.9.5: Tracking — deselection events
            w.on('change', '.mh-bt-checkbox--linked', function(){
                if (!$(this).is(':checked') && mainId && C.ajaxUrl) {
                    $.post(C.ajaxUrl, { action:'mh_bt_track', type:'deselect', product_id:parseInt($(this).data('product-id'))||0, source_id:mainId, nonce:C.nonce });
                }
            });

            // v2.7.1: Editable qty inputs — event handlers
            // Main product input → sync to WooCommerce qty input
            w.on('change', '.mh-bt-qty-input--main', function(){
                var v = Math.max(1, parseInt($(this).val())||1);
                $(this).val(v);
                qtyInput.val(v).trigger('change');
            });
            // Linked product inputs → recalculate prices from current input values
            w.on('input change', '.mh-bt-qty-input--linked', function(){
                update();
            });
            // Plus/minus buttons
            w.on('click', '.mh-bt-qty-minus, .mh-bt-qty-plus', function(e){
                e.preventDefault();
                e.stopPropagation();
                var btn = $(this);
                var input = btn.closest('.mh-bt-qty-stepper').find('.mh-bt-qty-input');
                var val = Math.max(1, parseInt(input.val())||1);
                if (btn.hasClass('mh-bt-qty-minus')) { val = Math.max(1, val - 1); }
                else { val = val + 1; }
                input.val(val).trigger('change');
            });
            // Prevent checkbox toggle when clicking inside stepper
            w.on('click', '.mh-bt-qty-stepper, .mh-bt-qty-input, .mh-bt-qty-btn', function(e){ e.stopPropagation(); });

            // v2.9.2: Image preview tooltip — appended to body with fixed positioning
            w.find('.mh-bt-product__image[data-preview-img]').each(function(){
                var imgEl = $(this);
                var src = imgEl.data('preview-img');
                var name = imgEl.data('preview-name') || '';
                if (!src) return;
                var tip = $('<span class="mh-bt-preview-tooltip"><img src="'+src+'" alt="" /><span class="mh-bt-preview-tooltip__name">'+$('<span>').text(name).html()+'</span></span>');
                $('body').append(tip);
                var showTimer;
                function positionTip() {
                    var r = imgEl[0].getBoundingClientRect();
                    var tipW = 134, tipH = 150;
                    var left = r.right + 10;
                    if (left + tipW > window.innerWidth) { left = r.left - tipW - 10; }
                    var top = r.top + r.height / 2 - tipH / 2;
                    if (top < 8) top = 8;
                    if (top + tipH > window.innerHeight - 8) top = window.innerHeight - tipH - 8;
                    tip.css({ left: left + 'px', top: top + 'px' });
                }
                imgEl.on('mouseenter', function(){ positionTip(); showTimer = setTimeout(function(){ tip.addClass('mh-bt-preview--visible'); }, 350); });
                imgEl.on('mouseleave', function(){ clearTimeout(showTimer); tip.removeClass('mh-bt-preview--visible'); });
            });

            // v2.9.2: Show-more toggle for collapsible product lists
            w.find('.mh-bt-show-more').on('click', function(){
                var btn = $(this);
                var collapsible = btn.prev('.mh-bt-collapsible');
                if (collapsible.is(':visible')) {
                    collapsible.slideUp(250);
                    btn.removeClass('mh-bt-show-more--open');
                    var origCount = collapsible.find('.mh-bt-product').length;
                    btn.find('.mh-bt-show-more__text').text(origCount + ' weitere Produkte anzeigen');
                } else {
                    collapsible.slideDown(250);
                    btn.addClass('mh-bt-show-more--open');
                    btn.find('.mh-bt-show-more__text').text(C.i18n.showLess || 'Weniger anzeigen');
                }
            });

            // Add to cart with success animation.
            w.on('click', '.mh-bt-add-all', function(e){
                e.preventDefault();
                var btn=$(this), notice=w.find('.mh-bt-notice');
                var btnText = btn.find('.mh-bt-add-all__text');
                var iconCart = btn.find('.mh-bt-add-all__icon-cart');
                var iconCheck = btn.find('.mh-bt-add-all__icon-check');
                var mq = mainQty();
                var items = [];

                // Primary box: add main product first.
                var mainId = parseInt(btn.data('main-product'))||0;
                if (!noMain && mainId) {
                    items.push({id: mainId, qty: mq});
                }

                w.find('.mh-bt-checkbox--linked:checked').each(function(){
                    items.push({ id: parseInt($(this).val()), qty: parseInt($(this).data('calc-qty'))||1 });
                });
                if (items.length < 1) { notice.attr('class','mh-bt-notice mh-bt-notice--error').text(C.i18n.noItems).show(); return; }

                btn.prop('disabled',true);
                btnText.text(C.i18n.adding);
                iconCart.css('transform','rotate(15deg)');
                notice.hide();
                $.post(C.ajaxUrl, { action:'mh_bt_add_to_cart', nonce:C.nonce, items:items, source_product_id:mainId }, function(r){
                    if (r&&r.success) {
                        w.data('mh-bt-success', true);
                        btn.addClass('mh-bt-add-all--success').removeClass('mh-bt-add-all--bundle-glow');
                        iconCart.hide();
                        iconCheck.show();
                        btnText.text(C.i18n.btnOk);
                        notice.attr('class','mh-bt-notice mh-bt-notice--success')
                              .html(C.i18n.added+' <a href="'+C.cartUrl+'">'+C.i18n.cart+'</a>').show();
                        var fragments = (r.data && r.data.fragments) ? r.data.fragments : false;
                        var cartHash  = (r.data && r.data.cart_hash) ? r.data.cart_hash : '';
                        if (fragments) {
                            $.each(fragments, function(key, value) {
                                $(key).replaceWith(value);
                            });
                        }
                        $(document.body).trigger('added_to_cart', [fragments, cartHash, null]);
                        $(document.body).trigger('wc_fragment_refresh');
                        setTimeout(function(){
                            w.data('mh-bt-success', false);
                            btn.removeClass('mh-bt-add-all--success');
                            iconCheck.hide();
                            iconCart.show().css('transform','');
                            btn.prop('disabled',false);
                            update();
                        }, 2500);
                    } else {
                        notice.attr('class','mh-bt-notice mh-bt-notice--error').text((r&&r.data)||C.i18n.error).show();
                        btn.prop('disabled',false);
                        iconCart.css('transform','');
                        update();
                    }
                }).fail(function(){
                    notice.attr('class','mh-bt-notice mh-bt-notice--error').text(C.i18n.error).show();
                    btn.prop('disabled',false);
                    iconCart.css('transform','');
                    update();
                });
            });
        }

        function mhBtInit() {
            $('.mh-bt-widget').each(function(){ mhBtInitWidget($(this)); });
        }
        $(document).ready(mhBtInit);
        $(document).on('oxygen-ajax-loaded', mhBtInit);
    })(jQuery);

    /* v3.2: Included Products toggle — inline fallback */
    (function($){
        'use strict';
        function mhIpInit() {
            $('.mh-ip-show-more').each(function(){
                var btn = $(this);
                if (btn.data('mh-ip-init')) return;
                btn.data('mh-ip-init', true);
                btn.on('click', function(e){
                    e.preventDefault();
                    var collapsible = btn.prev('.mh-ip-collapsible');
                    if (!collapsible.hasClass('mh-ip-collapsible--hidden')) {
                        collapsible.addClass('mh-ip-collapsible--hidden');
                        btn.removeClass('mh-ip-show-more--open');
                        btn.attr('aria-expanded', 'false');
                        var origCount = collapsible.find('.mh-ip-product').length;
                        btn.find('.mh-ip-show-more__text').text(origCount + ' weitere Produkte anzeigen');
                    } else {
                        collapsible.removeClass('mh-ip-collapsible--hidden');
                        btn.addClass('mh-ip-show-more--open');
                        btn.attr('aria-expanded', 'true');
                        btn.find('.mh-ip-show-more__text').text('Weniger anzeigen');
                    }
                });
            });
        }
        $(document).ready(mhIpInit);
        $(document).on('oxygen-ajax-loaded', mhIpInit);
    })(jQuery);
    </script>
    <?php
}

/* ==========================================================================
   HELPER: STOCK INDICATOR (#3)
   ========================================================================== */

function mh_bt_render_stock_indicator( $product ) {
    if ( ! $product->is_in_stock() ) {
        return;
    }
    $stock_qty = $product->get_stock_quantity();

    if ( $product->managing_stock() && null !== $stock_qty ) {
        if ( $stock_qty <= 0 ) {
            // Negative or zero stock with backorders enabled → in production.
            echo '<span class="mh-bt-stock mh-bt-stock--production"><span class="mh-bt-stock__dot"></span>' . esc_html__( 'In Produktion – lieferbar', 'mh-bought-together' ) . '</span>';
        } elseif ( $stock_qty <= 5 ) {
            // Low stock warning (1-5 remaining).
            printf(
                '<span class="mh-bt-stock mh-bt-stock--low"><span class="mh-bt-stock__dot"></span>%s</span>',
                sprintf( esc_html__( 'Nur noch %d auf Lager', 'mh-bought-together' ), intval( $stock_qty ) )
            );
        } else {
            // Normal stock.
            echo '<span class="mh-bt-stock mh-bt-stock--in"><span class="mh-bt-stock__dot"></span>' . esc_html__( 'Auf Lager', 'mh-bought-together' ) . '</span>';
        }
    } else {
        // Not managing stock or stock is null → just show available.
        echo '<span class="mh-bt-stock mh-bt-stock--in"><span class="mh-bt-stock__dot"></span>' . esc_html__( 'Auf Lager', 'mh-bought-together' ) . '</span>';
    }
}

function mh_bt_get_frontend_css() {
    return '
/* === v2.6 Design-Polishing === */

/* #1 Stagger-Entrance Animation */
@keyframes mhBtSlideIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
.mh-bt-product { opacity:0; animation:mhBtSlideIn .4s ease forwards; }
.mh-bt-product:nth-child(1) { animation-delay:.05s; }
.mh-bt-product:nth-child(2) { animation-delay:.15s; }
.mh-bt-product:nth-child(3) { animation-delay:.25s; }
.mh-bt-product:nth-child(4) { animation-delay:.35s; }
.mh-bt-product:nth-child(5) { animation-delay:.45s; }
.mh-bt-plus-separator { opacity:0; animation:mhBtSlideIn .3s ease forwards; }
.mh-bt-plus-separator:nth-child(2) { animation-delay:.1s; }
.mh-bt-plus-separator:nth-child(4) { animation-delay:.2s; }
.mh-bt-plus-separator:nth-child(6) { animation-delay:.3s; }
.mh-bt-plus-separator:nth-child(8) { animation-delay:.4s; }

/* Checkbox-Bounce Animation */
@keyframes mhBtBounce { 0%{transform:scale(1)} 40%{transform:scale(1.2)} 70%{transform:scale(0.9)} 100%{transform:scale(1)} }

/* v3.2.4: Full-width — matches IP widget container */
.mh-bt-widget { margin:1.5em 0; padding:1em 0; border:none; border-radius:0; background:transparent; width:100%; box-sizing:border-box; box-shadow:none; border-top:1px solid #eee; }

/* Heading — smaller, lighter */
.mh-bt-heading { font-size:1.05em; margin:0 0 0.3em; padding-bottom:0; border-bottom:none; display:flex; align-items:center; gap:0.4em; flex-wrap:wrap; font-weight:600; }

/* Social Proof — smaller */
.mh-bt-social-proof { display:flex; align-items:center; gap:0.35em; font-size:0.72em; color:#999; margin-bottom:0.5em; }
.mh-bt-social-proof svg { flex-shrink:0; color:#bbb; }

/* Qty-Sync Hint — text only, no box */
.mh-bt-qty-sync-hint { display:flex; align-items:center; gap:0.35em; font-size:0.7em; color:#bbb; margin-bottom:0.5em; padding:0; background:none; border:none; border-radius:0; line-height:1.3; }
.mh-bt-qty-sync-hint svg { flex-shrink:0; color:#f7af4a; opacity:0.5; }

/* Bundle-Rabatt Fortschrittsbalken — v3.0 Milestone */
@keyframes mhBtMilestoneHit { 0%{transform:translateX(50%) scale(1)} 40%{transform:translateX(50%) scale(1.3)} 70%{transform:translateX(50%) scale(0.9)} 100%{transform:translateX(50%) scale(1)} }
.mh-bt-bundle-progress { margin-bottom:0.5em; }
.mh-bt-bundle-progress__track { height:8px; border-radius:4px; background:#f0f0f0; overflow:visible; position:relative; }
.mh-bt-bundle-progress__fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#f7af4a,#e59a2f); transition:width .5s cubic-bezier(.34,1.56,.64,1); }
.mh-bt-bundle-progress__fill.mh-bt-bundle-progress--reached { background:linear-gradient(90deg,#5cb85c,#4cae4c); }
.mh-bt-bundle-progress__milestone { position:absolute; top:-4px; right:0; width:16px; height:16px; border-radius:50%; background:#fff; border:2px solid #ddd; z-index:2; transform:translateX(50%); transition:border-color .3s ease,background .3s ease; display:flex; align-items:center; justify-content:center; }
.mh-bt-bundle-progress__milestone.mh-bt-bundle-progress--reached { border-color:#5cb85c; background:#5cb85c; animation:mhBtMilestoneHit .4s ease; }
.mh-bt-bundle-progress__milestone svg { opacity:0; transition:opacity .2s ease; }
.mh-bt-bundle-progress__milestone.mh-bt-bundle-progress--reached svg { opacity:1; }
.mh-bt-bundle-progress__info { display:flex; justify-content:space-between; align-items:center; margin-top:0.3em; }
.mh-bt-bundle-progress__text { font-size:0.7em; font-weight:500; color:#d48a0a; transition:color .3s ease; }
.mh-bt-bundle-progress__text.mh-bt-bundle-progress--reached { color:#27ae60; }
.mh-bt-bundle-progress__badge { display:none; align-items:center; gap:3px; font-size:0.65em; font-weight:600; color:#27ae60; background:rgba(39,174,96,0.08); padding:0.15em 0.5em; border-radius:10px; }
.mh-bt-bundle-progress__badge.mh-bt-bundle-progress--reached { display:inline-flex; }
.mh-bt-bundle-progress__badge svg { flex-shrink:0; color:#27ae60; }

/* Plus-Separator */
.mh-bt-products { display:flex; flex-direction:column; gap:0; }

/* v2.9.6: Show-more — elegant chip */
.mh-bt-collapsible { display:flex; flex-direction:column; gap:0; }
.mh-bt-show-more { display:flex; align-items:center; justify-content:center; gap:0.35em; background:rgba(0,0,0,0.025); border:none; border-radius:20px; padding:0.45em 1.2em; margin:0.6em auto 0.2em; font-size:0.75em; color:#999; cursor:pointer; transition:all .2s ease; width:auto; }
.mh-bt-show-more:hover { background:rgba(247,175,74,0.08); color:#b87a12; }
.mh-bt-show-more__icon { transition:transform .25s ease; width:12px; height:12px; }
.mh-bt-show-more--open .mh-bt-show-more__icon { transform:rotate(180deg); }

/* Optional Box — subtle blue accent */
.mh-bt-widget--optional { background:transparent; box-shadow:none; border-left:3px solid #3498db; border-top:none; padding-left:1em; border-radius:0; }
.mh-bt-widget--optional .mh-bt-heading { font-size:1.1em; color:#555; }
.mh-bt-widget--optional .mh-bt-add-all { background:#3498db!important; }
.mh-bt-widget--optional .mh-bt-add-all:hover { background:#2980b9!important; }
.mh-bt-plus-separator { display:flex; align-items:center; justify-content:center; padding:0.05em 0; transition:opacity .3s ease; }
/* Plus-Icon — flat, no circle bg */
.mh-bt-plus-separator__icon { width:20px; height:20px; border-radius:50%; background:transparent; border:none; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:400; color:#ddd; line-height:1; transition:color .2s ease; }
.mh-bt-plus-separator__icon:hover { color:#f7af4a; }

/* v2.6 #6: Plus separator dims when next product is unchecked */
.mh-bt-plus-separator.mh-bt-dimmed { animation:none; opacity:0.3; transition:opacity .3s ease; }

/* Product Rows — compact, calm */
@keyframes mhBtQtyHighlight { 0%{background:rgba(247,175,74,0.05)} 100%{background:transparent} }
.mh-bt-product__label { display:flex; align-items:center; gap:0.6em; padding:0.5em 0.35em; border:none; border-radius:0; border-bottom:1px solid #f5f5f5; background:transparent; cursor:pointer; transition:background .2s ease,opacity .3s ease,filter .3s ease; }
/* v2.9 A2: Qty-sync highlight pulse */
.mh-bt-product.mh-bt-qty-highlight .mh-bt-product__label { animation:mhBtQtyHighlight .5s ease forwards; }
.mh-bt-product__label:hover { background:rgba(0,0,0,0.01); }
.mh-bt-product--main .mh-bt-product__label { background:transparent; border-bottom:1px solid #f0f0f0; }
.mh-bt-product--main .mh-bt-product__label:hover { background:rgba(0,0,0,0.01); }

/* Unchecked product dimming */
.mh-bt-product.mh-bt-unchecked .mh-bt-product__label { opacity:0.45; filter:grayscale(30%); }
.mh-bt-product.mh-bt-unchecked .mh-bt-product__label:hover { opacity:0.65; filter:grayscale(10%); background:rgba(0,0,0,0.015); }
/* v2.9.8: Checked linked product — amber left accent */
.mh-bt-product.mh-bt-checked .mh-bt-product__label { border-left:2px solid #f7af4a; padding-left:calc(0.35em + 2px); border-radius:0; }

/* Focus ring — appears then fades out */
@keyframes mhBtFocusFade { 0%{box-shadow:0 0 0 2px rgba(247,175,74,0.4)} 70%{box-shadow:0 0 0 2px rgba(247,175,74,0.4)} 100%{box-shadow:0 0 0 0 rgba(247,175,74,0)} }
.mh-bt-product__label:focus-within { outline:none; animation:mhBtFocusFade .8s ease forwards; }

/* Custom Checkbox */
.mh-bt-checkbox { position:absolute; opacity:0; width:0; height:0; pointer-events:none; }
.mh-bt-checkbox-custom { flex-shrink:0; width:20px; height:20px; border-radius:4px; border:1.5px solid #ccc; background:#fff; display:flex; align-items:center; justify-content:center; transition:all .2s ease; pointer-events:none; }
.mh-bt-checkbox-custom svg { opacity:0; transform:scale(0.5); transition:all .15s ease; }
.mh-bt-checkbox:checked + .mh-bt-checkbox-custom { background:#f7af4a; border-color:#f7af4a; animation:mhBtBounce .3s ease; }
.mh-bt-checkbox:checked + .mh-bt-checkbox-custom svg { opacity:1; transform:scale(1); }
/* v2.9 D2: Main product lock pin — replaces disabled checkbox */
.mh-bt-main-pin { flex-shrink:0; width:20px; height:20px; display:flex; align-items:center; justify-content:center; color:#f7af4a; opacity:0.6; }

/* Image — compact */
.mh-bt-product__image { flex-shrink:0; width:46px; height:46px; overflow:hidden; border-radius:5px; border:1px solid #eee; box-shadow:none; position:relative; transition:border-color .2s ease; }
.mh-bt-product__image img { width:46px; height:46px; object-fit:cover; border-radius:4px; transition:transform .25s ease; }
/* v2.9.2: Hover only on direct image interaction, not full row */
.mh-bt-product__image:hover img { transform:scale(1.05); }
.mh-bt-product__image:hover { border-color:#f7af4a; }

/* v2.6 #9: Image hover link hint */
.mh-bt-image-hint { position:absolute; bottom:3px; right:3px; width:18px; height:18px; border-radius:4px; background:rgba(0,0,0,0.55); display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity .25s ease; pointer-events:none; }
.mh-bt-image-hint svg { color:#fff; }
.mh-bt-product__image:hover .mh-bt-image-hint { opacity:1; }

/* v2.9.2: Image preview tooltip — appended to body, fixed positioning */
.mh-bt-preview-tooltip { position:fixed; z-index:99999; background:#fff; border:1px solid #e8e8e8; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.12); padding:6px; pointer-events:none; opacity:0; transition:opacity .2s ease; }
.mh-bt-preview-tooltip.mh-bt-preview--visible { opacity:1; }
.mh-bt-preview-tooltip img { display:block; width:120px; height:120px; object-fit:cover; border-radius:5px; }
.mh-bt-preview-tooltip__name { display:block; max-width:120px; font-size:0.7em; color:#555; text-align:center; margin-top:4px; line-height:1.25; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* Product Info */
.mh-bt-product__info { display:flex; flex-direction:column; gap:0.15em; min-width:0; flex:1; }
.mh-bt-product__name { font-weight:500; font-size:0.82em; line-height:1.3; display:flex; align-items:center; gap:0.4em; flex-wrap:wrap; overflow-wrap:anywhere; word-break:break-word; }
.mh-bt-product__name a { color:inherit; text-decoration:none; }
.mh-bt-product__name a:hover { text-decoration:underline; }
/* Reason / explanation text with info icon */
.mh-bt-product__reason { font-size:0.78em; color:#777; font-style:italic; line-height:1.3; display:flex; align-items:center; gap:0.3em; }
.mh-bt-product__reason svg { flex-shrink:0; color:#bbb; width:13px; height:13px; }
.mh-bt-product__price { font-size:0.85em; color:#555; display:inline-flex; align-items:baseline; gap:6px; flex-wrap:wrap; }
.mh-bt-product__price * { margin:0; padding:0; }
.mh-bt-product__price del { color:#aaa; font-size:0.88em; font-weight:400; text-decoration:line-through; text-decoration-color:#ccc; }
.mh-bt-product__price del .woocommerce-Price-amount { color:#aaa; }
.mh-bt-product__price ins { text-decoration:none; font-weight:700; color:inherit; font-size:1.05em; }
.mh-bt-product__price del + ins { position:relative; }

/* v2.7.1: Qty Stepper — minus/input/plus */
.mh-bt-product__qty-calc { display:inline-flex; align-items:center; flex-shrink:0; position:relative; font-size:0.8em; margin-left:auto; }
.mh-bt-product__qty-calc:empty { display:none; }
.mh-bt-qty-stepper { display:inline-flex; align-items:stretch; border:1px solid #e0e0e0; border-radius:6px; overflow:hidden; height:2em; background:#fafafa; }
.mh-bt-qty-btn { display:flex; align-items:center; justify-content:center; width:1.8em; flex-shrink:0; background:#f5f5f5!important; border:none!important; color:#555!important; font-size:14px!important; font-weight:400!important; cursor:pointer; transition:background .15s ease,color .15s ease; padding:0!important; margin:0!important; line-height:1!important; -webkit-text-fill-color:#555!important; user-select:none; }
.mh-bt-qty-btn:hover { background:#eee!important; color:#222!important; -webkit-text-fill-color:#222!important; }
.mh-bt-qty-btn:active { background:#e0e0e0!important; }
.mh-bt-qty-input { width:2.4em!important; flex-shrink:0; height:100%!important; padding:0!important; border:none!important; border-left:1px solid #e0e0e0!important; border-right:1px solid #e0e0e0!important; border-radius:0!important; background:#fff!important; color:#000!important; font-size:13px!important; font-weight:600!important; text-align:center!important; -moz-appearance:textfield!important; -webkit-appearance:none!important; font-family:inherit; line-height:2em!important; appearance:none!important; }
.mh-bt-widget input[type="number"].mh-bt-qty-input { color:#000!important; background:#fff!important; opacity:1!important; -webkit-text-fill-color:#000!important; min-height:auto!important; max-width:2.4em!important; box-shadow:none!important; font-size:13px!important; font-weight:600!important; margin:0!important; padding:0!important; border-radius:0!important; -webkit-appearance:none!important; -moz-appearance:textfield!important; appearance:none!important; }
.mh-bt-qty-input::-webkit-inner-spin-button,.mh-bt-qty-input::-webkit-outer-spin-button { -webkit-appearance:none!important; display:none!important; margin:0!important; opacity:0!important; pointer-events:none!important; }
.mh-bt-widget input[type="number"].mh-bt-qty-input::-webkit-inner-spin-button,.mh-bt-widget input[type="number"].mh-bt-qty-input::-webkit-outer-spin-button { -webkit-appearance:none!important; display:none!important; margin:0!important; }
.mh-bt-qty-input:focus,.mh-bt-widget input[type="number"].mh-bt-qty-input:focus { outline:none!important; background:#fef8ee!important; color:#000!important; -webkit-text-fill-color:#000!important; box-shadow:none!important; }
.mh-bt-qty-suffix { font-size:0.8em; color:#b09060; font-weight:500; }

/* v2.7 #9: Qty-Pill Scale-Pop */
@keyframes mhBtQtyPop { 0%{transform:scale(1)} 50%{transform:scale(1.06)} 100%{transform:scale(1)} }
.mh-bt-product__qty-calc.mh-bt-qty-pop { animation:mhBtQtyPop .2s ease; }

/* v2.7: Qty-Explain — human-readable calculation */
.mh-bt-product__qty-explain { display:none; font-size:0.72em; color:#888; line-height:1.3; align-self:flex-start; }
.mh-bt-product__qty-explain.mh-bt-qty-explain--visible { display:flex; align-items:center; gap:0.3em; }
.mh-bt-product__qty-explain svg { flex-shrink:0; color:#bbb; }

/* Badges */
.mh-bt-badge { display:inline-block; font-size:0.65em; font-weight:500; padding:0.1em 0.4em; border-radius:8px; vertical-align:middle; }
/* v2.9.3: Main product — subtle 2px border, same image size as accessories */
.mh-bt-product--main .mh-bt-product__label { border-left:2px solid #f7af4a; border-radius:0; padding-left:calc(0.5em + 2px); }
.mh-bt-product--main .mh-bt-product__image { width:46px; height:46px; }
.mh-bt-product--main .mh-bt-product__image img { width:46px; height:46px; }

/* Stock Indicator */
.mh-bt-stock { display:inline-flex; align-items:center; gap:0.3em; font-size:0.75em; font-weight:500; margin-top:0.15em; align-self:flex-start; }
.mh-bt-stock--in { color:#27ae60; }
.mh-bt-stock--low { color:#e67e22; }
.mh-bt-stock--production { color:#3498db; }
.mh-bt-stock__dot { display:inline-block; width:6px; height:6px; border-radius:50%; flex-shrink:0; }
.mh-bt-stock--in .mh-bt-stock__dot { background:#27ae60; }
.mh-bt-stock--low .mh-bt-stock__dot { background:#e67e22; animation:mhBtPulse 1.5s ease-in-out infinite; }
.mh-bt-stock--production .mh-bt-stock__dot { background:#3498db; animation:mhBtPulse 2s ease-in-out infinite; }
@keyframes mhBtPulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

/* Counter Badge — very subtle */
.mh-bt-heading__count { display:inline-flex; align-items:center; justify-content:center; font-size:0.55em; font-weight:500; background:rgba(0,0,0,0.04); color:#aaa; padding:0.1em 0.45em; border-radius:8px; min-width:1.4em; text-align:center; transition:transform .2s ease; }
.mh-bt-heading__count.mh-bt-pop { transform:scale(1.2); }

/* Footer — v2.9.4: Compact */
.mh-bt-footer { margin-top:1em; padding:0.8em 0 0; border-top:none; position:relative; display:flex; flex-direction:column; gap:0.5em; background:transparent; border-radius:0; }
.mh-bt-footer::before { content:""; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent 5%,rgba(247,175,74,0.18) 30%,rgba(247,175,74,0.22) 50%,rgba(247,175,74,0.18) 70%,transparent 95%); border-radius:1px; }
.mh-bt-total { flex:1; display:flex; align-items:baseline; gap:0.4em; flex-wrap:wrap; }
.mh-bt-total__label { font-weight:600; font-size:0.9em; color:#666; }

/* Animated Price Counter — v2.9.3: balanced size */
.mh-bt-total__price { font-weight:700; font-size:1.35em; color:#2d2d2d; transition:color .6s ease; font-variant-numeric:tabular-nums; }
.mh-bt-total__price.mh-bt-price--down { color:#27ae60; }
.mh-bt-total__original { font-size:0.85em; color:#999; text-decoration:line-through; transition:opacity .25s ease; }

/* v2.9 E2: Savings Pill — single consolidated indicator */
.mh-bt-savings-pill { display:none; align-items:center; gap:0.25em; font-size:0.78em; font-weight:600; color:#1a7a42; background:rgba(39,174,96,0.08); padding:0.3em 0.7em; border-radius:4px; white-space:nowrap; }
.mh-bt-savings-pill.mh-bt-savings-pill--visible { display:inline-flex; }
.mh-bt-savings-pill svg { flex-shrink:0; color:#27ae60; }

/* CTA Button */
@keyframes mhBtCtaGlow { 0%,85%,100%{box-shadow:0 0 0 0 rgba(247,175,74,0)} 90%{box-shadow:0 0 12px 2px rgba(247,175,74,0.25)} 95%{box-shadow:0 0 6px 1px rgba(247,175,74,0.1)} }
.mh-bt-add-all { display:inline-flex; align-items:center; gap:0.5em; background:#f7af4a!important; color:#fff!important; border:none!important; padding:0.65em 1.2em!important; font-size:0.85em!important; font-weight:600!important; border-radius:6px!important; cursor:pointer; transition:background .3s ease,transform .15s ease; white-space:nowrap; text-transform:uppercase; letter-spacing:0.03em; width:100%; justify-content:center; box-sizing:border-box; position:relative; overflow:hidden; }
/* v2.9: Periodic glow pulse when bundle discount is reached */
.mh-bt-add-all--bundle-glow { animation:mhBtCtaGlow 4s ease-in-out infinite; }
.mh-bt-add-all::after { content:""; position:absolute; top:0; left:-40%; width:30%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,0.25),transparent); transition:left .6s ease; pointer-events:none; }
.mh-bt-add-all:hover { background:#e59a2f!important; }
.mh-bt-add-all:hover::after { left:120%; }
.mh-bt-add-all:active { transform:scale(0.98); }
.mh-bt-add-all:disabled { opacity:0.6; cursor:not-allowed; }
.mh-bt-add-all:disabled::after { display:none; }
.mh-bt-add-all svg { flex-shrink:0; transition:transform .2s ease; }
.mh-bt-add-all--success { background:#27ae60!important; }
.mh-bt-add-all--success:hover { background:#219a52!important; }
.mh-bt-add-all--success::after { display:none; }
.mh-bt-add-all__icon-cart,.mh-bt-add-all__icon-check,.mh-bt-add-all__text { pointer-events:none; position:relative; z-index:1; }

/* CTA Focus Ring */
.mh-bt-add-all:focus-visible { outline:2px solid #f7af4a; outline-offset:2px; }

/* Notice */
.mh-bt-notice { width:100%; padding:0.75em 1em; border-radius:6px; font-size:0.9em; font-weight:500; }
.mh-bt-notice--success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.mh-bt-notice--success a { color:#155724; font-weight:700; text-decoration:underline; }
.mh-bt-notice--error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

/* Responsive */
@media(max-width:768px) {
    .mh-bt-widget { padding:1em 0; }
    .mh-bt-product__image,.mh-bt-product__image img { width:40px!important; height:40px!important; }
    .mh-bt-product__label { padding:0.5em 0.3em; gap:0.5em; }
    .mh-bt-heading { font-size:0.95em; }
    .mh-bt-plus-separator__icon { width:20px; height:20px; font-size:12px; }
    .mh-bt-qty-sync-hint { font-size:0.65em; }
    /* v3.2.4: Sticky footer within widget on mobile */
    .mh-bt-footer { flex-direction:column; align-items:stretch; position:sticky; bottom:0; z-index:100; background:#fff; margin:0; padding:0.6em 0 0; border-top:1px solid #f0f0f0; box-shadow:0 -2px 8px rgba(0,0,0,0.05); border-radius:0; width:100%; }
    .mh-bt-total { flex-direction:row; align-items:baseline; gap:0.4em; width:100%; flex-wrap:wrap; }
    .mh-bt-total__label { font-size:0.8em; }
    .mh-bt-total__price { font-size:1.2em; }
    .mh-bt-total__original { font-size:0.8em; }
    .mh-bt-savings-pill { font-size:0.7em; white-space:nowrap; }
    .mh-bt-add-all { text-align:center; justify-content:center; font-size:0.9em!important; padding:0.7em 1em!important; border-radius:8px!important; }
}
@media(max-width:400px) {
    .mh-bt-footer { padding:0.5em 0 0; }
}

/* Reduced Motion */
@media(prefers-reduced-motion:reduce) {
    .mh-bt-product,.mh-bt-plus-separator { animation:none; opacity:1; }
    .mh-bt-checkbox:checked + .mh-bt-checkbox-custom { animation:none; }
    .mh-bt-stock--low .mh-bt-stock__dot { animation:none; }
    .mh-bt-stock--production .mh-bt-stock__dot { animation:none; }
    .mh-bt-add-all::after { display:none; }
    .mh-bt-product__qty-calc.mh-bt-qty-pop { animation:none; }
    .mh-bt-bundle-progress__fill { transition:none; }
    .mh-bt-bundle-progress__milestone.mh-bt-bundle-progress--reached { animation:none; }
    .mh-bt-add-all--bundle-glow { animation:none; }
    .mh-bt-preview-tooltip { transition:none; }
    .mh-bt-product.mh-bt-qty-highlight .mh-bt-product__label { animation:none; }
    .mh-bt-product__label:focus-within { animation:none; }
}

/* v3.2: Included Products Widget — matches BT widget DNA */
@keyframes mhIpSlideIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.mh-ip-widget { margin:1.5em 0; padding:1em 0; border:none; border-radius:0; background:transparent; width:100%; box-sizing:border-box; box-shadow:none; border-top:1px solid #eee; overflow:hidden; }
.mh-ip-heading { font-size:1.05em; margin:0 0 0.3em; padding-bottom:0; border-bottom:none; display:flex; align-items:center; gap:0.4em; flex-wrap:wrap; font-weight:600; }
.mh-ip-heading__count { font-size:0.72em; font-weight:400; color:#999; }
.mh-ip-products { display:flex; flex-direction:column; gap:0; }
.mh-ip-product { display:flex; align-items:center; gap:0.6em; padding:0.5em 0.35em; border:none; border-radius:0; border-bottom:1px solid #f5f5f5; background:transparent; opacity:0; animation:mhIpSlideIn .4s ease forwards; }
.mh-ip-product:nth-child(1) { animation-delay:.05s; }
.mh-ip-product:nth-child(2) { animation-delay:.12s; }
.mh-ip-product:nth-child(3) { animation-delay:.19s; }
.mh-ip-product:nth-child(4) { animation-delay:.26s; }
.mh-ip-product:nth-child(5) { animation-delay:.33s; }
.mh-ip-product:nth-child(6) { animation-delay:.40s; }
.mh-ip-product:last-child { border-bottom:none; }
.mh-ip-product__check { flex-shrink:0; width:18px; height:18px; border-radius:50%; background:#5cb85c; display:flex; align-items:center; justify-content:center; }
.mh-ip-product__image { flex-shrink:0; width:44px; height:44px; border-radius:6px; overflow:hidden; background:#fafafa; }
.mh-ip-product__image img { width:100%; height:100%; object-fit:cover; display:block; }
.mh-ip-product__image a { display:block; text-decoration:none; transition:opacity .2s ease; }
.mh-ip-product__image a:hover { opacity:0.8; }
.mh-ip-product__info { flex:1; min-width:0; display:flex; flex-direction:column; gap:0.1em; }
.mh-ip-product__name { font-size:0.82em; font-weight:500; line-height:1.3; word-break:break-word; }
.mh-ip-product__name a { color:inherit; text-decoration:none; }
.mh-ip-product__name a:hover { text-decoration:underline; }
.mh-ip-product__bottom { display:flex; align-items:baseline; gap:0.3em; flex-wrap:wrap; font-size:0.7em; color:#999; line-height:1.3; }
.mh-ip-product__price { color:#999; display:inline-flex; align-items:baseline; gap:4px; flex-wrap:wrap; }
.mh-ip-product__price * { margin:0; padding:0; }
.mh-ip-product__price del { color:#aaa; font-size:0.88em; font-weight:400; text-decoration:line-through; text-decoration-color:#ccc; }
.mh-ip-product__price del .woocommerce-Price-amount { color:#aaa; }
.mh-ip-product__price ins { text-decoration:none; font-weight:700; color:#555; font-size:1.05em; }
.mh-ip-product__sep { color:#ddd; }
.mh-ip-product__label { color:#5cb85c; font-weight:500; }
.mh-ip-product__desc { color:#bbb; }
.mh-ip-collapsible { display:flex; flex-direction:column; gap:0; min-width:0; overflow:hidden; max-height:2000px; transition:max-height .35s ease, opacity .25s ease; opacity:1; }
.mh-ip-collapsible--hidden { max-height:0 !important; opacity:0; transition:max-height .25s ease, opacity .15s ease; }
.mh-ip-show-more { display:flex; align-items:center; justify-content:center; gap:0.35em; background:rgba(0,0,0,0.025); border:none; border-radius:20px; padding:0.45em 1.2em; margin:0.6em auto 0.2em; font-size:0.75em; color:#999; cursor:pointer; transition:all .2s ease; width:auto; }
.mh-ip-show-more:hover { background:rgba(92,184,92,0.08); color:#5cb85c; }
.mh-ip-show-more__icon { transition:transform .25s ease; width:12px; height:12px; }
.mh-ip-show-more--open .mh-ip-show-more__icon { transform:rotate(180deg); }
.mh-ip-total { display:flex; align-items:center; justify-content:space-between; padding:0.5em 0.35em 0; margin-top:0.2em; border-top:1px solid #f5f5f5; }
.mh-ip-total__label { font-size:0.72em; color:#999; }
.mh-ip-total__price { font-size:0.82em; font-weight:600; color:#5cb85c; }
@media(max-width:480px) { .mh-ip-product__image { width:38px; height:38px; } }
@media(prefers-reduced-motion:reduce) { .mh-ip-product { animation:none; opacity:1; } }';
}

/* ==========================================================================
   AJAX: ADD TO CART (with per-product quantities)
   ========================================================================== */

function mh_bt_ajax_add_to_cart() {
    check_ajax_referer( 'mh_bt_add_to_cart', 'nonce' );

    $items = array();

    // New format: items = [{id, qty}, ...]
    if ( isset( $_POST['items'] ) && is_array( $_POST['items'] ) ) {
        foreach ( wp_unslash( $_POST['items'] ) as $item ) { // phpcs:ignore
            $pid = absint( $item['id'] ?? 0 );
            $qty = max( 1, absint( $item['qty'] ?? 1 ) );
            if ( $pid ) { $items[] = array( 'id' => $pid, 'qty' => $qty ); }
        }
    }
    // Backward compat: product_ids = [123, 456]
    if ( empty( $items ) && isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ) {
        foreach ( array_map( 'absint', wp_unslash( $_POST['product_ids'] ) ) as $pid ) {
            if ( $pid ) { $items[] = array( 'id' => $pid, 'qty' => 1 ); }
        }
    }

    if ( empty( $items ) ) {
        wp_send_json_error( __( 'Keine Produkte ausgewählt.', 'mh-bought-together' ) );
    }

    // v2.8: Determine bundle discount from source product (server-side, not from frontend).
    $source_product_id = absint( $_POST['source_product_id'] ?? 0 );
    $bundle_discount   = 0;
    $bundle_min_items  = 2;
    $bundle_id         = '';

    if ( $source_product_id ) {
        $disc_settings    = mh_bt_get_product_discount( $source_product_id );
        $bundle_discount  = intval( $disc_settings['discount'] );
        $bundle_min_items = intval( $disc_settings['min_items'] );
    }

    // Check if bundle discount conditions are met (enough items).
    $apply_discount = ( $bundle_discount > 0 && count( $items ) >= $bundle_min_items );
    if ( $apply_discount ) {
        $bundle_id = 'mhbt_' . $source_product_id . '_' . time();
    }

    $added  = 0;
    $errors = array();

    foreach ( $items as $item ) {
        $product = wc_get_product( $item['id'] );
        if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            $errors[] = sprintf( __( '%s ist nicht verfügbar.', 'mh-bought-together' ), $product ? $product->get_name() : '#' . $item['id'] );
            continue;
        }
        // Stock check — skip if backorders are allowed.
        $qty = $item['qty'];
        if ( $product->managing_stock() && ! $product->backorders_allowed() && $product->get_stock_quantity() < $qty ) {
            $avail = max( 0, $product->get_stock_quantity() );
            if ( $avail < 1 ) {
                $errors[] = sprintf( __( '%s ist ausverkauft.', 'mh-bought-together' ), $product->get_name() );
                continue;
            }
            $errors[] = sprintf( __( '%s: Nur %d auf Lager, entsprechend angepasst.', 'mh-bought-together' ), $product->get_name(), $avail );
            $qty = $avail;
        }

        // v2.8: Add bundle metadata to cart item.
        $cart_item_data = array();
        if ( $apply_discount ) {
            $cart_item_data['_mh_bt_bundle_id']       = $bundle_id;
            $cart_item_data['_mh_bt_bundle_discount']  = $bundle_discount;
            $cart_item_data['_mh_bt_source_product']   = $source_product_id;
        }

        if ( WC()->cart->add_to_cart( $item['id'], $qty, 0, array(), $cart_item_data ) ) {
            $added++;
        }
    }

    if ( $added > 0 ) {
        // Return cart fragments so the frontend can update the cart counter immediately.
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();

        $data = array(
            'added'     => $added,
            'errors'    => $errors,
            'discount_applied' => $apply_discount ? $bundle_discount : 0,
            'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array(
                'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
            ) ),
            'cart_hash' => WC()->cart->get_cart_hash(),
        );

        wp_send_json_success( $data );
    } else {
        wp_send_json_error( __( 'Produkte konnten nicht hinzugefügt werden.', 'mh-bought-together' ) );
    }
}

/* ==========================================================================
   v2.8: CART — Apply bundle discount as negative fee
   ========================================================================== */

/**
 * Restore bundle metadata when WooCommerce rebuilds cart from session.
 *
 * This is CRITICAL — without this filter, the custom _mh_bt_* keys
 * would be lost on page reload (navigating to cart/checkout).
 *
 * @param array  $cart_item   Cart item being restored.
 * @param array  $values      Raw session values for this item.
 * @param string $key         Cart item key.
 * @return array Cart item with bundle data restored.
 */
function mh_bt_restore_cart_item_from_session( $cart_item, $values, $key ) {
    if ( isset( $values['_mh_bt_bundle_id'] ) ) {
        $cart_item['_mh_bt_bundle_id'] = $values['_mh_bt_bundle_id'];
    }
    if ( isset( $values['_mh_bt_bundle_discount'] ) ) {
        $cart_item['_mh_bt_bundle_discount'] = $values['_mh_bt_bundle_discount'];
    }
    if ( isset( $values['_mh_bt_source_product'] ) ) {
        $cart_item['_mh_bt_source_product'] = $values['_mh_bt_source_product'];
    }
    return $cart_item;
}

/**
 * Calculate and apply bundle discounts in the cart.
 *
 * Groups cart items by their bundle ID, calculates the percentage discount
 * on the bundle subtotal, and adds it as a negative fee (Gebühr).
 *
 * @param WC_Cart $cart The WooCommerce cart object.
 */
function mh_bt_apply_bundle_discount( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    // Group cart items by bundle ID.
    $bundles = array();
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( empty( $cart_item['_mh_bt_bundle_id'] ) || empty( $cart_item['_mh_bt_bundle_discount'] ) ) {
            continue;
        }
        if ( ! isset( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
            continue;
        }
        $bid = sanitize_text_field( $cart_item['_mh_bt_bundle_id'] );
        if ( ! isset( $bundles[ $bid ] ) ) {
            $bundles[ $bid ] = array(
                'discount' => intval( $cart_item['_mh_bt_bundle_discount'] ),
                'source'   => intval( $cart_item['_mh_bt_source_product'] ?? 0 ),
                'subtotal' => 0,
                'count'    => 0,
            );
        }
        $bundles[ $bid ]['subtotal'] += floatval( $cart_item['data']->get_price() ) * intval( $cart_item['quantity'] );
        $bundles[ $bid ]['count']++;
    }

    // Build list of qualified bundle IDs (shared with badge display via static).
    $qualified = array();

    // Apply each bundle discount as a negative fee.
    foreach ( $bundles as $bid => $bundle ) {
        if ( $bundle['discount'] <= 0 || $bundle['subtotal'] <= 0 ) {
            continue;
        }

        // Re-validate min_items from source product.
        $min_items = 2;
        if ( $bundle['source'] ) {
            $disc_settings = mh_bt_get_product_discount( $bundle['source'] );
            $min_items     = intval( $disc_settings['min_items'] );
        }
        if ( $bundle['count'] < $min_items ) {
            continue; // Not enough items left — no discount.
        }

        $discount_amount = round( $bundle['subtotal'] * ( $bundle['discount'] / 100 ), 2 );
        if ( $discount_amount <= 0 ) {
            continue;
        }

        $qualified[ $bid ] = true;

        $fee_label = sprintf(
            /* translators: 1: discount percentage, 2: number of bundle items */
            __( 'Bundle-Rabatt -%d%% (%d Produkte)', 'mh-bought-together' ),
            $bundle['discount'],
            $bundle['count']
        );

        $cart->add_fee( $fee_label, -$discount_amount, true );
    }

    // Store qualified bundles for badge display.
    mh_bt_get_qualified_bundles( $qualified );
}

/**
 * Static store for qualified bundle IDs.
 * Called with array to set, called without to get.
 *
 * @param array|null $set If array, stores it. If null, returns current.
 * @return array Qualified bundle IDs.
 */
function mh_bt_get_qualified_bundles( $set = null ) {
    static $qualified = array();
    if ( is_array( $set ) ) {
        $qualified = $set;
    }
    return $qualified;
}

/**
 * Display bundle badge in cart and checkout for bundle items.
 * Only shows badge if the bundle actually qualifies for the discount.
 *
 * @param array $item_data  Existing item data for display.
 * @param array $cart_item  Cart item data.
 * @return array Modified item data.
 */
/**
 * Output complete bundle badge injection script in footer.
 * Fully theme-proof: finds cart rows by scanning product links for product IDs.
 * No WooCommerce hooks/filters needed — works with any theme.
 */
function mh_bt_cart_bundle_data_script() {
    if ( ! is_cart() && ! is_checkout() ) { return; }
    if ( ! WC()->cart ) { return; }

    $bundle_items = array();
    foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
        if ( empty( $cart_item['_mh_bt_bundle_id'] ) || empty( $cart_item['_mh_bt_bundle_discount'] ) ) { continue; }
        $qualified = mh_bt_get_qualified_bundles();
        if ( empty( $qualified[ $cart_item['_mh_bt_bundle_id'] ] ) ) { continue; }

        $product = $cart_item['data'] ?? null;
        $qty     = intval( $cart_item['quantity'] ?? 1 );
        $savings = 0;
        $permalink = '';
        if ( $product && is_a( $product, 'WC_Product' ) ) {
            $savings   = round( floatval( $product->get_price() ) * $qty * ( intval( $cart_item['_mh_bt_bundle_discount'] ) / 100 ), 2 );
            $permalink = $product->get_permalink();
        }

        $bundle_items[] = array(
            'product_id' => intval( $cart_item['product_id'] ),
            'discount'   => intval( $cart_item['_mh_bt_bundle_discount'] ),
            'savings'    => number_format( $savings, 2, ',', '.' ),
            'slug'       => $product ? $product->get_slug() : '',
        );
    }

    if ( empty( $bundle_items ) ) { return; }
    ?>
    <script type="text/javascript">
    (function(){
        var bundleData = <?php echo wp_json_encode( $bundle_items ); ?>;

        function injectBundleBadges() {
            if (!bundleData || !bundleData.length) return;

            /* Collect all product links once */
            var allLinks = document.querySelectorAll('a[href*="/produkt/"], a[href*="/product/"]');

            bundleData.forEach(function(item) {
                if (item._done) return;
                var slug = item.slug;
                if (!slug) return;

                /* Find ALL matching links for this product, then pick the name link (not image) */
                var nameLink = null;
                var anyLink = null;
                allLinks.forEach(function(link) {
                    var href = link.getAttribute('href') || '';
                    if (href.indexOf(slug) === -1) return;
                    anyLink = link;
                    /* Name link = has text content but no <img> child */
                    if (!link.querySelector('img') && link.textContent.trim().length > 2) {
                        nameLink = link;
                    }
                });

                var targetLink = nameLink || anyLink;
                if (!targetLink) {
                    console.log('[MH-BT] No link found for slug:', slug);
                    return;
                }

                /* Walk up to find a row-like container */
                var row = targetLink.closest('tr')
                       || targetLink.closest('.cart_item')
                       || targetLink.closest('.woocommerce-cart-form__cart-item')
                       || targetLink.closest('[class*="cart-item"]')
                       || targetLink.closest('[class*="cart_item"]');
                if (!row) return;
                if (row.dataset.mhBtBadged) return;
                row.dataset.mhBtBadged = '1';
                item._done = true;

                /* Green left border */
                row.style.borderLeft = '3px solid #27ae60';

                /* Insert badge right after the name link */
                var wrap = document.createElement('div');
                wrap.style.cssText = 'display:flex;flex-direction:column;gap:2px;margin-top:4px;margin-bottom:4px;';

                var badge = document.createElement('span');
                badge.style.cssText = 'display:inline-flex;align-items:center;gap:5px;background:#f0faf4;color:#1a7a42;font-size:0.78em;font-weight:600;padding:3px 8px;border-radius:4px;border:1px solid rgba(39,174,96,0.18);line-height:1.4;white-space:nowrap;width:fit-content;';
                badge.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#27ae60" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg><span>-' + item.discount + '% Bundle-Rabatt</span>';
                wrap.appendChild(badge);

                if (item.savings && item.savings !== '0,00') {
                    var sav = document.createElement('span');
                    sav.style.cssText = 'font-size:0.72em;color:#1a7a42;font-weight:500;padding-left:2px;';
                    sav.innerHTML = 'Du sparst <strong>' + item.savings + '&euro;</strong>';
                    wrap.appendChild(sav);
                }

                /* Insert badge in the right position */
                var nameCell = targetLink.closest('td.wc-block-cart-item__product')
                            || targetLink.closest('td.product-name')
                            || targetLink.closest('td')
                            || targetLink.parentElement;
                if (!nameCell) return;

                /* WC Blocks cart: insert after the first child div of wc-block-cart-item__wrap */
                var wrapDiv = nameCell.querySelector('.wc-block-cart-item__wrap');
                if (wrapDiv && wrapDiv.children.length > 0) {
                    /* First child contains name + price + SPARE — insert after it */
                    wrapDiv.children[0].after(wrap);
                    return;
                }

                /* Classic cart fallback: insert after SPARE badge or price */
                var insertRef = null;

                /* Look for SPARE text element */
                nameCell.querySelectorAll('span, div, p, strong').forEach(function(el) {
                    if (!insertRef && /SPARE|spare|Spare/.test(el.textContent) && el.textContent.length < 30) {
                        insertRef = el;
                    }
                });

                /* If no SPARE, look for price */
                if (!insertRef) {
                    insertRef = nameCell.querySelector('del, ins, .amount, [class*="price"]');
                }

                /* Last fallback: after the name link */
                if (!insertRef) {
                    insertRef = targetLink.parentElement !== nameCell ? targetLink.parentElement : targetLink;
                }

                /* Walk up to direct child of nameCell */
                while (insertRef && insertRef.parentElement && insertRef.parentElement !== nameCell) {
                    insertRef = insertRef.parentElement;
                }

                if (insertRef && insertRef.parentNode === nameCell) {
                    insertRef.after(wrap);
                } else {
                    nameCell.appendChild(wrap);
                }
            });

            /* Style fee rows */
            document.querySelectorAll('tr.fee th, tr.fee td, .fee-label').forEach(function(el) {
                if (el.dataset.mhBtStyled) return;
                var text = el.textContent || '';
                if (text.indexOf('Bundle-Rabatt') === -1) return;
                el.dataset.mhBtStyled = '1';
                el.style.color = '#1a7a42';
                el.style.fontWeight = '600';
            });
        }

        /* Run immediately + with delays for themes that render late */
        injectBundleBadges();
        setTimeout(injectBundleBadges, 500);
        setTimeout(injectBundleBadges, 1500);

        /* Also on DOM ready and WC AJAX events */
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', injectBundleBadges);
        }
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('updated_cart_totals updated_checkout', function() {
                /* Reset badges on AJAX cart update */
                bundleData.forEach(function(item) { item._done = false; });
                document.querySelectorAll('[data-mh-bt-badged]').forEach(function(el) { el.removeAttribute('data-mh-bt-badged'); });
                setTimeout(injectBundleBadges, 100);
            });
        }
    })();
    </script>
    <?php
}

/**
 * Style the fee row HTML in cart/checkout totals for bundle discounts.
 *
 * @param string $cart_totals_fee_html The fee HTML.
 * @param object $fee                  The fee object.
 * @return string Modified HTML.
 */
function mh_bt_style_fee_html( $cart_totals_fee_html, $fee ) {
    // Check if this is our bundle fee by name prefix.
    if ( strpos( $fee->name, 'Bundle-Rabatt' ) === false ) {
        return $cart_totals_fee_html;
    }
    // Wrap the amount in our styled span.
    $cart_totals_fee_html = str_replace(
        '<span class="woocommerce-Price-amount',
        '<span class="mh-bt-fee-amount woocommerce-Price-amount',
        $cart_totals_fee_html
    );
    return $cart_totals_fee_html;
}

/**
 * Save bundle meta to order line items for reference.
 *
 * @param WC_Order_Item_Product $item          Order item.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values        Cart item data.
 * @param WC_Order              $order         Order object.
 */
function mh_bt_save_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if ( ! empty( $values['_mh_bt_bundle_id'] ) ) {
        $item->add_meta_data( '_mh_bt_bundle_id', sanitize_text_field( $values['_mh_bt_bundle_id'] ), true );
    }
    if ( ! empty( $values['_mh_bt_bundle_discount'] ) ) {
        $item->add_meta_data( '_mh_bt_bundle_discount', intval( $values['_mh_bt_bundle_discount'] ), true );
    }
    if ( ! empty( $values['_mh_bt_source_product'] ) ) {
        $item->add_meta_data( '_mh_bt_source_product', intval( $values['_mh_bt_source_product'] ), true );
    }
}

/* ==========================================================================
   v2.9.5: TRACKING & ANALYTICS DASHBOARD
   ========================================================================== */

/**
 * AJAX handler for lightweight frontend event tracking.
 * Stores daily counters in wp_options as compact JSON.
 */
function mh_bt_ajax_track() {
    // Lightweight — no nonce check for tracking (fire-and-forget, no sensitive data).
    $type       = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
    $product_id = absint( $_POST['product_id'] ?? 0 );
    $source_id  = absint( $_POST['source_id'] ?? 0 );

    if ( ! $type || ! $product_id ) {
        wp_send_json_success();
        return;
    }

    if ( 'impression' === $type ) {
        // v3.1 Perf: Batch impressions in a short-lived transient, flush periodically.
        $buffer = get_transient( 'mh_bt_imp_buffer' );
        if ( ! is_array( $buffer ) ) { $buffer = array(); }

        $today = gmdate( 'Y-m-d' );
        if ( ! isset( $buffer[ $today ] ) ) { $buffer[ $today ] = 0; }
        $buffer[ $today ]++;

        // Flush to permanent option every ~50 impressions or if buffer is old.
        $buffer_count = array_sum( $buffer );
        if ( $buffer_count >= 50 ) {
            mh_bt_flush_impression_buffer( $buffer );
            delete_transient( 'mh_bt_imp_buffer' );
        } else {
            set_transient( 'mh_bt_imp_buffer', $buffer, 120 ); // 2 min TTL — auto-expires.
        }
    }

    if ( 'deselect' === $type && $product_id ) {
        // v3.2.6: Store deselections with daily timestamp for time-filtering.
        $ds    = get_option( 'mh_bt_deselections', array() );
        $today = gmdate( 'Y-m-d' );
        $key   = $product_id . ':' . $source_id;
        if ( ! isset( $ds[ $key ] ) ) {
            // Migrate legacy format (plain int) to dated format.
            $ds[ $key ] = array();
        } elseif ( ! is_array( $ds[ $key ] ) ) {
            // Legacy migration: convert old int count to undated bucket.
            $ds[ $key ] = array( '_legacy' => intval( $ds[ $key ] ) );
        }
        if ( ! isset( $ds[ $key ][ $today ] ) ) { $ds[ $key ][ $today ] = 0; }
        $ds[ $key ][ $today ]++;
        update_option( 'mh_bt_deselections', $ds, false );
    }

    wp_send_json_success();
}

/**
 * Flush buffered impressions to permanent option.
 * Merges buffer counts into the main mh_bt_impressions option.
 *
 * @param array $buffer Buffered impression counts keyed by date.
 */
function mh_bt_flush_impression_buffer( $buffer ) {
    if ( empty( $buffer ) ) { return; }

    $stats = get_option( 'mh_bt_impressions', array() );
    foreach ( $buffer as $date => $count ) {
        if ( ! isset( $stats[ $date ] ) ) { $stats[ $date ] = 0; }
        $stats[ $date ] += intval( $count );
    }

    // Keep only last 120 days.
    $cutoff = gmdate( 'Y-m-d', strtotime( '-120 days' ) );
    $stats  = array_filter( $stats, function( $v, $k ) use ( $cutoff ) { return $k >= $cutoff; }, ARRAY_FILTER_USE_BOTH );

    update_option( 'mh_bt_impressions', $stats, false );
}

/**
 * Flush any remaining impression buffer on shutdown.
 * Catches leftovers when buffer hasn't reached 50 and transient hasn't expired.
 */
add_action( 'shutdown', function() {
    // Only run during AJAX tracking requests to avoid overhead on normal pages.
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) { return; }

    $buffer = get_transient( 'mh_bt_imp_buffer' );
    if ( ! empty( $buffer ) && is_array( $buffer ) ) {
        // v3.2.6: Always flush remaining buffer — no cooldown, prevents data loss.
        mh_bt_flush_impression_buffer( $buffer );
        delete_transient( 'mh_bt_imp_buffer' );
    }
} );

/**
 * Query bundle order stats for a given period.
 *
 * @param int $days Number of days to look back (0 = all time).
 * @return array Associative stats array.
 */
function mh_bt_get_bundle_stats( $days = 30 ) {
    global $wpdb;

    $date_clause = '';
    if ( $days > 0 ) {
        $since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $date_clause = $wpdb->prepare( " AND p.post_date >= %s", $since );
    }

    // Get all order items with bundle meta from completed/processing orders.
    $results = $wpdb->get_results(
        "SELECT oi.order_id,
                oi.order_item_id,
                bm_id.meta_value   AS bundle_id,
                bm_disc.meta_value AS bundle_discount,
                bm_src.meta_value  AS source_product,
                oim_total.meta_value AS line_total,
                oim_qty.meta_value   AS qty,
                oi.order_item_name AS product_name,
                oim_pid.meta_value AS product_id
         FROM {$wpdb->prefix}woocommerce_order_items oi
         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta bm_id
             ON oi.order_item_id = bm_id.order_item_id AND bm_id.meta_key = '_mh_bt_bundle_id'
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta bm_disc
             ON oi.order_item_id = bm_disc.order_item_id AND bm_disc.meta_key = '_mh_bt_bundle_discount'
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta bm_src
             ON oi.order_item_id = bm_src.order_item_id AND bm_src.meta_key = '_mh_bt_source_product'
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_total
             ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty
             ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_pid
             ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
         INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
         WHERE oi.order_item_type = 'line_item'
           AND p.post_status IN ('wc-completed','wc-processing')
           {$date_clause}
         ORDER BY p.post_date DESC",
        ARRAY_A
    );

    // Also check HPOS (High-Performance Order Storage) if available.
    if ( empty( $results ) && class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
        $hpos = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        if ( $hpos ) {
            $date_clause_hpos = '';
            if ( $days > 0 ) {
                $date_clause_hpos = $wpdb->prepare( " AND o.date_created_gmt >= %s", $since . ' 00:00:00' );
            }
            $results = $wpdb->get_results(
                "SELECT oi.order_id,
                        oi.order_item_id,
                        bm_id.meta_value   AS bundle_id,
                        bm_disc.meta_value AS bundle_discount,
                        bm_src.meta_value  AS source_product,
                        oim_total.meta_value AS line_total,
                        oim_qty.meta_value   AS qty,
                        oi.order_item_name AS product_name,
                        oim_pid.meta_value AS product_id
                 FROM {$wpdb->prefix}woocommerce_order_items oi
                 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta bm_id
                     ON oi.order_item_id = bm_id.order_item_id AND bm_id.meta_key = '_mh_bt_bundle_id'
                 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta bm_disc
                     ON oi.order_item_id = bm_disc.order_item_id AND bm_disc.meta_key = '_mh_bt_bundle_discount'
                 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta bm_src
                     ON oi.order_item_id = bm_src.order_item_id AND bm_src.meta_key = '_mh_bt_source_product'
                 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_total
                     ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
                 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty
                     ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
                 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_pid
                     ON oi.order_item_id = oim_pid.order_item_id AND oim_pid.meta_key = '_product_id'
                 INNER JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
                 WHERE oi.order_item_type = 'line_item'
                   AND o.status IN ('wc-completed','wc-processing')
                   {$date_clause_hpos}
                 ORDER BY o.date_created_gmt DESC",
                ARRAY_A
            );
        }
    }

    // Aggregate.
    $bundles          = array();
    $total_revenue    = 0;
    $crosssell_revenue = 0;
    $total_discount   = 0;
    $combinations     = array();

    foreach ( $results as $row ) {
        $bid  = $row['bundle_id'];
        $line = floatval( $row['line_total'] );
        $disc = intval( $row['bundle_discount'] );
        $src  = intval( $row['source_product'] );
        $pid  = intval( $row['product_id'] );

        $total_revenue += $line;

        if ( ! isset( $bundles[ $bid ] ) ) {
            $bundles[ $bid ] = array( 'revenue' => 0, 'discount' => $disc, 'source' => $src, 'has_accessory' => false );
        }
        $bundles[ $bid ]['revenue'] += $line;

        // Track cross-sell items (accessory ≠ main product).
        if ( $src && $pid !== $src ) {
            $bundles[ $bid ]['has_accessory'] = true;
            $crosssell_revenue += $line;

            $combo_key = $src . ':' . $pid;
            if ( ! isset( $combinations[ $combo_key ] ) ) {
                $combinations[ $combo_key ] = array( 'source' => $src, 'linked' => $pid, 'count' => 0, 'linked_name' => $row['product_name'] );
            }
            $combinations[ $combo_key ]['count']++;
        }
    }

    // v3.2.6: Only count bundles that actually contain at least one accessory item.
    $valid_bundles = array_filter( $bundles, function( $b ) { return $b['has_accessory']; } );

    // Recalculate revenue/discount only for valid bundles.
    $valid_revenue  = 0;
    $total_discount = 0;
    foreach ( $valid_bundles as $b ) {
        $valid_revenue += $b['revenue'];
        if ( $b['discount'] > 0 && $b['revenue'] > 0 ) {
            $pre_discount    = $b['revenue'] / ( 1 - $b['discount'] / 100 );
            $total_discount += ( $pre_discount - $b['revenue'] );
        }
    }

    // Impressions.
    $impressions_data = get_option( 'mh_bt_impressions', array() );
    $total_impressions = 0;
    if ( $days > 0 ) {
        $since_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        foreach ( $impressions_data as $date => $count ) {
            if ( $date >= $since_date ) { $total_impressions += intval( $count ); }
        }
    } else {
        $total_impressions = array_sum( $impressions_data );
    }

    $bundle_count     = count( $valid_bundles );
    $conversion_rate  = $total_impressions > 0 ? round( ( $bundle_count / $total_impressions ) * 100, 1 ) : 0;
    $avg_bundle_value = $bundle_count > 0 ? round( $valid_revenue / $bundle_count, 2 ) : 0;

    // Sort combinations by count.
    usort( $combinations, function( $a, $b ) { return $b['count'] - $a['count']; } );

    return array(
        'bundle_count'      => $bundle_count,
        'total_revenue'     => round( $valid_revenue, 2 ),
        'crosssell_revenue' => round( $crosssell_revenue, 2 ),
        'total_discount'    => round( $total_discount, 2 ),
        'avg_bundle_value'  => $avg_bundle_value,
        'total_impressions' => $total_impressions,
        'conversion_rate'   => $conversion_rate,
        'combinations'      => array_slice( $combinations, 0, 15 ),
        'impressions_daily' => $impressions_data,
    );
}

/**
 * Get deselection stats — which products are most often unchecked.
 *
 * @param int $limit Max results.
 * @param int $days  Number of days to look back (0 = all time).
 * @return array Sorted deselection data.
 */
function mh_bt_get_deselection_stats( $limit = 10, $days = 0 ) {
    $ds = get_option( 'mh_bt_deselections', array() );
    if ( empty( $ds ) ) { return array(); }

    $since_date = $days > 0 ? gmdate( 'Y-m-d', strtotime( "-{$days} days" ) ) : '';

    // Aggregate counts per product (across all source products).
    $aggregated = array();
    foreach ( $ds as $key => $value ) {
        $parts = explode( ':', $key );
        $pid   = absint( $parts[0] ?? 0 );
        if ( ! $pid ) { continue; }

        $count = 0;
        if ( is_array( $value ) ) {
            // v3.2.6 format: array of date => count.
            foreach ( $value as $date => $c ) {
                if ( $since_date && '_legacy' !== $date && $date < $since_date ) { continue; }
                // Legacy bucket always included when no filter or all-time.
                if ( '_legacy' === $date && $since_date ) { continue; }
                $count += intval( $c );
            }
        } else {
            // Pre-v3.2.6 legacy: plain int (no date info — include only for all-time).
            if ( ! $since_date ) {
                $count = intval( $value );
            }
        }

        if ( $count <= 0 ) { continue; }

        if ( ! isset( $aggregated[ $pid ] ) ) {
            $aggregated[ $pid ] = 0;
        }
        $aggregated[ $pid ] += $count;
    }

    // Build output with product names.
    $items = array();
    foreach ( $aggregated as $pid => $count ) {
        $product = wc_get_product( $pid );
        $items[] = array(
            'product_id'   => $pid,
            'product_name' => $product ? $product->get_name() : '#' . $pid,
            'count'        => $count,
        );
    }
    usort( $items, function( $a, $b ) { return $b['count'] - $a['count']; } );
    return array_slice( $items, 0, $limit );
}

/**
 * Render the Bundle-Statistiken admin dashboard page.
 */
function mh_bt_stats_page_html() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }

    $period = sanitize_text_field( wp_unslash( $_GET['period'] ?? '30' ) );
    $days   = 'all' === $period ? 0 : absint( $period );
    if ( ! in_array( $days, array( 0, 7, 30, 90 ), true ) && 'all' !== $period ) { $days = 30; }
    $stats  = mh_bt_get_bundle_stats( $days );
    $ds     = mh_bt_get_deselection_stats( 10, $days );
    $period_label = $days > 0 ? sprintf( __( 'Letzte %d Tage', 'mh-bought-together' ), $days ) : __( 'Gesamt', 'mh-bought-together' );
    $currency     = get_woocommerce_currency_symbol();
    $page_url     = admin_url( 'admin.php?page=mh-bt-stats' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Bundle-Statistiken', 'mh-bought-together' ); ?></h1>
        <p class="description"><?php echo esc_html__( 'Umsatz, Conversion und Beliebtheit eurer "Häufig zusammen gekauft"-Bundles.', 'mh-bought-together' ); ?></p>

        <div style="margin:16px 0 24px; display:flex; gap:4px;">
            <?php foreach ( array( '7' => '7 Tage', '30' => '30 Tage', '90' => '90 Tage', 'all' => 'Gesamt' ) as $p => $label ) :
                $active = ( (string) $period === (string) $p ) || ( '30' === $p && ! isset( $_GET['period'] ) ); ?>
                <a href="<?php echo esc_url( add_query_arg( 'period', $p, $page_url ) ); ?>"
                   class="button <?php echo $active ? 'button-primary' : ''; ?>"
                   style="<?php echo $active ? '' : 'background:#f0f0f0;color:#555;border-color:#ddd;'; ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:32px;">
            <div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
                <div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;"><?php esc_html_e( 'Cross-Sell-Umsatz', 'mh-bought-together' ); ?></div>
                <div style="font-size:28px; font-weight:700; color:#2d2d2d;"><?php echo esc_html( number_format( $stats['crosssell_revenue'], 2, ',', '.' ) . ' ' . $currency ); ?></div>
                <div style="font-size:11px; color:#999; margin-top:4px;"><?php echo esc_html( number_format( $stats['total_revenue'], 2, ',', '.' ) . ' ' . $currency . ' Gesamt-Bundle' ); ?></div>
            </div>
            <div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
                <div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;"><?php esc_html_e( 'Bundles verkauft', 'mh-bought-together' ); ?></div>
                <div style="font-size:28px; font-weight:700; color:#2d2d2d;"><?php echo intval( $stats['bundle_count'] ); ?></div>
                <div style="font-size:11px; color:#999; margin-top:4px;"><?php esc_html_e( 'Abgeschlossene Bestellungen', 'mh-bought-together' ); ?></div>
            </div>
            <div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
                <div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;"><?php esc_html_e( 'Conversion-Rate', 'mh-bought-together' ); ?></div>
                <div style="font-size:28px; font-weight:700; color:<?php echo $stats['conversion_rate'] > 5 ? '#27ae60' : '#e67e22'; ?>;"><?php echo esc_html( $stats['conversion_rate'] . '%' ); ?></div>
                <div style="font-size:11px; color:#999; margin-top:4px;"><?php printf( esc_html__( '%s Brutto-Impressions → %s Bundles', 'mh-bought-together' ), number_format( $stats['total_impressions'], 0, ',', '.' ), intval( $stats['bundle_count'] ) ); ?></div>
            </div>
            <div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
                <div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;"><?php esc_html_e( 'Ø Bundle-Wert', 'mh-bought-together' ); ?></div>
                <div style="font-size:28px; font-weight:700; color:#2d2d2d;"><?php echo esc_html( number_format( $stats['avg_bundle_value'], 2, ',', '.' ) . ' ' . $currency ); ?></div>
                <div style="font-size:11px; color:#999; margin-top:4px;"><?php echo esc_html( number_format( $stats['total_discount'], 2, ',', '.' ) . ' ' . $currency . ' Rabatt gewährt' ); ?></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
            <div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
                <h3 style="margin:0 0 16px; font-size:14px; font-weight:600;"><?php esc_html_e( 'Beliebteste Bundle-Kombinationen', 'mh-bought-together' ); ?></h3>
                <?php if ( empty( $stats['combinations'] ) ) : ?>
                    <p style="color:#999; font-size:13px;"><?php esc_html_e( 'Noch keine Daten vorhanden.', 'mh-bought-together' ); ?></p>
                <?php else : ?>
                    <table class="widefat striped" style="border:0;">
                        <thead>
                            <tr>
                                <th style="font-size:12px;"><?php esc_html_e( 'Zubehör-Produkt', 'mh-bought-together' ); ?></th>
                                <th style="font-size:12px; text-align:right;"><?php esc_html_e( 'Verkauft', 'mh-bought-together' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $stats['combinations'] as $combo ) :
                                $src_product = wc_get_product( $combo['source'] );
                                $src_name    = $src_product ? $src_product->get_name() : '#' . $combo['source'];
                            ?>
                                <tr>
                                    <td style="font-size:13px;">
                                        <?php echo esc_html( $combo['linked_name'] ); ?>
                                        <span style="color:#bbb; font-size:11px;">← <?php echo esc_html( $src_name ); ?></span>
                                    </td>
                                    <td style="font-size:13px; text-align:right; font-weight:600;"><?php echo intval( $combo['count'] ); ?>×</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div style="background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px;">
                <h3 style="margin:0 0 16px; font-size:14px; font-weight:600;"><?php esc_html_e( 'Am häufigsten abgewählt', 'mh-bought-together' ); ?></h3>
                <?php if ( empty( $ds ) ) : ?>
                    <p style="color:#999; font-size:13px;"><?php esc_html_e( 'Noch keine Abwahl-Daten. Tracking startet ab jetzt.', 'mh-bought-together' ); ?></p>
                <?php else : ?>
                    <table class="widefat striped" style="border:0;">
                        <thead>
                            <tr>
                                <th style="font-size:12px;"><?php esc_html_e( 'Produkt', 'mh-bought-together' ); ?></th>
                                <th style="font-size:12px; text-align:right;"><?php esc_html_e( 'Abwahlen', 'mh-bought-together' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $ds as $d ) : ?>
                                <tr>
                                    <td style="font-size:13px;"><?php echo esc_html( $d['product_name'] ); ?></td>
                                    <td style="font-size:13px; text-align:right; font-weight:600; color:#e67e22;"><?php echo intval( $d['count'] ); ?>×</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <p style="margin-top:24px; font-size:12px; color:#bbb;">
            <?php esc_html_e( 'Bundle-Umsatz und Kombinationen werden aus Bestell-Metadaten berechnet. Impressionen und Abwahlen werden seit Plugin-Aktivierung getrackt.', 'mh-bought-together' ); ?>
        </p>
    </div>
    <?php
}
