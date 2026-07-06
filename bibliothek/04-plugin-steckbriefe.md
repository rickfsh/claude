# Plugin-Steckbriefe

Kurzprofil jedes Plugins in `reference/` — was es tut, was darin besonders gut gelungen ist,
und wofür es als Vorlage taugt.

---

## mh-shop-galerie — v1.2.9
**Zweck:** Produktbild-Galerie für Shop-Seiten (Hero + Thumbnail-Strip + Lightbox).
**Struktur:** Eine finale Klasse in der Hauptdatei, Assets unter `public/css`/`public/js`. `declare(strict_types=1)`.
**Shortcode:** `[mh_shopgalerie product_id="…"]` (0 = aktuelles Produkt).
**Highlights / als Vorlage für:**
- **Sauberste Galerie + Lightbox-Kombi** — das `LB`-Singleton-Modul (`public/js/mh-shop-galerie.js:24-336`) ist die Referenz-Lightbox: Click-Zoom zum Klickpunkt, Wheel-Zoom, Pinch, Pan, Peek-Swipe, Swipe-down-close, Thumbnails, Tastatur, A11y.
- **Custom Thumbnail-Scrollbar** mit Drag/Track-Jump (`:544-621`).
- **Inline-Asset-Strategie** für Oxygen (Header-Kommentar erklärt warum) + LCP-Preload mit `fetchpriority="high"`.
- Detailliertes Header-Changelog (v1.1.0→v1.2.9).
**Anmerkung:** Header sagt „1:1-Port der Spielhaus-Galerie, Farben auf Mülltonnenbox-Design gemappt" — gutes Beispiel fürs Forken bestehender Plugins.

---

## mh-birthday-sale — v1.7.0
**Zweck:** Zeitgesteuerte Sale-Aktion (Countdown, Rabatt-Anzeige in Cart/Checkout, Planner-Override).
**Struktur:** Multi-File — Hauptdatei + 5 `includes/class-mhbs-*.php` + `assets/`. Bringt eigenes `CLAUDE.md` mit.
**Highlights / als Vorlage für:**
- **Bestes Admin-UI:** Settings-Seite mit farbigem Status-Banner (läuft/geplant/beendet/pausiert), `datetime-local` mit `wp_timezone()`-Normalisierung (`includes/class-mhbs-settings.php:183-242`).
- **Bedingtes Asset-Loading** nach Kontext + Zeitfenster: nur `is_cart()/is_checkout()` und nur während `mhbs_is_active_window()` (`includes/class-mhbs-assets.php:22-49`).
- Options-Hierarchie Default < Option < Filter (`mhbs_load_planner_override`).
- **DOM-only Preis-Overlay** per MutationObserver (`assets/js/planner-override.js`) — Pattern, um fremde Seiten-UI zu ergänzen ohne deren Code anzufassen.

---

## mh-spielturm-vergleich — v5.42.0
**Zweck:** Produktvergleich/Konfigurator für Spieltürme & Spielhäuser (Ausstattungslevel, Vergleichsmatrix, 360°-Viewer, Analytics). Das größte und reifste Plugin.
**Struktur:** `includes/` (6 Klassen) + `admin/` (2) + `public/` mit `.min`-Varianten. Eigenes `CLAUDE.md`.
**Shortcodes:** `[mh_spielturm_vergleich id="…"]`, `[mh_360_viewer id="…" autoplay="true"]`.
**Highlights / als Vorlage für:**
- **Fortschrittlichste Lightbox** (`public/js/spielturm-vergleich.js:3085-3827`): achsen-gelocktes Peek-Swipe, Double-Tap-Zoom, Velocity-Commit, 360°-Slide in der Lightbox, `requestIdleCallback`-Prebuild.
- **360°-Viewer** (`public/js/spielturm-360.js`): Batch-Preload (8 parallel), rAF-Drag, EMA-Velocity, Momentum mit Friction, Smoothstep-Autoplay, IntersectionObserver-Sichtbarkeit, saubere `MH360.create/destroy`-API.
- **Vergleichsmatrix** data-driven als CSS-Grid (`spielturm-vergleich.js:1619-1766`).
- **ARIA-Tabs** mit Pfeiltasten-Nav + Content-Cache (`:1330-1616`).
- **Transient-Caching + Invalidierung**, Rate-Limiting, **eigene Analytics-Tabelle** (dbDelta, atomare Upserts, CSV-Export), **versionsgated Migrationen**.
- **Content-Hash-Cache-Busting** + `.min`/`SCRIPT_DEBUG` (`includes/class-frontend.php:47-83`).
- Enthält auch den **Spielhaus-Konfigurator** (`public/js/spielhaus-konfigurator.js`, siehe `features/konfigurator.md`).

---

## mh-lazy-konfigurator — v7.2.2
**Zweck:** Lazy-Loading-Wrapper für den (schweren) 3D-Konfigurator — BabylonJS-Scripts werden aus dem Markup gestrippt und erst bei Klick geladen; Splash-Screen davor.
**Struktur:** **Echtes Single-File-Plugin** — PHP, CSS, JS, HTML als Heredoc in einer Datei. Kein Shortcode, kein Enqueue.
**Highlights / als Vorlage für:**
- **Buffer-Injection-Strategie**: `rocket_buffer`-Filter (Fallback `ob_start`), str_replace vor `</head>`/`</body>` (`mh-lazy-konfigurator.php:131-240`).
- Pattern „schweres Fremd-Script erst bei Interaktion laden".
- Settings-Minimalfall: eine Option (`mh_konfig_show_splash`) über `add_options_page`.
**Bekannter Makel:** Header-Version 7.2.0 ≠ ausgelieferte Version 7.2.2 — Mahnung, bei jedem Release Header + Konstante zu bumpen.

---

## mh-kp-gallery („MH Shop the Look") — v3.8.5
**Zweck:** Kundenprojekte-Galerie: Masonry-Grid mit Filter, „Shop the Look"-Slider mit Produkt-Pins, Produktseiten-Galerie, Upload-Portal mit Team-Login.
**Struktur:** 9 `includes/class-*.php` + `assets/`. CPT `kundenprojekt` + Taxonomie `mh_projekt_kategorie`. Interner Prefix `MH_STL` (Text-Domain `mh-stl`) — Achtung: weicht vom Slug ab.
**Shortcodes:** `[mh_shop_the_look]`, `[mh_kundenprojekte_grid per_page="12" columns="3"]`, `[mh_kundenprojekte_produkt]`, `[mh_upload_portal title="…"]`.
**Highlights / als Vorlage für:**
- **REST-API mit vollem CRUD** (`includes/class-rest-api.php`): Namespace `mh-stl/v1`, Team-Token-Auth (Transient, `wp_generate_password(40)`), serverseitige WebP-Konvertierung (GD/Imagick), optionaler FTP-Upload.
- **Masonry/Grid-Patterns** (`assets/js/frontend.js`): FLIP-Append (`:782-867`), AJAX Load-More mit Skeletons + min-height-Lock (`:1287-1390`), Stagger-Reveal per IntersectionObserver (`:1161-1199`), Sticky-Filter mit gleitendem Pill-Indicator (`:1085-1159`).
- **Bidirektionaler Hover-Sync** Pin ↔ Produktzeile ↔ Thumbnail (`:707-769`, `:81-115`).
- **Mobile Bottom-Sheet** mit Swipe-to-dismiss (`:545-684`), scrollbar-sprungfreier Scroll-Lock (`:10-25`).
- Crossfade-Bildwechsel mit Ghost-Clone (`:961-1032`), Morph-Open-Lightbox.

---

## mh-bought-together („MH Häufig zusammen gekauft") — v3.2.6
**Zweck:** WooCommerce Cross-Sell-Widget „Wird oft zusammen gekauft" mit Mengenformel (`ceil(Hauptmenge × Multiplikator + Offset)`), Bundle-Rabatt und eigenem Analytics-Dashboard; plus rein informatives „Im Lieferumfang enthalten"-Widget (grünes Checklist-Design).
**Struktur:** Faktisch **monolithisch** — eine prozedurale 3072-Zeilen-Hauptdatei (keine Klassen, kein `includes/`), `assets/css|js`, `uninstall.php`, README mit Changelog. Zweit-Kürzel `mh_ip_*` fürs Lieferumfang-Widget (Anti-Pattern, siehe `02-architektur.md` §2).
**Shortcodes:** `[mh_bought_together product_id="…"]`, `[mh_included_products product_id="…"]` (Achtung: Attribut heißt `product_id`, nicht `id`).
**Highlights / als Vorlage für:**
- **Bundle-Rabatt als negative WC-Fee** mit serverseitiger Neuvalidierung gegen Manipulation und `woocommerce_get_cart_item_from_session`-Filter, damit die Cart-Item-Meta einen Reload überlebt (`mh-bought-together.php:2306`, Kommentar „CRITICAL").
- **Theme-proofe Cart-Badge-Injection** (`:2422-2593`): findet Produktzeilen per Slug-Scan, unterstützt Classic-Cart **und** WC-Blocks, re-injiziert auf `updated_cart_totals`.
- **DB-tabellenlose Analytics**: Impressions in Transient gebuffert (120s TTL), Flush bei 50 Stück bzw. auf `shutdown` in Option, 120-Tage-Pruning, Legacy-Migration (`:2657-2730`); Stats-Dashboard mit **HPOS-Fallback**-Query (`:2780`).
- **Mengenformel-Engine + injizierte Qty-Stepper** mit Live-Sync zum WC-Mengenfeld; animierter Preis-Counter (rAF + Cubic-Easing), Zwei-Stufen-Grün-Animation bei Rabatt, Milestone-Progressbar.
- **Integrationsvertrag für Fremd-Plugins**: `window.mhBtInit` / `window.mhBtReinit` (`assets/js/mh-bt-frontend.js:415`) — genau darüber bettet mh-spielturm-vergleich das Widget live ein (`mh_stv_refresh_bt` → Re-Render → `mhBtReinit()`).
**Anmerkungen / Makel (siehe `05-verbesserungen.md`):** jQuery statt Vanilla ES5; **CSS/JS existieren doppelt** (Datei + veralteter Inline-Fallback in `mh_bt_get_frontend_css()`/`mh_bt_print_footer_script` — gedriftet, Inline-JS exponiert `mhBtReinit` NICHT); Tracking-Endpoint `mh_bt_track` ohne Nonce; `uninstall.php` unvollständig (lässt `mh_bt_impressions`, `mh_bt_deselections` u.a. zurück); kein Open Sans, kein `is-*`, keine CSS-Variablen, markenfremdes Blau `#3498db`.
