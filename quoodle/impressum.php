<?php
/*
 * ============================================================================
 *  IMPRESSUM-VORLAGE
 *  Bitte VOR der Veröffentlichung anpassen.
 *  Pflichtangaben in Deutschland gemäß § 5 Telemediengesetz (TMG) und
 *  § 18 Medienstaatsvertrag (MStV).
 *  Was Du brauchst, hängt von Deiner Rechtsform ab (Privatperson, Verein,
 *  Schule, Behörde, GmbH, …). Im Zweifel bei der Schule, dem Schulträger
 *  oder einer juristischen Beratung rückfragen.
 *  Diese Vorlage ist KEINE Rechtsberatung.
 * ============================================================================
 */

require_once __DIR__ . '/lib/lang.php';
$page_title = t('impressum');
require __DIR__ . '/lib/header.php';
?>

<div class="card">
  <h2>Impressum</h2>
  <p class="subtitle">Angaben gemäß § 5 TMG</p>

  <h3>Anbieter / Diensteanbieter</h3>
  <p>
    <strong>[Name oder Bezeichnung der Einrichtung / Schule / Person]</strong><br>
    [Straße und Hausnummer]<br>
    [PLZ Ort]<br>
    [Land]
  </p>

  <h3>Vertretungsberechtigte Person</h3>
  <p>
    [Vor- und Nachname der vertretungsberechtigten Person]<br>
    [Funktion, z.&nbsp;B. Schulleitung, Vorstand, Geschäftsführung]
  </p>

  <h3>Kontakt</h3>
  <p>
    Telefon: [Telefonnummer]<br>
    E-Mail: <a href="mailto:[E-Mail-Adresse]">[E-Mail-Adresse]</a>
  </p>

  <h3>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h3>
  <p>
    [Vor- und Nachname]<br>
    [Anschrift, falls abweichend]
  </p>

  <!--
    Falls zutreffend, hier ergänzen:
    - Umsatzsteuer-Identifikationsnummer nach § 27 a UStG
    - Berufsbezeichnung und zuständige Kammer
    - Aufsichtsbehörde
    - Handelsregister-/Vereinsregistereintrag
  -->

  <h3>Haftungsausschluss</h3>
  <p>
    Die Inhalte dieses Angebots wurden mit größtmöglicher Sorgfalt erstellt.
    Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte kann jedoch
    keine Gewähr übernommen werden. Als Diensteanbieter bin ich gemäß § 7 Abs. 1
    TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen
    verantwortlich. Nach §§ 8 bis 10 TMG bin ich als Diensteanbieter jedoch
    nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu
    überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige
    Tätigkeit hinweisen.
  </p>
  <p>
    Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen
    nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine
    diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer
    konkreten Rechtsverletzung möglich. Bei Bekanntwerden entsprechender
    Rechtsverletzungen werden diese Inhalte umgehend entfernt.
  </p>

  <h3>Haftung für Links</h3>
  <p>
    Mein Angebot enthält ggf. Links zu externen Websites Dritter, auf deren
    Inhalte ich keinen Einfluss habe. Deshalb kann ich für diese fremden
    Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten
    Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten
    verantwortlich.
  </p>

  <h3>Urheberrecht</h3>
  <p>
    Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen
    Seiten unterliegen dem deutschen Urheberrecht. Beiträge Dritter sind als
    solche gekennzeichnet. Vervielfältigung, Bearbeitung, Verbreitung und
    jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen
    der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.
  </p>
</div>

<?php require __DIR__ . '/lib/footer.php'; ?>
