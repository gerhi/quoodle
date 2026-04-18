# Quoodle — Requirements Addendum v1.1

**Datum:** 2026-04-17
**Anlass:** Erweiterung um sofortiges Feedback, Bearbeitungszeit, Tab-Wechsel-Erkennung und deren aggregierte Auswertung für Lehrkräfte.

Dieses Dokument ergänzt das ursprüngliche `quoodle-requirements.md`. Die bestehenden Requirements bleiben unverändert; dieser Anhang führt neue bzw. geänderte Requirements ein.

---

## 4.2 Quiz (Student Flow) — Änderungen

### FR-10a: Immediate Answer Feedback (NEU)

Sobald ein Lernender in einem Stepper-Schritt eine Antwort auswählt:

1. **Keine weiteren Klicks** auf Antwortoptionen sind in diesem Schritt möglich (der erste Klick zählt wie ein Commit).
2. Die **korrekte Antwort** wird sofort grün hervorgehoben (Haken-Marker ✓).
3. **Bei falscher Antwort** wird die vom Lernenden gewählte Option rot hervorgehoben (Kreuz-Marker ✗).
4. Eine optionale **Erklärung** (Spalte F der Quelldatei) wird unterhalb der Antworten eingeblendet, sofern vorhanden.
5. Der **"Weiter"-Button** wird nach dem Klick je nach Ergebnis wie folgt gesteuert:
   - **Richtige Antwort** → sofort aktiv, Lernender kann direkt zur nächsten Frage.
   - **Falsche Antwort** → für **5 Sekunden deaktiviert**; der Button zeigt einen sichtbaren Countdown ("Weiter (5s)", "Weiter (4s)", …). Erst danach kann weitergeklickt werden.
6. Die Änderung der Antwortmarkierung und der Erklärungsanzeige erfolgt **ohne Serverrunde** (Client-seitig).
7. Bei der finalen Abgabe zählt weiterhin die Server-Bewertung (Exact-String-Match gegen die gespeicherte Korrektantwort), damit Client-Manipulation die Statistik nicht verfälscht.

**Akzeptanzkriterien:**
- Klick auf Antwort → Farbmarkierung + ggf. Erklärung sofort sichtbar.
- Bei richtiger Antwort: "Weiter" sofort klickbar.
- Bei falscher Antwort: "Weiter" zeigt 5 Sekunden lang einen Countdown, danach aktiv.
- Zurück-Navigation zu einer bereits beantworteten Frage: bereits gezeigtes Feedback bleibt sichtbar.
- Wird der Zurück-Button benutzt und dann wieder Weiter gedrückt, startet **keine** neue Wartezeit.
- Ohne JavaScript entfällt das Feedback; der klassische Bulk-Submit am Ende bleibt funktionsfähig.

**Sicherheitsbemerkung:** Für sofortiges Feedback muss der Browser die korrekten Antworten kennen. Diese werden als JSON im Seiten-Markup ausgeliefert. Findige Lernende können sie in den DevTools einsehen. Quoodle ist als **Lern- und Selbsttest-Werkzeug** konzipiert, nicht als Prüfungssystem — dieser Tradeoff ist bewusst gewählt. Für prüfungsrelevante Szenarien müsste das Feedback server-seitig pro Frage via Fetch-Request erfolgen, was in dieser Version nicht implementiert ist.

---

### FR-10b: Elapsed-Time Tracking (NEU)

Die Client-Seite misst die Zeit zwischen dem Laden der Quiz-Seite und dem Klick auf den Submit-Button und übermittelt diese in Sekunden als versteckter Formularwert (`elapsed_time`).

**Anforderungen:**
- Zeitmessung startet beim Script-Start (`window.__QUOODLE` Initialisierung).
- Zeitmessung endet beim Submit-Event.
- Der Wert wird auf Ganzsekunden gerundet.
- Der Server **begrenzt** den Wert auf das Intervall [0; 86 400] (24 h); unplausible Werte werden auf 0 gesetzt.
- Der Wert wird **pro Abgabe** an den Lernenden zurückgemeldet (Feedback-Seite) und **kumuliert** in der Quiz-Statistik gespeichert (siehe FR-14a).

**Anzeige für Lernende:** In der Score-Banner-Zone unterhalb der Prozentangabe:
> ⏱ Bearbeitungszeit: **2:47**

Format: `m:ss` bis 59:59; darüber `h:mm:ss`.

---

### FR-10c: Tab-Switch Detection (NEU)

Die Client-Seite zählt, wie oft während der Quiz-Bearbeitung die Tab-Sichtbarkeit auf "hidden" wechselt. Erfasst über das `visibilitychange`-Event der Page Visibility API. Übermittelt als versteckter Formularwert (`tab_switches`).

**Anforderungen:**
- Jeder Wechsel zu einem anderen Tab, einem anderen Fenster, das Minimieren oder das Sperren des Geräts zählt als ein Wechsel.
- Das bloße Ändern der Fenstergröße oder Öffnen eines DevTools-Panels zählt nicht (unabhängig vom Browser-Verhalten).
- Der Server **begrenzt** den Wert auf das Intervall [0; 10 000]; unplausible Werte werden auf 0 gesetzt.

**Anzeige für Lernende:** Nur wenn `tab_switches > 0`, direkt neben der Bearbeitungszeit:
> ⚠ 3 Tab-Wechsel

Pluralbehandlung über die bestehende `tp()`-Funktion.

---

## 4.3 Analytics (Educator Flow) — Änderungen

### FR-14a: Aggregation von Zeit & Tab-Wechseln (NEU)

Das `stats`-Objekt im Datenmodell wird um folgende Top-Level-Felder ergänzt:

| Feld                        | Typ  | Beschreibung                                                           |
|-----------------------------|------|------------------------------------------------------------------------|
| `total_time_seconds`        | int  | Summe aller Bearbeitungszeiten in Sekunden                            |
| `total_tab_switches`        | int  | Summe aller Tab-Wechsel über alle Abgaben                             |
| `attempts_with_tab_switch`  | int  | Zahl der Abgaben mit mindestens einem Tab-Wechsel                     |

**Aggregation:** Alle drei Felder werden bei jeder `db_record_submission()` atomar inkrementiert, innerhalb der bestehenden SQLite-Transaktion mit Exponential-Backoff-Retry (siehe FR-14).

**Migration:** Bestehende Quizzes, deren `stats`-Objekt diese Felder noch nicht enthält, werden defensiv behandelt (`?? 0`). Beim ersten neuen Submit werden die Felder automatisch angelegt. Es ist **keine manuelle Datenmigration** notwendig.

---

### FR-12a: Erweiterte Summary-Cards (GEÄNDERT)

Die Stats-Seite zeigt **zusätzlich zu den bisherigen drei Karten** (Abgaben, Fragen, Ø richtig) zwei neue Karten:

- **Ø Zeit** — durchschnittliche Bearbeitungszeit pro Abgabe (`total_time_seconds / attempts`), formatiert als `m:ss` / `h:mm:ss`. Anzeige `—` wenn keine Abgaben oder alle Zeiten = 0.
- **Mit Tab-Wechsel** — Prozentsatz der Abgaben, bei denen mindestens einmal der Tab gewechselt wurde (`attempts_with_tab_switch / attempts * 100`). Anzeige `—` wenn keine Abgaben, `0` wenn niemand gewechselt hat.

Die Summary-Cards passen sich in einem responsiven Grid an und stellen sich auf Mobilgeräten zweispaltig dar.

---

### FR-15a: Summary-Sheet im Excel-Export (GEÄNDERT)

Das Sheet "Zusammenfassung" (Summary) enthält **zwei zusätzliche Zeilen**:

| Feld                        | Wert                            |
|-----------------------------|---------------------------------|
| Ø Bearbeitungszeit          | `m:ss` oder `h:mm:ss`           |
| Anteil mit Tab-Wechsel      | `XX.X%`                         |

Identisch im CSV-Export.

---

### FR-16a: CSV-Export mit Mehrabschnitts-Layout (GEÄNDERT)

Der CSV-Export war in der v1.0-Implementierung auf den Answer-Options-Block reduziert. Er wird nun auf das spezifikationskonforme Drei-Sektionen-Format gebracht, um Feature-Parität zur XLSX zu erreichen:

- **Trennzeichen:** Semikolon (`;`)
- **Kodierung:** UTF-8 mit BOM
- **Sektionsüberschriften:** `# Zusammenfassung` / `# Fragen` / `# Antwortoptionen`, jeweils gefolgt von einer Leerzeile zwischen den Sektionen
- **Zeilenenden:** CRLF
- **Quoting:** Felder mit `"`, `;`, `\n` oder `\r` werden RFC-4180-konform gequotet

---

## 6. Privacy Requirements — Klarstellung

Die in FR-14a eingeführten Felder sind **reine Aggregate** und erfüllen PRIV-01 und PRIV-02 weiterhin vollständig:

- Es werden **keine individuellen Bearbeitungszeiten oder Tab-Wechsel-Zählungen** gespeichert — nur deren Summen über alle Abgaben.
- Aus den Aggregaten lässt sich **keine einzelne Abgabe rekonstruieren** (weder zuordenbar zu einer Person noch zu einer Sitzung).
- Es werden **keine Zeitstempel** einzelner Abgaben persistiert.
- Es werden **keine IP-Adressen, User-Agents oder Sessions** erfasst.

Die aggregierten Werte sind damit datenschutzrechtlich auf gleicher Stufe wie die bereits vorhandenen Counters (Zahl der Abgaben, Antworthäufigkeiten pro Option).

---

## 8. i18n — neue Keys

| Key                         | DE                                | EN                                                  |
|-----------------------------|-----------------------------------|-----------------------------------------------------|
| `quiz.wait_seconds`         | `Bitte warten Sie %d Sekunden …`  | `Please wait %d seconds …`                          |
| `feedback.elapsed`          | `Bearbeitungszeit`                | `Time taken`                                        |
| `feedback.tab_switch`       | `%d Tab-Wechsel\|%d Tab-Wechsel`  | `%d tab switch\|%d tab switches`                    |
| `stats.avg_time`            | `Ø Zeit`                          | `Avg. time`                                         |
| `stats.tab_switches_rate`   | `Mit Tab-Wechsel`                 | `With tab switch`                                   |
| `stats.tab_switches_hint`   | `Anteil der Abgaben, bei denen …` | `Share of submissions where the tab was switched …` |
| `export.row.avg_time`       | `Ø Bearbeitungszeit`              | `Avg. time taken`                                   |
| `export.row.tab_rate`       | `Anteil mit Tab-Wechsel`          | `Share with tab switch`                             |
