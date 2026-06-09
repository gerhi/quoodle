<?php
/**
 * Minimal XLSX reader.
 * Uses only PHP built-ins (ZipArchive, SimpleXML) — no external dependencies.
 * Reads the first worksheet and returns rows as a 2D array of strings.
 */

function xlsx_read_first_sheet(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Die PHP-Erweiterung ZipArchive wird zum Lesen von .xlsx-Dateien benötigt.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Die hochgeladene .xlsx-Datei konnte nicht geöffnet werden. Ist es eine gültige Excel-Datei?');
    }

    // 1. Build shared strings table (if any)
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = @simplexml_load_string(xlsx_strip_default_ns($ssXml));
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                $sharedStrings[] = xlsx_extract_si_text($si);
            }
        }
    }

    // 2. Find the first worksheet (sheet1.xml is conventional, but verify via workbook.xml.rels)
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        // fall back: first file matching the pattern
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetXml = $zip->getFromName($name);
                break;
            }
        }
    }
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('In der .xlsx-Datei wurde kein Tabellenblatt gefunden.');
    }

    $sheet = @simplexml_load_string(xlsx_strip_default_ns($sheetXml));
    if ($sheet === false) {
        throw new RuntimeException('Das Tabellenblatt-XML konnte nicht ausgewertet werden.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $maxCol = -1;
        foreach ($row->c as $c) {
            $ref  = (string)$c['r'];               // e.g. "A1"
            $col  = xlsx_col_index_from_ref($ref); // zero-based
            $type = (string)$c['t'];
            $value = '';
            if ($type === 's') {
                $idx = (int)$c->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = xlsx_extract_si_text($c->is);
            } elseif ($type === 'b') {
                $value = ((string)$c->v) === '1' ? 'TRUE' : 'FALSE';
            } else {
                // numeric, date, formula result, etc. — keep as string
                $value = (string)$c->v;
            }
            $rowData[$col] = $value;
            if ($col > $maxCol) {
                $maxCol = $col;
            }
        }
        if ($maxCol < 0) {
            // empty row
            $rows[] = [];
            continue;
        }
        $normalized = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $normalized[] = isset($rowData[$i]) ? trim($rowData[$i]) : '';
        }
        $rows[] = $normalized;
    }

    return $rows;
}

function xlsx_col_index_from_ref(string $ref): int
{
    if (!preg_match('/^([A-Z]+)/', $ref, $m)) {
        return 0;
    }
    $letters = $m[1];
    $col = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $col = $col * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $col - 1;
}

function xlsx_extract_si_text(SimpleXMLElement $si): string
{
    // <si><t>...</t></si>  OR  <si><r><t>...</t></r><r><t>...</t></r></si>
    if (isset($si->t) && count($si->t) > 0) {
        $text = '';
        foreach ($si->t as $t) {
            $text .= (string)$t;
        }
        if ($text !== '') {
            return $text;
        }
    }
    $text = '';
    if (isset($si->r)) {
        foreach ($si->r as $r) {
            if (isset($r->t)) {
                $text .= (string)$r->t;
            }
        }
    }
    return $text;
}

/**
 * Strip the default xmlns attribute from the root element so that
 * SimpleXML's ->name accessor works without explicit namespace registration.
 * Other (prefixed) namespaces are left untouched.
 */
function xlsx_strip_default_ns(string $xml): string
{
    return preg_replace('/\sxmlns="[^"]*"/', '', $xml, 1);
}
