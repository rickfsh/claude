# MH Häufig zusammen gekauft — v3.2

Custom WooCommerce Plugin für Mega-Holz.de — zeigt "Wird oft zusammen gekauft"-Empfehlungen mit **intelligenter Mengenberechnung** und **Premium-Design-Details**. Neu: **"Im Lieferumfang enthalten"**-Widget für Produkte die bereits inklusive sind.

## Was ist neu in v3.2

### "Im Lieferumfang enthalten" — Included Products Widget (NEU)

Zeigt dem Kunden welche Produkte bereits im Hauptprodukt inklusive sind (z.B. Rutsche, Kletterwand, Schaukel bei einem Spielturm). Rein informativ — kein Warenkorb-Button, stärkt den wahrgenommenen Wert.

#### Admin
- **Neue Meta-Box** "Im Lieferumfang enthalten" im Produkt-Editor
- **Product-Search Repeater** — WooCommerce-Produktsuche wie bei "Häufig zusammen gekauft"
- **Kategorie-Label** (optional) — z.B. "Rutsche", "Spielzeug" — erscheint als grüner Badge
- **Beschreibung** (optional) — z.B. "300cm, gelb" — Zusatzinfo unter dem Produkt

#### Settings (WooCommerce → Zusammen gekauft)
- **Widget-Überschrift** — Standard: "Im Lieferumfang enthalten"
- **Anzeigemodus** — "Aufklappbar" (zeigt erst X Produkte, Rest per Klick) oder "Immer aufgeklappt"
- **Aufklappen nach X Produkten** — Ab welchem Produkt eingeklappt wird (Standard: 3)
- **Einzelpreise anzeigen** — Zeigt "Einzelwert: XX €" bei jedem Produkt
- **Inklusiv-Wert anzeigen** — Summen-Zeile "Inklusiv-Wert gesamt: XXX €"

#### Frontend
- **Checklist-Design** mit grünem Akzent (visuell abgegrenzt vom orangenen Cross-Sell-Widget)
- **WooCommerce-Produktbilder** als Thumbnails
- **Stagger-Entrance-Animation** für sanftes Einblenden
- **Collapsible** mit slideDown/slideUp und Pfeil-Animation
- **Responsive** bis 480px mit kleineren Thumbnails
- **Reduced Motion** Support
- **Shortcode:** `[mh_included_products]` oder `[mh_included_products product_id="123"]`
- **Auto-Placement** über `woocommerce_before_add_to_cart_form` (wenn aktiviert)

#### Datenformat
- Eigener Meta-Key: `_mh_bt_included_products`
- Getrennt von `_mh_bt_linked_products` — beide Features arbeiten unabhängig
- Cleanup in `uninstall.php` ergänzt

## Was war neu in v2.0

- **Mengenformel pro Produkt:** Jedes verknüpfte Produkt hat einen Multiplikator und einen Offset
- **Formel:** `Menge = aufrunden( Hauptprodukt-Menge × Multiplikator + Offset )`
- **Live-Berechnung:** Widget reagiert auf Änderungen am Mengen-Input des Hauptprodukts
- **Mengen im Warenkorb:** AJAX sendet korrekte Stückzahlen pro Produkt
- **Rückwärts-kompatibel:** Alte v1-Verknüpfungen (nur Produkt-IDs) werden automatisch als ×1 +0 interpretiert

## Formel-Beispiele

| Hauptprodukt | Zubehör | × Mult. | + Offset | Ergebnis bei 3 Stk |
|---|---|---|---|---|
| Sichtschutzzaun | Pfosten | 1 | 1 | 4 Pfosten |
| Sichtschutzzaun | Pfostenträger | 1 | 1 | 4 Pfostenträger |
| Sichtschutzzaun | Pfostenkappen | 1 | 1 | 4 Kappen |
| Sichtschutzzaun | Lasur (1 Dose/2 Felder) | 0.5 | 0 | 2 Dosen |
| Hochbeet | Hochbeetvlies | 1 | 0 | 3 Vlies |
| WPC Diele | Clips (8 pro Diele) | 8 | 0 | 24 Clips |
| Gartengarnitur | Sitzauflagen-Set | 1 | 0 | 3 Sets |

## Installation

1. Ordner `mh-bought-together` nach `/wp-content/plugins/` hochladen
2. Plugin aktivieren
3. Produkte bearbeiten → Meta-Box "Häufig zusammen gekauft" → Verknüpfungen anlegen

### Oxygen Builder

Das Plugin versucht sich automatisch einzuklinken. Falls nötig: Shortcode `[mh_bought_together]` in einem Oxygen Code Block platzieren.

## Admin-Bedienung

Die Meta-Box ist jetzt unter der Produktbeschreibung (nicht in der Sidebar) und hat pro Zeile:

1. **Produkt-Suche** — WooCommerce-Produktsuche, wie gewohnt
2. **Multiplikator (×)** — z.B. 1.0 für 1:1, 0.5 für "halb so viele"
3. **Offset (+)** — z.B. 1 für den Extra-Pfosten am Anfang
4. **Live-Beispiel** — zeigt direkt was passiert bei "2 Stk Hauptprodukt → X Stk"

## Technische Details

- **Backward-kompatibel:** Alte v1-Daten (flache ID-Arrays) werden automatisch konvertiert
- **Bestandsprüfung:** AJAX prüft Lagerbestand und passt Menge ggf. nach unten an
- **Oxygen-kompatibel:** Drei Fallback-Methoden (WooCommerce-Hooks → Shortcode → the_content-Filter)
- **Performance:** Kein externer CSS/JS, kein Extra-DB-Table
- **Sicherheit:** Nonces, Capability-Checks, Sanitization, Escaping
- **Accessibility:** prefers-reduced-motion, keyboard focus rings, tabular-nums
