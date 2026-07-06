# Vergleichstabelle (data-driven Matrix)

**Referenz:** `buildComparisonView` in
`reference/mh-spielturm-vergleich/public/js/spielturm-vergleich.js:1619-1766`.

## Kern-Techniken

- **CSS-Grid-Matrix** statt `<table>`: Spaltenzahl als Custom Property
  (`--_cols` = Anzahl Ausstattungslevel) → responsive steuerbar.
- **Vollständig datengetrieben**: Feature-Zeilen entstehen aus dem Merge von
  `base_features` + `series_features` + per-Serie `features_matrix`;
  Dedup über eine `seenFeats`-Map.
- Zellen als ✓ (`✓`) oder — (`—`); aktuelle Spalte mit `.is-active` hervorgehoben.
- **Preis-Zeile** und **CTA-Zeile** mit „Auswählen"-Button pro Spalte:
  ruft `applyLevel()` + `update(true)` auf und smooth-scrollt zum Haupt-CTA.
- Dazugehörig: **ARIA-Tabs** für die Detailansicht mit Pfeiltasten-Navigation und
  Content-Cache (`updateTabs`/`switchTab`/`tabKeyHandler`, `:1330-1616`) —
  Tab-Inhalte werden lazy per AJAX geladen (`loadProductDetail`, `:343`) und gecacht.

## Stolperfallen

- HTML aus Produktdaten nie roh einsetzen: `esc()`/`sanitizeHTML()` verwenden
  (`spielturm-vergleich.js:232`).
- Bei Feature-Merge auf Duplikate achten (Map, nicht Array-Suche).
- Smooth-Scroll hinter `prefers-reduced-motion`-Check (`REDUCED_MOTION`-Flag, `:228`).
