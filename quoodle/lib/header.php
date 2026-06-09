<?php
require_once __DIR__ . '/lang.php';
if (!isset($page_title)) $page_title = 'Quoodle';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$container_class = $container_class ?? 'container';
$htmlLang = current_lang();
?><!doctype html>
<html lang="<?= $htmlLang ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title) ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/style.css">
<script>
// Prevent FOUC: apply saved theme before first paint
(function(){var t=(document.cookie.match(/(?:^|; )theme=(\w+)/)||[])[1];if(t==='dark'){document.documentElement.setAttribute('data-theme','dark')}})();
</script>
</head>
<body>
<header class="site">
  <div class="inner">
    <a href="<?= htmlspecialchars($base) ?>/index.php" class="brand" style="text-decoration:none;">
      <span class="dot">Q</span>
      <span>Quoodle</span>
    </a>
    <nav class="header-nav">
      <a href="<?= htmlspecialchars($base) ?>/index.php"><?= t('new_quiz') ?></a>
      <span class="sep">·</span>
      <!-- Language switcher -->
      <?php if (current_lang() === 'de'): ?>
        <span class="lang-active">DE</span>
        <a href="<?= htmlspecialchars(lang_switch_url('en')) ?>" class="lang-link">EN</a>
      <?php else: ?>
        <a href="<?= htmlspecialchars(lang_switch_url('de')) ?>" class="lang-link">DE</a>
        <span class="lang-active">EN</span>
      <?php endif; ?>
      <span class="sep">·</span>
      <!-- Dark mode toggle -->
      <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle dark mode" title="Toggle dark mode">
        <span class="icon-sun">☀</span><span class="icon-moon">☾</span>
      </button>
    </nav>
  </div>
</header>
<main class="<?= htmlspecialchars($container_class) ?>">
