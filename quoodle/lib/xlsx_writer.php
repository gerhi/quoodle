<?php
declare(strict_types=1);

// Ensure format_duration() is available — pages calling these functions
// usually load helpers.php first, but this guarantees it in case they don't.
require_once __DIR__ . '/helpers.php';

/**
 * XlsxWriter – Minimal self-contained XLSX (Office Open XML) generator.
 *
 * Creates a multi-sheet workbook using PHP's ZipArchive and raw XML.
 * No external libraries required.
 *
 * Usage:
 *   $wb = new XlsxWriter();
 *   $wb->add_sheet('Sheet1', [['A','B'], [1, 2]]);
 *   $bytes = $wb->build();
 *   header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
 *   echo $bytes;
 */
final class XlsxWriter {

    /** @var array{name: string, rows: array}[] */
    private array $sheets = [];

    /** Add a sheet. $rows is a 2D array of scalars. */
    public function add_sheet(string $name, array $rows): void {
        $this->sheets[] = ['name' => $name, 'rows' => $rows];
    }

    /**
     * Build the XLSX binary and return it as a string.
     * Uses a temporary file internally (ZipArchive requires a file path).
     */
    public function build(): string {
        // Pick a temp directory: prefer sys_get_temp_dir(), fall back to the
        // data/ folder (which is writable by the app) if the system temp is not.
        $temp_dir = sys_get_temp_dir();
        if (!is_dir($temp_dir) || !is_writable($temp_dir)) {
            $temp_dir = __DIR__ . '/../data';
            if (!is_dir($temp_dir)) @mkdir($temp_dir, 0750, true);
        }

        $tmp = @tempnam($temp_dir, 'qd_xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException(
                'Konnte keine temporäre Datei in "' . $temp_dir . '" anlegen. ' .
                'Bitte Schreibrechte prüfen.'
            );
        }

        $zip = new ZipArchive();
        // CREATE | OVERWRITE handles both new and existing target files safely.
        $open_result = $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($open_result !== true) {
            @unlink($tmp);
            throw new \RuntimeException('ZipArchive::open fehlgeschlagen (Code ' . (int)$open_result . ').');
        }

        $zip->addFromString('[Content_Types].xml',  $this->content_types());
        $zip->addFromString('_rels/.rels',           $this->root_rels());
        $zip->addFromString('xl/workbook.xml',        $this->workbook_xml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbook_rels());
        $zip->addFromString('xl/styles.xml',          $this->styles_xml());
        $zip->addFromString('xl/sharedStrings.xml',   $this->shared_strings_xml());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($i + 1) . '.xml',
                $this->sheet_xml($sheet['rows'])
            );
        }

        if (!$zip->close()) {
            @unlink($tmp);
            throw new \RuntimeException('ZipArchive::close fehlgeschlagen.');
        }

        $bytes = @file_get_contents($tmp);
        @unlink($tmp);

        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Erzeugte XLSX-Datei ist leer oder konnte nicht gelesen werden.');
        }
        return $bytes;
    }

    // ── [Content_Types].xml ──────────────────────────────────────────────
    private function content_types(): string {
        $sheets = '';
        foreach ($this->sheets as $i => $_) {
            $n = $i + 1;
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml"'
                     . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
             . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
             . '<Default Extension="xml"  ContentType="application/xml"/>'
             . '<Override PartName="/xl/workbook.xml"'
             . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
             . '<Override PartName="/xl/styles.xml"'
             . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
             . '<Override PartName="/xl/sharedStrings.xml"'
             . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
             . $sheets
             . '</Types>';
    }

    // ── _rels/.rels ──────────────────────────────────────────────────────
    private function root_rels(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1"'
             . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
             . ' Target="xl/workbook.xml"/>'
             . '</Relationships>';
    }

    // ── xl/workbook.xml ──────────────────────────────────────────────────
    private function workbook_xml(): string {
        $sheets_xml = '';
        foreach ($this->sheets as $i => $s) {
            $n = $i + 1;
            $name = htmlspecialchars($s['name'], ENT_XML1, 'UTF-8');
            $sheets_xml .= '<sheet name="' . $name . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
             . '  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
             . '<sheets>' . $sheets_xml . '</sheets>'
             . '</workbook>';
    }

    // ── xl/_rels/workbook.xml.rels ───────────────────────────────────────
    private function workbook_rels(): string {
        $rels = '';
        foreach ($this->sheets as $i => $_) {
            $n = $i + 1;
            $rels .= '<Relationship Id="rId' . $n . '"'
                   . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                   . ' Target="worksheets/sheet' . $n . '.xml"/>';
        }
        $last = count($this->sheets);
        $rels .= '<Relationship Id="rId' . ($last + 1) . '"'
               . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
               . ' Target="styles.xml"/>';
        $rels .= '<Relationship Id="rId' . ($last + 2) . '"'
               . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"'
               . ' Target="sharedStrings.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . $rels
             . '</Relationships>';
    }

    // ── xl/styles.xml — minimal: 2 styles (normal + bold header) ─────────
    private function styles_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<fonts count="2">'
             . '<font><sz val="11"/><name val="Calibri"/></font>'
             . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
             . '</fonts>'
             . '<fills count="2">'
             . '<fill><patternFill patternType="none"/></fill>'
             . '<fill><patternFill patternType="gray125"/></fill>'
             . '</fills>'
             . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
             . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
             . '<cellXfs count="2">'
             . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'       // style 0: normal
             . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'       // style 1: bold
             . '</cellXfs>'
             . '</styleSheet>';
    }

    // ── xl/sharedStrings.xml ─────────────────────────────────────────────
    // We store all string values here for proper Unicode support.
    private function shared_strings_xml(): string {
        $strings = [];
        foreach ($this->sheets as $sheet) {
            foreach ($sheet['rows'] as $row) {
                foreach ($row as $cell) {
                    if (is_string($cell) && !isset($strings[$cell])) {
                        $strings[$cell] = count($strings);
                    }
                }
            }
        }
        $this->_ss = $strings; // cache for sheet_xml usage

        $si = '';
        foreach (array_keys($strings) as $str) {
            $si .= '<si><t xml:space="preserve">' . htmlspecialchars($str, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        $cnt = count($strings);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
             . ' count="' . $cnt . '" uniqueCount="' . $cnt . '">'
             . $si
             . '</sst>';
    }

    private array $_ss = []; // shared strings index cache

    // ── xl/worksheets/sheetN.xml ─────────────────────────────────────────
    private function sheet_xml(array $rows): string {
        $rows_xml = '';
        foreach ($rows as $ri => $row) {
            $row_num  = $ri + 1;
            $cells_xml = '';
            foreach ($row as $ci => $val) {
                $col_name = $this->col_name($ci);
                $ref      = $col_name . $row_num;
                // First row = header → bold (style 1)
                $style = ($ri === 0) ? ' s="1"' : '';

                if (is_int($val) || is_float($val)) {
                    $cells_xml .= '<c r="' . $ref . '"' . $style . '>'
                                . '<v>' . $val . '</v>'
                                . '</c>';
                } else {
                    // String → shared strings reference
                    $s = (string)$val;
                    $idx = $this->_ss[$s] ?? null;
                    if ($idx === null) {
                        // Fallback: inline string
                        $cells_xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '>'
                                    . '<is><t xml:space="preserve">'
                                    . htmlspecialchars($s, ENT_XML1, 'UTF-8')
                                    . '</t></is></c>';
                    } else {
                        $cells_xml .= '<c r="' . $ref . '" t="s"' . $style . '>'
                                    . '<v>' . $idx . '</v>'
                                    . '</c>';
                    }
                }
            }
            $rows_xml .= '<row r="' . $row_num . '">' . $cells_xml . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<sheetData>' . $rows_xml . '</sheetData>'
             . '</worksheet>';
    }

    // ── Column index → Excel letter (0→A, 25→Z, 26→AA, …) ──────────────
    private function col_name(int $idx): string {
        $name = '';
        $idx++;
        while ($idx > 0) {
            $idx--;
            $name = chr(65 + ($idx % 26)) . $name;
            $idx  = intdiv($idx, 26);
        }
        return $name;
    }
}

// ── Generate the stats export workbook ───────────────────────────────────────

/**
 * Build a stats XLSX export for a quiz.
 *
 * @param array $quiz   Full quiz row from db_get_quiz()
 * @param array $labels Translated labels (from t() calls)
 * @return string Raw XLSX binary
 */
function build_stats_xlsx(array $quiz, array $labels): string {
    $wb   = new XlsxWriter();
    $q    = $quiz['questions'];
    $s    = $quiz['stats'];
    $n_q  = count($q);
    $att  = $s['attempts'];

    // ── Sheet 1: Summary ──
    $avg_pct = 0;
    if ($att > 0 && $n_q > 0) {
        $total_correct = 0;
        foreach ($s['questions'] as $qs) {
            $total_correct += $qs['total_count'] > 0
                ? $qs['correct_count'] / $qs['total_count']
                : 0;
        }
        $avg_pct = round(($total_correct / $n_q) * 100, 1);
    }

    // New v1.1 aggregates
    $total_time   = (int)($s['total_time_seconds']       ?? 0);
    $total_tabs   = (int)($s['total_tab_switches']       ?? 0);
    $tab_attempts = (int)($s['attempts_with_tab_switch'] ?? 0);
    $avg_time_sec  = ($att > 0) ? (int)round($total_time / $att) : 0;
    $pct_with_tabs = ($att > 0) ? round($tab_attempts / $att * 100, 1) : 0;

    $summary = [
        [$labels['col.field'],        $labels['col.value']],
        [$labels['row.title'],         $quiz['title']],
        [$labels['row.attempts'],      $att],
        [$labels['row.num_q'],         $n_q],
        [$labels['row.avg_pct'],       $avg_pct],
        [$labels['row.avg_time'],      format_duration($avg_time_sec)],
        [$labels['row.tab_rate'],      $pct_with_tabs . '%'],
        [$labels['row.created'],       $quiz['created_at']],
    ];
    $wb->add_sheet($labels['sheet.summary'], $summary);

    // ── Sheet 2: Per-question stats ──
    $q_rows = [[
        $labels['col.nr'],
        $labels['col.question'],
        $labels['col.correct_ans'],
        $labels['col.attempts'],
        $labels['col.correct'],
        $labels['col.pct'],
    ]];
    foreach ($q as $i => $question) {
        $qs  = $s['questions'][$i] ?? ['total_count' => 0, 'correct_count' => 0];
        $tot = $qs['total_count'];
        $cor = $qs['correct_count'];
        $pct = $tot > 0 ? round($cor / $tot * 100, 1) : 0;
        $q_rows[] = [
            $i + 1,
            $question['text'],
            $question['correct'],
            $tot,
            $cor,
            $pct,
        ];
    }
    $wb->add_sheet($labels['sheet.questions'], $q_rows);

    // ── Sheet 3: Per-choice breakdown ──
    $c_rows = [[
        $labels['col.q_nr'],
        $labels['col.question'],
        $labels['col.answer'],
        $labels['col.is_correct'],
        $labels['col.count'],
        $labels['col.share_pct'],
    ]];
    foreach ($q as $i => $question) {
        $qs     = $s['questions'][$i] ?? ['total_count' => 0, 'choice_counts' => []];
        $tot    = $qs['total_count'];
        $counts = $qs['choice_counts'];

        $all_choices = array_merge([$question['correct']], $question['distractors']);
        foreach ($all_choices as $choice) {
            $cnt  = $counts[$choice] ?? 0;
            $pct  = $tot > 0 ? round($cnt / $tot * 100, 1) : 0;
            $c_rows[] = [
                $i + 1,
                $question['text'],
                $choice,
                $choice === $question['correct'] ? $labels['yes'] : $labels['no'],
                $cnt,
                $pct,
            ];
        }
    }
    $wb->add_sheet($labels['sheet.choices'], $c_rows);

    return $wb->build();
}

/**
 * Build a stats CSV export (single flat table: question × choice).
 *
 * @param array $quiz   Full quiz row
 * @param array $labels Translated labels
 * @return string UTF-8 CSV with BOM
 */
function build_stats_csv(array $quiz, array $labels): string {
    $q   = $quiz['questions'];
    $s   = $quiz['stats'];
    $att = (int)($s['attempts'] ?? 0);
    $n_q = count($q);

    // v1.1 aggregates
    $total_time   = (int)($s['total_time_seconds']       ?? 0);
    $tab_attempts = (int)($s['attempts_with_tab_switch'] ?? 0);
    $avg_time_sec = ($att > 0) ? (int)round($total_time / $att) : 0;
    $pct_with_tabs = ($att > 0) ? round($tab_attempts / $att * 100, 1) : 0;

    // Average correct %
    $avg_pct = 0;
    if ($att > 0 && $n_q > 0) {
        $sum = 0;
        foreach ($s['questions'] as $qs) {
            $sum += ($qs['total_count'] ?? 0) > 0
                ? ($qs['correct_count'] ?? 0) / $qs['total_count']
                : 0;
        }
        $avg_pct = round(($sum / $n_q) * 100, 1);
    }

    $out = '';
    // CSV row helper — semicolon delimiter (European locale)
    $row = function (array $fields) use (&$out) {
        $escaped = array_map(function ($v) {
            $v = (string)$v;
            if (str_contains($v, '"') || str_contains($v, ';') || str_contains($v, "\n") || str_contains($v, "\r")) {
                $v = '"' . str_replace('"', '""', $v) . '"';
            }
            return $v;
        }, $fields);
        $out .= implode(';', $escaped) . "\r\n";
    };

    // ── Section 1: Summary ──
    $out .= '# ' . $labels['sheet.summary'] . "\r\n";
    $row([$labels['col.field'],     $labels['col.value']]);
    $row([$labels['row.title'],      $quiz['title']]);
    $row([$labels['row.attempts'],   $att]);
    $row([$labels['row.num_q'],      $n_q]);
    $row([$labels['row.avg_pct'],    $avg_pct]);
    $row([$labels['row.avg_time'],   format_duration($avg_time_sec)]);
    $row([$labels['row.tab_rate'],   $pct_with_tabs . '%']);
    $row([$labels['row.created'],    $quiz['created_at']]);
    $out .= "\r\n";

    // ── Section 2: Per-question stats ──
    $out .= '# ' . $labels['sheet.questions'] . "\r\n";
    $row([
        $labels['col.nr'],
        $labels['col.question'],
        $labels['col.correct_ans'],
        $labels['col.attempts'],
        $labels['col.correct'],
        $labels['col.pct'],
    ]);
    foreach ($q as $i => $question) {
        $qs  = $s['questions'][$i] ?? ['total_count' => 0, 'correct_count' => 0];
        $tot = (int)($qs['total_count']   ?? 0);
        $cor = (int)($qs['correct_count'] ?? 0);
        $pct = $tot > 0 ? round($cor / $tot * 100, 1) : 0;
        $row([$i + 1, $question['text'], $question['correct'], $tot, $cor, $pct]);
    }
    $out .= "\r\n";

    // ── Section 3: Answer options ──
    $out .= '# ' . $labels['sheet.choices'] . "\r\n";
    $row([
        $labels['col.q_nr'],
        $labels['col.question'],
        $labels['col.answer'],
        $labels['col.is_correct'],
        $labels['col.count'],
        $labels['col.share_pct'],
    ]);
    foreach ($q as $i => $question) {
        $qs     = $s['questions'][$i] ?? ['total_count' => 0, 'choice_counts' => []];
        $tot    = (int)($qs['total_count'] ?? 0);
        $counts = $qs['choice_counts'] ?? [];
        $all_choices = array_merge([$question['correct']], $question['distractors']);
        foreach ($all_choices as $choice) {
            $cnt = (int)($counts[$choice] ?? 0);
            $pct = $tot > 0 ? round($cnt / $tot * 100, 1) : 0;
            $row([
                $i + 1,
                $question['text'],
                $choice,
                $choice === $question['correct'] ? $labels['yes'] : $labels['no'],
                $cnt,
                $pct,
            ]);
        }
    }

    return "\xEF\xBB\xBF" . $out; // UTF-8 BOM for Excel compatibility
}
