# Konfigurator-Engine (State, Preise, URL-Sharing)

**Referenz:** `reference/mh-spielturm-vergleich/public/js/spielhaus-konfigurator.js` (2344 Zeilen)
— der Spielhaus-Konfigurator. (Der „Lazy-Konfigurator" ist dagegen nur ein Lade-Wrapper
für den 3D-Konfigurator, siehe Steckbrief.)

## Architektur

- **Ein zentrales State-Objekt** als Single Source of Truth (`:184-189`):
  ```js
  state = { houseId, selections: { addonId: { selected, qty } } }
  ```
  Die UI wird immer aus dem State abgeleitet, nie umgekehrt.
- **`recompute()` als Update-Hub** (`:1709-1724`): jede Interaktion ruft es auf —
  es berechnet alle Extra-Preise neu, baut die Summary und füllt die Mobile-Bar.
- **Kein Wizard**: alle Schritte gleichzeitig sichtbar und live-reaktiv
  (Schritt 1 Haus-Auswahl → 2 Addon-Cards mit Mengen-Steppern → 3 Live-Summary → 4 Add-to-Cart).

## Staffelpreise / Bundles

- `tierPercent(addon, distinct)` wählt die höchste passende Rabattstufe nach **Anzahl
  unterschiedlicher gewählter Extras** (`:507-514`).
- `computeTotals()` (`:517-556`) liefert Positionen mit brutto/netto/Prozent,
  Gesamtersparnis, `total = house.price + extrasNet`.
- **Wichtig:** Client-Rabatt ist nur Vorschau — der echte Rabatt wird **serverseitig als
  negative Fee** angewendet.

## URL-State (teilbare Konfigurationen)

- `encodeConfig()` serialisiert die Auswahl kompakt als `id.qty-id-…`
  (Sentinel `"0"` = explizit leer); in die Adresszeile via `history.replaceState`
  ohne Reload (`writeUrl`/`syncUrl`, `:328-370`).
- `applyUrlConfig`/`parseConfig` lesen robust zurück — auch gegen von Hand getippte URLs (`:280-326`).
- `isDefaultConfig()` lässt den Parameter weg, wenn die Auswahl den Defaults entspricht.
- **Share-Button**: Clipboard mit `execCommand`-Fallback (`copyToClipboard`/`copyFallback`,
  `:406-432`), natives `navigator.share`, UTM-Tagging.

## Add-to-Cart & Analytics

- Bundle (Haus + Extras) per `fetch` + FormData an AJAX-Endpoint; danach
  WooCommerce-Fragment-Event feuern (`:579-657`).
- **Batched Analytics**: Event-Queue, alle 2s geflusht und auf `pagehide` per
  `navigator.sendBeacon` (`track`/`flushTrack`, `:199-230`; gespiegelt in
  `spielturm-vergleich.js:188-226`). Serverseitig: eigene Tabelle mit atomaren Upserts
  (`includes/class-analytics.php`).

## UI-Details, die gut ankamen

- Preis-Count-up-Animation bei Änderungen (`animatePrice`, `spielturm-vergleich.js:625`).
- „Inklusive"-Addons als Cards mit `border-style: dashed`; gewählte Cards mit
  Akzent-Inset-Ring + warmem Tint (siehe `01-design-tokens.md` §4).
- Sticky-CTA: mobil unten als Bar, Desktop oben — Tokens dort erneut deklarieren
  (außerhalb des Scopes!).
- Mobile DOM-Reorder per JS (`applyLayout`, `spielturm-vergleich.js:1853-1913`).
