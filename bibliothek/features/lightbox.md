# Lightbox

Drei handgebaute, erprobte Lightboxen — **keine Library** (kein PhotoSwipe/Fancybox).
Gemeinsame DNA: Peek-Swipe, Zoom zum Klickpunkt, Thumbnail-Strip, ESC/Pfeiltasten, Nachbar-Preload.

## Welche als Vorlage nehmen?

| Bedarf | Vorlage |
|---|---|
| **Standard-Fall**: saubere, vollständige Lightbox | `LB`-Modul, `reference/mh-shop-galerie/public/js/mh-shop-galerie.js:24-336` |
| Maximale Gesten (Double-Tap, Velocity-Flick, 360°-Slide inline) | `reference/mh-spielturm-vergleich/public/js/spielturm-vergleich.js:3085-3827` |
| Leichtgewichtig, DOM-String-basiert, Morph-Open aus Grid-Card | `reference/mh-kp-gallery/assets/js/frontend.js` (`openLightbox` :379, `openGridLightbox` :1417, `openProductLightbox` :1767) |
| Lightbox + 360°-Einstieg im Konfigurator | `reference/mh-spielturm-vergleich/public/js/spielhaus-konfigurator.js:725-1101` |

## Kern-Techniken (Standard-`LB`-Modul)

- **Singleton**: eine geteilte Instanz für alle Galerien der Seite.
- **Click-to-Zoom zum Klickpunkt** (2.5×): `zoomTo(s2, cx, cy)` rechnet in Bild-Koordinaten,
  damit der geklickte Pixel fixiert bleibt (`:50-61`). Zweiter Klick = zurück.
- **Wheel-Zoom** stufenlos 1–5×, Cursor-Punkt bleibt fix (`:163-168`, Listener `{passive:false}` + `preventDefault`).
- **Pinch-Zoom** über 2-Finger-Distanz-Ratio (`:202-233`); **Pan** im Zoom mit `clampPan()`-Grenzen (`:43-49`).
- **Peek-Swipe**: das Nachbarbild folgt wirklich dem Finger; Commit-Schwelle `max(50, 15% Breite)` (`:64-109`).
- **Swipe-down-to-close** mobil (`dy > 80` + vertikale Achse).
- **Tastatur**: Escape/←/→; Fokus-Restore auf `lastFocus` beim Schließen.
- **Zähler** („X von Y"), Thumbnail-Strip mit Active-Sync, **Nachbar-Preload** via `new Image()`.
- **A11y**: `role="dialog"`, `aria-modal`, Scroll-Lock über Klasse auf `<html>`.

## Extras der Spielturm-Variante (bei Bedarf übernehmen)

- **Achsen-Locking**: nach 6–8px Deadzone entscheidet `|dx| >= |dy|` horizontal (Bildwechsel)
  vs. vertikal (Dismiss) — verhindert Gesten-Konflikte (`lbSwipeMove` :3417-3483).
- **Proportionales Fade** beim Runterziehen (`opacity = 1 - progress*0.5`), Commit bei >120px
  **oder** Flick-Velocity >0.4 (`:3490-3523`).
- **Double-Tap-Zoom** mit 320ms/30px-Toleranz (`:3563-3581`).
- Tastatur-Zoom mit `+`/`-`/`=`.
- **Perf**: Lightbox-DOM auf `requestIdleCallback` vorbauen → kein Jank beim ersten Öffnen (`:3833-3842`).

## kp-gallery-Variante: andere Zoom-Philosophie

Statt scale-to-point: Klick toggelt `.zoomed`, dann folgt `transform-origin` dem Zeiger
in % (`mousemove`/`touchmove`, `frontend.js:1613-1625`) — „Lupen"-Gefühl.
**Morph-Open**: `transformOrigin` auf das Zentrum der geklickten Card setzen (`:1496-1506`);
Schließen über `.mh-lb-closing`-Klasse + 300ms-Timeout.

## Stolperfallen (bereits gelöst — nicht neu erleiden)

- Lightbox hängt an `<body>` → **außerhalb des CSS-Token-Scopes**: Variablen dort erneut deklarieren.
- Klick nach Drag darf die Lightbox nicht öffnen: `dragMoved`-Flag setzen und Click unterdrücken.
- `{passive:false}` nur wo `preventDefault()` wirklich nötig (Wheel, gelockter Horizontal-Swipe),
  sonst `{passive:true}` — sonst ruckelt das Seiten-Scrolling.
- Backdrop-Farbe folgt dem dunklen Anker des Plugins: `rgba(17,17,17,.94)` (Charcoal) bzw.
  `rgba(6,11,35,.92)` (Navy).
- Scroll-Lock ohne Layout-Sprung: Scrollbar-Breite messen und als `padding-right` kompensieren
  (`reference/mh-kp-gallery/assets/js/frontend.js:10-25`).
