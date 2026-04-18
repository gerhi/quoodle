<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/layout.php';

$lang = i18n_init();
$base = get_base_url();

// ── Validate params + auth ───────────────────────────────────────────────────
$id    = $_GET['id'] ?? '';
$token = $_GET['t']  ?? '';

if (!validate_id($id) || !validate_token($token)) {
    not_found();
}

$quiz = db_get_quiz($id);
if (!$quiz || !safe_token_compare($quiz['teacher_token'], $token)) {
    not_found();
}

$questions = $quiz['questions'];
$stats     = $quiz['stats'];
$n_q       = count($questions);
$attempts  = (int)($stats['attempts'] ?? 0);

// ── New v1.1 aggregates (defensive defaults for pre-existing quizzes) ────────
$total_time   = (int)($stats['total_time_seconds']       ?? 0);
$total_tabs   = (int)($stats['total_tab_switches']       ?? 0);
$tab_attempts = (int)($stats['attempts_with_tab_switch'] ?? 0);

$avg_time_sec  = ($attempts > 0) ? (int)round($total_time / $attempts) : 0;
$avg_tabs      = ($attempts > 0) ? round($total_tabs / $attempts, 1)   : 0.0;
$pct_with_tabs = ($attempts > 0) ? round($tab_attempts / $attempts * 100) : 0;

// ── Average correct % ────────────────────────────────────────────────────────
$avg_pct = 0;
if ($attempts > 0 && $n_q > 0) {
    $sum = 0;
    foreach ($stats['questions'] as $qs) {
        $tot = $qs['total_count'] ?? 0;
        $cor = $qs['correct_count'] ?? 0;
        $sum += $tot > 0 ? $cor / $tot : 0;
    }
    $avg_pct = round(($sum / $n_q) * 100);
}

$student_url = $base . '/quiz.php?id=' . urlencode($id);
$export_base = $base . '/export.php?id=' . urlencode($id) . '&t=' . urlencode($token);

render_header(t('stats.headline') . ': ' . $quiz['title'], $lang);
?>



    <h1 class="page-title"><?= e($quiz['title']) ?></h1>
    <p class="page-subtitle">
      <?= e(t('stats.created')) ?>: <?= e(date('d.m.Y', strtotime($quiz['created_at']))) ?>
    </p>

    <!-- Summary cards -->
    <div class="summary-cards">
      <div class="summary-card">
        <div class="summary-card-value"><?= $attempts ?></div>
        <div class="summary-card-label"><?= e(t('stats.total_attempts')) ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-card-value"><?= $n_q ?></div>
        <div class="summary-card-label"><?= e(t('stats.num_questions')) ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-card-value"><?= $attempts > 0 ? $avg_pct . '%' : '—' ?></div>
        <div class="summary-card-label"><?= e(t('stats.avg_correct')) ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-card-value"><?= $attempts > 0 && $avg_time_sec > 0 ? e(format_duration($avg_time_sec)) : '—' ?></div>
        <div class="summary-card-label"><?= e(t('stats.avg_time')) ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-card-value">
          <?php if ($attempts === 0): ?>—<?php
            elseif ($tab_attempts === 0): ?>0<?php
            else: ?><?= $pct_with_tabs ?>%<?php
          endif; ?>
        </div>
        <div class="summary-card-label"><?= e(t('stats.tab_switches_rate')) ?></div>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="stats-actions">
      <a href="<?= e('?id=' . urlencode($id) . '&t=' . urlencode($token) . '&lang=' . urlencode($lang)) ?>"
         class="btn btn-secondary btn-sm">↻ <?= e(t('stats.refresh')) ?></a>
      <a href="<?= e($student_url . '&lang=' . urlencode($lang)) ?>"
         class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
        <?= e(t('stats.open_quiz')) ?> ↗</a>
      <?php if ($attempts > 0): ?>
      <a href="<?= e($export_base . '&format=xlsx&lang=' . urlencode($lang)) ?>"
         class="btn btn-secondary btn-sm">📊 <?= e(t('stats.export_xlsx')) ?></a>
      <a href="<?= e($export_base . '&format=csv&lang=' . urlencode($lang)) ?>"
         class="btn btn-secondary btn-sm">📄 <?= e(t('stats.export_csv')) ?></a>
      <?php endif; ?>
    </div>

    <?php if ($attempts === 0): ?>
    <!-- Empty state -->
    <div class="card" style="text-align:center;padding:48px 32px">
      <div style="font-size:2.5rem;margin-bottom:12px">📊</div>
      <h2 class="card-title"><?= e(t('stats.no_attempts')) ?></h2>
      <p class="card-subtitle"><?= e(t('stats.no_attempts_sub')) ?></p>
    </div>

    <?php else: ?>
    <!-- Per-question breakdown -->
    <?php foreach ($questions as $qi => $q): ?>
    <?php
      $qs  = $stats['questions'][$qi] ?? ['total_count'=>0,'correct_count'=>0,'choice_counts'=>[]];
      $tot = (int)($qs['total_count']   ?? 0);
      $cor = (int)($qs['correct_count'] ?? 0);
      $pct = $tot > 0 ? round($cor / $tot * 100) : 0;
      $bar_mod = $pct >= 75 ? 'stat-bar--green' : ($pct >= 50 ? 'stat-bar--amber' : 'stat-bar--red');

      $choice_counts = $qs['choice_counts'] ?? [];
      $known = array_merge([$q['correct']], $q['distractors']);
      $unknowns = array_diff(array_keys($choice_counts), $known);
      $max_cnt = max(1, ...array_merge(array_values($choice_counts), [0]));
    ?>
    <div class="card stat-question">
      <div class="stat-qhead">
        <span class="stat-qnum"><?= e(t('stats.question')) ?> <?= $qi + 1 ?></span>
        <strong><?= e($q['text']) ?></strong>
        <span class="stat-fraction"><?= $cor ?>/<?= $tot ?> · <?= $pct ?>%</span>
      </div>

      <div class="stat-bar-wrap">
        <div class="stat-bar <?= $bar_mod ?>" style="width:<?= $pct ?>%"></div>
      </div>

      <ul class="choice-breakdown">
        <?php foreach ($known as $choice): ?>
        <?php
          $cnt       = (int)($choice_counts[$choice] ?? 0);
          $share     = $tot > 0 ? round($cnt / $tot * 100) : 0;
          $bar_w     = $max_cnt > 0 ? round($cnt / $max_cnt * 100) : 0;
          $is_correct= ($choice === $q['correct']);
        ?>
        <li class="cb-row <?= $is_correct ? 'cb-row--correct' : '' ?>">
          <span class="cb-marker"><?= $is_correct ? '✓' : '' ?></span>
          <span class="cb-text"><?= e($choice) ?></span>
          <div class="cb-bar-wrap"><div class="cb-bar" style="width:<?= $bar_w ?>%"></div></div>
          <span class="cb-count"><?= $cnt ?> · <?= $share ?>%</span>
        </li>
        <?php endforeach; ?>
        <?php foreach ($unknowns as $unk): ?>
        <?php $cnt = (int)($choice_counts[$unk] ?? 0); $share = $tot > 0 ? round($cnt/$tot*100) : 0; ?>
        <li class="cb-row">
          <span class="cb-marker cb-marker--unknown">?</span>
          <span class="cb-text"><?= e($unk) ?> <em class="text-muted">(unknown answer)</em></span>
          <div class="cb-bar-wrap"><div class="cb-bar" style="width:<?= $max_cnt > 0 ? round($cnt/$max_cnt*100) : 0 ?>%"></div></div>
          <span class="cb-count"><?= $cnt ?> · <?= $share ?>%</span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>



<?php
render_footer();
