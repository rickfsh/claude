# Design-Tokens — die Mega-Holz Design-Sprache

Alle 5 Plugins teilen eine gemeinsame Design-DNA. Neue Plugins übernehmen diese Werte,
damit alles auf der Seite wie aus einem Guss wirkt. Quelle: Analyse von
`reference/mh-shop-galerie`, `mh-spielturm-vergleich`, `mh-kp-gallery`, `mh-birthday-sale`, `mh-lazy-konfigurator`.

## 1. Farben

### Akzent (Mega-Holz Orange) — der wichtigste Token
Warmes Orange (~Farbton 35°), je Plugin leicht unterschiedlich, aber gleiche Design-Absicht:

| Wert | Rolle | Verwendet in |
|---|---|---|
| `#FAA41A` | Primär-Akzent | shop-galerie (`--mhsg-a`), lazy-konfigurator |
| `#FF9000` | Hover / „heißer" Akzent | shop-galerie, birthday, lazy-konfigurator |
| `#e8910c` | Primär-Akzent | spielturm + spielhaus (`--mh-stv-accent`, `--_a`) |
| `#f7af4a` / `#e59a2f` | Akzent / Hover-Akzent | kp-gallery (`--stl-accent` / `--stl-accent-dark`) |

**Für neue Plugins:** `#FAA41A` als Primär, `#FF9000` als Hover — oder als themebare CSS-Variable
mit Override-Hook (siehe Abschnitt 7). Transluzente Formen für Glows/Schatten:
`rgba(250,164,26,.35)` bzw. `rgba(247,175,74,.35)`.

### Dunkle Anker (zwei „Welten")
- **Navy-Welt** (Spielturm/Spielhaus-Erbe): `#060B23` (Titel, Totals), Sekundär-Navy `#33335C`
- **Charcoal-Welt** (Shop/Mülltonnenbox): `#2a2a2a`, `#111` / `#1a1a1a` (Text/Dark-Canvas)

Text-Graus: `#444`/`#555` (Body), `#636360` (muted), `#666`/`#999`, `#9a9a94` (durchgestrichen/gedimmt).

### Flächen & Borders (sehr konsistent)
- Warmes Off-White als Seitenhintergrund: `#f8f7f4` (spielturm) / `#f5f3f0` (kp-gallery)
- Warmer Orange-Tint für aktiv/ausgewählt: `#fef8ef` / `#FFF7EA` / `#fef3e2`
- Border-Grau: `#e5e3df` / `#e7e6e2` / `#e5e5e5`; Hover-Border: `#d9d8d3` / `#cfcdc8`

### Semantische Farben
- **Erfolg:** Grün `#2E7D32` (auch `#246b28`, `#16a34a`), Tint-Hintergrund `#e8f5e0` / `#eef7ee`
- **Fehler:** Rot `#dc2626` / `#c0392b`, Tint `#fde8e8`
- **Lieferrückstand/Warnung:** Amber `#d4a017` / `#8a6d2b`, Tint `#fff5e0`
- **Rabatt-Pill:** Grün `#3B6D11` auf `#27500A`-Nuancen (Spielhaus-Preisersparnis)

### Admin-Bereich
Admin-CSS weicht bewusst von der Marke ab: native WordPress-Admin-Graus
(`#c3c4c7`, `#787c82`, `#f0f0f1`, `#50575e`) — nur der Orange-Akzent bleibt für interaktive Highlights.

## 2. Typografie

- **Familie überall:** `"Open Sans", Helvetica, Arial, sans-serif`
  (kp-gallery erzwingt sie mit `!important` gegen Theme-Overrides, `-webkit-font-smoothing: antialiased`)
- **Gewichte:** 400 (Body), 500–600 (Labels/sekundär), **700** (Standard-Betonung), **800** (Preise, Produktnamen, CTAs). 900 wird nicht verwendet.
- **Größen-Skala (px):**
  - Micro-Labels/Badges: 10–12px
  - Body/Controls: 13–14px, `line-height: 1.5–1.7`
  - Sektions-Label: 12px, UPPERCASE, `letter-spacing: .4–.6px`, Gewicht 700
  - Zwischentitel: 15px · Titel: 22–26px · Hero: 28px
  - Preise: Hero 30–36px Gewicht 800, Card-Preise 13–20px
- **Preise immer:** `font-variant-numeric: tabular-nums` (Markenzeichen der Plugins!)

## 3. Buttons

- **Primär-CTA:** flacher Akzent-Hintergrund (keine Gradients auf Buttons!), weißer Text,
  `border-radius: 8–10px`, Gewicht 700–800, großzügiges Padding (`13px 28px` bis `16px 20px`)
- **Hover** (zwei erprobte Varianten): `filter: brightness(1.05–1.08)` ODER Wechsel auf dunkleren/heißeren Akzent
- **Pressed:** `transform: scale(.96–.98)` oder `translateY(1px)` — taktiles Feedback ist Standard
  (kp-gallery hat global `:active { transform: scale(.96); transition-duration: .08s }`)
- **Schatten:** akzentgetönter Glow — `box-shadow: 0 2px 8px rgba(247,175,74,.35)`,
  Hover vertieft auf `0 4px 16px rgba(247,175,74,.45)`
- **Sekundär/Outline:** transparent, 1.5–2px Border, Akzent bei Hover
- **Pills:** `border-radius: 999px` (oder `100px`) für Filter, Chips, Badges, Zähler

## 4. Cards & Container

- **Radius-Skala:** 4–6px (Badges, kleine Chips) · 8–10px (Toggles, Thumbs, Inputs) ·
  **12–14px (Cards** — `--_radius: 12px`, `--mhsg-radius: 14px`) · 999px (Pills)
- **Schatten-Familien:**
  - Ruhend: `0 1px 3px rgba(0,0,0,.06)` → Hover-Lift: `0 6px 24px rgba(0,0,0,.08)`
  - Grid-Cards (kp): `0 1px 4px rgba(0,0,0,.06)` → Hover `0 16px 40px rgba(0,0,0,.12)`, Hero `0 20px 50px rgba(0,0,0,.18)`
- **Ausgewählt-Zustand:** Akzent-Border + warmer Tint (`#fef8ef`) + Inset-Ring:
  `box-shadow: inset 0 0 0 1px var(--_a)` oder `0 0 0 3px rgba(250,164,26,.12)`
- **„Inklusive"-Cards:** `border-style: dashed`
- **Card-Bauweise:** `#fff` + `1–2px solid var(--_border)` + Radius + `padding: 12–24px`
- **Hover-Bewegung:** `translateY(-2px / -4px)` auf Cards, `translateX(5px)` auf Listenzeilen, `scale(1.04)` auf Card-Bildern

## 5. Spacing & Breakpoints

- **Spacing-Rhythmus:** 8 / 12 / 14 / 16 / 24 / 32 px (Gaps); Grid-Gutter 20px (Masonry), 32px (2-Spalten); Sektions-Padding 24px
- **Breakpoint-Leiter:** `1024 → 900/880 → 768 → 600 → 480` (max-width)
  - `600px` ist der nahezu universelle Mobile-Breakpoint
  - Spielhaus nutzt zusätzlich **Container Queries** (`@container mhaddons (max-width: 460px)`) mit `@media`-Fallback
- **Pflicht-Guards:**
  - `@media (prefers-reduced-motion: reduce)` — Transitions/Animationen deaktivieren
  - `@media (hover: hover)` — Hover-States nur auf Hover-fähigen Geräten
  - optional `@media (prefers-color-scheme: dark)` (kp-gallery Sekundär-Button)

## 6. Motion (Transitions, Easing, Keyframes)

- **Dauern:** `.15s` Mikro-Interaktionen (dominant) · `.2–.25s` Buttons/größere UI ·
  `.1s` Press-Feedback · `.3–.5s` Card-Lifts/Bild-Zoom
- **Signatur-Easings:**
  - `cubic-bezier(.22,1,.36,1)` — „smooth ease-out" für FLIP, Filter-Indicator, Card-Reveals, Sticky-Bars, Progress
  - `cubic-bezier(.16,1,.3,1)` — Lightbox-Morph, gestaffelte Panel-Entrances
- **Keyframe-Familien** (namentlich wiederverwendet): Pulse/Skeleton (`opacity 1→.4` bzw. Shimmer),
  Spinner (360°-Rotation), Lightbox-Fade (`.18s ease`), gestaffeltes Fade-Up mit
  inkrementellem `animation-delay` auf `:nth-child`
- **Fokus-Ring (universell):** `outline: 2px solid var(--_a); outline-offset: 2px`
- **Overlay-Scrims:** `rgba(0,0,0,.5–.6)` für Controls; Lightbox-Backdrop folgt dem dunklen Anker
  des Plugins: `rgba(17,17,17,.94)` (Charcoal-Welt) bzw. `rgba(6,11,35,.92)` (Navy-Welt);
  `backdrop-filter: blur()` wo passend

## 7. CSS-Variablen-Startblock (kopierfertig für neue Plugins)

Jedes Plugin definiert seine Tokens **lokal am Root-Element** (nicht global in `:root`,
außer bewusst wie kp-gallery). Konvention: themebare Akzent-Variable mit Override-Hook +
kurze `--_*` Arbeits-Tokens:

```css
/* Root-Scope des Plugins, z.B. #mh-xyz-widget oder .mhxyz */
.mhxyz {
  /* themebarer Hook: Seite kann --mh-xyz-accent überschreiben */
  --_a:      var(--mh-xyz-accent, #FAA41A); /* Akzent */
  --_a-hov:  #FF9000;                       /* Hover-Akzent */
  --_bg:     #f8f7f4;                       /* warmes Off-White */
  --_border: #e5e3df;
  --_text:   #1a1a1a;
  --_muted:  #636360;
  --_radius: 12px;

  font-family: "Open Sans", Helvetica, Arial, sans-serif;
  color: var(--_text);
}
.mhxyz, .mhxyz *, .mhxyz *::before, .mhxyz *::after { box-sizing: border-box; }
```

**Achtung:** Elemente, die per JS an `<body>` gehängt werden (Lightbox, Sticky-CTA),
liegen **außerhalb** des Token-Scopes → Tokens dort erneut deklarieren
(so macht es spielturm bei `.mh-stv-sticky-cta`).

## 8. CSS-Namenskonventionen

- **Vendor-Prefix `mh-`** + Plugin-Namespace: `.mhsg-*`, `.mhbs-*`, `.mh-stv-*`, `.mh-sh-*`, `.mh-stl-*`, `.mh-lazy-konfig__*`
- **State-Klassen einheitlich `is-*`:** `.is-active`, `.is-on`, `.is-selected`, `.is-open`,
  `.is-visible`, `.is-loading`, `.is-success`, `.is-error`, `.is-dragging`, `.is-disabled`
- BEM-Modifier wo sinnvoll: `.mhbs-step--ok`, `.mhbs-chip--on`
- Alles unter einer Root-ID/-Klasse gescoped, mit `box-sizing`-Reset (siehe oben)
