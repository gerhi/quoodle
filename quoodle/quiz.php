<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/layout.php';

$lang = i18n_init();
$base = get_base_url();

// ── Validate quiz ID ─────────────────────────────────────────────────────────
$id = $_GET['id'] ?? '';
if (!validate_id($id)) {
    not_found();
}

$quiz = db_get_quiz($id);
if (!$quiz) {
    not_found();
}

$questions = $quiz['questions'];
$n         = count($questions);

// ── Shuffle answer choices server-side ──────────────────────────────────────
$shuffled = [];
foreach ($questions as $q) {
    $choices = array_merge([$q['correct']], $q['distractors']);
    shuffle($choices);
    $shuffled[] = $choices;
}

// ── Build JS data: correct answers + explanations per question ──────────────
$js_correct = [];
$js_expl    = [];
foreach ($questions as $qi => $q) {
    $js_correct[$qi] = $q['correct'];
    $js_expl[$qi]    = $q['explanation'] ?? '';
}
// JSON_HEX_* escapes characters that could break out of <script>
$json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

render_header(e($quiz['title']), $lang);
?>

  <h1 class="page-title"><?= e($quiz['title']) ?></h1>

  <!-- Progress (visible once JS activates stepper) -->
  <div class="quiz-progress" id="progressText"
       data-template="<?= e(sprintf(t('quiz.question_of'), '{0}', '{1}')) ?>">
    <?= e(sprintf(t('quiz.question_of'), 1, $n)) ?>
  </div>
  <div class="progress-bar" id="progressBar">
    <div class="progress-fill" id="progressFill" style="width:<?= round(1/$n*100) ?>%"></div>
  </div>

  <div class="card">
    <form id="quiz-form" method="post" action="<?= e($base) ?>/submit.php" novalidate>
      <input type="hidden" name="quiz_id"       value="<?= e($id) ?>">
      <input type="hidden" name="lang"          value="<?= e($lang) ?>">
      <input type="hidden" name="elapsed_time"  id="elapsedTime"  value="0">
      <input type="hidden" name="tab_switches"  id="tabSwitches"  value="0">

      <?php foreach ($questions as $qi => $q): ?>
      <div class="quiz-step<?= $qi === 0 ? ' active' : '' ?>" data-step="<?= $qi ?>">
        <p style="font-size:.8125rem;color:var(--text-muted);margin-bottom:6px">
          <?= e(sprintf(t('quiz.question_of'), $qi + 1, $n)) ?>
        </p>
        <p style="font-size:1.0625rem;font-weight:600;margin-bottom:16px"><?= e($q['text']) ?></p>

        <ul class="choices" role="radiogroup">
          <?php foreach ($shuffled[$qi] as $choice): ?>
          <li>
            <label class="choice-label">
              <input class="choice-radio" type="radio"
                     name="answers[<?= $qi ?>]"
                     value="<?= e($choice) ?>">
              <span><?= e($choice) ?></span>
              <span class="choice-mark" aria-hidden="true"></span>
            </label>
          </li>
          <?php endforeach; ?>
        </ul>

        <!-- Inline explanation (revealed on answer) -->
        <div class="explanation-block quiz-inline-expl" style="display:none">
          <div class="explanation-label"><?= e(t('feedback.explanation')) ?></div>
          <div class="quiz-inline-expl-text"></div>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Validation message (shown if user tries to advance without answering) -->
      <p class="validation-msg" id="validationMsg" role="alert">
        <?= e(t('quiz.err.no_answer')) ?>
      </p>

      <!-- Stepper navigation -->
      <div class="step-nav" id="stepNav">
        <button type="button" class="btn btn-ghost" id="stepPrev" style="display:none">
          ← <?= e(t('quiz.prev')) ?>
        </button>
        <button type="button" class="btn btn-primary" id="stepNext" disabled>
          <?= e(t('quiz.next')) ?> →
        </button>
        <button type="submit" class="btn btn-primary" id="stepSubmit" style="display:none" disabled>
          <?= e(t('quiz.submit')) ?>
        </button>
      </div>

      <!-- No-JS fallback -->
      <noscript>
        <style>
          .quiz-step { display: block !important; }
          #stepNav, .quiz-inline-expl, .choice-mark, #progressBar, #progressText { display: none !important; }
        </style>
        <div style="margin-top:24px">
          <button type="submit" class="btn btn-primary"><?= e(t('quiz.submit')) ?></button>
        </div>
      </noscript>

    </form>
  </div>

<script>
// Expose quiz data for app.js to consume
window.__QUOODLE = {
  correct:      <?= json_encode($js_correct, $json_flags) ?>,
  explanations: <?= json_encode($js_expl,    $json_flags) ?>,
  labels: {
    wait:      <?= json_encode(t('quiz.wait_seconds'),   $json_flags) ?>,
    next:      <?= json_encode(t('quiz.next'),           $json_flags) ?>,
    submit:    <?= json_encode(t('quiz.submit'),         $json_flags) ?>
  }
};
</script>

<?php
render_footer();
