<?php
require __DIR__ . '/lib/storage.php';
require __DIR__ . '/lib/qrcode.php';

$id    = $_GET['id'] ?? '';
$token = $_GET['t']  ?? '';
$quiz  = load_quiz($id);

if (!$quiz || !verify_teacher_token($quiz, $token)) {
    http_response_code(404);
    $page_title = t('not_found');
    require __DIR__ . '/lib/header.php';
    echo '<div class="card"><div class="alert error">' . htmlspecialchars(t('share_invalid')) . '</div></div>';
    require __DIR__ . '/lib/footer.php';
    exit;
}

$page_title = $quiz['title'] . ' — Links';
$studentUrl = quiz_url($id);
$teacherUrl = teacher_stats_url($id, $token);
$qrStudentSvg = QRCode::svg($studentUrl, 240);
$qrTeacherSvg = QRCode::svg($teacherUrl, 180);
$qCount = count($quiz['questions']);

require __DIR__ . '/lib/header.php';
?>

<div class="card">
  <div class="alert success">
    <strong><?= t('quiz_created') ?></strong> <?= t('share_bookmark') ?>
  </div>

  <h2><?= htmlspecialchars($quiz['title']) ?></h2>
  <p class="subtitle"><?= $qCount ?> <?= tp('questions_count', $qCount) ?></p>

  <?php
  $expiresAt = $quiz['expires_at'] ?? null;
  if ($expiresAt):
      $expDate = date(current_lang() === 'de' ? 'd.m.Y' : 'M j, Y', strtotime($expiresAt));
  ?>
    <p class="hint" style="margin-bottom:18px;">⏱ <?= sprintf(t('share_expires_note'), $expDate) ?></p>
  <?php endif; ?>

  <div class="share-block">
    <div class="label-row">
      <h3><?= t('for_students') ?></h3>
      <span class="pill student"><?= t('public') ?></span>
    </div>
    <p class="hint" style="margin:0 0 6px;"><?= t('share_student_hint') ?></p>
    <div class="share-link">
      <input type="text" id="link-student" value="<?= htmlspecialchars($studentUrl) ?>" readonly onclick="this.select();">
      <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('link-student').value);this.textContent=<?= json_encode(t('copied')) ?>;setTimeout(()=>this.textContent=<?= json_encode(t('copy')) ?>,1500);"><?= t('copy') ?></button>
    </div>
    <div class="qr-wrap">
      <?= $qrStudentSvg ?>
      <div class="hint"><?= t('qr_scan_hint') ?></div>
    </div>
    <div class="button-row">
      <a class="btn" href="<?= htmlspecialchars($studentUrl) ?>" target="_blank"><?= t('preview_quiz') ?></a>
    </div>
  </div>

  <div class="share-block">
    <div class="label-row">
      <h3><?= t('for_teacher') ?></h3>
      <span class="pill teacher"><?= t('keep_secret') ?></span>
    </div>
    <p class="hint" style="margin:0 0 6px;"><?= t('teacher_hint') ?></p>
    <div class="share-link">
      <input type="text" id="link-teacher" value="<?= htmlspecialchars($teacherUrl) ?>" readonly onclick="this.select();">
      <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('link-teacher').value);this.textContent=<?= json_encode(t('copied')) ?>;setTimeout(()=>this.textContent=<?= json_encode(t('copy')) ?>,1500);"><?= t('copy') ?></button>
    </div>
    <div class="qr-wrap" style="background:#fffbeb; border-color:#fde68a;">
      <?= $qrTeacherSvg ?>
      <div class="hint"><?= t('qr_teacher_hint') ?></div>
    </div>
    <div class="button-row">
      <a class="btn" href="<?= htmlspecialchars($teacherUrl) ?>" target="_blank"><?= t('open_results') ?></a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/lib/footer.php'; ?>
