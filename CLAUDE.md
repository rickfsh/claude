# Mega-Holz Plugin-Bibliothek

Dieses Repo ist die **Wissensdatenbank für die WordPress/WooCommerce-Plugin-Entwicklung**
von Mega-Holz (mega-holz.de, Umgebung: Oxygen Builder + WP Rocket). Es enthält keine
aktive Codebasis, sondern:

- `bibliothek/` — kuratierte Doku: Checkliste, Design-Tokens, Architektur-Konventionen,
  Umgebungs-Stolperfallen, Feature-Rezepte, Plugin-Steckbriefe
- `reference/` — die vollständigen Quellen aller bisher gebauten Plugins (unverändert,
  als Nachschlagewerk)

## Arbeitsregel (wichtig!)

**Vor jeder Plugin-Arbeit** (neu bauen, erweitern, stylen):

1. `bibliothek/00-checkliste.md` lesen und befolgen.
2. Für jedes Feature zuerst `bibliothek/features/` und die dort verlinkte
   Referenz-Implementierung in `reference/` prüfen. **Nichts neu erfinden, was dort
   schon gelöst und gehärtet ist** — die Referenz-Implementierung als Vorlage übernehmen.
3. Styling immer aus `bibliothek/01-design-tokens.md` ableiten.

## Die 10 wichtigsten Konventionen (Kurzfassung)

1. **Akzent-Orange** `#FAA41A` (Hover `#FF9000`), warme Flächen `#f8f7f4`, Borders `#e5e3df`;
   Tokens lokal am Plugin-Root als CSS-Variablen (`--_a`, `--_bg`, `--_border`, `--_radius`).
2. **Open Sans**, Gewichte 400/700/800, Preise mit `font-variant-numeric: tabular-nums`.
3. Flache Akzent-Buttons (Radius 8–10px, Press-Feedback `scale(.96)`), weiße Cards
   (Radius 12–14px, Hover-Lift), Pills `999px`, `is-*` State-Klassen.
4. **Prefix-Disziplin**: ein `MH<Kürzel>`-Prefix pro Plugin für alles (vergeben:
   MHSG, MHBS, MH_STV, MH_SH, MH_STL, MH_KONFIG).
5. `ABSPATH`-Guard, `*_VERSION`-Konstante = Header-Version, finale Klassen mit `init()`,
   Changelog im Header-Doc-Block.
6. **Oxygen/WP-Rocket-sicheres Asset-Loading**: inline-on-shortcode oder
   register+lazy-enqueue — nie blind global enqueuen (`bibliothek/03-umgebung.md`).
7. Shortcodes mit `shortcode_atts` + Auflösung des aktuellen WooCommerce-Produkts +
   Debug-HTML-Kommentar statt leerem Output.
8. PHP→JS via `wp_localize_script` mit Nonce; AJAX-Handler mit `check_ajax_referer`.
9. Frontend-JS: **Vanilla ES5**, kein Framework/Build-Step; jQuery nur für
   WooCommerce-Fragment-Events; `esc()` vor `innerHTML`.
10. `prefers-reduced-motion` respektieren, nur `transform`/`opacity` animieren,
    Signatur-Easing `cubic-bezier(.22,1,.36,1)`, Mikro-Transitions `.15s`.

## Pflege

Nach jedem fertigen/abgenommenen Plugin: `/bibliothek-update` ausführen (Skill) —
neue Version nach `reference/` übernehmen und Learnings in `bibliothek/` einpflegen.
Die Doku ist auf Deutsch; Code-Snippets bleiben im Original.
