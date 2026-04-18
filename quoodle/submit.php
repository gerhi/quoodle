<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/layout.php';

$lang = i18n_init();
$base = get_base_url();

// Only handle POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

// ── Validate quiz ID ─────────────────────────────────────────────────────────
$quiz_id = $_POST['quiz_id'] ?? '';
if (!validate_id($quiz_id)) {
    not_found();
}

$quiz = db_get_quiz($quiz_id);
if (!$quiz) {
    not_found();
}

$questions = $quiz['questions'];
$n         = count($questions);
$answers   = $_POST['answers'] ?? [];   // index → answer text

// ── Parse meta fields (bounded to sensible ranges) ───────────────────────────
$elapsed_sec  = isset($_POST['elapsed_time']) ? (int)$_POST['elapsed_time'] : 0;
$tab_switches = isset($_POST['tab_switches']) ? (int)$_POST['tab_switches'] : 0;
if ($elapsed_sec  < 0 || $elapsed_sec  > 86400) $elapsed_sec  = 0;   // cap @ 24 h
if ($tab_switches < 0 || $tab_switches > 10000) $tab_switches = 0;

// ── Grade answers ────────────────────────────────────────────────────────────
$results       = [];
$correct_count = 0;

foreach ($questions as $qi => $q) {
    $submitted  = isset($answers[$qi]) ? trim((string)$answers[$qi]) : null;
    $is_correct = ($submitted !== null && $submitted === $q['correct']);
    if ($is_correct) $correct_count++;
    $results[$qi] = [
        'submitted'  => $submitted,
        'is_correct' => $is_correct,
    ];
}

// ── Record stats (best-effort) ───────────────────────────────────────────────
$submission_answers = [];
foreach ($results as $qi => $r) {
    $submission_answers[$qi] = $r['submitted'] ?? '';
}
@db_record_submission($quiz_id, $submission_answers, $elapsed_sec, $tab_switches);

// ── Render feedback page ─────────────────────────────────────────────────────
$pct         = $n > 0 ? round($correct_count / $n * 100) : 0;
$quiz_url    = $base . '/quiz.php?id=' . urlencode($quiz_id) . '&lang=' . urlencode($lang);

render_header(t('feedback.headline'), $lang);
?>

  <div class="score-banner">
    <div class="score-main"><?= $correct_count ?> / <?= $n ?></div>
    <div class="score-pct"><?= sprintf(e(t('feedback.correct_pct')), $pct) ?></div>

    <?php if ($elapsed_sec > 0 || $tab_switches > 0): ?>
    <div class="score-meta">
      <?php if ($elapsed_sec > 0): ?>
        <span class="score-meta-item">
          ⏱ <?= e(t('feedback.elapsed')) ?>:
          <strong><?= e(format_duration($elapsed_sec)) ?></strong>
        </span>
      <?php endif; ?>
      <?php if ($tab_switches > 0): ?>
        <span class="score-meta-item score-meta-warn">
          ⚠ <?= e(sprintf(tp('feedback.tab_switch', $tab_switches), $tab_switches)) ?>
        </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <?php foreach ($questions as $qi => $q): ?>
    <?php $res = $results[$qi]; $all = array_merge([$q['correct']], $q['distractors']); ?>
    <div class="feedback-question">
      <div class="feedback-qhead">
        <span class="text-muted text-sm"><?= e(t('stats.question')) ?> <?= $qi + 1 ?></span>
        <?php if ($res['is_correct']): ?>
          <span class="badge badge-correct">✓ <?= e(t('feedback.correct_lbl')) ?></span>
        <?php else: ?>
          <span class="badge badge-incorrect">✗ <?= e(t('feedback.incorrect_lbl')) ?></span>
        <?php endif; ?>
      </div>
      <p style="margin:6px 0 10px;font-weight:600"><?= e($q['text']) ?></p>

      <ul class="feedback-choices">
        <?php foreach ($all as $choice): ?>
        <?php
          $is_correct_c = ($choice === $q['correct']);
          $was_chosen   = ($choice === $res['submitted']);
          $cls = $is_correct_c ? 'feedback-choice fc-correct' : ($was_chosen ? 'feedback-choice fc-wrong' : 'feedback-choice');
        ?>
        <li class="<?= $cls ?>">
          <span class="fc-marker">
            <?php if ($is_correct_c): ?>✓
            <?php elseif ($was_chosen): ?>✗
            <?php else: ?>&nbsp;<?php endif; ?>
          </span>
          <span class="fc-text">
            <?= e($choice) ?>
            <?php if ($was_chosen && !$is_correct_c): ?>
              <small class="text-muted"> — <?= e(t('feedback.your_answer')) ?></small>
            <?php endif; ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>

      <?php if (!empty($q['explanation'])): ?>
      <div class="explanation-block">
        <div class="explanation-label"><?= e(t('feedback.explanation')) ?></div>
        <?= e($q['explanation']) ?>
      </div>
      <?php endif; ?>

      <?php if ($qi < $n - 1): ?><hr class="divider"><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <p class="mt-16">
    <a href="<?= e($quiz_url) ?>" class="btn btn-secondary">
      ← <?= e(t('feedback.try_again')) ?>
    </a>
  </p>

<?php
render_footer();
