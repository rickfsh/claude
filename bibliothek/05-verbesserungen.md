# Verbesserungen & Optimierungsbedarf (Roadmap)

Ergebnis einer kritischen Review über alle 6 Plugins (Stand: 2026-07-06,
mh-bought-together v3.2.6 eingeschlossen). Zwei Zwecke:

1. **Für neue Plugins:** die Regeln unter „Ab sofort verbindlich" gelten für alles Neue.
2. **Für Bestands-Plugins:** die Funde sind Kandidaten für Fix-Aufträge — Priorität
   hoch → niedrig. `reference/` dokumentiert den ausgelieferten Stand und wird erst
   aktualisiert, wenn ein Fix wirklich released wurde.

## Ab sofort verbindlich (für jedes neue Plugin)

- **Focus-Trap** in jedem Modal/Overlay (Tab/Shift-Tab zyklisch), zusätzlich zu ESC + Fokus-Restore.
- **Kontrast**: kein kleiner weißer Text auf Akzent-Orange (≈1.9:1); dunkler Text auf Akzent
  oder dunklerer Ton für Text-Träger.
- **Rate-Limit + Nonce + Validierung** auf allen schreibenden und allen nopriv-Endpoints.
- **Eine Quelle pro Asset** — Inline-Fallback wird aus der Datei generiert, nie doppelt gepflegt.
- **Vollständiges `uninstall.php`** (alle Options/Transients/Meta).
- **Uploads**: `wp_check_filetype_and_ext()` + Endungs-Whitelist, erst validieren, dann verarbeiten.
- Kanonische Utilities verwenden: sprungfreier Scroll-Lock + `esc()` aus
  `reference/mh-kp-gallery/assets/js/frontend.js:10-25` bzw. `:504`.

## Priorisierte Funde

### HOCH — Sicherheit

**#1 kp-gallery: Upload validiert nur Client-MIME, FTP-Push vor echter Validierung → RCE-Risiko**
`reference/mh-kp-gallery/includes/class-rest-api.php:200-227`, `includes/class-ftp-handler.php:134-141`
Einzige Typprüfung ist `in_array($file['type'], $allowed)` — der Client-MIME-String ist frei
fälschbar. Dateien >2MB überspringen die WebP-Konvertierung und gehen **unverändert per FTP raus,
bevor** `wp_handle_upload` (die einzige echte Validierung) läuft; `safe_filename()` übernimmt die
Endung ungeprüft. Ein Team-Token-Inhaber kann so `shell.php` als „image/jpeg" hochladen.
→ **Fix:** `wp_check_filetype_and_ext()`/`finfo_file()` vor jedem FTP-Push, Endungs-Whitelist,
Reihenfolge umkehren (erst `wp_handle_upload`, dann FTP mit dem validierten Pfad).

**#2 kp-gallery: Team-Login ohne Rate-Limit → Brute-Force**
`reference/mh-kp-gallery/includes/class-upload-portal.php:31-54`
Ein geteiltes Team-Passwort, unbegrenzt probierbar über `POST /mh-stl/v1/auth`.
→ **Fix:** Transient-Zähler pro IP (z.B. 5 Versuche/15min → 429).

### MITTEL

**#3 bought-together: nopriv-Tracking ohne Nonce/Validierung/Limit → unbegrenztes Options-Wachstum**
`reference/mh-bought-together/mh-bought-together.php:2644-2689`
`mh_bt_track` ignoriert die mitgesendete Nonce bewusst; jede neue `product_id:source_id`-Kombination
legt dauerhaft einen Key in `mh_bt_deselections` an — anonym aufblähbar (DoS/Datenmüll).
→ **Fix:** IDs gegen existierende Produkte validieren, Keys deckeln + GC, Rate-Limit
(Muster: `mh-spielturm-vergleich/includes/class-frontend.php:347-353`).

**#4 bought-together: Add-to-Cart ohne Rate-Limit**
`mh-bought-together.php:2193-2236` — Nonce ✓, serverseitiger Rabatt ✓, aber kein Limit
(Hauskonvention: max. 12/min wie spielturm). → Transient-Rate-Limit nachrüsten.

**#5 A11y: Kein echter Focus-Trap in irgendeiner Lightbox**
`mh-shop-galerie.js` (aria-modal :114, Fokus-Restore :322), `spielturm-vergleich.js:3641`
ESC/Pfeile/Fokus-Restore existieren, aber kein Tab-Handler — Tab wandert aus dem offenen
Modal in die Seite. spielturm setzt zudem nur `role=dialog` ohne `aria-modal`.
→ **Fix:** gemeinsamer Focus-Trap-Baustein, einheitlich in alle Lightboxen.

**#6 A11y: Weißer Text auf Akzent-Orange verfehlt WCAG AA**
Systemisch (alle Primär-CTAs): `#FAA41A`/`#f7af4a` + Weiß ≈ 1.9:1 (AA verlangt 4.5:1).
→ **Fix:** dunkler Text auf Akzent-Flächen oder dunklerer Ton (z.B. `#b56a00`) für
Text-Träger; Orange bevorzugt für Flächen/Borders/Icons.

**#7 Kein Build-Step: unminifizierte Inline-Assets, uneinheitliche .min-Pipeline**
`mh-shop-galerie.php:108-127` inlined pro Produktseite ~9.3KB CSS + ~27KB JS unminifiziert
(je Request, nicht browser-cachebar). kp-gallery hat gar keine `.min`; spielturm minifiziert
manuell (Drift-Gefahr). → **Fix:** minimaler Build (esbuild/terser + cssnano), Inline-Strategien
inlinen das Minifikat.

**#8 bought-together: 3072-Zeilen-Monolith, prozedural**
Verstößt gegen den Größen-Gradienten (`02-architektur.md` §1) — Admin, AJAX, Analytics,
Metaboxen, Rendering ungetrennt. → Bei nächster größerer Arbeit in `includes/class-mh-bt-*.php`
aufteilen.

**#9 bought-together: keine Design-Tokens, markenfremde Farben**
0× `var(--…)` im CSS; Bootstrap-Blau `#3498db`/`#2980b9` und Bootstrap-Grün `#5cb85c` neben
dem Marken-Orange. → Token-Startblock (`01-design-tokens.md` §7) nachrüsten, Fremdfarben
durch Marken-Semantikfarben ersetzen.

**#10 Duplizierte Helfer mit Qualitätsgefälle**
`esc()` in ≥6 Kopien/2 Varianten; Scroll-Lock in 3 Ausführungen — kp-gallery hat die
sprungfreie Zähler-Variante (`frontend.js:10-25`), spielturm nutzt naives
`overflow:hidden` (`spielturm-vergleich.js:971,3170` → 17px-Layout-Sprung), shop-galerie keine.
→ kp-gallery-Varianten sind kanonisch (siehe „Ab sofort verbindlich"); spielturm bei
Gelegenheit umstellen.

### NIEDRIG

**#11 Keyframes 4–7× unter eigenem Namen redefiniert** — Spinner/Pulse-Familien je Plugin
neu definiert. Akzeptiert (bewusste Plugin-Isolation), aber bei Forks nicht weiter vermehren.

**#12 Keyframe-Drift bei gleichem Namen**: `mhStvPulse` inline in PHP mit `50%{opacity:.8}`
(`class-frontend.php:315`) vs. CSS `50%{opacity:.4}` (`spielturm-vergleich.css:204`).
→ eine Quelle.

**#13 Kopplung spielturm ↔ bought-together**: Einbettung ist sauber per `shortcode_exists()`
abgesichert, aber die Bundle-Add-to-Cart-Logik wurde **kopiert** (`_mh_sh_*`-Meta) und driftet.
→ Bei nächster Änderung an einer der beiden Stellen: gemeinsame Funktion extrahieren.

**#14 Versionierung/i18n uneinheitlich**: lazy-konfigurator hat weiterhin keine
`*_VERSION`-Konstante (Header 7.2.0 ≠ 7.2.2). bought-together ist voll internationalisiert
(170× `__()`), alle anderen hardcoden Deutsch. → i18n-Politik einmal festlegen
(für Single-Tenant mega-holz.de ist hartes Deutsch okay — dann konsequent überall).

**#15 Kein Linting/CI**: keine PHPCS/ESLint/Stylelint-Configs, keine Tests, keine CI.
→ Minimal-Setup: PHPCS (WordPress-Standards) + ESLint + ein GitHub-Action-Lint-Job.

## Empfohlene Reihenfolge für Fix-Aufträge

1. **Sofort:** #1 + #2 (kp-gallery-Härtung — ein Auftrag), #3 (Tracking-Härtung)
2. **Nächster bought-together-Release:** #4, #9, Inline-Drift beseitigen (eine Asset-Quelle), uninstall vervollständigen
3. **Querschnitt:** #5 Focus-Trap-Baustein + #6 Kontrast (ein „A11y-Runde"-Auftrag über alle Plugins)
4. **Infrastruktur:** #7 Build-Step + #15 Tooling (entschärft #10–#12 an der Wurzel)
