<?php $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); ?>
</main>
<footer class="site">
  <div class="footer-links">
    <a href="<?= htmlspecialchars($base) ?>/impressum.php"><?= t('impressum') ?></a>
    <span class="sep">·</span>
    <a href="<?= htmlspecialchars($base) ?>/datenschutz.php"><?= t('privacy') ?></a>
  </div>
  <div class="footer-meta"><?= t('footer_text') ?></div>
</footer>
<script>
function toggleTheme(){
  var h=document.documentElement;
  var d=h.getAttribute('data-theme')==='dark'?'light':'dark';
  h.setAttribute('data-theme',d);
  document.cookie='theme='+d+';path=/;max-age=31536000;SameSite=Lax';
}
</script>
</body>
</html>
