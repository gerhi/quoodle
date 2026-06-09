<?php
/**
 * Wandelt Tabellenzeilen (2D-Array von Strings) in eine validierte Quiz-Struktur um.
 *
 * Erwartetes Layout (Zeile 1 = Kopfzeile, ab Zeile 2 = Daten):
 *   Spalte A: Frage
 *   Spalte B: Richtige Antwort
 *   Spalte C: Distraktor 1
 *   Spalte D: Distraktor 2
 *   Spalte E: Distraktor 3   (optional)
 *   Spalte F: Distraktor 4   (optional)
 *   Spalte G: Erklärung
 *
 * Die LETZTE nicht-leere Spalte wird immer als Erklärung interpretiert.
 */

function build_quiz_from_rows(array $rows, string $title): array
{
    if (count($rows) < 2) {
        throw new RuntimeException('Die Datei muss eine Kopfzeile und mindestens eine Frage enthalten.');
    }

    $header = $rows[0];
    $numCols = count($header);
    if ($numCols < 4) {
        throw new RuntimeException('Die Datei benötigt mindestens 4 Spalten: Frage, Richtige Antwort, mindestens ein Distraktor, Erklärung.');
    }

    $questions = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        while (count($row) < $numCols) {
            $row[] = '';
        }
        $isEmpty = true;
        foreach ($row as $cell) {
            if ($cell !== '') { $isEmpty = false; break; }
        }
        if ($isEmpty) continue;

        $question     = $row[0];
        $correct      = $row[1];
        $explanation  = $row[$numCols - 1];
        $distractors  = [];
        for ($c = 2; $c < $numCols - 1; $c++) {
            if ($row[$c] !== '') {
                $distractors[] = $row[$c];
            }
        }

        if ($question === '' || $correct === '') {
            throw new RuntimeException("Zeile " . ($i + 1) . ": Frage und richtige Antwort sind erforderlich.");
        }
        if (count($distractors) < 1) {
            throw new RuntimeException("Zeile " . ($i + 1) . ": Mindestens ein Distraktor ist erforderlich.");
        }

        $questions[] = [
            'question'    => $question,
            'correct'     => $correct,
            'distractors' => $distractors,
            'explanation' => $explanation,
        ];
    }

    if (empty($questions)) {
        throw new RuntimeException('In der Datei wurden keine Fragen gefunden.');
    }

    return [
        'title'     => trim($title) !== '' ? trim($title) : 'Quiz ohne Titel',
        'questions' => $questions,
    ];
}

/**
 * Liest eine CSV-Datei in dieselbe 2D-Array-Form wie xlsx_read_first_sheet().
 */
function csv_read_rows(string $path): array
{
    $rows = [];
    if (($h = fopen($path, 'r')) === false) {
        throw new RuntimeException('CSV-Datei konnte nicht geöffnet werden.');
    }
    // Trennzeichen erkennen: Komma vs. Semikolon (Standard im deutschen Excel)
    $firstLine = fgets($h);
    if ($firstLine === false) {
        fclose($h);
        return [];
    }
    $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    rewind($h);
    while (($cells = fgetcsv($h, 0, $delim)) !== false) {
        $rows[] = array_map(static fn($v) => is_string($v) ? trim($v) : (string)$v, $cells);
    }
    fclose($h);
    return $rows;
}
