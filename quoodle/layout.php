<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';

/**
 * Render HTML <head> + site header.
 * Theme is read from cookie server-side → no FOUC.
 */
function render_header(string $page_title, string $lang = ''): void {
    if ($lang === '') $lang = current_lang();
    $theme = $_COOKIE['theme'] ?? 'light';
    if (!in_array($theme, ['light', 'dark'], true)) $theme = 'light';
    $base  = get_base_url();
    $icon  = $theme === 'dark' ? '☀' : '☾';
    $other = $lang === 'de' ? 'en' : 'de';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" data-theme="<?= e($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title) ?> – Quoodle</title>
<link rel="stylesheet" href="<?= e($base) ?>/assets/style.css">
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="<?= e($base) ?>/index.php">
      <span class="brand-q">Q</span>
      <span class="brand-name">Quoodle</span>
    </a>
    <nav class="header-nav">
      <a href="<?= e($base) ?>/index.php?lang=<?= e($lang) ?>"
         class="nav-link"><?= e(t('nav.new_quiz')) ?></a>
      <span class="lang-switch">
        <?php if ($lang === 'de'): ?>
          <span class="lang-active"><?= e(t('nav.lang_de')) ?></span>
          <a href="<?= e(lang_url('en')) ?>" class="lang-link"><?= e(t('nav.lang_en')) ?></a>
        <?php else: ?>
          <a href="<?= e(lang_url('de')) ?>" class="lang-link"><?= e(t('nav.lang_de')) ?></a>
          <span class="lang-active"><?= e(t('nav.lang_en')) ?></span>
        <?php endif; ?>
      </span>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
        <span class="theme-icon"><?= $icon ?></span>
      </button>
    </nav>
  </div>
</header>
<main class="page-main">
  <div class="content-wrap">
<?php
}

/**
 * Render site footer + closing tags.
 */
function render_footer(): void {
    $base = get_base_url();
    $lang = current_lang();
?>
  </div><!-- .content-wrap -->
</main>
<footer class="site-footer">
  <div class="footer-inner">
    <span class="footer-tagline"><?= e(t('footer.tagline')) ?></span>
    <nav class="footer-nav">
      <a href="<?= e($base) ?>/impressum.php"><?= e(t('footer.imprint')) ?></a>
      <a href="<?= e($base) ?>/datenschutz.php"><?= e(t('footer.privacy')) ?></a>
    </nav>
  </div>
</footer>
<script src="<?= e($base) ?>/assets/app.js"></script>
</body>
</html>
<?php
}
