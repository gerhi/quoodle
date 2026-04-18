<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/layout.php';

$lang = i18n_init();

// Only handle POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$errors = [];

// ── 1. Validate title ────────────────────────────────────────────────────────
$title = trim($_POST['title'] ?? '');
if ($title === '') {
    $errors[] = t('upload.err.no_title');
} elseif (mb_strlen($title) > 200) {
    $title = mb_substr($title, 0, 200);
}

// ── 2. Validate file upload ──────────────────────────────────────────────────
$file = $_FILES['quiz_file'] ?? null;

if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
    $errors[] = t('upload.err.no_file');
} elseif ($file['error'] !== UPLOAD_ERR_OK) {
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errors[] = t('upload.err.too_large');
    } else {
        $errors[] = t('upload.err.no_file');
    }
} else {
    $original_name = $file['name'] ?? '';
    $ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        $errors[] = t('upload.err.bad_type');
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = t('upload.err.too_large');
    }
}

if (!empty($errors)) {
    show_error_page($errors, $lang, $title);
    exit;
}

// ── 3. Parse the file ────────────────────────────────────────────────────────
$parse_result = ['questions' => [], 'errors' => []];

if ($ext === 'xlsx') {
    require_once __DIR__ . '/lib/xlsx_parser.php';
    $parse_result = parse_xlsx($file['tmp_name']);
} else {
    require_once __DIR__ . '/lib/csv_parser.php';
    $parse_result = parse_csv($file['tmp_name']);
}

if (!empty($parse_result['errors'])) {
    show_error_page($parse_result['errors'], $lang, $title, t('upload.err.parse_failed'));
    exit;
}

$questions = $parse_result['questions'];

if (count($questions) === 0) {
    show_error_page([t('upload.err.parse_failed')], $lang, $title);
    exit;
}

// Cap at 50 questions
if (count($questions) > 50) {
    $questions = array_slice($questions, 0, 50);
}

// ── 4. Create quiz in DB ─────────────────────────────────────────────────────
$quiz_id = generate_id();
$token   = generate_token();

try {
    db_create_quiz([
        'id'            => $quiz_id,
        'teacher_token' => $token,
        'title'         => $title,
        'created_at'    => date('Y-m-d H:i:s'),
        'questions'     => $questions,
        'stats'         => db_empty_stats($questions),
    ]);
} catch (Throwable $e) {
    show_error_page(['Interner Fehler beim Speichern des Quiz. Bitte erneut versuchen.'], $lang, $title);
    exit;
}

// ── 5. Redirect to share page ────────────────────────────────────────────────
redirect('share.php?id=' . urlencode($quiz_id) . '&t=' . urlencode($token) . '&lang=' . urlencode($lang));

// ── Helper: show error page ──────────────────────────────────────────────────
function show_error_page(array $errors, string $lang, string $title = '', string $lead = ''): void {
    render_header(t('home.title'), $lang);
    $base = get_base_url();
    ?>
    <main>
      <div class="page-title">
        <h1><?= e(t('home.title')) ?></h1>
      </div>
      <div class="alert alert-error">
        <?php if ($lead): ?><strong><?= e($lead) ?></strong><br><?php endif; ?>
        <?php if (count($errors) === 1): ?>
          <?= e($errors[0]) ?>
        <?php else: ?>
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <a href="<?= e($base) ?>/index.php?lang=<?= e($lang) ?>" class="btn btn-secondary">← Zurück</a>
    </main>
    <?php
    render_footer();
}
