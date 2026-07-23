# MH SONO Grid

Automatisches, filterbares Modell-Karten-Grid für die MEGA FLEX SONO Produktreihe.
Ersetzt die handgepflegte Modell-Liste im Oxygen-Code-Block der SONO-Landingpage:
Produkte erscheinen **automatisch**, sobald sie in der SONO-Kategorie liegen —
inklusive künftiger Varianten und neuer Kategorien (Einzeltore, Doppeltore, …).

## Shortcodes

| Shortcode | Zweck |
|---|---|
| `[mh_sono_grid category="sono"]` | Das komplette Grid: Filterleiste + Sektionen + Modell-Karten |
| `[mh_sono_count what="models"]` | Nur eine Zahl: `models`, `heights`, `products` oder `finishes` (für Zähler wie „13 Designs") |

Weitere Grid-Attribute: `show_filters="0"` (Filterleiste aus), `button_text="…"`.

## Daten-Vertrag: So erscheint ein Produkt automatisch auf der SONO-Seite

1. **Produkt in die Kategorie „SONO" legen.** Tore zusätzlich in die passende
   Unterkategorie (z. B. *Einzeltore*, *Doppeltore*) — jede Unterkategorie wird
   automatisch eine eigene Sektion **und** ein Filter-Eintrag.
2. **Drei Eigenschaften (globale Attribute) am Produkt pflegen:**
   - **Modell** (`pa_modell`, z. B. *Milano*) — gruppiert die Farbvarianten zu **einer** Karte
   - **Höhe** (`pa_hoehe`, z. B. *180 cm*) — bestimmt Höhen-Sektion und Höhen-Filter
   - **Oberfläche** (`pa_oberflaeche`, z. B. *Anthrazit / Silber / Lärchenoptik*) — wird zum Farb-Punkt auf der Karte
3. **Produktbild setzen, Produkt veröffentlichen.** Name, Preis, Bild, Link und
   Lagerstatus kommen automatisch aus WooCommerce.

Fehlt etwas, erscheint das Produkt trotzdem (als Einzel-Karte unter „Weitere
Modelle") und im WP-Admin ein Hinweis mit Edit-Links, was nachzupflegen ist.
Alle drei Angaben werden notfalls aus dem Produkttitel erkannt:

- **Höhe:** „… 180 cm …"
- **Oberfläche:** Keyword im Titel (Anthrazit / Silber / Lärche)
- **Modell (seit 1.1.0):** das letzte komplett **GROSS** geschriebene Wort im Titel
  (Namenskonvention, z. B. „… 180 x 120 cm **ALBA** - Anthrazit" → Modell *Alba*).
  `MEGA`/`FLEX`/`SONO120` u. ä. werden über Stoppliste + „nur Buchstaben"-Regel
  ignoriert (Filter `mh_sono_model_stopwords`).

Das Attribut `pa_modell` bleibt die sauberste Quelle und **gewinnt immer**, wenn
es gepflegt ist — der Titel-Parse greift nur als Fallback.

**Neue Modelle, Höhen, Oberflächen und Tor-Kategorien brauchen keine Code-Änderung.**
Eine neue Oberfläche bekommt automatisch einen (grauen) Punkt; für eine eigene
Farbe eine CSS-Regel `.mhsono-finish--<slug>` ergänzen.

## Sortierung (ohne Code steuerbar)

- **Modelle innerhalb einer Sektion:** WooCommerce-Produkt-Sortierung (`menu_order`,
  im Produkte-Listing per Drag), Gleichstand alphabetisch.
- **Tore-Sektionen:** Reihenfolge der Unterkategorien (Produkte → Kategorien → Drag).
- **Höhen-Sektionen:** immer absteigend 180 → 90.
- **Oberflächen-Punkte:** Anthrazit → Silber → Lärche (Filter `mh_sono_finish_order`).

## Technik-Notizen

- **Query:** `WP_Query` + `tax_query` auf `product_cat` (inkl. Unterkategorien),
  katalog-versteckte Produkte ausgeschlossen; Shop-Option „ausverkaufte Artikel
  verstecken" wird respektiert, sonst ausgegrauter Punkt + Hinweis.
- **Filter:** rein client-seitig (ES5) über `data-*`-Attribute — kein AJAX, dadurch
  WP-Rocket-/Page-Cache-sicher und alle Modelle im Quelltext (SEO). Deep-Links:
  `#sono-h180` (Höhe) und `#sono-typ-einzeltore` (Typ) wählen den Filter vor.
- **Cache:** Struktur 1 h im Transient `mh_sono_grid_*`; Preis + Lagerstatus werden
  bei jedem Aufruf frisch geholt (Sales greifen sofort). Invalidierung bei
  Produkt-/Kategorie-Änderungen automatisch.
- **WP Rocket:** cached die ganze Landingpage als HTML. Damit neue Produkte sofort
  sichtbar werden: die SONO-Seiten-URL unter *WP Rocket → Erweiterte Regeln →
  Immer bereinigen* eintragen (sonst erscheinen sie erst nach dem nächsten Purge).
- **Assets:** Registrierung auf `wp_enqueue_scripts`, Enqueue erst im Shortcode
  (Oxygen-sicher, mit `wp_footer`-Spätdruck-Fallback), Content-Hash-Versionierung.
- **Filter-Hooks:** `mh_sono_attr_map`, `mh_sono_model_stopwords`, `mh_sono_finish_order`, `mh_sono_finish_keywords`,
  `mh_sono_height_titles`, `mh_sono_height_section_types`, `mh_sono_default_type_label`,
  `mh_sono_notice_category`.

## Einmalige Einrichtung (Go-Live-Checkliste)

1. Plugin aktivieren (`mhsonogridv1.0.0.zip`).
2. Globale Attribute `Modell`, `Höhe`, `Oberfläche` anlegen (falls nicht vorhanden)
   und an allen SONO-Produkten pflegen — die Admin-Notice listet alles Fehlende.
   Nutzt der Shop andere Attribut-Slugs: Filter `mh_sono_attr_map` setzen.
3. Landingpage-Code-Block auf v6 aktualisieren (`sono-landingpage/sono-landingpage-codeblock.php`)
   — Modelle-Sektion kommt dann per `[mh_sono_grid]`, Zähler rechnen sich selbst.
4. WP-Rocket-Purge-Regel für die SONO-Seite eintragen (s. o.).
