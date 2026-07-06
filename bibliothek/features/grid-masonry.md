# Grid / Masonry mit Filter & Load-More

**Referenz:** `reference/mh-kp-gallery/assets/js/frontend.js` + `assets/css/frontend.css`
(Shortcode `[mh_kundenprojekte_grid per_page="12" columns="3"]`).

## Kern-Techniken

- **CSS-Column-Masonry** (kein JS-Layout nötig).
- **FLIP-Append** für ruckelfreies Nachladen (`mhFlipAppend`, `frontend.js:782-867`):
  Rects snapshotten → neue Cards versteckt einfügen → Scroll-Drift korrigieren →
  invertierte Transforms setzen → über 400ms in Endposition spielen.
- **AJAX Load-More** (`loadGridProjects`, `:1287-1390`):
  - **Skeleton-Platzhalter** mit variierenden Höhen während des Ladens (`:1308-1315`)
  - **min-height-Lock** auf dem Container, damit das Layout beim Lazy-Load nicht kollabiert (`:1341-1379`)
  - Filter-Wechsel = Crossfade des Grids
- **Reveal-on-Scroll mit Stagger**: IntersectionObserver sammelt eintretende Cards in eine
  Queue und enthüllt sie per `setInterval` im 60–80ms-Abstand (`initRevealObserver`, `:1161-1199`).
- **Sticky-Filterleiste** über IntersectionObserver-Sentinel (`initStickyFilters`, `:1085-1101`);
  mobil horizontal scrollbar mit Edge-Fade-Erkennung.
- **Gleitender Pill-Indicator** unter dem aktiven Filter-Button — animiert
  `left/top/width/height` (`moveFilterIndicator`, `:1132-1159`).
- **Event-Delegation** (ein `document`-Click-Listener + `closest('.mh-grid-card')`) —
  AJAX-nachgeladene Cards brauchen kein Re-Binding (`:1393`).
- Lightbox-Öffnung aus der Card mit **Morph-Effekt** (siehe `features/lightbox.md`).

## Serverseite

AJAX-Action `mh_stl_grid_load` (+`nopriv`) in `reference/mh-kp-gallery/mh-kp-gallery.php:35-44`,
Rendering in `includes/class-grid.php`. CPT `kundenprojekt` + Taxonomie als Filterbasis.

## Stolperfallen

- Ohne min-height-Lock springt die Seite beim Nachladen (CLS).
- Ohne Scroll-Drift-Korrektur im FLIP „ruckt" der Viewport beim Append.
- Skeletons mit identischer Höhe sehen künstlich aus — Höhen variieren.
