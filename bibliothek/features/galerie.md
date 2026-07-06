# Galerie & Slider

## Das Signatur-Pattern: Hero + Thumbnail-Strip

Kommt in drei Plugins vor — beste Vorlage:
`reference/mh-shop-galerie/public/js/mh-shop-galerie.js:339-625` (`initGallery`).
Varianten: `reference/mh-spielturm-vergleich/public/js/spielhaus-konfigurator.js:1104+` (`renderGallery`),
`reference/mh-spielturm-vergleich/public/js/spielturm-vergleich.js:2006-2331` (linke Spalte).

Kern-Techniken:
- **Ein einziges `<img>`-Hero mit src-Swap** (kein DOM-Stacking) + `srcset`/`sizes` —
  vermeidet Ghosting/Flicker.
- **Peek-Drag auf dem Hero**: Nachbarbild folgt dem Finger, snappt beim Loslassen;
  Commit-Schwelle `max(40, 12% Breite)` (`dStart/dMove/dEnd`, `:416-493`).
- Immer sichtbare Prev/Next-Pfeile; **Klick auf Hero → Lightbox** (nach Drag unterdrückt
  via `dragMoved`-Flag).
- **Custom Thumbnail-Scrollbar** `◄ [Track + Balken] ►` komplett in JS (`:544-621`):
  Drag-to-scroll auf den Thumbs, proportionaler ziehbarer Balken
  (`bw = max(28, tw*(cw/sw))`), Klick-auf-Track springt, rAF-gedrosselter Scroll-Sync.
- **Aktives Thumbnail auto-scrollt in Sicht** — container-gescoped
  `scrollTo({behavior:'smooth'})`, nie ein Seiten-Jump.
- **Nachbar-Preload** via `new Image().src`.

## Crossfade-Bildwechsel (ohne Flackern)

`reference/mh-kp-gallery/assets/js/frontend.js:961-1032` (`mhInitGallery.goTo`):
aktuelles Bild als „Ghost" klonen und obenauf legen → `src` swappen → Ghost bei `load`
ausfaden (mit 80ms-Timeout als Cache-Absicherung). Echter Crossfade mit einem `<img>`.

## Slider mit Progress („Shop the Look")

`reference/mh-kp-gallery/assets/js/frontend.js:160-302` (`showSlide`):
Fade-out → Swap → Fade-in, Progress-Bar, Zähler im Stil `01 — 08`,
Preload der Nachbar-Slides (`preloadAdjacent` :313-325), einfacher Touch-Swipe (`:147-156`).

## Stolperfallen

- **Kein** `scrollIntoView()` auf Thumbnails — das scrollt die ganze Seite. Immer den
  Container gezielt scrollen.
- Drag und Click sauber trennen (`dragMoved`/`dragScrolled`-Flag + kurzer Timeout).
- Bilder responsive ausliefern (`srcset`/`sizes`), LCP-Hero zusätzlich per
  `<link rel="preload" fetchpriority="high">` im `wp_head` (Priorität 1) —
  siehe `reference/mh-shop-galerie/mh-shop-galerie.php` (`preload_hero`).
- Multi-Instanz: pro `[data-…]`-Root initialisieren, Re-Init-Guard setzen.
