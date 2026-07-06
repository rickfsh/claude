# 360°-Produkt-Viewer

**Referenz (komplett übernehmbar):** `reference/mh-spielturm-vergleich/public/js/spielturm-360.js`
— eigenständiges Modul mit sauberer `MH360.create(...)` / `destroy()`-API.
Shortcode: `[mh_360_viewer id="…" autoplay="true"]`; Einbindung auch innerhalb der
Lightbox möglich (`LB_360_MARKER`-Pattern in `spielturm-vergleich.js:3231`).

## Kern-Techniken

- **Frame-Preload in Batches**: 8 Bilder parallel (`PRELOAD_BATCH`, `:186-230`),
  Live-Prozent-Loader mit Spinner-Keyframe (`mh360spin`).
- **rAF-gedrosseltes Drag-Rendering**: gerendert wird mit Bildschirm-Refresh-Rate,
  nicht mit mousemove-Rate (`dragRAF`, `:398-408`).
- **Sub-Pixel-Akkumulator + EMA-Velocity-Glättung** (`dragAccum`, `VELOCITY_SMOOTH=0.3`,
  `:372-409`) — macht das Drehen butterweich.
- **Momentum mit Friction** (0.85) nach dem Loslassen; **Smoothstep-Ease-in** für den Autoplay-Anlauf.
- **Autoplay nur wenn sichtbar**: IntersectionObserver, `threshold: 0.3` (`:248-260`).
- **Double-Tap → Fullscreen** (350ms-Fenster, `:151-166`).
- **Ein `<img>` mit src-Swap** statt Frame-Stapel (expliziter v1.2.0-Fix gegen Ghosting).
- Drag-Hint-Animation (`mh360hintPulse`/`mh360hintSlide`), verschwindet nach erster Interaktion.
- Saubere `destroy()`: alle gebundenen Handler werden referenziert und gelöst.

## Stolperfallen

- Frames nie alle auf einmal laden (Netzwerk-Stau) — Batch-Preload mit Fortschritt.
- Autoplay ohne Sichtbarkeits-Check verbrennt CPU in Tabs/unterhalb des Folds.
- Ohne EMA-Glättung springt die Rotation bei unregelmäßigen Pointer-Events.
