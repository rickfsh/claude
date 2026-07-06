---
name: bibliothek-update
description: >
  Pflegt die Plugin-Bibliothek: ein fertiges/aktualisiertes Plugin (Zip-Upload oder
  Pfad) wird in reference/ übernommen und neue Patterns/Learnings werden in die
  bibliothek/-Doku eingearbeitet. Verwenden, wenn der Nutzer ein fertiges Plugin
  einpflegen, die Bibliothek aktualisieren oder Learnings sichern will.
---

# Bibliothek aktualisieren

Ziel: `reference/` und `bibliothek/` auf den Stand des neuesten Plugin-Codes bringen,
damit zukünftige Sessions davon profitieren.

## Schritte

1. **Quelle beschaffen:** Zip aus dem Upload-Verzeichnis ins Scratchpad entpacken
   (oder angegebenen Pfad verwenden). Die innere Plugin-Ebene identifizieren
   (Ordner mit der Haupt-`.php`-Datei, ohne Versions-Wrapper).

2. **Abgleich mit `reference/`:**
   - **Bestehendes Plugin (neue Version):** `diff -r` gegen `reference/<slug>/` —
     die Änderungen sind die Learnings. Header-Changelog der neuen Version lesen.
   - **Neues Plugin:** vollständige Analyse wie bei der Erst-Erstellung der Bibliothek:
     Architektur (Struktur, Shortcodes, Asset-Strategie, AJAX/Caching), Styling
     (Farben, Tokens, Abweichungen), JS-Features (was ist neu, was ist übernommen).

3. **Doku aktualisieren** (nur was sich wirklich geändert hat, Stil und Sprache
   der bestehenden Seiten beibehalten — Deutsch, konkrete Datei:Zeilen-Referenzen):
   - `bibliothek/04-plugin-steckbriefe.md`: Steckbrief anlegen/aktualisieren
     (Version, Highlights, wofür als Vorlage geeignet).
   - Betroffene `bibliothek/features/*.md`: neue/verbesserte Implementierungen als
     Referenz eintragen; gefixte Bugs unter „Stolperfallen" dokumentieren.
   - `bibliothek/01-design-tokens.md` / `02-architektur.md` / `03-umgebung.md`:
     nur bei neuen Konventionen oder bewussten Abweichungen anpassen.
   - Neues Plugin-Kürzel in die Vergeben-Liste in `bibliothek/00-checkliste.md` §1,
     `CLAUDE.md` (Konvention 4) und `02-architektur.md` §2 aufnehmen.

4. **`reference/` ersetzen/ergänzen:** alten Stand von `reference/<slug>/` löschen,
   neue Quellen unverändert hineinkopieren.

5. **Konsistenz prüfen:** Alle in der Doku genannten `reference/…`-Pfade existieren;
   bei geänderten Dateien stichprobenartig prüfen, ob genannte Funktionsnamen/
   Zeilenbereiche noch stimmen (sonst Zeilenangaben aktualisieren oder auf
   Funktionsnamen umstellen).

6. **Committen & pushen:** aussagekräftige Commit-Message, z.B.
   `bibliothek: mh-xyz v2.1.0 eingepflegt (neues Sticky-Filter-Pattern)`.
   Push auf den aktuellen Branch mit `git push -u origin <branch>`.

## Wichtig

- Referenz-Code **niemals verändern** — er dokumentiert den ausgelieferten Stand.
- Versions-Mismatches (Header ≠ Ordnername) nicht „reparieren", sondern im
  Steckbrief vermerken.
- Kurz zusammenfassen, welche Learnings neu aufgenommen wurden.
