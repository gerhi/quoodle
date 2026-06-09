<?php
require __DIR__ . '/lib/storage.php';

$id = $_GET['id'] ?? '';
$quiz = load_quiz($id);

if (!$quiz) {
    http_response_code(404);
    $page_title = t('not_found');
    require __DIR__ . '/lib/header.php';
    echo '<div class="card"><div class="alert error">' . htmlspecialchars(t('quiz_invalid')) . '</div></div>';
    require __DIR__ . '/lib/footer.php';
    exit;
}

$page_title = $quiz['title'];
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$total = count($quiz['questions']);
require __DIR__ . '/lib/header.php';
?>

<div class="card">
  <h2><?= htmlspecialchars($quiz['title']) ?></h2>
  <p class="subtitle"><?= $total ?> <?= tp('questions_count', $total) ?>. <?= t('quiz_intro') ?></p>

  <form id="quiz-form" action="<?= htmlspecialchars($base) ?>/submit.php" method="post" autocomplete="off">
    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

    <div class="quiz-progress">
      <span id="prog-text"><?= t('question_n') ?> 1 <?= t('q_of') ?> <?= $total ?></span>
      <div class="progress-bar-wrap"><span id="prog-bar" style="width: <?= round(100/$total, 1) ?>%;"></span></div>
    </div>

    <div id="quiz-alert" class="quiz-alert"><?= t('please_select') ?></div>

    <?php foreach ($quiz['questions'] as $qi => $q):
        $choices = array_merge([$q['correct']], $q['distractors']);
        shuffle($choices);
    ?>
      <div class="question" data-qi="<?= $qi ?>">
        <div class="qnum"><?= t('question_n') ?> <?= $qi + 1 ?></div>
        <div class="qtext"><?= nl2br(htmlspecialchars($q['question'])) ?></div>
        <div class="choices">
          <?php foreach ($choices as $choice): ?>
            <label>
              <input type="radio" name="answers[<?= $qi ?>]" value="<?= htmlspecialchars($choice) ?>">
              <span><?= htmlspecialchars($choice) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="quiz-nav">
      <button type="button" id="btn-prev" class="btn ghost" onclick="stepQuiz(-1)"><?= t('prev') ?></button>
      <button type="button" id="btn-next" class="btn" onclick="stepQuiz(1)"><?= t('next') ?></button>
      <button type="submit" id="btn-submit" class="btn" style="display:none;"><?= t('submit_answers') ?></button>
    </div>
  </form>
</div>

<script>
(function(){
  var qs = document.querySelectorAll('.question');
  var total = qs.length;
  var cur = 0;
  var alertEl = document.getElementById('quiz-alert');

  function show(i){
    qs.forEach(function(q,idx){ q.classList.toggle('active', idx===i); });
    document.getElementById('prog-text').textContent =
      <?= json_encode(t('question_n')) ?> + ' ' + (i+1) + ' ' + <?= json_encode(t('q_of')) ?> + ' ' + total;
    document.getElementById('prog-bar').style.width = ((i+1)/total*100) + '%';
    document.getElementById('btn-prev').style.display = i===0 ? 'none' : '';
    document.getElementById('btn-next').style.display = i===total-1 ? 'none' : '';
    document.getElementById('btn-submit').style.display = i===total-1 ? '' : 'none';
    alertEl.style.display = 'none';
    // Scroll to top of card
    qs[0].closest('.card').scrollIntoView({behavior:'smooth', block:'start'});
  }

  window.stepQuiz = function(dir){
    if(dir===1){
      // Check if current question has a selected answer
      var radios = qs[cur].querySelectorAll('input[type=radio]');
      var answered = false;
      radios.forEach(function(r){ if(r.checked) answered = true; });
      if(!answered){
        alertEl.style.display = 'block';
        return;
      }
    }
    cur = Math.max(0, Math.min(total-1, cur+dir));
    show(cur);
  };

  // Handle form submission: check last question
  document.getElementById('quiz-form').addEventListener('submit', function(e){
    var radios = qs[cur].querySelectorAll('input[type=radio]');
    var answered = false;
    radios.forEach(function(r){ if(r.checked) answered = true; });
    if(!answered){
      e.preventDefault();
      alertEl.style.display = 'block';
    }
  });

  show(0);
})();
</script>

<?php require __DIR__ . '/lib/footer.php'; ?>
