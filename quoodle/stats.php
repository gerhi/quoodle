<?php
require __DIR__ . '/lib/storage.php';

$id    = $_GET['id'] ?? $_POST['id'] ?? '';
$token = $_GET['t']  ?? $_POST['t']  ?? '';
$quiz  = load_quiz($id);

if (!$quiz || !verify_teacher_token($quiz, $token)) {
    http_response_code(404);
    $page_title = t('not_found');
    require __DIR__ . '/lib/header.php';
    echo '<div class="card"><div class="alert error">' . htmlspecialchars(t('stats_invalid')) . '</div></div>';
    require __DIR__ . '/lib/footer.php';
    exit;
}

// ---- Handle POST actions ----
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        delete_quiz($id);
        // Redirect to landing page with flash
        $base = base_path();
        header('Location: ' . $base . '/index.php?deleted=1');
        exit;
    }

    if ($action === 'set_expiry') {
        $mode = $_POST['mode'] ?? '';
        $newExpiry = null;
        switch ($mode) {
            case '+1m':  $newExpiry = date('c', strtotime('+30 days'));  break;
            case '+3m':  $newExpiry = date('c', strtotime('+90 days'));  break;
            case '+1y':  $newExpiry = date('c', strtotime('+365 days')); break;
            case 'never': $newExpiry = null; break;
            case 'date':
                $d = $_POST['expiry_date'] ?? '';
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    $newExpiry = date('c', strtotime($d . ' 23:59:59'));
                }
                break;
        }
        update_expiry($id, $newExpiry);
        // Reload quiz to get updated data
        $quiz = load_quiz($id);
        if (!$quiz) { header('Location: index.php'); exit; }
        $flash = 'expiry_updated';
    }
}

// ---- Prepare display data ----
$page_title      = $quiz['title'];
$container_class = 'container wide';

$stats     = $quiz['stats'] ?? ['attempts' => 0, 'questions' => []];
$attempts  = (int)($stats['attempts'] ?? 0);
$qStats    = $stats['questions'] ?? [];

$totalAnswered = 0; $totalCorrect = 0;
foreach ($qStats as $q) {
    $totalAnswered += (int)($q['total_count']   ?? 0);
    $totalCorrect  += (int)($q['correct_count'] ?? 0);
}
$avgPct = $totalAnswered > 0 ? round(100 * $totalCorrect / $totalAnswered) : null;
$qCount = count($quiz['questions']);

// Expiry helpers
$expiresAt = $quiz['expires_at'] ?? null;
$expiryDisplay = null;
$expiryClass = '';
$expiryCountdown = '';
if ($expiresAt !== null) {
    $expTs = strtotime($expiresAt);
    $daysLeft = (int)ceil(($expTs - time()) / 86400);
    $expiryDisplay = date(current_lang() === 'de' ? 'd.m.Y' : 'M j, Y', $expTs);
    if ($daysLeft < 0) {
        $expiryCountdown = t('expired');
        $expiryClass = 'danger';
    } elseif ($daysLeft === 0) {
        $expiryCountdown = t('expires_today');
        $expiryClass = 'danger';
    } elseif ($daysLeft <= 3) {
        $expiryCountdown = t('expires_in') . ' ' . $daysLeft . ' ' . tp('expires_days', $daysLeft);
        $expiryClass = 'danger';
    } elseif ($daysLeft <= 14) {
        $expiryCountdown = t('expires_in') . ' ' . $daysLeft . ' ' . tp('expires_days', $daysLeft);
        $expiryClass = 'warning';
    } else {
        $expiryCountdown = t('expires_in') . ' ' . $daysLeft . ' ' . tp('expires_days', $daysLeft);
        $expiryClass = 'ok';
    }
}

function pct_class(int $pct): string {
    if ($pct >= 75) return 'high';
    if ($pct >= 50) return 'mid';
    return 'low';
}

require __DIR__ . '/lib/header.php';
?>

<?php if ($flash === 'expiry_updated'): ?>
  <div class="alert success" style="margin-bottom:20px;"><?= t('expiry_updated') ?></div>
<?php endif; ?>

<div class="card">
  <h2 style="margin-bottom:4px;"><?= htmlspecialchars($quiz['title']) ?></h2>
  <p class="subtitle"><?= t('stats_subtitle') ?></p>

  <div class="stat-summary">
    <div class="stat-card">
      <div class="num"><?= $attempts ?></div>
      <div class="lbl"><?= tp('attempts', $attempts) ?></div>
    </div>
    <div class="stat-card">
      <div class="num"><?= $qCount ?></div>
      <div class="lbl"><?= tp('questions_count', $qCount) ?></div>
    </div>
    <div class="stat-card">
      <div class="num"><?= $avgPct === null ? '—' : $avgPct . '&nbsp;%' ?></div>
      <div class="lbl"><?= t('avg_correct') ?></div>
    </div>
    <div class="stat-card expiry-card <?= $expiryClass ?>">
      <div class="num" style="font-size:1.1rem;">
        <?= $expiresAt ? htmlspecialchars($expiryDisplay) : t('no_expiry') ?>
      </div>
      <div class="lbl">
        <?= t('expiry_label') ?>
        <?php if ($expiryCountdown): ?> — <span class="expiry-countdown <?= $expiryClass ?>"><?= $expiryCountdown ?></span><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <h2 style="margin-bottom:4px;"><?= t('per_question') ?></h2>
  <p class="subtitle"><?= t('per_question_sub') ?></p>

  <?php if ($attempts === 0): ?>
    <div class="empty-state">
      <span class="icon">📭</span>
      <div><?= t('no_attempts_yet') ?></div>
      <div style="margin-top:6px; font-size:0.9rem;"><?= t('no_attempts_hint') ?></div>
    </div>
  <?php else: ?>
    <?php foreach ($quiz['questions'] as $qi => $q):
        $qs = $qStats[$qi] ?? ['correct_count'=>0,'total_count'=>0,'choice_counts'=>[]];
        $tot = (int)$qs['total_count']; $cor = (int)$qs['correct_count'];
        $pct = $tot > 0 ? (int)round(100*$cor/$tot) : 0;
        $cls = pct_class($pct);
        $choices = array_merge([$q['correct']], $q['distractors']); sort($choices);
        $counts = (array)($qs['choice_counts'] ?? []);
    ?>
      <div class="stat-question">
        <div class="stat-head">
          <div>
            <div class="qnum"><?= t('question_n') ?> <?= $qi+1 ?></div>
            <div class="qtext" style="margin-bottom:0;"><?= nl2br(htmlspecialchars($q['question'])) ?></div>
          </div>
          <div class="pct <?= $cls ?>"><?= $cor ?>/<?= $tot ?> · <?= $pct ?>&nbsp;%</div>
        </div>
        <div class="bar <?= $cls ?>"><span style="width:<?= $pct ?>%;"></span></div>
        <?php foreach ($choices as $choice):
            $cnt = (int)($counts[$choice] ?? 0);
            $share = $tot > 0 ? (int)round(100*$cnt/$tot) : 0;
            $isCorrect = ($choice === $q['correct']);
        ?>
          <div class="choice-stat <?= $isCorrect ? 'is-correct' : '' ?>">
            <div class="marker"><?= $isCorrect ? '✓' : '' ?></div>
            <div class="text"><?= htmlspecialchars($choice) ?></div>
            <div class="mini-bar"><span style="width:<?= $share ?>%;"></span></div>
            <div class="count"><?= $cnt ?> · <?= $share ?>&nbsp;%</div>
          </div>
        <?php endforeach; ?>
        <?php $allKnown = array_flip($choices);
          foreach ($counts as $answer => $cnt):
              if (isset($allKnown[$answer])) continue;
              $share = $tot > 0 ? (int)round(100*(int)$cnt/$tot) : 0;
        ?>
          <div class="choice-stat">
            <div class="marker">?</div>
            <div class="text"><em><?= htmlspecialchars($answer) ?></em> <span class="hint"><?= t('unknown_answer') ?></span></div>
            <div class="mini-bar"><span style="width:<?= $share ?>%;"></span></div>
            <div class="count"><?= (int)$cnt ?> · <?= $share ?>&nbsp;%</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="button-row">
    <a class="btn" href="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><?= t('refresh') ?></a>
    <a class="btn secondary" href="<?= htmlspecialchars(quiz_url($id)) ?>" target="_blank"><?= t('open_student_quiz') ?></a>
    <?php if ($attempts > 0): ?>
      <a class="btn secondary" href="export.php?id=<?= urlencode($id) ?>&amp;t=<?= urlencode($token) ?>&amp;format=xlsx"><?= t('export_excel') ?></a>
      <a class="btn secondary" href="export.php?id=<?= urlencode($id) ?>&amp;t=<?= urlencode($token) ?>&amp;format=csv"><?= t('export_csv') ?></a>
    <?php endif; ?>
  </div>
</div>

<!-- Expiry management -->
<div class="card">
  <h3 style="margin-top:0;"><?= t('expiry_label') ?></h3>
  <p class="hint" style="margin-bottom:14px;"><?= t('expiry_hint') ?></p>

  <form method="post" style="display:flex; flex-wrap:wrap; gap:8px; align-items:end;">
    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
    <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="set_expiry">

    <button type="submit" name="mode" value="+1m" class="btn secondary"><?= t('extend_1m') ?></button>
    <button type="submit" name="mode" value="+3m" class="btn secondary"><?= t('extend_3m') ?></button>
    <button type="submit" name="mode" value="+1y" class="btn secondary"><?= t('extend_1y') ?></button>
    <button type="submit" name="mode" value="never" class="btn secondary"><?= t('set_never') ?></button>

    <span class="sep" style="margin:0 4px; color:var(--text-soft);">|</span>

    <input type="date" name="expiry_date"
           value="<?= $expiresAt ? date('Y-m-d', strtotime($expiresAt)) : date('Y-m-d', strtotime('+30 days')) ?>"
           min="<?= date('Y-m-d') ?>"
           style="padding:9px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); font-family:inherit; font-size:0.92rem; background:var(--surface); color:var(--text);">
    <button type="submit" name="mode" value="date" class="btn secondary"><?= t('set_date') ?></button>
  </form>
</div>

<!-- Danger zone -->
<div class="card danger-zone">
  <h3 style="margin-top:0; color:var(--danger);"><?= t('danger_zone') ?></h3>

  <div id="delete-prompt" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
    <button type="button" class="btn danger" onclick="document.getElementById('delete-prompt').style.display='none'; document.getElementById('delete-confirm').style.display='block';">
      <?= t('delete_quiz') ?>
    </button>
    <span class="hint"><?= t('delete_confirm_text') ?></span>
  </div>

  <div id="delete-confirm" style="display:none;">
    <div class="alert error" style="margin-bottom:14px;">
      <?= t('delete_confirm_text') ?>
    </div>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap;">
      <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
      <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="btn danger"><?= t('delete_confirm_btn') ?></button>
      <button type="button" class="btn secondary" onclick="document.getElementById('delete-confirm').style.display='none'; document.getElementById('delete-prompt').style.display='flex';">
        <?= t('delete_cancel') ?>
      </button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/lib/footer.php'; ?>
