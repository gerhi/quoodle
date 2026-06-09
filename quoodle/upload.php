<?php
require __DIR__ . '/lib/xlsx_reader.php';
require __DIR__ . '/lib/quiz_parser.php';
require __DIR__ . '/lib/storage.php';

$page_title = 'Upload';
$error = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException(t('err_method'));
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException(t('err_no_file'));
    if ($_FILES['file']['size'] > 2 * 1024 * 1024) throw new RuntimeException(t('err_too_large'));

    $title = $_POST['title'] ?? '';
    $tmp   = $_FILES['file']['tmp_name'];
    $ext   = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

    if ($ext === 'xlsx') $rows = xlsx_read_first_sheet($tmp);
    elseif ($ext === 'csv') $rows = csv_read_rows($tmp);
    else throw new RuntimeException(t('err_format'));

    $quiz = build_quiz_from_rows($rows, $title);
    $saved = save_quiz($quiz);

    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header('Location: ' . $base . '/share.php?id=' . urlencode($saved['id']) . '&t=' . urlencode($saved['teacher_token']));
    exit;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

require __DIR__ . '/lib/header.php';
?>
<div class="card">
  <div class="alert error"><strong><?= t('upload_failed') ?></strong> <?= htmlspecialchars($error) ?></div>
  <div class="button-row">
    <a class="btn" href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')) ?>/index.php"><?= t('back') ?></a>
  </div>
</div>
<?php require __DIR__ . '/lib/footer.php'; ?>
