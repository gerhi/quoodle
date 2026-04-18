<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/xlsx_writer.php';

$lang = i18n_init();

// ── Validate params + auth ───────────────────────────────────────────────────
$id     = $_GET['id']     ?? '';
$token  = $_GET['t']      ?? '';
$format = $_GET['format'] ?? 'xlsx';

if (!validate_id($id) || !validate_token($token)) {
    not_found();
}

$quiz = db_get_quiz($id);
if (!$quiz || !safe_token_compare($quiz['teacher_token'], $token)) {
    not_found();
}

if (!in_array($format, ['xlsx', 'csv'], true)) {
    $format = 'xlsx';
}

// ── Build label map ──────────────────────────────────────────────────────────
$labels = [
    'sheet.summary'     => t('export.sheet.summary'),
    'sheet.questions'   => t('export.sheet.questions'),
    'sheet.choices'     => t('export.sheet.choices'),
    'col.field'         => t('export.col.field'),
    'col.value'         => t('export.col.value'),
    'row.title'         => t('export.row.title'),
    'row.attempts'      => t('export.row.attempts'),
    'row.num_q'         => t('export.row.num_q'),
    'row.avg_pct'       => t('export.row.avg_pct'),
    'row.avg_time'      => t('export.row.avg_time'),
    'row.tab_rate'      => t('export.row.tab_rate'),
    'row.created'       => t('export.row.created'),
    'col.nr'            => t('export.col.nr'),
    'col.question'      => t('export.col.question'),
    'col.correct_ans'   => t('export.col.correct_ans'),
    'col.attempts'      => t('export.col.attempts'),
    'col.correct'       => t('export.col.correct'),
    'col.pct'           => t('export.col.pct'),
    'col.q_nr'          => t('export.col.q_nr'),
    'col.answer'        => t('export.col.answer'),
    'col.is_correct'    => t('export.col.is_correct'),
    'col.count'         => t('export.col.count'),
    'col.share_pct'     => t('export.col.share_pct'),
    'yes'               => t('export.yes'),
    'no'                => t('export.no'),
];

$slug = 'Quoodle_' . safe_filename($quiz['title']) . '_' . date('Y-m-d');

// ── Wrap the entire build in try/catch; show a readable error page on failure ──
try {
    if ($format === 'xlsx') {
        // Prereq check: without ZipArchive we cannot build XLSX files
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'PHP-Erweiterung "zip" (ZipArchive) ist auf diesem Server nicht installiert. ' .
                'Bitte den Hoster bitten, die Erweiterung php-zip zu aktivieren, ' .
                'oder alternativ den CSV-Export nutzen.'
            );
        }

        // Buffer output so we can abort cleanly on error without sending
        // partial binary garbage to the browser.
        ob_start();
        $bytes = build_stats_xlsx($quiz, $labels);
        $buffer_warn = ob_get_clean();

        if ($buffer_warn !== '') {
            // Something printed a warning before/during the build — dump it
            throw new RuntimeException(
                "Unerwartete Ausgabe während des XLSX-Builds:\n\n" . $buffer_warn
            );
        }

        if ($bytes === '' || $bytes === false) {
            throw new RuntimeException('XLSX-Build lieferte keine Daten.');
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $slug . '.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-cache, must-revalidate');
        echo $bytes;
        exit;
    }

    // CSV branch
    ob_start();
    $csv = build_stats_csv($quiz, $labels);
    $buffer_warn = ob_get_clean();
    if ($buffer_warn !== '') {
        throw new RuntimeException("Unerwartete Ausgabe während des CSV-Builds:\n\n" . $buffer_warn);
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $slug . '.csv"');
    header('Content-Length: ' . strlen($csv));
    header('Cache-Control: no-cache, must-revalidate');
    echo $csv;
    exit;

} catch (Throwable $e) {
    // Discard any buffered binary content — we're going to show HTML instead
    while (ob_get_level() > 0) ob_end_clean();

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    header_remove('Content-Disposition');
    header_remove('Content-Length');

    echo '<!doctype html><html><head>'
        . '<meta charset="UTF-8"><title>Export-Fehler – Quoodle</title>'
        . '<style>body{font-family:-apple-system,sans-serif;max-width:720px;margin:40px auto;padding:0 20px;color:#111}'
        . 'h1{color:#b91c1c}pre{background:#fee;border:1px solid #fcc;padding:14px;border-radius:6px;white-space:pre-wrap;word-break:break-word;font-size:.8125rem}'
        . '.hint{background:#fffbeb;border:1px solid #fde68a;padding:12px;border-radius:6px;color:#92400e;margin:16px 0}'
        . 'a{color:#4f46e5}</style></head><body>';
    echo '<h1>⚠ Export-Fehler</h1>';
    echo '<p>Der Export konnte nicht erstellt werden. Bitte diese Meldung an den Entwickler weiterleiten:</p>';
    echo '<pre>';
    echo 'Format: '  . htmlspecialchars($format, ENT_QUOTES, 'UTF-8') . "\n";
    echo 'PHP: '     . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . "\n";
    echo 'ZipArchive: ' . (class_exists('ZipArchive') ? 'ja' : 'NEIN') . "\n";
    echo 'Meldung: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n";
    echo 'Datei: '   . htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . "\n\n";
    echo 'Trace:' . "\n" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
    echo '</pre>';

    if ($format === 'xlsx') {
        $csv_url = '?id=' . urlencode($id) . '&t=' . urlencode($token) . '&format=csv&lang=' . urlencode($lang);
        echo '<div class="hint">Tipp: Der <a href="' . htmlspecialchars($csv_url, ENT_QUOTES, 'UTF-8') . '">CSV-Export</a> sollte weiter funktionieren und lässt sich in Excel öffnen.</div>';
    }
    echo '<p><a href="stats.php?id=' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '&t=' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '&lang=' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '">← Zurück zur Statistik</a></p>';
    echo '</body></html>';
    exit;
}
