# Quoodle

Eine kleine, eigenständige PHP-Anwendung, mit der Lehrende kurze Single-Choice-Quizze
an ihre Kurse oder Klassen verteilen können. Sie laden eine Excel- oder CSV-Datei mit
den Fragen hoch und erhalten **zwei** Links: einen öffentlichen Link für die Lernenden
sowie einen privaten Link für die Auswertung.

Der Name *Quoodle* spielt auf die spielerische Leichtigkeit von *Doodle* an, aber
für den Quiz-Kontext — einfach, schnell, niedrigschwellig.

## Funktionen

- **Upload aus Excel** (`.xlsx`) oder CSV — kein neuer Editor nötig
- **Zwei einzigartige URLs pro Quiz**:
  - ein **öffentlicher Teilnahme-Link** (mit QR-Code) zum Ausfüllen des Quiz
  - ein **privater Auswertungs-Link** (mit eigenem Token) für die Live-Statistik
- **Single-Choice-Fragen** mit 1–5 Distraktoren pro Frage
- **Automatisches Feedback** für Lernende: Gesamtpunktzahl plus Erklärung zu jeder Frage
- **Live-Auswertung** für Lehrende: prozentualer Anteil richtiger Antworten pro Frage
  und Aufschlüsselung pro Antwortmöglichkeit, damit sichtbar wird, welche Distraktoren
  besonders verlockend waren
- **Export als Excel oder CSV** — aggregierte Zahlen auf Ebene jeder einzelnen
  Antwortmöglichkeit, für eigene Auswertungen oder Archivierung
- **Antwortreihenfolge wird bei jedem Aufruf neu gemischt**
- **Eine Frage pro Seite** — Stepper-Navigation mit Fortschrittsbalken,
  damit Lernende sich auf die aktuelle Frage konzentrieren
- **Deutsch / Englisch** — Sprachumschaltung im Header, wird per Cookie gespeichert;
  automatische Erkennung über `Accept-Language`
- **Dark Mode** — umschaltbar im Header, wird per Cookie gespeichert;
  erkennt die gespeicherte Einstellung beim Laden ohne Flackern (FOUC-frei)
- **Vollständig anonym** — kein Login, keine Teilnehmerliste, keine Zuordnung
  einzelner Antworten
- **Concurrency-sicher** dank `flock()` — gleichzeitige Abgaben kollidieren nicht
- **Keine Abhängigkeiten**: kein Composer, keine Datenbank, kein Node.js — nur PHP-Dateien
- **Keine externen Dienste**: QR-Codes werden lokal erzeugt — kein Datenabfluss
- **Impressum und Datenschutzerklärung** als Mustertexte enthalten (siehe unten)

## Voraussetzungen

- PHP 7.4 oder neuer (PHP 8.x empfohlen)
- PHP-Erweiterungen: `zip`, `xml`/`simplexml` (in PHP-Standardbuilds enthalten)
- Ein Webserver mit PHP-Unterstützung (Apache + mod_php, Nginx + PHP-FPM, …)
- Das Verzeichnis `data/` muss vom Webserver beschreibbar sein

## Installation

1. Den gesamten Ordner `quizapp` in das Document-Root des Webservers kopieren
   (oder in ein beliebiges Unterverzeichnis — die App nutzt relative Pfade).
2. Schreibrechte für `data/` setzen:
   ```bash
   chmod -R 775 data/
   chown -R www-data:www-data data/   # an den jeweiligen Webserver-User anpassen
   ```
3. Die Seite `https://ihr-server/quizapp/` im Browser öffnen. Fertig.

Es gibt keine Konfigurationsdatei und keine Datenbank.

## ⚠️ Vor dem Produktiv-Einsatz unbedingt anpassen

Die Anwendung enthält Mustertexte für **Impressum** (`impressum.php`) und
**Datenschutzerklärung** (`datenschutz.php`). Diese sind im Footer jeder Seite
verlinkt. Bevor Sie das Tool öffentlich erreichbar machen:

1. **Alle gelb markierten Platzhalter** in `impressum.php` und `datenschutz.php`
   durch Ihre tatsächlichen Angaben ersetzen.
2. **Im Schul-Einsatz**: Mit der Schulleitung bzw. dem Schulträger und ggf. dem
   schulischen Datenschutzbeauftragten klären, ob die Anwendung dort eingesetzt
   werden darf.
3. **Rechtsberatung einholen**, wenn Sie unsicher sind — die Mustertexte sind
   keine Rechtsberatung. Insbesondere folgende Punkte können je nach Einsatz
   variieren: Aufsichtsbehörde, Umsatzsteuer-IdNr., Datenschutzbeauftragter,
   Hosting-Provider-Angaben.

## So funktionieren die zwei URLs

Beim Erstellen eines Quiz werden zwei Zufalls-Tokens generiert:

| URL | Pfad | Wer hat ihn? | Zweck |
|---|---|---|---|
| **Schüler** | `/quiz.php?id=<id>` | Klasse | Quiz ausfüllen, eigenes Ergebnis und Erklärungen sehen |
| **Lehrkraft** | `/stats.php?id=<id>&t=<token>` | Nur die Lehrkraft | Aggregierte Auswertung: % richtig pro Frage, Aufschlüsselung pro Antwort |

Die `id` umfasst 64 Zufalls-Bits, der zusätzliche Lehrkraft-Token weitere 96 Bits.
Auch wer den Schüler-Link kennt, kann den Lehrkraft-Link daraus nicht ableiten.

Nach dem Upload werden beide Links zusammen auf der **Freigabeseite**
(`/share.php?id=…&t=…`) angezeigt. **Diese Seite als Lesezeichen speichern** —
sie ist die einzige Stelle, an der beide Links gemeinsam sichtbar sind.

## Was die Lehrkraft sieht

Die Auswertungsseite zeigt für jede Frage:
- Den Fragetext und den Anteil richtiger Antworten
  (farblich kodiert: grün ≥ 75 %, orange 50–74 %, rot < 50 %)
- Einen Balken zur Visualisierung des Anteils
- Eine Aufschlüsselung jeder Antwortmöglichkeit mit Anzahl und prozentualem Anteil;
  die korrekte Antwort ist hervorgehoben

So sieht man auf einen Blick, welche Fragen schwierig waren und welche
Distraktoren besonders verlockend formuliert sind.

## Export der Auswertung

Auf der Auswertungsseite (`stats.php`) stehen zwei Download-Buttons zur Verfügung,
sobald mindestens ein Quiz-Versuch eingegangen ist:

- **Excel-Export** (`.xlsx`) mit drei Tabellenblättern:
  - *Zusammenfassung* — Titel, Anzahl Versuche, Ø richtig
  - *Fragen* — eine Zeile pro Frage mit Gesamtzahl, Richtige, Anteil richtig
  - *Antwortmöglichkeiten* — eine Zeile pro Antwortmöglichkeit mit Anzahl und Anteil
- **CSV-Export** (`.csv`, UTF-8 mit BOM) mit denselben Informationen als
  Semikolon-getrennte Datei für einfachen Import in Excel oder statistische
  Programme

Die Exporte enthalten ausschließlich aggregierte Zahlen. Einzelne Antworten
oder personenbezogene Daten werden nicht exportiert, weil sie gar nicht
gespeichert sind.



```
quizapp/
├── index.php           ← Startseite (Upload-Formular)
├── upload.php          ← Verarbeitet den Upload, erstellt das Quiz
├── share.php           ← Zeigt BEIDE Links und QR-Codes an
├── quiz.php            ← Teilnahme-Seite für Lernende
├── submit.php          ← Wertet aus, aktualisiert Zähler, zeigt Feedback
├── stats.php           ← Private Auswertung (für Lehrende)
├── export.php          ← Excel-/CSV-Export der Auswertung
├── impressum.php       ← Mustertext, vor Veröffentlichung anpassen
├── datenschutz.php     ← Mustertext, vor Veröffentlichung anpassen
├── assets/style.css
├── lib/                ← Interne Module (nicht über das Web erreichbar)
│   ├── xlsx_reader.php ← Liest .xlsx-Uploads
│   ├── xlsx_writer.php ← Schreibt .xlsx-Exports
│   ├── quiz_parser.php
│   ├── qrcode.php      ← Lokaler QR-Code-Generator (SVG)
│   ├── lang.php        ← i18n-System (DE/EN, Cookie, Accept-Language)
│   ├── storage.php
│   ├── header.php
│   ├── footer.php
│   └── .htaccess
├── lang/               ← Sprachdateien
│   ├── de.php
│   ├── en.php
│   └── .htaccess
├── data/
│   ├── quizzes/        ← Eine JSON-Datei pro Quiz, benannt nach Zufalls-ID
│   └── .htaccess
└── templates/
    ├── quiz_vorlage.xlsx
    └── quiz_vorlage.csv
```

## Dateiformat für Quiz-Inhalte

Die erste Zeile ist die Kopfzeile. Folgende Zeilen enthalten je eine Frage.

| Spalte | Bedeutung |
|---|---|
| A | Fragetext |
| B | Korrekte Antwort |
| C–E (oder mehr) | Distraktoren (falsche Antworten) |
| Letzte Spalte | Erklärung, die im Feedback angezeigt wird |

Sie können zwischen **1 und 5 Distraktor-Spalten** verwenden. Leere Distraktor-Zellen
werden ignoriert, sodass Fragen unterschiedlich viele Antwortmöglichkeiten haben können.
Die letzte nicht-leere Spalte wird **immer** als Erklärung interpretiert.

## Was wird gespeichert?

Pro Quiz wird eine JSON-Datei unter `data/quizzes/<id>.json` angelegt. Sie enthält:

- die Fragen, korrekten Antworten, Distraktoren und Erklärungen
- den Lehrkraft-Token (zur Authentifizierung der Auswertungsseite)
- ein aggregiertes Stats-Objekt: Gesamtanzahl Versuche und pro Frage je
  `correct_count`, `total_count` und eine `choice_counts`-Map

**Einzelne Schüler-Antworten werden nicht gespeichert.** Es werden ausschließlich
Zähler aktualisiert — eine Zuordnung von Antworten zu einer Person ist technisch
nicht möglich. Auch IP-Adressen oder Zeitstempel werden in der Quiz-Datei nicht abgelegt.

## Sicherheitshinweise

- **Quiz-IDs** sind 64 Bit (`random_bytes(8)`). **Lehrkraft-Tokens** zusätzlich 96 Bit.
  Beides ist nicht durchprobierbar.
- **Path-Traversal** wird verhindert: IDs werden gegen `^[a-f0-9]{16}$` validiert.
- **Token-Vergleich** erfolgt mit `hash_equals()` (timing-sicher).
- **Concurrent Stats-Updates** nutzen `flock(LOCK_EX)` — gleichzeitige Abgaben
  überschreiben sich nicht.
- **Upload-Validierung**: nur `.xlsx`- und `.csv`-Endungen mit Größenlimit.
- **Kein SQL** im Spiel → keine SQL-Injection möglich.
- **Alle Ausgaben** werden mit `htmlspecialchars()` HTML-escaped.
- **`data/` und `lib/`** sind per `.htaccess` gegen Direktzugriff geschützt.
  Für Nginx entsprechend ergänzen:
  ```nginx
  location ~ ^/quizapp/(data|lib)/ { deny all; }
  ```

## Anpassungen

### Maximale Upload-Größe
In `upload.php` anpassen. Außerdem `upload_max_filesize` und `post_max_size` in
der `php.ini` prüfen.

### Optik
Die gesamte Gestaltung steckt in `assets/style.css`. Die Farbpalette ist als
CSS-Custom-Properties am Dateianfang definiert — durch Anpassen dieser Werte
lässt sich das gesamte Aussehen umfärben.

### QR-Codes
QR-Codes werden lokal vom mitgelieferten Generator (`lib/qrcode.php`) erzeugt
und als SVG direkt in die Seite eingebettet — **keine externen Dienste, keine
zusätzlichen Bibliotheken, keine GD-Erweiterung nötig**. Der Generator
implementiert ISO/IEC 18004 (Byte-Modus, Fehlerkorrektur-Level M) und unterstützt
URLs bis ca. 270 Zeichen.

### Quiz-Dateien löschen
Die App löscht keine Dateien automatisch. Empfehlung: am Ende des Schuljahres
oder nach Abschluss einer Unterrichtseinheit den Inhalt von `data/quizzes/`
manuell aufräumen.

### Per-Schüler-Statistik
Aus Datenschutzgründen werden bewusst keine personenbezogenen Daten gespeichert.
Wer trotzdem detailliertere Auswertungen benötigt, müsste `submit.php` so
erweitern, dass z. B. Namen oder Pseudonyme erfasst werden — dabei aber
zwingend die Datenschutzerklärung und (im Schul-Einsatz) die Vorgaben des
Schulträgers entsprechend anpassen.

## Lizenz

MIT — beliebig verwendbar.
