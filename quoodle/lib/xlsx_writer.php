<?php
/**
 * Schlanker XLSX-Writer in reinem PHP.
 *
 * - Schreibt eine einzelne Arbeitsmappe mit einem Tabellenblatt.
 * - Nutzt inline strings (keine shared strings table nötig).
 * - Unterstützt numerische Werte und Strings; optional fette Kopfzeile.
 *
 * Abhängigkeiten: nur `ZipArchive` (PHP-Standard).
 * Ausgabe: direkt an den Browser als Download.
 *
 * Verwendung:
 *   $writer = new XlsxWriter();
 *   $writer->addSheet('Auswertung', [
 *       ['Frage', 'Richtig', 'Gesamt', 'Quote'],   // Header
 *       ['Frage 1', 42, 56, 0.75],
 *       ['Frage 2', 31, 56, 0.55],
 *   ]);
 *   $writer->output('auswertung.xlsx');
 */
final class XlsxWriter
{
    /** @var array<array{name: string, rows: array<array<int, string|int|float|null>>}> */
    private array $sheets = [];

    /** @var int Anzahl Zeilen, die fett gesetzt werden sollen (ab Zeile 1). Standard 1 = Header fett. */
    private int $boldHeaderRows = 1;

    public function addSheet(string $name, array $rows): void
    {
        // Sheet-Namen: max 31 Zeichen, keine : \ / ? * [ ]
        $name = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/', '_', $name);
        $name = substr($name, 0, 31);
        if ($name === '') $name = 'Sheet';
        $this->sheets[] = ['name' => $name, 'rows' => $rows];
    }

    public function setBoldHeaderRows(int $n): void
    {
        $this->boldHeaderRows = max(0, $n);
    }

    /**
     * Sendet die XLSX-Datei als Download an den Browser.
     * Ruft exit auf.
     */
    public function output(string $filename): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $this->writeToFile($tmp);

        $size = filesize($tmp);
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . $size);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function writeToFile(string $path): void
    {
        if (empty($this->sheets)) {
            throw new RuntimeException('Keine Tabellenblätter zum Schreiben.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Konnte XLSX-Datei nicht zum Schreiben öffnen.');
        }

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        // _rels/.rels
        $zip->addFromString('_rels/.rels', $this->topRelsXml());
        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        // xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        // Sheets
        foreach ($this->sheets as $idx => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($idx + 1) . '.xml', $this->sheetXml($sheet['rows']));
        }

        $zip->close();
    }

    // ---- XML-Generatoren ----

    private function contentTypesXml(): string
    {
        $parts = [];
        foreach ($this->sheets as $idx => $_s) {
            $n = $idx + 1;
            $parts[] = '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
             . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
             . '<Default Extension="xml" ContentType="application/xml"/>'
             . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
             . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
             . implode('', $parts)
             . '</Types>';
    }

    private function topRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
             . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheetTags = '';
        foreach ($this->sheets as $idx => $sheet) {
            $n = $idx + 1;
            $name = htmlspecialchars($sheet['name'], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $sheetTags .= '<sheet name="' . $name . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
             . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
             . '<sheets>' . $sheetTags . '</sheets>'
             . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $rels = '<Relationship Id="rStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        foreach ($this->sheets as $idx => $_s) {
            $n = $idx + 1;
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . $rels
             . '</Relationships>';
    }

    /**
     * Styles: Index 0 = Standard, Index 1 = Fett (für Header).
     */
    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<fonts count="2">'
             .   '<font><sz val="11"/><name val="Calibri"/></font>'
             .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
             . '</fonts>'
             . '<fills count="2">'
             .   '<fill><patternFill patternType="none"/></fill>'
             .   '<fill><patternFill patternType="gray125"/></fill>'
             . '</fills>'
             . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="2">'
             .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
             .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
             . '</cellXfs>'
             . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
             . '</styleSheet>';
    }

    private function sheetXml(array $rows): string
    {
        $rowsXml = '';
        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $cellsXml = '';
            $cIdx = 0;
            foreach ($row as $value) {
                $cellRef = $this->colLetter($cIdx) . $rowNum;
                $cellsXml .= $this->cellXml($cellRef, $value, $rIdx < $this->boldHeaderRows);
                $cIdx++;
            }
            $rowsXml .= '<row r="' . $rowNum . '">' . $cellsXml . '</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<sheetData>' . $rowsXml . '</sheetData>'
             . '</worksheet>';
    }

    private function cellXml(string $ref, $value, bool $bold): string
    {
        $styleAttr = $bold ? ' s="1"' : '';
        if ($value === null || $value === '') {
            return '<c r="' . $ref . '"' . $styleAttr . '/>';
        }
        if (is_int($value) || (is_float($value) && !is_nan($value) && !is_infinite($value))) {
            return '<c r="' . $ref . '"' . $styleAttr . '><v>' . $value . '</v></c>';
        }
        // Numerische Strings sicher als Zahl erkennen
        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return '<c r="' . $ref . '"' . $styleAttr . '><v>' . $value . '</v></c>';
        }
        // String als inline-String (keine shared strings table)
        $escaped = htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        // Excel erwartet xml:space="preserve" bei führenden/nachstehenden Leerzeichen
        $preserve = (trim($escaped) !== $escaped) ? ' xml:space="preserve"' : '';
        return '<c r="' . $ref . '"' . $styleAttr . ' t="inlineStr"><is><t' . $preserve . '>' . $escaped . '</t></is></c>';
    }

    private function colLetter(int $idx): string
    {
        // 0 -> A, 25 -> Z, 26 -> AA, ...
        $letters = '';
        $n = $idx;
        while (true) {
            $letters = chr(65 + ($n % 26)) . $letters;
            $n = intdiv($n, 26) - 1;
            if ($n < 0) break;
        }
        return $letters;
    }
}
