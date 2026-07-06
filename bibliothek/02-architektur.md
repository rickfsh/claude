# Architektur-Konventionen (WordPress / WooCommerce)

Der gemeinsame „Hausstil" aller 5 Plugins. Neue Plugins folgen diesen Regeln,
außer es gibt einen dokumentierten Grund abzuweichen.

## 1. Grundgerüst & Bootstrapping

Jedes Plugin hat:

```php
<?php
/**
 * Plugin Name: MH <Name>
 * Description: ...
 * Version: 1.0.0
 * Author: Mega-Holz
 */
if ( ! defined( 'ABSPATH' ) ) exit;          // Pflicht-Guard, erste Zeile nach dem Header

define( 'MHXYZ_VERSION', '1.0.0' );          // immer Version-Konstante = Header-Version
define( 'MHXYZ_PATH', plugin_dir_path( __FILE__ ) );
define( 'MHXYZ_URL',  plugin_dir_url( __FILE__ ) );
```

- Klassen sind `final`, Einstieg über statisches `init()` / `boot()` (unten in der Datei
  oder auf `plugins_loaded`/`init` gehookt). Klassendateien heißen `class-<name>.php`.
- Moderne Einzelklassen-Plugins nutzen `declare(strict_types=1)` + typisierte Signaturen
  (shop-galerie, lazy-konfigurator); die großen Plugins klassischen PHPCS-Stil mit Doc-Blocks.
- **Changelog im Header-Doc-Block** pflegen (Version-für-Version, siehe shop-galerie v1.1.0→v1.2.9).

### Größen-Gradient — wann welche Struktur?
| Größe | Struktur | Vorbild |
|---|---|---|
| Mini-Utility / Seiten-Hack | Eine Datei, CSS/JS inline | `reference/mh-lazy-konfigurator/` |
| Ein Feature, überschaubar | Hauptdatei + `public/css` + `public/js` | `reference/mh-shop-galerie/` |
| Mittelgroß, mehrere Services | Hauptdatei + `includes/class-*.php` + `assets/` | `reference/mh-birthday-sale/` |
| Groß (AJAX, Admin, Analytics, CPT) | `includes/` + `admin/` + `public/` + `.min`-Pipeline | `reference/mh-spielturm-vergleich/`, `reference/mh-kp-gallery/` |

## 2. Prefix-Disziplin

Ein Kürzel pro Plugin, konsequent für **alles**: Konstanten (`MHXYZ_`), Klassen (`MH_XYZ_*`),
Funktionen (`mh_xyz_*`), CSS-Klassen (`.mhxyz-*`), Enqueue-Handles, Option-Namen,
AJAX-Actions, Localize-Objekte. Bestehende Kürzel (nicht wiederverwenden):
`MHSG` (Shop-Galerie), `MHBS` (Birthday), `MH_STV` (Spielturm), `MH_SH` (Spielhaus),
`MH_STL` (Shop-the-Look/kp-gallery), `MH_KONFIG` (Lazy-Konfigurator), `MH_BT` (Bought-Together).

**Anti-Pattern (aus mh-bought-together gelernt):** kein Zweit-Kürzel im selben Plugin —
das Lieferumfang-Widget nutzt dort `mh_ip_*` neben `mh_bt_*`. Ein Plugin = ein Kürzel;
Sub-Features bekommen ein Suffix (`mh_bt_ip_*`), kein eigenes Prefix.

## 3. Shortcodes

Registrierung entweder zentral in der Hauptdatei oder per Selbstregistrierung in der Klasse.
Immer `shortcode_atts()`. **Produkt-Kontext-Auflösung** ist das Standard-Pattern:

```php
public function shortcode_output( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'mh_xyz' );
    $product_id = absint( $atts['id'] );
    if ( ! $product_id && is_singular( 'product' ) ) {
        $product_id = get_queried_object_id();   // aktuelles WooCommerce-Produkt
    }
    if ( ! $product_id ) {
        return '<!-- MH XYZ: No product ID -->';  // Debug-Kommentar statt leerem String!
    }
    // ...
}
```

Bestehende Shortcodes: `[mh_shopgalerie]`, `[mh_spielturm_vergleich]`, `[mh_360_viewer]`,
`[mh_shop_the_look]`, `[mh_kundenprojekte_grid]`, `[mh_kundenprojekte_produkt]`, `[mh_upload_portal]`.

## 4. Asset-Loading — 3 erprobte Strategien

Hintergrund: **Oxygen Builder + WP Rocket** machen naives `wp_enqueue` unzuverlässig
(Details in `03-umgebung.md`). Je nach Kontext:

1. **Inline-on-Shortcode** (`reference/mh-shop-galerie/mh-shop-galerie.php:113-130`):
   CSS/JS per `file_get_contents` lesen und als `<style id>`/`<script id>` einmal pro Request
   (statischer `$done`-Guard) vor das Shortcode-HTML setzen. Robusteste Variante in
   Oxygen-Code-Blöcken. Bonus: `wp_head`-Preload (Priorität 1) mit `fetchpriority="high"`
   für das LCP-Bild.
2. **Buffer-Injection** (`reference/mh-lazy-konfigurator/mh-lazy-konfigurator.php:131-240`):
   `rocket_buffer`-Filter (Fallback `template_redirect` + `ob_start`), str_replace vor
   `</head>`/`</body>`. Nur für Seiten-Hacks ohne eigenes Markup-Rendering.
3. **Register + Lazy-Enqueue** (Standard für „richtige" Plugins,
   `reference/mh-spielturm-vergleich/includes/class-frontend.php:85-107`):
   `wp_register_*` auf `wp_enqueue_scripts`, **`wp_enqueue_*` erst im Shortcode-Callback**
   (mit `$already_enqueued`-Guard) → lädt nie auf Seiten ohne Shortcode.
   Alternativ Gates: `has_shortcode()`, `is_page($slugs)`, `is_cart() || is_checkout()`
   (birthday-sale: `includes/class-mhbs-assets.php:22-49`).

### PHP→JS-Brücke
Immer `wp_localize_script` in ein geprefixtes globales Objekt, immer mit `ajaxUrl`/REST-URL
und `wp_create_nonce`:

```php
wp_localize_script( 'mh-xyz', 'mhXyzData', array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'mh_xyz_nonce' ),
    // ...daten
) );
```

Alternativ (shop-galerie-Stil): Daten als JSON in `<script type="application/json">`/
Data-Attribut, im JS mit `try/catch` + `JSON.parse(dataEl.textContent)` lesen.

## 5. AJAX / REST

- **AJAX:** `wp_ajax_<action>` + `wp_ajax_nopriv_<action>`, im Handler **immer**
  `check_ajax_referer()`. Vorbild mit 5 Handlern:
  `reference/mh-spielturm-vergleich/includes/class-frontend.php:344-495`.
- **Rate-Limiting** bei schreibenden Aktionen: Transient pro Session, z.B. max. 12
  Add-to-Cart/Minute (`class-frontend.php:347-353`).
- **REST** (für CRUD-lastige Features): Namespace `mh-<kürzel>/v1`, `permission_callback`
  mit `current_user_can('edit_posts')` oder Team-Token. Vorbild inkl. WebP-Konvertierung
  und Upload: `reference/mh-kp-gallery/includes/class-rest-api.php`.
- **AJAX-Add-to-Cart** endet immer mit WooCommerce-Fragment-Event:
  `jQuery(document.body).trigger('added_to_cart', …)` (einzige erlaubte jQuery-Nutzung im Frontend).
- **Bundle-Rabatt = negative Fee, serverseitig validiert** — Client-Preise sind nur Vorschau.
  Cart-Item-Meta braucht den `woocommerce_get_cart_item_from_session`-Filter, sonst geht sie
  beim Reload verloren (`reference/mh-bought-together/mh-bought-together.php:2306`, „CRITICAL").
  Details: `features/cross-sell-warenkorb.md`.
- **Auch nopriv-Tracking-Endpoints** brauchen Nonce + Input-Validierung + Rate-Limit —
  `mh_bt_track` (ohne alles drei) ist das dokumentierte Anti-Pattern
  (`05-verbesserungen.md` #3).

## 6. Caching & Daten

- **Transients** für teure Produkt-Abfragen: Key `mh_xyz_base_<md5(gruppe)>`,
  TTL `HOUR_IN_SECONDS`, Invalidierung auf `woocommerce_update_product` / `save_post_product`
  per Bulk-`DELETE … WHERE option_name LIKE '_transient_mh_xyz_base_%'`
  (`reference/mh-spielturm-vergleich/includes/class-data-provider.php:137-321` + Hauptdatei `:76-94`).
  Zusätzlich statischer In-Memory-Cache pro Request.
- **Eigene DB-Tabelle** nur wenn nötig (Analytics): `dbDelta` bei Aktivierung, DB-Version
  als Option, atomare `INSERT … ON DUPLICATE KEY UPDATE`
  (`reference/mh-spielturm-vergleich/includes/class-analytics.php`).
  **Leichte Alternative ohne Tabelle:** Events in Transient buffern (Flush bei N Stück
  bzw. auf `shutdown` in eine Option, `autoload=false`, mit Pruning) —
  `reference/mh-bought-together/mh-bought-together.php:2657-2730`. Dann aber Keys
  deckeln/GC'en, sonst wächst die Option unbegrenzt.
- **CPT + Taxonomie** mit `show_in_rest => true`, Aktivierung flusht Rewrite-Rules
  (`reference/mh-kp-gallery/includes/class-cpt.php`).
- **Migrationen** einmalig, gated auf Versions-Option (`mh_stv_migrated_570`) auf `admin_init`
  (`reference/mh-spielturm-vergleich/mh-spielturm-vergleich.php:99-261`).

## 7. Settings / Admin

- **Settings API**: `register_setting` mit `sanitize_callback`, `settings_fields()`,
  `form-table`-Markup, Capability-Check (`manage_options` / `manage_woocommerce` / `edit_posts`).
- Menüplatzierung nach Produktfläche: Utility → `add_options_page`;
  WooCommerce-nah → `add_submenu_page('woocommerce', …)`; CPT-gebunden →
  Submenu unter `edit.php?post_type=<cpt>`.
- Bestes Admin-UX-Vorbild: farbiges **Status-Banner** (läuft/geplant/beendet/pausiert) +
  `datetime-local` normalisiert über `wp_timezone()`:
  `reference/mh-birthday-sale/includes/class-mhbs-settings.php:183-242`.
- Options-Hierarchie: Default < Admin-Option < Filter-Override
  (birthday-sale, z.B. Filter `mhbs_load_planner_override`).

## 8. Versionierung & Cache-Busting

- Header-Version = `*_VERSION`-Konstante = `$ver`-Argument aller `wp_enqueue_*`.
- **Gold-Standard** (spielturm, `includes/class-frontend.php:47-83`): Content-Hash-Busting
  `substr(md5_file($file), 0, 8)` — Query-String ändert sich nur bei echter Dateiänderung;
  `.min`-Auswahl über `SCRIPT_DEBUG`.
- Bei jeder Auslieferung an den Nutzer: Version in Header **und** Konstante bumpen,
  Changelog-Zeile ergänzen. (Bekannter Altfehler: lazy-konfigurator Header sagt 7.2.0,
  Ordner v7_2_2 — Header nicht mitgezogen.)

## 9. JavaScript-Grundregeln (Frontend)

- **100 % Vanilla ES5**, kein Framework, kein Build-Step. IIFE + `'use strict'`.
  jQuery **nur** für WooCommerce-Fragment-Events (und in Admin-Tools).
- Init-Idiom (readyState-sicher):
  ```js
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  ```
- **Multi-Instanz-fähig:** `initAll()` scannt `[data-mhxyz]`-Roots, Re-Init-Guard per
  `data`-Flag (`reference/mh-shop-galerie/public/js/mh-shop-galerie.js:339-341,627-636`).
- **Event-Delegation** für AJAX-nachgeladene Elemente: ein `document`-Listener +
  `e.target.closest('.mh-xyz-card')` (kp-gallery `assets/js/frontend.js:1393`).
- **XSS-sicher:** kleines `esc()` via `createTextNode` überall, wo Strings in `innerHTML` landen.
- Gebundene Handler referenzieren und in `destroy()` per `removeEventListener` lösen
  (360-Viewer als Vorbild für saubere create/destroy-API).
