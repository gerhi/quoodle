<?php
/*
 * ============================================================================
 *  DATENSCHUTZERKLÄRUNG-VORLAGE
 *  Bitte VOR der Veröffentlichung anpassen.
 *  Diese Vorlage ist auf das Verhalten dieser konkreten Quiz-App zugeschnitten,
 *  ersetzt aber KEINE Rechtsberatung. Im schulischen Kontext bitte zusätzlich
 *  die Vorgaben Deines Schulträgers, der Landesdatenschutzbehörde sowie
 *  ggf. einen Datenschutzbeauftragten konsultieren.
 *
 *  Bitte besonders prüfen:
 *  - Server-Logging: Welche Daten loggt Dein Webserver wie lange?
 *    (Stichwort IP-Adressen — i. d. R. personenbezogen.)
 *  - Hosting-Anbieter (Auftragsverarbeitung gemäß Art. 28 DSGVO).
 * ============================================================================
 */

require_once __DIR__ . '/lib/lang.php';
$page_title = t('privacy');
require __DIR__ . '/lib/header.php';
?>

<div class="card">
  <h2>Datenschutzerklärung</h2>
  <p class="subtitle">Informationen zur Verarbeitung personenbezogener Daten gemäß Art. 13 DSGVO</p>

  <h3>1. Verantwortliche Stelle</h3>
  <p>
    Verantwortlich für die Datenverarbeitung auf dieser Website ist:<br>
    <strong>[Name oder Bezeichnung der Einrichtung]</strong><br>
    [Anschrift]<br>
    E-Mail: <a href="mailto:[E-Mail]">[E-Mail]</a><br>
    Telefon: [Telefonnummer]
  </p>
  <p>
    Datenschutzbeauftragte/r (sofern benannt):<br>
    [Name]<br>
    [Kontakt]
  </p>

  <h3>2. Welche Daten werden in Quoodle verarbeitet?</h3>

  <h4>a) Quiz-Inhalte (von der Lehrkraft hochgeladen)</h4>
  <p>
    Beim Erstellen eines Quiz lädt die Lehrkraft eine Excel- oder CSV-Datei
    hoch. Diese Inhalte (Fragen, Antwortmöglichkeiten, Erklärungen, Titel)
    werden auf dem Webserver in einer JSON-Datei gespeichert.
    <strong>Personenbezogene Daten sollten in den Quiz-Inhalten nicht enthalten sein.</strong>
    Verantwortlich für den Inhalt der hochgeladenen Dateien ist die jeweilige
    Lehrkraft.
  </p>
  <p>
    Rechtsgrundlage: Art. 6 Abs. 1 lit. e DSGVO (Wahrnehmung einer Aufgabe im
    öffentlichen Interesse, hier: Bildungsauftrag) bzw. Art. 6 Abs. 1 lit. f
    DSGVO (berechtigtes Interesse an der Bereitstellung des Dienstes).
  </p>

  <h4>b) Antworten der Schüler:innen</h4>
  <p>
    Wenn ein:e Schüler:in das Quiz beantwortet und absendet, verarbeitet die
    Anwendung die abgegebenen Antworten ausschließlich zur Berechnung des
    Ergebnisses und zur Aktualisierung anonymer Zähler.
    <strong>Es werden weder Namen noch IP-Adressen, Browser-Kennungen,
    Cookies oder andere Identifikatoren mit den Antworten verknüpft oder
    gespeichert.</strong>
    Auf dem Server werden lediglich folgende anonyme Zähler je Quiz und Frage
    geführt:
  </p>
  <ul>
    <li>Gesamtzahl der Versuche</li>
    <li>Anzahl richtiger Antworten je Frage</li>
    <li>Anzahl, wie häufig welche Antwortmöglichkeit insgesamt gewählt wurde</li>
  </ul>
  <p>
    Aus diesen Zählern lässt sich nicht ableiten, welche einzelne Person welche
    Antwort gegeben hat. Eine Re-Identifizierung einzelner Schüler:innen ist
    durch die Anwendung nicht vorgesehen und nicht möglich.
  </p>

  <h4>c) Server-Logfiles</h4>
  <p>
    Beim Aufruf der Website werden vom Webserver automatisch technische
    Informationen verarbeitet, die der Browser übermittelt. Dies umfasst
    typischerweise:
  </p>
  <ul>
    <li>IP-Adresse des aufrufenden Endgeräts</li>
    <li>Datum und Uhrzeit des Zugriffs</li>
    <li>Aufgerufene URL</li>
    <li>HTTP-Statuscode und übertragene Datenmenge</li>
    <li>Referrer-URL</li>
    <li>User-Agent (Browser- und Betriebssystem-Kennung)</li>
  </ul>
  <p>
    Die Verarbeitung erfolgt zur Sicherstellung des stabilen und sicheren
    Betriebs der Website. Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO.
    Die Speicherdauer der Logfiles richtet sich nach den Einstellungen des
    Hosting-Anbieters und beträgt üblicherweise <strong>[Speicherdauer angeben,
    z.&nbsp;B. 7 Tage]</strong>. Anschließend werden die Daten gelöscht oder
    anonymisiert.
  </p>

  <h4>d) Cookies / lokale Speicherung</h4>
  <p>
    Diese Anwendung setzt ausschließlich zwei <strong>optionale Präferenz-Cookies</strong>,
    die keine personenbezogenen Daten enthalten:
  </p>
  <ul>
    <li><code>lang</code> — speichert die gewählte Sprache (Wert: <code>de</code> oder <code>en</code>), Gültigkeit: 1 Jahr.</li>
    <li><code>theme</code> — speichert die Darstellung (Wert: <code>light</code> oder <code>dark</code>), Gültigkeit: 1 Jahr.</li>
  </ul>
  <p>
    Beide Cookies dienen ausschließlich dazu, die Benutzeroberfläche bei
    erneutem Besuch in der zuvor gewählten Sprache und Darstellung anzuzeigen.
    Es werden <strong>keine Tracking-Cookies</strong>, keine Web-Analytics,
    keine Pixel und keine vergleichbaren Verfahren eingesetzt.
  </p>

  <h3>3. Eingebundene Drittdienste</h3>
  <p>
    Diese Anwendung bindet <strong>keine externen Dienste</strong> ein.
    QR-Codes werden direkt auf dem Server erzeugt und als SVG in die Seite
    eingebettet. Es findet keine Übermittlung von Daten (insbesondere keine
    URLs oder IP-Adressen) an Drittanbieter statt — weder beim Aufruf des Quiz
    durch Schüler:innen, noch beim Aufruf der Freigabe- oder Auswertungsseite
    durch die Lehrkraft.
  </p>

  <h3>4. Empfänger der Daten / Übermittlung in Drittländer</h3>
  <p>
    Eine Übermittlung der Quiz-Inhalte oder der Schüler:innen-Antworten an
    Dritte findet nicht statt. Die Daten verbleiben auf dem von
    [Verantwortliche Stelle / Hosting-Anbieter angeben] betriebenen Server in
    [Land].
  </p>

  <h3>5. Speicherdauer</h3>
  <p>
    Quiz-Inhalte und Statistik-Zähler bleiben gespeichert, bis sie von der
    Lehrkraft bzw. von der verantwortlichen Stelle gelöscht werden. Eine
    automatische Löschung nach einer bestimmten Frist findet derzeit nicht
    statt. Auf Anfrage werden Quizze unverzüglich entfernt.
  </p>

  <h3>6. Deine Rechte</h3>
  <p>
    Sofern Daten zu Deiner Person verarbeitet werden, stehen Dir die folgenden
    Rechte zu:
  </p>
  <ul>
    <li>Recht auf Auskunft (Art. 15 DSGVO)</li>
    <li>Recht auf Berichtigung (Art. 16 DSGVO)</li>
    <li>Recht auf Löschung (Art. 17 DSGVO)</li>
    <li>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
    <li>Recht auf Datenübertragbarkeit (Art. 20 DSGVO)</li>
    <li>Widerspruchsrecht (Art. 21 DSGVO)</li>
    <li>Recht auf Beschwerde bei einer Aufsichtsbehörde (Art. 77 DSGVO)</li>
  </ul>
  <p>
    Zur Ausübung dieser Rechte wende Dich bitte an die unter Punkt&nbsp;1
    genannte verantwortliche Stelle.
  </p>

  <h3>7. Zuständige Aufsichtsbehörde</h3>
  <p>
    [Name und Anschrift der zuständigen Landesdatenschutzbehörde — abhängig
    vom Bundesland des Sitzes der verantwortlichen Stelle. Beispiel für Hessen:
    Der Hessische Beauftragte für Datenschutz und Informationsfreiheit,
    Postfach 3163, 65021 Wiesbaden,
    <a href="https://datenschutz.hessen.de" target="_blank" rel="noopener noreferrer">datenschutz.hessen.de</a>.]
  </p>

  <h3>8. Änderungen dieser Datenschutzerklärung</h3>
  <p>
    Diese Datenschutzerklärung kann angepasst werden, wenn sich die Rechtslage
    oder die Funktionsweise der Anwendung ändert. Die jeweils aktuelle Fassung
    ist auf dieser Seite abrufbar.
  </p>

  <p class="hint" style="margin-top:24px;">Stand: [Datum eintragen]</p>
</div>

<?php require __DIR__ . '/lib/footer.php'; ?>
