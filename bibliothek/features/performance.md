# Performance-Patterns

Erprobt in allen 5 Plugins — bei jedem neuen Plugin durchgehen.

## Laden & Sichtbarkeit

- **IntersectionObserver** für: 360°-Autoplay nur wenn sichtbar (`threshold: 0.3`,
  `reference/mh-spielturm-vergleich/public/js/spielturm-360.js:248-260`),
  Reveal-on-Scroll, Sticky-Sentinels (`spielturm-vergleich.js:3018`).
- **Nachbar-Preload** in jeder Galerie/Lightbox (`new Image().src`);
  360°-Frames in Batches à 8 mit Live-%-Anzeige (`spielturm-360.js:186-230`).
- **Lazy-Detail-Loading**: Beschreibung/Attribute/Reviews erst bei Bedarf per AJAX,
  dann cachen (`loadProductDetail`, `spielturm-vergleich.js:343`).
- **LCP-Preload** im `wp_head` (Priorität 1) mit `fetchpriority="high"`
  (`reference/mh-shop-galerie/mh-shop-galerie.php`, `preload_hero`).
- Schwere Fremd-Scripts (z.B. BabylonJS) aus dem Markup strippen und **erst bei Klick**
  laden (`reference/mh-lazy-konfigurator/mh-lazy-konfigurator.php:131-240`).
- **Widget-Lazy-Init** unterhalb des Folds: IntersectionObserver mit `rootMargin: 200px`,
  Init erst bei Annäherung (`reference/mh-bought-together/assets/js/mh-bt-frontend.js:420`,
  `mhBtLazyInit`).

## Rendering & Jank-Vermeidung

- **rAF-Throttling**: Drag-Rendering (`dragRAF`, `spielturm-360.js:398-408`),
  Scroll/Resize-Sync mit `raf`-Flag (`mh-shop-galerie.js:581-582`),
  Lightbox-Transforms über `scheduleTransform`.
- **`requestIdleCallback`** zum Vorbauen der Lightbox-DOM abseits des kritischen Pfads
  (`spielturm-vergleich.js:3833-3842`).
- **Ein `<img>` + src-Swap** statt DOM-Stacking (Anti-Ghosting).
- **Layout-Stabilität (CLS)**: min-height-Lock während AJAX-Loads,
  Scroll-Drift-Korrektur im FLIP (`reference/mh-kp-gallery/assets/js/frontend.js:782-867`, `:1341-1379`).
- **Scroll-Lock ohne Sprung**: Scrollbar-Breite messen, `padding-right` kompensieren,
  ref-counted (`frontend.js:10-25`).
- Nur `transform`/`opacity` animieren.

## Netzwerk & Events

- **Debounce/Batching**: Analytics-Queue alle 2s + `navigator.sendBeacon` auf `pagehide`
  (`spielhaus-konfigurator.js:199-230`); debounced AJAX-Refresh
  (`refreshBoughtTogether`, `spielturm-vergleich.js:1777`); serverseitig gebufferte
  Impressions (Transient 120s, Flush bei 50 bzw. `shutdown`, 120-Tage-Pruning —
  `reference/mh-bought-together/mh-bought-together.php:2657-2730`).
- **Sub-Pixel-Akkumulator + EMA-Glättung** für Drag-Physik (`spielturm-360.js:372-409`).
- **Transient-Caching serverseitig** mit Invalidierung an Produkt-Hooks
  (siehe `02-architektur.md` §6); Rate-Limiting für schreibende AJAX-Calls.

## Checkliste

- [ ] Assets laden nur wo gebraucht (Shortcode-/Seiten-Gate)?
- [ ] LCP-Bild gepreloaded?
- [ ] IO statt Scroll-Listener?
- [ ] Drag/Scroll rAF-gedrosselt?
- [ ] CLS geprüft (Skeletons, min-height)?
- [ ] Teure Daten in Transients, mit Invalidierung?
- [ ] `.min`-Assets + Content-Hash-Busting (bei größeren Plugins)?
