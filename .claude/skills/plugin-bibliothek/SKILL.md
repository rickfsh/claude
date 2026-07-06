---
name: plugin-bibliothek
description: >
  Nachschlagewerk für die Mega-Holz Plugin-Entwicklung. IMMER verwenden, wenn ein
  WordPress/WooCommerce-Plugin gebaut, erweitert, gefixt oder gestylt wird — oder wenn
  es um Features wie Lightbox, Galerie, Slider, 360°-Viewer, Grid/Masonry, Konfigurator,
  Vergleichstabelle, Hover-Effekte, Animationen, Touch-Gesten oder um Styling/Farben/
  Buttons/Cards geht. Verhindert, dass bereits gelöste Probleme neu erfunden werden.
---

# Plugin-Bibliothek nutzen

Du arbeitest an einem Plugin für Mega-Holz. In diesem Repo existiert eine kuratierte
Bibliothek mit allem, was in 5 bisherigen Plugins bereits gelöst und gehärtet wurde.

## Vorgehen (verbindlich)

1. **Checkliste lesen:** `bibliothek/00-checkliste.md` — sie ist der rote Faden für
   jedes neue Plugin und jedes größere Feature. Punkte beim Arbeiten wirklich abarbeiten.

2. **Feature-Rezepte prüfen — bevor du etwas selbst entwirfst.** Für jedes angefragte
   Feature die passende Seite lesen und die dort verlinkte Referenz-Implementierung
   in `reference/` öffnen:
   - Lightbox → `bibliothek/features/lightbox.md`
   - Galerie/Slider → `bibliothek/features/galerie.md`
   - 360°-Viewer → `bibliothek/features/360-viewer.md`
   - Grid/Masonry/Filter/Load-More → `bibliothek/features/grid-masonry.md`
   - Konfigurator/Preislogik/URL-Sharing → `bibliothek/features/konfigurator.md`
   - Cross-Sell/Bundles/Warenkorb-Rabatt → `bibliothek/features/cross-sell-warenkorb.md`
   - Vergleichstabelle → `bibliothek/features/vergleichstabelle.md`
   - Hover/Animationen → `bibliothek/features/hover-animationen.md`
   - Touch/Mobile → `bibliothek/features/mobile-gesten.md`
   - Performance → `bibliothek/features/performance.md`

   **Regel:** Die Referenz-Implementierung ist die Vorlage. Übernehmen und anpassen
   statt neu bauen — der Code dort ist über Versionen hinweg gehärtet (Gesten-Konflikte,
   Flicker, CLS etc. sind dort bereits gefixt).

3. **Styling ausschließlich aus den Design-Tokens ableiten:**
   `bibliothek/01-design-tokens.md` (Farben, Typo, Buttons, Cards, Breakpoints, Motion —
   inkl. kopierfertigem CSS-Variablen-Block in §7).

4. **Architektur nach Hausstil:** `bibliothek/02-architektur.md` (Prefixe, Shortcodes,
   Asset-Strategien, AJAX/Nonces, Caching, Versionierung).

5. **Umgebung beachten:** `bibliothek/03-umgebung.md` — Oxygen Builder und WP Rocket
   brechen naives `wp_enqueue`; die dort beschriebenen Strategien verwenden.

6. **Fork-Kandidaten prüfen:** `bibliothek/04-plugin-steckbriefe.md` — oft ist ein
   bestehendes Plugin die beste Startbasis (so entstand mh-shop-galerie als Port der
   Spielhaus-Galerie).

7. **Verbindliche Regeln & bekannte Schwächen:** `bibliothek/05-verbesserungen.md` —
   der Abschnitt „Ab sofort verbindlich" gilt für jedes neue Plugin (Focus-Trap,
   Kontrast, Rate-Limits, eine Asset-Quelle, vollständiges uninstall); beim Anfassen
   von Bestands-Code die dort gelisteten Funde mitdenken.

## Nach getaner Arbeit

Wenn ein Plugin fertig/abgenommen ist, den Nutzer an `/bibliothek-update` erinnern,
damit neue Patterns und Learnings in die Bibliothek zurückfließen.
