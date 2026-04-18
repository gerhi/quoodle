<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/layout.php';

$lang = i18n_init();
$base = get_base_url();

// ── Template download ────────────────────────────────────────────────────────
if (isset($_GET['dl'])) {
    if ($_GET['dl'] === 'csv') {
        $csv_path = __DIR__ . '/templates/template.csv';
        if (file_exists($csv_path)) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="quoodle-vorlage.csv"');
            header('Content-Length: ' . filesize($csv_path));
            readfile($csv_path);
            exit;
        }
    }
    if ($_GET['dl'] === 'xlsx') {
        require_once __DIR__ . '/lib/xlsx_writer.php';
        $wb = new XlsxWriter();
        $wb->add_sheet('Quoodle', [
            ['Frage', 'Richtige Antwort', 'Falschantwort 1', 'Falschantwort 2', 'Falschantwort 3', 'Erklärung'],
            ['Was ist die Hauptstadt von Deutschland?', 'Berlin', 'München', 'Hamburg', 'Frankfurt', 'Berlin ist seit 1990 wieder die Hauptstadt.'],
            ['Welches Element hat das Symbol O?', 'Sauerstoff', 'Wasserstoff', 'Stickstoff', 'Kohlenstoff', 'O steht für Oxygenium.'],
            ['Wie viele Seiten hat ein Hexagon?', '6', '4', '5', '8', 'Hexa = sechs.'],
        ]);
        $bytes = $wb->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="quoodle-vorlage.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }
    redirect('index.php');
}

render_header(t('index.headline'), $lang);
?>

  <div class="home-hero">
    <h1><?= e(t('index.headline')) ?></h1>
    <p><?= e(t('index.subtitle')) ?></p>
  </div>

  <!-- Upload form -->
  <div class="card">
    <form action="<?= e($base) ?>/upload.php" method="post" enctype="multipart/form-data" novalidate>

      <div class="form-group">
        <label for="quiz-title"><?= e(t('index.label.title')) ?></label>
        <input type="text" id="quiz-title" name="title"
               maxlength="200" required autocomplete="off"
               placeholder="<?= e(t('index.placeholder.title')) ?>">
        <p class="form-hint" id="char-count" aria-live="polite"></p>
      </div>

      <div class="form-group">
        <label for="quiz-file"><?= e(t('index.label.file')) ?></label>
        <input type="file" id="quiz-file" name="quiz_file"
               accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
               required>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        <?= e(t('index.btn.upload')) ?>
      </button>
    </form>
  </div>

  <!-- Templates -->
  <div class="card">
    <h2 class="card-title"><?= e(t('index.template.headline')) ?></h2>
    <p class="card-subtitle"><?= e(t('index.template.text')) ?></p>
    <div class="template-links">
      <a href="<?= e($base) ?>/index.php?dl=xlsx&lang=<?= e($lang) ?>"
         class="btn btn-secondary btn-sm">📊 <?= e(t('index.template.xlsx')) ?></a>
      <a href="<?= e($base) ?>/index.php?dl=csv&lang=<?= e($lang) ?>"
         class="btn btn-secondary btn-sm">📄 <?= e(t('index.template.csv')) ?></a>
    </div>
  </div>

  <!-- Format reference -->
  <div class="card">
    <h2 class="card-title"><?= e(t('index.format.headline')) ?></h2>
    <table class="format-table">
      <thead><tr><th>Spalte</th><th>Inhalt</th></tr></thead>
      <tbody>
        <tr><td>A</td><td><?= e(t('index.format.col_a')) ?></td></tr>
        <tr><td>B</td><td><?= e(t('index.format.col_b')) ?></td></tr>
        <tr><td>C … vorletzte</td><td><?= e(t('index.format.col_c')) ?></td></tr>
        <tr><td>Letzte</td><td><?= e(t('index.format.col_last')) ?></td></tr>
      </tbody>
    </table>
    <p class="form-hint mt-8">
      CSV: Semikolon als Trennzeichen. Erste Zeile = Kopfzeile (wird übersprungen).
    </p>
  </div>

<script>
// Live character counter
(function(){
  var inp = document.getElementById('quiz-title');
  var ctr = document.getElementById('char-count');
  if(!inp || !ctr) return;
  function update(){ ctr.textContent = inp.value.length + ' / 200'; }
  inp.addEventListener('input', update);
  update();
})();
</script>
<?php
render_footer();
