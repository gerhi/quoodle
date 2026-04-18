<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/layout.php';

$lang = i18n_init();

render_header('Impressum', $lang);
?>

  <div class="page-title">
    <h1>Impressum</h1>
    <p class="text-muted text-sm">Angaben gemäß § 5 TMG</p>
  </div>

  <div class="card">
    <div class="alert alert-warn mb-2">
      <strong>Hinweis:</strong> Dieses Impressum ist ein Platzhalter.
      Bitte ersetze diese Angaben durch deine eigenen Kontaktdaten.
    </div>

    <h2>Verantwortliche Person</h2>
    <p class="mt-1">
      Vorname Nachname<br>
      Musterstraße 1<br>
      12345 Musterstadt<br>
      Deutschland
    </p>

    <h2 class="mt-2">Kontakt</h2>
    <p class="mt-1">
      E-Mail: <a href="mailto:deine@email.de">deine@email.de</a>
    </p>

    <h2 class="mt-2">Haftungsausschluss</h2>
    <p class="mt-1 text-sm">
      Die Inhalte dieser Seite wurden mit größtmöglicher Sorgfalt erstellt.
      Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte wird
      keine Haftung übernommen.
    </p>
  </div>

<?php
render_footer();
