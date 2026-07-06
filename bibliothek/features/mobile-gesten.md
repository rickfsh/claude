# Mobile & Touch-Gesten

Alles handgerollt (kein Hammer.js o.ä.) und über die Plugins hinweg iterativ gehärtet.

## Gesten-Baukasten

| Geste | Technik | Referenz |
|---|---|---|
| **Peek-Swipe** (Bild folgt dem Finger) | `touchstart/move/end`, Nachbar per `translateX` | `reference/mh-shop-galerie/public/js/mh-shop-galerie.js:64-109` |
| **Achsen-Locking** | nach 6–8px Deadzone: `|dx| >= |dy|` → horizontal locked | `reference/mh-spielturm-vergleich/public/js/spielturm-vergleich.js:3417-3483` |
| **Pinch-Zoom** | 2-Touch-Distanz-Ratio (`Math.hypot`), Clamp 1–5× | `spielturm-vergleich.js:3737-3766`, `mh-shop-galerie.js:202-233` |
| **Double-Tap-Zoom** | 320ms/30px-Toleranzfenster | `spielturm-vergleich.js:3563-3581` |
| **Swipe-down-to-dismiss** | vertikale Achse, Commit >120px oder Velocity >0.4, proportionales Backdrop-Fade | `spielturm-vergleich.js:3442-3523` |
| **Bottom-Sheet mit Swipe-to-dismiss** | Popup wird mobil zum Sheet, Snap-back unterhalb Schwelle | `reference/mh-kp-gallery/assets/js/frontend.js:545-684` |
| **1-Finger-Pan im Zoom** | nur aktiv wenn `scale > 1`, `clampPan()`-Grenzen | `mh-shop-galerie.js:43-49` |

## Die wichtigste Regel: `passive` richtig setzen

```js
el.addEventListener('touchmove', onMove, { passive: true });  // Standard
// preventDefault() NUR rufen, nachdem ein horizontaler Swipe „gelockt" wurde —
// dafür braucht der Listener { passive: false }
```
Vertikales Seiten-Scrollen muss immer flüssig bleiben. Vorbilder:
`mh-shop-galerie.js:231`, `spielturm-vergleich.js:3764`.

## Mobile Layout-Patterns

- `isMobile = vw < 768` als Schwelle für Popup→Bottom-Sheet.
- **Sticky-CTA**: mobil als Bottom-Bar, Desktop sticky oben (spielturm).
- **JS-DOM-Reorder** für mobile Spaltenreihenfolge (`applyLayout`,
  `spielturm-vergleich.js:1853-1913`).
- Filterleisten mobil horizontal scrollbar mit Edge-Fade.
- Breakpoint-Leiter und Container-Query-Option: siehe `01-design-tokens.md` §5.

## Stolperfallen

- Ohne Achsen-Locking kämpfen Swipe und Seiten-Scroll gegeneinander.
- Click-Events feuern nach Touch-Drags: `dragMoved`-Flag + Unterdrückung.
- iOS-Momentum-Scroll im Sheet: Scroll-Lock mit Scrollbar-Kompensation nutzen
  (`frontend.js:10-25`).
