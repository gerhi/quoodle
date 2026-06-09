<?php
require_once __DIR__ . '/lib/lang.php';
require_once __DIR__ . '/lib/storage.php';

// Clean up expired quizzes (rate-limited, at most once per hour)
sweep_expired();

$page_title = t('create_title');
require __DIR__ . '/lib/header.php';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
?>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert success" style="margin-bottom:20px;"><?= t('quiz_deleted') ?></div>
<?php endif; ?>

<div class="card">
  <h2><?= t('create_title') ?></h2>
  <p class="subtitle"><?= t('create_subtitle') ?></p>

  <form action="<?= htmlspecialchars($base) ?>/upload.php" method="post" enctype="multipart/form-data">
    <label for="title"><?= t('quiz_title_label') ?></label>
    <input type="text" id="title" name="title" required maxlength="200" placeholder="<?= htmlspecialchars(t('quiz_title_ph')) ?>">

    <label for="file"><?= t('quiz_file_label') ?></label>
    <input type="file" id="file" name="file" accept=".xlsx,.csv" required>
    <p class="hint"><?= t('file_hint') ?></p>

    <div class="button-row">
      <button type="submit"><?= t('create_btn') ?></button>
    </div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;"><?= t('format_title') ?></h3>
  <p><?= t('format_intro') ?></p>

  <table class="format-table">
    <thead>
      <tr>
        <th>A — <?= t('col_question') ?></th>
        <th>B — <?= t('col_correct') ?></th>
        <th>C — <?= t('col_distractor') ?> 1</th>
        <th>D — <?= t('col_distractor') ?> 2</th>
        <th>E — <?= t('col_distractor') ?> 3</th>
        <th>F — <?= t('col_explanation') ?></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Welches Gas nehmen Pflanzen bei der Photosynthese auf?</td>
        <td>Kohlendioxid</td><td>Sauerstoff</td><td>Stickstoff</td><td>Wasserstoff</td>
        <td>Pflanzen nehmen CO₂ über die Spaltöffnungen auf…</td>
      </tr>
    </tbody>
  </table>

  <p class="hint"><?= t('format_hint') ?></p>

  <div class="button-row">
    <a class="btn secondary" href="<?= htmlspecialchars($base) ?>/templates/quiz_vorlage.xlsx"><?= t('dl_excel') ?></a>
    <a class="btn secondary" href="<?= htmlspecialchars($base) ?>/templates/quiz_vorlage.csv"><?= t('dl_csv') ?></a>
  </div>
</div>

<?php require __DIR__ . '/lib/footer.php'; ?>
