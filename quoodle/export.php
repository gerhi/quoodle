<?php
require __DIR__ . '/lib/storage.php';

$id     = $_GET['id']     ?? '';
$token  = $_GET['t']      ?? '';
$format = $_GET['format'] ?? 'xlsx';
$quiz   = load_quiz($id);

if (!$quiz || !verify_teacher_token($quiz, $token)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$stats    = $quiz['stats'] ?? ['attempts' => 0, 'questions' => []];
$attempts = (int)($stats['attempts'] ?? 0);
$qStats   = $stats['questions'] ?? [];
$qCount   = count($quiz['questions']);

$totalAnswered = 0; $totalCorrect = 0;
foreach ($qStats as $q) {
    $totalAnswered += (int)($q['total_count']   ?? 0);
    $totalCorrect  += (int)($q['correct_count'] ?? 0);
}
$avgPct = $totalAnswered > 0 ? round(100 * $totalCorrect / $totalAnswered, 1) : 0;

$safeTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($quiz['title'], 0, 40));
$datePart  = date('Y-m-d');

// Build data arrays (shared by CSV and XLSX)

// Summary data
$summaryRows = [
    [t('quiz_title_label'), $quiz['title']],
    [tp('attempts', $attempts), $attempts],
    [tp('questions_count', $qCount), $qCount],
    [t('avg_correct'), $avgPct . '%'],
];

// Questions overview
$questionRows = [
    ['Nr.', t('col_question'), t('col_correct'), tp('attempts', 2), t('correct'), '%'],
];
foreach ($quiz['questions'] as $qi => $q) {
    $qs  = $qStats[$qi] ?? ['correct_count'=>0,'total_count'=>0];
    $tot = (int)$qs['total_count'];
    $cor = (int)$qs['correct_count'];
    $pct = $tot > 0 ? round(100*$cor/$tot, 1) : 0;
    $questionRows[] = [$qi+1, $q['question'], $q['correct'], $tot, $cor, $pct];
}

// Answer choices
$choiceRows = [
    [t('exp_q_nr'), t('col_question'), t('exp_answer'), t('exp_is_correct'), t('exp_count'), t('exp_pct')],
];
foreach ($quiz['questions'] as $qi => $q) {
    $qs     = $qStats[$qi] ?? ['total_count'=>0,'choice_counts'=>[]];
    $tot    = (int)$qs['total_count'];
    $counts = (array)($qs['choice_counts'] ?? []);
    $choices = array_merge([$q['correct']], $q['distractors']); sort($choices);
    foreach ($choices as $choice) {
        $cnt   = (int)($counts[$choice] ?? 0);
        $share = $tot > 0 ? round(100*$cnt/$tot, 1) : 0;
        $choiceRows[] = [
            $qi+1, $q['question'], $choice,
            ($choice === $q['correct']) ? t('yes') : t('no'),
            $cnt, $share
        ];
    }
}

// ------------------------------------------------------------------
// CSV export
// ------------------------------------------------------------------
if ($format === 'csv') {
    $filename = 'Quoodle_' . $safeTitle . '_' . $datePart . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    $sep = ';';

    fputcsv($out, [t('exp_summary')], $sep);
    foreach ($summaryRows as $row) fputcsv($out, $row, $sep);
    fputcsv($out, [''], $sep);

    fputcsv($out, [t('exp_questions_ov')], $sep);
    foreach ($questionRows as $row) fputcsv($out, $row, $sep);
    fputcsv($out, [''], $sep);

    fputcsv($out, [t('exp_choices')], $sep);
    foreach ($choiceRows as $row) fputcsv($out, $row, $sep);

    fclose($out);
    exit;
}

// ------------------------------------------------------------------
// XLSX export
// ------------------------------------------------------------------
require __DIR__ . '/lib/xlsx_writer.php';

$writer = new XlsxWriter();
$writer->addSheet(t('exp_summary'), $summaryRows);
$writer->addSheet(t('exp_questions_ov'), $questionRows);
$writer->addSheet(t('exp_choices'), $choiceRows);

$filename = 'Quoodle_' . $safeTitle . '_' . $datePart . '.xlsx';
$writer->output($filename);
