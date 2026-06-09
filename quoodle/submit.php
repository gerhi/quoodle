<?php
require __DIR__ . '/lib/storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$id = $_POST['id'] ?? '';
$quiz = load_quiz($id);

if (!$quiz) {
    http_response_code(404);
    $page_title = t('not_found');
    require __DIR__ . '/lib/header.php';
    echo '<div class="card"><div class="alert error">' . htmlspecialchars(t('quiz_not_found')) . '</div></div>';
    require __DIR__ . '/lib/footer.php';
    exit;
}

$submitted = $_POST['answers'] ?? [];
$results = []; $attemptStats = []; $correctCount = 0;

foreach ($quiz['questions'] as $qi => $q) {
    $userAnswer = isset($submitted[$qi]) ? trim((string)$submitted[$qi]) : '';
    $isCorrect  = ($userAnswer !== '' && $userAnswer === $q['correct']);
    if ($isCorrect) $correctCount++;
    $results[] = [
        'question' => $q['question'], 'correct' => $q['correct'],
        'distractors' => $q['distractors'], 'explanation' => $q['explanation'],
        'userAnswer' => $userAnswer, 'isCorrect' => $isCorrect,
    ];
    $attemptStats[$qi] = ['user_answer' => $userAnswer, 'is_correct' => $isCorrect];
}

try { record_attempt($id, $attemptStats); } catch (Throwable $e) {}

$total = count($quiz['questions']);
$percent = $total > 0 ? round(100 * $correctCount / $total) : 0;

$page_title = $quiz['title'];
require __DIR__ . '/lib/header.php';
?>

<div class="score-banner">
  <div class="big"><?= $correctCount ?> / <?= $total ?></div>
  <div class="small"><?= $percent ?>&nbsp;<?= t('pct_correct') ?></div>
</div>

<div class="card">
  <h2 style="margin-bottom:4px;"><?= t('feedback_title') ?></h2>
  <p class="subtitle"><?= t('feedback_subtitle') ?></p>

  <?php foreach ($results as $i => $r): ?>
    <div class="feedback-question">
      <div class="qnum">
        <?= t('question_n') ?> <?= $i + 1 ?>
        <?php if ($r['isCorrect']): ?>
          <span class="feedback-status ok"><?= t('correct') ?></span>
        <?php else: ?>
          <span class="feedback-status err"><?= t('incorrect') ?></span>
        <?php endif; ?>
      </div>
      <div class="qtext"><?= nl2br(htmlspecialchars($r['question'])) ?></div>

      <?php
      $allChoices = array_merge([$r['correct']], $r['distractors']);
      sort($allChoices);
      foreach ($allChoices as $choice):
          $isThisCorrect = ($choice === $r['correct']);
          $isUserPick    = ($choice === $r['userAnswer']);
          $cls = 'neutral'; $marker = '';
          if ($isThisCorrect) { $cls = 'correct'; $marker = '✓'; }
          elseif ($isUserPick) { $cls = 'wrong'; $marker = '✗'; }
      ?>
        <div class="choice-line <?= $cls ?>">
          <span class="marker"><?= $marker ?></span>
          <span><?= htmlspecialchars($choice) ?></span>
          <?php if ($isUserPick): ?><span class="your"><?= t('your_answer') ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (trim($r['explanation']) !== ''): ?>
        <div class="explanation"><strong><?= t('explanation') ?></strong> <?= nl2br(htmlspecialchars($r['explanation'])) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="button-row">
    <a class="btn" href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')) ?>/quiz.php?id=<?= htmlspecialchars($id) ?>"><?= t('try_again') ?></a>
  </div>
</div>

<?php require __DIR__ . '/lib/footer.php'; ?>
