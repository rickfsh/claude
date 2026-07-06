# Cross-Sell / Warenkorb-Bundles

**Referenz:** `reference/mh-bought-together/` — das kanonische Cross-Sell-Widget
(„Wird oft zusammen gekauft"). mh-spielturm-vergleich konsumiert es (Einbettung +
abgeleitete Bundle-Logik im Spielhaus-Konfigurator).

## Kern-Patterns

### Mengenformel-Engine
Jedes verknüpfte Produkt hat `Multiplikator` + `Offset`; Zielmenge =
`ceil(Hauptmenge × mult + offset)`, live gekoppelt an das WC-Mengenfeld
(`assets/js/mh-bt-frontend.js`, `update()` :60/:1465). Beispiele aus dem README:
Zaun→Pfosten ×1+1, Lasur ×0.5+0, WPC-Diele→Clips ×8+0. Dazu **injizierte
Qty-Stepper** (−/Input/+) pro Zeile (`createQtyInputs` :1447), Haupt-Input synct zurück.

### Bundle-Rabatt als negative Fee (das wichtigste Server-Pattern)
- Rabatt wird **serverseitig** ermittelt und als negative Fee angewendet —
  Client-Anzeige ist nur Vorschau; Neuvalidierung verhindert Manipulation
  (`mh-bought-together.php:2217-2227`, `:2363`).
- **`woocommerce_get_cart_item_from_session`-Filter** (`:2306`, „CRITICAL"):
  ohne ihn verliert das Cart-Item seine `_mh_bt_*`-Meta beim Page-Reload.
  Gruppierung über `bundle_id`.
- Add-to-Cart per AJAX mit Bestandsprüfung, WC-Fragmente zurück,
  `added_to_cart`/`wc_fragment_refresh`-Trigger (`:2193`, JS `:1789`).

### Theme-proofe Cart-Badge-Injection
Bundle-Badges im Warenkorb ohne Template-Override: DOM nach Produkt-Links per Slug
scannen, zur Row hochlaufen, Badge injizieren — funktioniert mit Classic-Cart **und**
WC-Blocks, Re-Injection auf `updated_cart_totals` (`:2422-2593`, `assets/js/mh-bt-cart.js`).

### UX-Bausteine
- **Milestone-Progressbar** zum Bundle-Rabatt (`:1570` im JS)
- **Animierter Preis-Counter** (rAF + Cubic-Easing, `animatePrice` :1429);
  bei Rabatt-Aktivierung Zwei-Stufen-Animation: hoch zum Vollpreis, dann grün
  runter auf Bundle-Preis (`:1602`)
- Dynamischer CTA-Text („X Stk in den Warenkorb · €"), konsolidierte Savings-Pill
- Unchecked-Dimming von Zeile + Plus-Separator; Collapsible „weitere Produkte"
- Body-appended Bild-Preview-Tooltip bei Hover (`:1710`)
- **Randloses „Blend-in"-Widget**: ab v3.2.4 bewusst ohne Card-Container
  (full-width, transparent) — fließt in die Produktseite ein
- Per-Zeile-Konfiguration: `box_type` (primary/optional), `checked_default`,
  `reason`, `qty_label`

### Leichte Analytics ohne DB-Tabelle
Impressions in Transient gebuffert (120s), Flush bei 50 bzw. auf `shutdown` in eine
Option; Deselections in datierten Buckets; 120-Tage-Pruning; HPOS-bewusste
Bestell-Statistik-Query (`:2657-2730`, `:2780`). Gute Alternative zur eigenen
Tabelle (die weiterhin für hochfrequente Events gilt, siehe spielturm).

### Integration in andere Plugins
Das Widget exponiert `window.mhBtInit` / `window.mhBtReinit`
(`assets/js/mh-bt-frontend.js:414-419`). Fremd-Plugins rendern
`do_shortcode('[mh_bought_together]')` und rufen nach AJAX-Austausch `mhBtReinit()` —
so macht es mh-spielturm-vergleich (`includes/class-frontend.php:228-236`, `:405-432`;
`spielturm-vergleich.js:1830`). Wer das Widget einbettet, braucht ein
`form.cart input[name="quantity"]` im DOM (notfalls versteckt), weil die Widget-JS
diesen Selektor sucht.

## Stolperfallen

- **Eine Quelle für CSS/JS!** Das Plugin hält Datei- und Inline-Fallback-Kopien,
  die auseinandergedriftet sind (Inline-JS exponiert `mhBtReinit` nicht → Integration
  bricht, wenn die Datei fehlt). Bei Übernahme des Patterns: Inline-Fallback aus der
  Datei generieren, nie zweimal pflegen.
- Tracking-Endpoints (nopriv) brauchen Nonce/Validierung/Rate-Limit — hier fehlt das
  (siehe `05-verbesserungen.md` #3).
- Neue Meta-Keys/Options ins `uninstall.php` aufnehmen — hier unvollständig.
- Session-Restore-Filter nicht vergessen, sonst „verschwindet" der Rabatt nach Reload.
