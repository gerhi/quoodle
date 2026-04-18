<?php
declare(strict_types=1);

require_once __DIR__ . '/csv_parser.php'; // for process_rows()

/**
 * Parse an XLSX file into question objects.
 * Uses PHP's built-in ZipArchive + SimpleXML — no external libraries.
 *
 * @param string $file_path Absolute path to the .xlsx file
 * @return array{questions: array, errors: array}
 */
function parse_xlsx(string $file_path): array {
    if (!extension_loaded('zip')) {
        return ['questions' => [], 'errors' => ['PHP ZipArchive-Erweiterung fehlt.']];
    }

    $zip = new ZipArchive();
    if ($zip->open($file_path) !== true) {
        return ['questions' => [], 'errors' => ['XLSX-Datei konnte nicht geöffnet werden.']];
    }

    // ── Read shared strings ──
    $shared_strings = [];
    $ss_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss_xml !== false) {
        $shared_strings = parse_shared_strings($ss_xml);
    }

    // ── Find the first worksheet ──
    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheet_xml === false) {
        // Try workbook.xml to find sheet name
        $wb_xml = $zip->getFromName('xl/workbook.xml');
        if ($wb_xml !== false) {
            $wb = load_xml_safe($wb_xml);
            if ($wb !== false) {
                // Find first sheet rId
                foreach (['sheets/sheet', 'sheets/sheet'] as $path) {
                    $sheets = $wb->xpath('//*[local-name()="sheet"]');
                    if ($sheets && !empty($sheets)) break;
                }
                if (!empty($sheets)) {
                    $rId = (string)($sheets[0]->attributes('r', true)['id'] ?? '');
                    if ($rId) {
                        $rels_xml = $zip->getFromName('xl/_rels/workbook.xml.rels');
                        if ($rels_xml !== false) {
                            $rels = load_xml_safe($rels_xml);
                            if ($rels !== false) {
                                foreach ($rels->xpath('//*[local-name()="Relationship"]') as $rel) {
                                    if ((string)$rel['Id'] === $rId) {
                                        $target = (string)$rel['Target'];
                                        $sheet_xml = $zip->getFromName('xl/' . ltrim($target, '/'));
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    $zip->close();

    if ($sheet_xml === false) {
        return ['questions' => [], 'errors' => ['Kein Arbeitsblatt in der XLSX-Datei gefunden.']];
    }

    // ── Parse worksheet ──
    $rows = parse_sheet($sheet_xml, $shared_strings);

    if (empty($rows)) {
        return ['questions' => [], 'errors' => ['Das Arbeitsblatt enthält keine Daten.']];
    }

    return process_rows($rows);
}

// ── Helper: load XML safely (strip default namespace to simplify XPath) ──
function load_xml_safe(string $xml): SimpleXMLElement|false {
    // Remove default namespace declarations to make XPath work reliably
    $xml = preg_replace('/\sxmlns(?::\w+)?="[^"]*"/', '', $xml) ?? $xml;
    libxml_use_internal_errors(true);
    $el = simplexml_load_string($xml);
    libxml_clear_errors();
    return $el;
}

// ── Parse sharedStrings.xml into an indexed array ──
function parse_shared_strings(string $xml): array {
    $el = load_xml_safe($xml);
    if ($el === false) return [];

    $strings = [];
    // Each <si> element contains either a <t> or multiple <r><t> runs
    foreach ($el->xpath('//*[local-name()="si"]') as $si) {
        $texts = $si->xpath('.//*[local-name()="t"]');
        $value = '';
        foreach ($texts as $t) {
            $value .= (string)$t;
        }
        $strings[] = $value;
    }
    return $strings;
}

// ── Parse sheet XML into 2D array of strings ──
function parse_sheet(string $xml, array $shared_strings): array {
    $el = load_xml_safe($xml);
    if ($el === false) return [];

    $rows       = [];
    $row_nodes  = $el->xpath('//*[local-name()="row"]');
    if (!$row_nodes) return [];

    foreach ($row_nodes as $row_node) {
        $row_idx = (int)($row_node['r'] ?? 0) - 1; // 0-based
        if ($row_idx < 0) continue;

        $cells = $row_node->xpath('.//*[local-name()="c"]');
        if (!$cells) continue;

        $row_data = [];
        foreach ($cells as $cell) {
            $ref  = (string)($cell['r'] ?? '');
            $type = (string)($cell['t'] ?? '');
            $col  = col_ref_to_index($ref);

            $v_nodes = $cell->xpath('.//*[local-name()="v"]');
            $raw_val = $v_nodes ? (string)$v_nodes[0] : '';

            // Rich-text inline strings
            if ($type === 'inlineStr') {
                $t_nodes = $cell->xpath('.//*[local-name()="t"]');
                $raw_val = '';
                foreach ($t_nodes as $t) $raw_val .= (string)$t;
            }

            $value = match ($type) {
                's'          => $shared_strings[(int)$raw_val] ?? '',
                'b'          => $raw_val === '1' ? 'TRUE' : 'FALSE',
                'inlineStr'  => $raw_val,
                default      => $raw_val, // number or formula result
            };

            $row_data[$col] = $value;
        }

        if (!empty($row_data)) {
            // Fill gaps with empty strings
            $max_col = max(array_keys($row_data));
            $full_row = [];
            for ($i = 0; $i <= $max_col; $i++) {
                $full_row[] = $row_data[$i] ?? '';
            }
            $rows[$row_idx] = $full_row;
        }
    }

    // Re-index rows without gaps
    ksort($rows);
    return array_values($rows);
}

// ── Convert a cell reference like "C3" or "AB12" to a 0-based column index ──
function col_ref_to_index(string $ref): int {
    // Extract column letters
    preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
    $letters = $m[1] ?? 'A';
    $idx = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $idx = $idx * 26 + (ord($letters[$i]) - 64);
    }
    return $idx - 1; // 0-based
}
