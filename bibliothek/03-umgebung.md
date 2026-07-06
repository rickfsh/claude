# Umgebung: Oxygen Builder, WP Rocket, WooCommerce

Die Ziel-Site (mega-holz.de) läuft mit **Oxygen Builder**, **WP Rocket** und **WooCommerce**.
Diese Kombination hat konkrete Konsequenzen — mehrere Plugins existieren in ihrer heutigen
Form nur wegen dieser Stolperfallen.

## Oxygen Builder

- Oxygen rendert Code-Blöcke **nach `wp_head`** → klassisches `wp_enqueue_style` aus einem
  Shortcode heraus kommt zu spät bzw. wird nicht ausgegeben.
- **Lösung 1 (bewährt):** CSS/JS inline mit dem Shortcode-HTML ausliefern
  (`<style id="…">`/`<script id="…">`, einmal pro Request via statischem Guard) —
  siehe `reference/mh-shop-galerie/mh-shop-galerie.php` (Begründung im Header-Kommentar).
- **Lösung 2:** `wp_register_*` früh auf `wp_enqueue_scripts`, `wp_enqueue_*` im
  Shortcode-Callback — funktioniert, wenn der Shortcode über `do_shortcode`/Template läuft
  (spielturm-Weg).
- Theme-/Builder-CSS kann aggressiv reinfunken (z.B. Monospace auf alles): im Zweifel
  Font-Stack mit `!important` auf einer langen Selektorliste durchsetzen
  (kp-gallery `assets/css/frontend.css:662-678`).

## WP Rocket

- **„Remove Unused CSS" / „Delay JS"** kann enqueued Assets entfernen oder verzögern →
  Inline-Auslieferung (Lösung 1 oben) umgeht beides.
- **`rocket_buffer`-Filter** ist der saubere Hook, um die fertige Seiten-HTML zu
  transformieren (Fallback: `template_redirect` + `ob_start`):
  `reference/mh-lazy-konfigurator/mh-lazy-konfigurator.php:131-240`.
  Dort auch das Pattern „schwere Fremd-Scripts (BabylonJS) aus dem Markup strippen und
  erst bei Klick nachladen".
- Cache-Busting über Versions-Query-String funktioniert mit Rocket; Content-Hash
  (`md5_file`) ist die zuverlässigste Variante (siehe `02-architektur.md` §8).

## WooCommerce

- **Produkt-Kontext:** aktuelles Produkt via `is_singular('product')` +
  `get_queried_object_id()` bzw. global `$product` auflösen (Standard-Shortcode-Pattern).
- **AJAX-Add-to-Cart:** nach Erfolg `jQuery(document.body).trigger('added_to_cart', [fragments, cart_hash, $button])`
  feuern, damit Mini-Cart & Co. sich aktualisieren — Vorbild:
  `reference/mh-spielturm-vergleich/includes/class-frontend.php` und
  `reference/mh-spielturm-vergleich/public/js/spielhaus-konfigurator.js:579-657`.
- **Bundles/Rabatte:** clientseitige Preisanzeige ist nur Vorschau; der echte Rabatt wird
  serverseitig als negative Fee angewendet (Konfigurator-Pattern).
- **Cache-Invalidierung** an Produkt-Hooks binden: `woocommerce_update_product`,
  `save_post_product`.
- Asset-Gates für Cart/Checkout: `is_cart() || is_checkout()`
  (`reference/mh-birthday-sale/includes/class-mhbs-assets.php`).

## Checkliste Umgebungs-Kompatibilität (bei jedem neuen Plugin)

- [ ] Lädt das CSS/JS auch, wenn der Shortcode in einem Oxygen-Code-Block steckt?
- [ ] Überlebt es WP Rocket „Delay JS" + „Remove Unused CSS"? (im Zweifel inline)
- [ ] Preload/`fetchpriority="high"` fürs LCP-Bild gesetzt?
- [ ] Assets laden NUR dort, wo sie gebraucht werden (Shortcode-/Seiten-Gate)?
- [ ] Font-Stack gegen Theme-Overrides abgesichert?
- [ ] WooCommerce-Fragment-Events nach Cart-Aktionen gefeuert?
