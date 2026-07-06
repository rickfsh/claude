# MH Birthday Sale

WordPress/WooCommerce-Plugin für die 22-Jahre-Geburtstagsaktion von Mega-Holz.

**Version: 1.7.0**

## Was das Plugin macht

1. **Warenkorb-Rabatt (serverseitig, die "Wahrheit"):** Negative Fee via
   `woocommerce_cart_calculate_fees`, wenn die Set-Bedingung erfüllt ist
   (≥1 Zaunfeld UND ≥2 qualifizierende Pfosten). Rabattiert werden NUR
   die Zaunfelder (−22% auf deren Positionssummen). Liegt zusätzlich
   eine LED-Abdeckleiste im Warenkorb, gibt es eine zweite Bonus-Fee (−22%
   auf die Leiste). Funktioniert unabhängig vom Einstiegsweg (Produktseite,
   Sale-Seite, 3D-Zaunplaner), weil rein Cart-basiert.
2. **Planer-Override (clientseitig, rein visuell):** ES5-Skript, das die
   Stückliste des Zaunplaners per DOM-Scan ausliest und injiziert:
   Unlock-Tracker (3 Schritte), −22%-Badge an Zaunfeld-Zeilen, Ersparnis-Block
   mit "Dein Geburtstagspreis", optionales Umschreiben des
   Gesamtpreis-Elements (neuer Preis + Streichpreis), Konfetti beim
   Freischalten.
3. **Cart-/Checkout-Notices:** "Fast geschafft"-Hinweis mit konkretem
   Ersparnis-Betrag, Erfolgsmeldung, LED-Upsell, Gutschein-Konflikt-Hinweis.
4. **Produktseiten-Hinweis (PDP):** Hinweisbox unter dem Preis auf den
   rabattfähigen Zaunfeldern. Zeigt den live berechneten rabattierten Preis
   ("für nur X,XX €"): aktueller WooCommerce-Anzeigepreis × (1 − rate),
   via `wc_get_price_to_display`, gerundet auf `wc_get_price_decimals`. Bei
   variablen Produkten der (ggf. "ab") Mindestpreis. Nur im Aktionszeitraum.
   Zwei Wege:
   Auto-Hook `woocommerce_single_product_summary` (Standard-Themes) UND
   Shortcode `[mhbs_birthday_hint]` für Page-Builder-Templates (Oxygen),
   die die Standard-Hooks nicht feuern. Doppel-Ausgabe wird verhindert.
6. **Warenkorb-Rabattzeile hervorheben (automatisch, ohne Shortcode):**
   `assets/css/cart-style.css` + `assets/js/cart-style.js` werden auf Cart/
   Checkout im Aktionszeitraum geladen. JS markiert die Zeile per Label
   (`mhbs_cart_js_config` → `labels`), CSS färbt sie grün/fett mit Häkchen.
   Funktioniert in klassischem Cart, Cart-Block und Oxygen (DOM-basiert).
5. **Admin-Einstellungen:** Einstellungen -> MH Birthday Sale. Start/Ende
   (datetime-local), Mindest-Pfosten und Ein/Aus-Schalter. Gespeichert in
   Option `mhbs_settings`; `mhbs_get_config()` überschreibt damit die
   Code-Defaults (Default < Admin < `mhbs_config`-Filter).

## Dateien

```
mh-birthday-sale.php                    Loader, MHBS_VERSION, mhbs_get_config(), Aktionsfenster
includes/class-mhbs-cart-discount.php   Fee-Logik + Cart-Notices
includes/class-mhbs-coupon-exclusion.php Zaunfelder von allen Gutscheinen ausschliessen
includes/class-mhbs-product-notice.php   Produktseiten-Hinweis (PDP, Variante B)
includes/class-mhbs-settings.php         Admin-Seite (Einstellungen -> MH Birthday Sale)
assets/js/cart-style.js                  Warenkorb-Rabattzeile markieren (DOM)
assets/css/cart-style.css                Hervorhebung der Rabattzeile
includes/class-mhbs-assets.php          Enqueue + wp_localize_script (MHBS_CFG)
assets/js/planner-override.js           ES5-Override (MutationObserver)
assets/css/birthday.css                 Tracker/Badge/Ersparnis/Konfetti-Styles
```

## Konfiguration

Alles über den Filter `mhbs_config` (PHP-Seite) bzw. `mhbs_js_config`
(Frontend-Selektoren/Pattern). Wichtigste Schlüssel:

| Schlüssel | Default | Bedeutung |
|---|---|---|
| `rate` | `0.22` | Rabattsatz |
| `min_posts` | `2` | Mindestanzahl qualifizierender Pfosten |
| `active_from` / `active_until` | im Admin gesetzt | Aktionsfenster (Site-Zeitzone), Einstellungen -> MH Birthday Sale |
| `enabled` | `true` | Master-Schalter (Admin), pausiert unabhängig vom Datum |
| `block_with_coupons` | `false` | false = Set-Rabatt koexistiert mit Gutscheinen (auf anderen Produkten). true = jeder Gutschein deaktiviert ihn |
| `panel_product_ids` | 5 IDs (Taiga, Massivo, Alu Rhombus) | rabattfähige Zaunfelder; eigene ODER Eltern-ID wird getroffen |
| `pfosten_product_ids` | 4 IDs (Mega Flex) | qualifizierende Pfosten |
| `planner_url` | leer → `/geburtstag/#planer` | Ziel des PDP-Hinweis-Links |
| `override_page_slugs` | `geburtstag`, `birthday-sale` | Wo das Override lädt |
| Filter `mhbs_load_planner_override` | — | Override auf weiteren Seiten laden |
| Filter `mhbs_is_active` | — | Aktionsfenster überschreiben (z.B. zum Testen) |

JS-Flags (`mhbs_js_config` → `flags`): `rewriteTotal` (Gesamtpreis
umschreiben, Default `true`), `confetti` (Default `true`).

## Technische Entscheidungen

- **Fee statt Preisanpassung:** Positionspreise bleiben unangetastet →
  kompatibel mit Zaunplaner-Cart-Items; eine klar benannte Rabattzeile im
  Warenkorb. Fee ist `taxable` und übernimmt die Steuerklasse des ersten
  Zaunfeld-Produkts. Fee-Basis ist netto (`line_subtotal`), Notices zeigen
  brutto.
- **Preis-Parsing im Override:** Immer der ERSTE Preiswert einer Zelle
  (= aktueller Preis), weil Taiga dauerhaft mit Streichpreis angezeigt wird
  (Streichpreis steht hinter dem aktuellen Preis). Die 22% kommen zusätzlich
  auf den aktuellen Preis.
- **Loop-Schutz:** Observer wird während eigener DOM-Schreibvorgänge
  pausiert; Original-Gesamtpreis wird in `data-mhbs-orig` /
  `data-mhbs-orig-html` gesichert, damit nie ein bereits rabattierter Preis
  erneut rabattiert wird. State-Hash verhindert Flicker durch unnötige
  Re-Renders.
- **Pfosten-Matching:** Pattern `pfosten` mit Exclude `pfostentr|abdeckung`,
  damit "Pfostenträger" und "Pfostenträger Abdeckung" NICHT als Pfosten
  zählen. ID-Whitelist ist aktiv (robuster als Pattern); Pattern bleibt nur Fallback.

## Offene Punkte / TODO vor Livegang

- [ ] Echte DOM-Selektoren des Planers eintragen (`mhbs_js_config` →
      `selectors.root/row/total/trackerAnchor/savingsAnchor`) und gegen den
      Live-DOM testen (Desktop + Mobile).
- [x] Produkt-IDs gepflegt (`panel_product_ids` = 5 Zaunfelder,
      `pfosten_product_ids` = 4 Mega-Flex-Pfosten). `led_product_ids` noch leer.
- [x] Aktionszeitraum im Backend einstellbar (Einstellungen -> MH Birthday
      Sale). Vor Livegang dort Start/Ende final setzen und Schalter an.
- [ ] PAngV prüfen (30-Tage-Bestpreis-Regel bei angekündigten Rabatten,
      Dauerstreichpreis + Extra-Rabatt) – rechtlich absegnen lassen.
- [ ] Notices laufen über Classic-Cart-Hooks (`woocommerce_before_cart`).
      Falls der Shop auf Cart-/Checkout-Blocks umstellt: Notices über die
      Store API / `woocommerce_store_api_cart_errors` o.ä. nachrüsten.
- [ ] Idee v1.1: hübscher Cart-Tracker (Progress-UI statt Text-Notices),
      Countdown-Komponente für die Sale-Seite.

## Konventionen

- Frontend-JS: **ES5-only** (var, function, keine Template-Literals).
- Bei JEDER Änderung: Version in Plugin-Header + `MHBS_VERSION` +
  diese CLAUDE.md (Changelog + Versionsfeld) aktualisieren.
- Auslieferung als versioniertes ZIP nach `/mnt/user-data/outputs/`.

## Changelog

### 1.0.0 (2026-06-10)
- Initiale Version: Cart-Fee-Rabattlogik (Taiga + Pfosten Set-Bedingung,
  LED-Bonus, Gutschein-Sperre, Aktionsfenster), Cart-/Checkout-Notices,
  Planer-Override (Tracker, Badges, Ersparnis-Block, Gesamtpreis-Rewrite,
  Konfetti), zentrale Konfiguration via Filter.
