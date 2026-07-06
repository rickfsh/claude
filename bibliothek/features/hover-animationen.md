# Hover-Effekte & Animationen

Grundsatz: **CSS-first** — Transitions/Keyframes in CSS, JS nur wo Physik oder
Layout-Messung nötig ist (Momentum, FLIP, Count-up). Konkrete Token-Werte
(Dauern, Easings, Keyframe-Familien) in `01-design-tokens.md` §6.

## Erprobte Hover-Patterns

- **Card-Lift**: `translateY(-2px/-4px)` + Schatten von `0 1px 3px` auf `0 6px 24px`
- **Listenzeile**: `translateX(5px)`
- **Card-Bild**: `scale(1.04)` (im `overflow:hidden`-Container)
- **Button**: `filter: brightness(1.05–1.08)` oder dunklerer Akzent; Pressed `scale(.96–.98)`
- **Bidirektionaler Hover-Sync** (Highlight-Kopplung zweier UI-Bereiche):
  Pin ↔ Produktzeile ↔ Galerie-Thumbnail, Rest wird gedimmt —
  `reference/mh-kp-gallery/assets/js/frontend.js:707-769` (`mhBindPinProductSync`)
  und `:81-115` (`mhBindRowImageNav`)
- Hover-States hinter `@media (hover: hover)` — sonst „kleben" sie auf Touch

## Erprobte Animations-Patterns

- **Reveal-on-Scroll mit Stagger** (IO + Queue + 60–80ms-Versatz):
  `frontend.js:1161-1199`, Produktseiten-Variante `:1715`
- **Gleitender Pill-Indicator** unter aktiven Filter-Buttons (`:1132-1159`)
- **FLIP-Append** für Masonry (siehe `features/grid-masonry.md`)
- **Morph-Open** der Lightbox via `transform-origin` auf die Quell-Card
- **Preis-Count-up** (`animatePrice`, `reference/mh-spielturm-vergleich/public/js/spielturm-vergleich.js:625`)
- **Skeleton-Pulse/Shimmer** während Ladezuständen (Keyframes `mhStvPulse`/`mhShimmer`-Familie)
- **Bild-Preview-Tooltip** bei Hover: fixed-position, an `<body>` gehängt (Achtung Token-Scope!)
  — `reference/mh-bought-together/assets/js/mh-bt-frontend.js` (Hover-Tooltip, ~`:1710`)
- **Gestaffeltes Fade-Up** mit inkrementellem `animation-delay` auf `:nth-child`
  (spielturm `.mh-stv-right > *`, kp `.mh-grid-lb-info > *`)

## Pflichtregeln

- `@media (prefers-reduced-motion: reduce)` deaktiviert Transitions/Animationen;
  im JS zusätzlich `REDUCED_MOTION`-Flag prüfen vor Smooth-Scroll
  (`spielturm-vergleich.js:228`)
- Nur `transform` + `opacity` animieren (GPU-freundlich), nie `top/left/width` —
  Ausnahme: der Pill-Indicator (bewusst, da selten und klein)
- Signatur-Easing `cubic-bezier(.22,1,.36,1)` für alles „Große", `.15s ease` für Mikro
