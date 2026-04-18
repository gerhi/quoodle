<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/layout.php';

$lang = i18n_init();

render_header('Datenschutzerklärung', $lang);
?>

  <div class="page-title">
    <h1>Datenschutzerklärung</h1>
    <p class="text-muted text-sm">Stand: <?= date('F Y') ?></p>
  </div>

  <div class="card">

    <h2>1. Grundsatz der Datensparsamkeit</h2>
    <p class="mt-1">
      Quoodle ist nach dem Prinzip der Datensparsamkeit gestaltet.
      Es werden <strong>keine personenbezogenen Daten</strong> von Lernenden erhoben oder gespeichert.
      Es gibt keine Nutzerkonten, keine Anmeldung und kein Tracking.
    </p>

    <h2 class="mt-2">2. Welche Daten werden gespeichert?</h2>
    <p class="mt-1">
      Beim Erstellen eines Quiz werden gespeichert:
    </p>
    <ul class="mt-1 text-sm" style="padding-left:1.5rem">
      <li>Der Quiz-Titel und die Fragen (eingegeben durch die Lehrkraft)</li>
      <li>Aggregierte Antwortstatistiken (Anzahl richtiger/falscher Antworten pro Frage)</li>
      <li>Der Erstellungszeitpunkt des Quiz</li>
    </ul>
    <p class="mt-1">
      Es werden <strong>keinerlei Daten gespeichert</strong>, die Rückschlüsse auf einzelne
      Lernende ermöglichen (keine IP-Adressen, keine Namen, keine Zeitstempel pro Abgabe).
    </p>

    <h2 class="mt-2">3. Cookies</h2>
    <p class="mt-1">
      Quoodle setzt zwei technisch notwendige Cookies:
    </p>
    <ul class="mt-1 text-sm" style="padding-left:1.5rem">
      <li>
        <strong>lang</strong>: Speichert die bevorzugte Sprache (DE/EN).
        Enthält keine personenbezogenen Daten.
      </li>
      <li>
        <strong>theme</strong>: Speichert das bevorzugte Farbschema (Hell/Dunkel).
        Enthält keine personenbezogenen Daten.
      </li>
    </ul>
    <p class="mt-1">
      Diese Cookies werden ausschließlich auf dem Gerät des Nutzers gespeichert
      und nicht an Dritte weitergegeben. Eine Einwilligung ist nach Art. 5 Abs. 3 ePrivacy-RL
      für technisch notwendige Cookies nicht erforderlich.
    </p>

    <h2 class="mt-2">4. Server-Logfiles</h2>
    <p class="mt-1">
      Beim Aufruf dieser Website speichert der Webserver automatisch Informationen
      in Server-Logfiles (IP-Adresse, Datum/Uhrzeit, aufgerufene Seite, HTTP-Statuscode).
      Diese Daten dienen ausschließlich der technischen Fehleranalyse und werden
      nicht mit anderen Daten zusammengeführt.
    </p>

    <h2 class="mt-2">5. Rechtsgrundlage</h2>
    <p class="mt-1">
      Die Verarbeitung der Server-Logfiles erfolgt auf Grundlage von
      Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der Sicherheit
      und Funktionsfähigkeit des Dienstes).
    </p>

    <h2 class="mt-2">6. Ihre Rechte</h2>
    <p class="mt-1">
      Da keine personenbezogenen Daten der Lernenden gespeichert werden, können
      Auskunfts-, Berichtigungs- und Löschungsrechte nicht auf einzelne Personen
      bezogen werden. Quiz-Inhalte können durch die Lehrkraft (Inhaberin des
      Lehrkraft-Links) gelöscht werden.
    </p>

    <h2 class="mt-2">7. Kontakt</h2>
    <p class="mt-1">
      Bei Fragen zum Datenschutz wende dich bitte an die im Impressum genannte
      verantwortliche Person.
    </p>

  </div>

<?php
render_footer();
