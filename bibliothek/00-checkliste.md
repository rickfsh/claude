# Checkliste für jedes neue Plugin

Diese Liste bei **jedem** neuen Plugin (und jedem größeren Feature) durchgehen.
Ziel: keine Iteration für Dinge, die schon gelöst sind.

## 0. Vor dem ersten Code

- [ ] `bibliothek/04-plugin-steckbriefe.md` gelesen — gibt es ein bestehendes Plugin,
      das als Fork-Basis taugt? (shop-galerie war z.B. ein 1:1-Port der Spielhaus-Galerie)
- [ ] Für jedes gewünschte Feature `bibliothek/features/` geprüft — **nichts neu erfinden,
      was in `reference/` schon gelöst ist**. Referenz-Implementierung als Vorlage öffnen.
- [ ] Größe einschätzen und Struktur wählen (Gradient in `02-architektur.md` §1).

## 1. Setup & Namensgebung

- [ ] Plugin-Name `MH <Name>`, Author `Mega-Holz`, Slug `mh-<name>`.
- [ ] **Neues, eindeutiges Kürzel** gewählt (nicht: MHSG, MHBS, MH_STV, MH_SH, MH_STL, MH_KONFIG)
      und konsequent für Konstanten, Klassen, CSS, Handles, Options, AJAX-Actions verwendet.
- [ ] `ABSPATH`-Guard, `*_VERSION`/`*_PATH`/`*_URL`-Konstanten, finale Klasse mit `init()`.
- [ ] Changelog-Block im Header angelegt.

## 2. Styling

- [ ] CSS-Variablen-Startblock aus `01-design-tokens.md` §7 übernommen
      (Akzent `#FAA41A`/Hover `#FF9000` bzw. themebar über `--mh-<kürzel>-accent`).
- [ ] Open Sans, Gewichte 400/700/800, Preise mit `tabular-nums`.
- [ ] Buttons/Cards/Pills nach `01-design-tokens.md` §3–4 (flache Akzent-Buttons,
      Cards 12–14px Radius, `is-*` State-Klassen, Inset-Ring für „ausgewählt").
- [ ] Breakpoint-Leiter 1024→768→600→480; `prefers-reduced-motion` + `(hover: hover)`-Guards.
- [ ] An `<body>` angehängte Elemente (Lightbox, Sticky-CTA): Tokens dort erneut deklariert.
- [ ] Alles unter Root-Klasse gescoped + `box-sizing`-Reset.

## 3. Architektur

- [ ] Asset-Strategie bewusst gewählt (`02-architektur.md` §4) — Oxygen/WP-Rocket-sicher
      (`03-umgebung.md`).
- [ ] Shortcode mit `shortcode_atts` + Produkt-Kontext-Auflösung + Debug-HTML-Kommentar
      bei fehlender ID.
- [ ] PHP→JS über `wp_localize_script` (geprefixtes Objekt, `ajaxUrl` + Nonce).
- [ ] AJAX-Handler mit `check_ajax_referer`; schreibende Aktionen rate-limitiert.
- [ ] Teure Abfragen in Transients, Invalidierung an `woocommerce_update_product`/`save_post_product`.
- [ ] Settings über Settings API mit `sanitize_callback` + Capability-Check;
      Menüplatzierung nach Produktfläche.

## 4. JavaScript

- [ ] Vanilla ES5, IIFE + `'use strict'`, kein Framework/Build-Step.
      jQuery nur für WooCommerce-Fragment-Events.
- [ ] readyState-sicheres Init; Multi-Instanz über `[data-…]`-Scan mit Re-Init-Guard.
- [ ] Event-Delegation für dynamische Elemente.
- [ ] `esc()` für alles, was in `innerHTML` landet.
- [ ] Touch: Achsen-Locking, `passive` korrekt, Click-nach-Drag unterdrückt
      (`features/mobile-gesten.md`).

## 5. Qualität & Performance

- [ ] Performance-Checkliste durchgegangen (`features/performance.md`).
- [ ] A11y: Fokus-Ring (`outline: 2px solid var(--_a)`), `role="dialog"`/`aria-modal`
      bei Overlays, Tastatur-Navigation, Fokus-Restore.
- [ ] Umgebungs-Checkliste durchgegangen (`03-umgebung.md`).
- [ ] Mobil getestet gedacht: Bottom-Sheets statt Popups, Sticky-CTA unten,
      Filterleisten scrollbar.

## 6. Release

- [ ] Version in Header **und** Konstante gebumpt (Altfehler lazy-konfigurator!),
      Changelog-Zeile ergänzt.
- [ ] Zip-Name: `mh<name>v<version>.zip` (Konvention der bisherigen Lieferungen).
- [ ] Nach Abnahme: `/bibliothek-update` ausführen, damit neue Learnings in diese
      Bibliothek zurückfließen.
