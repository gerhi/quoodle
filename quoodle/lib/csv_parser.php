<?php
declare(strict_types=1);

/**
 * Parse a CSV file into an array of question objects.
 *
 * Format (first row = header, ignored):
 *   A: Question text
 *   B: Correct answer
 *   C … last-1: Distractors (1–5)
 *   Last column: Explanation (optional)
 *
 * @param string $file_path Absolute path to the uploaded CSV file
 * @return array{questions: array, errors: array}
 */
function parse_csv(string $file_path): array {
    $content = file_get_contents($file_path);
    if ($content === false) {
        return ['questions' => [], 'errors' => ['Datei konnte nicht gelesen werden.']];
    }

    // Strip UTF-8 BOM if present
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }
    // Normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    // Auto-detect delimiter by checking the header line
    $first_line = strtok($content, "\n") ?: '';
    $delimiters = [';', ',', "\t", '|'];
    $delimiter  = ',';
    $max_count  = 0;
    foreach ($delimiters as $d) {
        $cnt = substr_count($first_line, $d);
        if ($cnt > $max_count) { $max_count = $cnt; $delimiter = $d; }
    }

    // Parse CSV
    $rows = [];
    foreach (str_getcsv($content, "\n") as $line) {
        if (trim($line) === '') continue;
        $rows[] = str_getcsv($line, $delimiter);
    }

    return process_rows($rows);
}

/**
 * Shared row processor for both CSV and XLSX parsers.
 *
 * @param array $rows 2D array (includes header row)
 * @return array{questions: array, errors: array}
 */
function process_rows(array $rows): array {
    $questions = [];
    $errors    = [];

    // Skip header row
    $data_rows = array_slice($rows, 1);

    foreach ($data_rows as $line_num => $row) {
        $row_num = $line_num + 2; // 1-indexed, +1 for header

        // Trim all cells and filter null values
        $row = array_map(fn($v) => trim((string)($v ?? '')), $row);

        // Remove trailing empty cells
        while (!empty($row) && $row[array_key_last($row)] === '') {
            array_pop($row);
        }

        // Minimum columns: A (question), B (correct), at least 1 distractor
        if (count($row) < 3) {
            if (count($row) > 0 && $row[0] !== '') {
                $errors[] = "Zeile {$row_num}: Mindestens 3 Spalten erforderlich (Frage, Antwort, 1 Distraktor).";
            }
            continue;
        }

        $question    = $row[0];
        $correct     = $row[1];
        $explanation = count($row) >= 4 ? $row[count($row) - 1] : ''; // last col
        $distractor_end = count($row) >= 4 ? count($row) - 2 : count($row) - 1;
        $distractors = array_slice($row, 2, $distractor_end - 1);

        if ($question === '') {
            $errors[] = "Zeile {$row_num}: Frage darf nicht leer sein.";
            continue;
        }
        if ($correct === '') {
            $errors[] = "Zeile {$row_num}: Richtige Antwort darf nicht leer sein.";
            continue;
        }
        $distractors = array_values(array_filter($distractors, fn($d) => $d !== ''));
        if (count($distractors) < 1) {
            $errors[] = "Zeile {$row_num}: Mindestens 1 Distraktor erforderlich.";
            continue;
        }
        if (count($distractors) > 5) {
            $distractors = array_slice($distractors, 0, 5); // cap at 5
        }
        // Check for duplicate answer texts
        $all_choices = array_merge([$correct], $distractors);
        if (count($all_choices) !== count(array_unique($all_choices))) {
            $errors[] = "Zeile {$row_num}: Antwortoptionen müssen eindeutig sein.";
            continue;
        }

        $questions[] = [
            'text'        => $question,
            'correct'     => $correct,
            'distractors' => $distractors,
            'explanation' => $explanation,
        ];
    }

    if (count($questions) === 0 && count($errors) === 0) {
        $errors[] = 'Die Datei enthält keine auswertbaren Zeilen.';
    }

    return ['questions' => $questions, 'errors' => $errors];
}
