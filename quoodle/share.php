<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/qr.php';
require_once __DIR__ . '/layout.php';

$lang = i18n_init();
$base = get_base_url();

// ── Validate params ──────────────────────────────────────────────────────────
$id    = $_GET['id'] ?? '';
$token = $_GET['t']  ?? '';

if (!validate_id($id) || !validate_token($token)) {
    not_found();
}

$quiz = db_get_quiz($id);
if (!$quiz || !safe_token_compare($quiz['teacher_token'], $token)) {
    not_found();
}

// ── Build URLs ───────────────────────────────────────────────────────────────
$student_url = $base . '/quiz.php?id=' . urlencode($id);
$teacher_url = $base . '/stats.php?id=' . urlencode($id) . '&t=' . urlencode($token);

// ── Generate QR codes ────────────────────────────────────────────────────────
try {
    $student_qr = QrCode::generate($student_url, 220);
    $teacher_qr = QrCode::generate($teacher_url, 220);
} catch (Throwable $e) {
    $student_qr = '';
    $teacher_qr = '';
}

$n = count($quiz['questions']);
$n_label = $n . ' ' . ($n === 1 ? t('stats.question') : t('stats.question') . 'en');

render_header(t('share.headline') . ': ' . $quiz['title'], $lang);
?>

  <div class="page-title">
    <h1><?= e(t('share.headline')) ?></h1>
    <p class="page-subtitle"><?= e($quiz['title']) ?> &middot; <?= e($n_label) ?></p>
  </div>

  <!-- Student link -->
  <div class="card">
    <h2 class="card-title"><?= e(t('share.student.title')) ?></h2>
    <p class="card-subtitle"><?= e(t('share.student.desc')) ?></p>

    <div class="qr-wrap">
      <?= $student_qr ?>
    </div>

    <div class="url-row">
      <span class="url-display" id="student-url"><?= e($student_url) ?></span>
      <button class="btn btn-secondary btn-sm"
              data-copy="#student-url"
              data-copied-label="<?= e(t('share.copied')) ?>"
      ><?= e(t('share.copy')) ?></button>
    </div>

    <a href="<?= e($student_url . '&lang=' . urlencode($lang)) ?>"
       class="btn btn-secondary mt-8" target="_blank" rel="noopener"
    ><?= e(t('share.student.preview')) ?> ↗</a>
  </div>

  <!-- Teacher link -->
  <div class="card">
    <h2 class="card-title"><?= e(t('share.teacher.title')) ?></h2>
    <p class="card-subtitle"><?= e(t('share.teacher.desc')) ?></p>

    <div class="qr-wrap">
      <?= $teacher_qr ?>
    </div>

    <div class="url-row">
      <span class="url-display" id="teacher-url"><?= e($teacher_url) ?></span>
      <button class="btn btn-secondary btn-sm"
              data-copy="#teacher-url"
              data-copied-label="<?= e(t('share.copied')) ?>"
      ><?= e(t('share.copy')) ?></button>
    </div>

    <div class="warning-box mt-8">
      ⚠ <?= e(t('share.teacher.warning')) ?>
    </div>

    <a href="<?= e($teacher_url . '&lang=' . urlencode($lang)) ?>"
       class="btn btn-primary mt-8"
    ><?= e(t('share.teacher.open')) ?> →</a>
  </div>


<?php
render_footer();
