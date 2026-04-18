<?php
/**
 * Quoodle — Diagnose
 * Lade diese Datei über den Browser auf: /quoodle/diagnose.php
 * Zeigt Umgebungsinfos. NACH DEM DEBUGGEN WIEDER LÖSCHEN!
 */

// Fehler sichtbar machen
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: text/html; charset=UTF-8');

$checks = [];

// 1. PHP Version (Quoodle benötigt 8.0+)
$v_ok = version_compare(PHP_VERSION, '8.0.0', '>=');
$checks[] = ['PHP-Version', PHP_VERSION, $v_ok, 'Benötigt: 8.0 oder höher'];

// 2. SQLite3-Treiber (PDO)
$pdo_sqlite = in_array('sqlite', PDO::getAvailableDrivers(), true);
$checks[] = ['PDO SQLite', $pdo_sqlite ? 'verfügbar' : 'FEHLT', $pdo_sqlite, 'Bei shared hosting ggf. im Kundenportal aktivieren'];

// 3. ZipArchive (für XLSX-Export + Parsing)
$zip_ok = class_exists('ZipArchive');
$checks[] = ['ZipArchive', $zip_ok ? 'verfügbar' : 'FEHLT', $zip_ok, 'PHP-Erweiterung "zip" wird benötigt'];

// 4. SimpleXML (für XLSX-Parser)
$xml_ok = function_exists('simplexml_load_string');
$checks[] = ['SimpleXML', $xml_ok ? 'verfügbar' : 'FEHLT', $xml_ok, 'Für das XLSX-Parsing'];

// 5. mbstring (UTF-8 Handling)
$mb_ok = function_exists('mb_strlen');
$checks[] = ['mbstring', $mb_ok ? 'verfügbar' : 'FEHLT', $mb_ok, 'Für Unicode-Titel'];

// 6. random_bytes (PHP 7+ Standard)
$rb_ok = function_exists('random_bytes');
$checks[] = ['random_bytes', $rb_ok ? 'verfügbar' : 'FEHLT', $rb_ok, 'Für Quiz-IDs'];

// 7. data/-Verzeichnis schreibbar?
$data_dir = __DIR__ . '/data';
if (!is_dir($data_dir)) @mkdir($data_dir, 0750, true);
$data_writable = is_dir($data_dir) && is_writable($data_dir);
$checks[] = ['data/ schreibbar', $data_writable ? 'ja' : 'NEIN', $data_writable, 'chmod 750 data/ ausführen'];

// 8. Kann eine SQLite-DB angelegt werden?
$db_ok = false;
$db_err = '';
if ($pdo_sqlite && $data_writable) {
    try {
        $test = new PDO('sqlite:' . $data_dir . '/test.db');
        $test->exec('CREATE TABLE IF NOT EXISTS t (x INT)');
        $db_ok = true;
        $test = null;
        @unlink($data_dir . '/test.db');
    } catch (Throwable $e) {
        $db_err = $e->getMessage();
    }
}
$checks[] = ['SQLite-DB anlegen', $db_ok ? 'OK' : ('FEHLER: ' . $db_err), $db_ok, ''];

// 9. Syntaxcheck aller PHP-Dateien
$files = [
    'layout.php', 'index.php', 'upload.php', 'quiz.php', 'submit.php',
    'share.php', 'stats.php', 'export.php', 'impressum.php', 'datenschutz.php',
    'lib/helpers.php', 'lib/i18n.php', 'lib/db.php', 'lib/qr.php',
    'lib/csv_parser.php', 'lib/xlsx_parser.php', 'lib/xlsx_writer.php',
    'lang/de.php', 'lang/en.php',
];

$syntax_errors = [];
foreach ($files as $rel) {
    $path = __DIR__ . '/' . $rel;
    if (!file_exists($path)) {
        $syntax_errors[] = "$rel: DATEI FEHLT";
        continue;
    }
    // php -l als shell-exec funktioniert auf manchen shared hosts nicht
    // stattdessen: token_get_all versucht die Datei zu parsen
    $src = file_get_contents($path);
    if ($src === false) continue;
    try {
        @token_get_all($src, TOKEN_PARSE);
    } catch (ParseError $e) {
        $syntax_errors[] = "$rel: " . $e->getMessage();
    } catch (Throwable $e) {
        $syntax_errors[] = "$rel: " . $e->getMessage();
    }
}

// 10. Versuchsweiser Include der helpers.php (fängt Fatal Errors ab)
$include_ok = false;
$include_err = '';
try {
    ob_start();
    // ACHTUNG: helpers.php setzt security headers; das ist hier OK
    @include_once __DIR__ . '/lib/helpers.php';
    @include_once __DIR__ . '/lib/i18n.php';
    ob_end_clean();
    $include_ok = function_exists('e') && function_exists('generate_id') && function_exists('t');
} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_end_clean();
    $include_err = $e->getMessage();
}

?>
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Quoodle Diagnose</title>
  <style>
    body { font-family: -apple-system,sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; color:#111; background:#f7f8fa; }
    h1 { margin-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #e5e7eb; }
    th { background: #f3f4f6; font-size: .875rem; }
    .ok   { color: #15803d; font-weight: 600; }
    .fail { color: #b91c1c; font-weight: 600; }
    pre { background: #fee; border: 1px solid #fcc; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: .8125rem; white-space: pre-wrap; }
    .box { background: #fff; padding: 16px; border-radius: 8px; margin: 16px 0; }
    .hint { color: #6b7280; font-size: .875rem; }
    .warn { background: #fffbeb; border: 1px solid #fde68a; padding: 12px; border-radius: 6px; margin: 20px 0; color: #92400e; }
  </style>
</head>
<body>

<h1>🔍 Quoodle Diagnose</h1>
<p class="hint">Diese Datei NACH DEM DEBUGGEN UNBEDINGT LÖSCHEN.</p>

<h2>Umgebung</h2>
<table>
  <tr><th>Check</th><th>Wert</th><th>Status</th><th>Hinweis</th></tr>
  <?php foreach ($checks as $c): [$name, $val, $ok, $hint] = $c; ?>
  <tr>
    <td><?= htmlspecialchars($name) ?></td>
    <td><?= htmlspecialchars((string)$val) ?></td>
    <td class="<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '✓ OK' : '✗ FEHLER' ?></td>
    <td class="hint"><?= htmlspecialchars($hint) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<h2>Dateien & Syntax</h2>
<?php if (empty($syntax_errors)): ?>
  <div class="box ok">✓ Alle PHP-Dateien sind vorhanden und syntaktisch korrekt.</div>
<?php else: ?>
  <div class="warn">
    <strong>Syntaxfehler oder fehlende Dateien:</strong>
    <pre><?= htmlspecialchars(implode("\n", $syntax_errors)) ?></pre>
  </div>
<?php endif; ?>

<h2>Bootstrap-Test</h2>
<?php if ($include_ok): ?>
  <div class="box ok">✓ helpers.php + i18n.php laden erfolgreich. Alle Basisfunktionen verfügbar.</div>
<?php else: ?>
  <div class="warn">
    <strong>Fehler beim Laden der Basisdateien:</strong>
    <pre><?= htmlspecialchars($include_err ?: 'Eine benötigte Funktion (e, t, generate_id) wurde nicht definiert. Prüfe den Inhalt von lib/helpers.php und lib/i18n.php.') ?></pre>
  </div>
<?php endif; ?>

<h2>Aktuelle Konfiguration</h2>
<div class="box">
  <p><strong>Script-Pfad:</strong> <?= htmlspecialchars(__FILE__) ?></p>
  <p><strong>Document Root:</strong> <?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '?') ?></p>
  <p><strong>Script-Name:</strong> <?= htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '?') ?></p>
  <p><strong>HTTP Host:</strong> <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '?') ?></p>
  <p><strong>error_log:</strong> <?= htmlspecialchars(ini_get('error_log') ?: '(nicht gesetzt)') ?></p>
  <p><strong>display_errors:</strong> <?= htmlspecialchars(ini_get('display_errors')) ?></p>
  <p><strong>Server Software:</strong> <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '?') ?></p>
</div>

<h2>Nächste Schritte</h2>
<ul>
  <li>Alle Zeilen oben müssen grün sein, damit Quoodle läuft.</li>
  <li>Prüfe die error_log des Hosters für Fatal Errors.</li>
  <li>Bei shared hosting: PHP-Version im Kundenportal auf <strong>8.1 oder 8.2</strong> setzen (falls wählbar).</li>
  <li>Falls <code>data/</code> nicht schreibbar ist: <code>chmod 755 data/</code> über FTP setzen.</li>
</ul>

</body>
</html>
