<?php
/**
 * Schlanker QR-Code-Generator in reinem PHP.
 *
 * - Implementiert ISO/IEC 18004 (Byte-Modus)
 * - Unterstützt Versionen 1-10 (genug für URLs bis ~270 Zeichen bei EC-Level M)
 * - Fehlerkorrektur-Level: M (~15 % Wiederherstellung) — gute Wahl für Bildschirmanzeige
 * - Ausgabe: SVG (skalierbar, kein GD nötig)
 *
 * Verwendung:
 *   require_once 'lib/qrcode.php';
 *   echo QRCode::svg('https://example.com/quiz?id=abc', 240);
 *
 * Lizenz: MIT (für diese Implementierung).
 * Algorithmus basiert auf dem öffentlich zugänglichen QR-Code-Standard (ISO/IEC 18004).
 */
final class QRCode
{
    /** Fehlerkorrektur-Level (verwenden M = 15 %) */
    private const EC_LEVEL = 'M';

    // ----------------------------------------------------------------
    // GF(256) Tabellen (primitives Polynom x^8 + x^4 + x^3 + x^2 + 1 = 285)
    // ----------------------------------------------------------------
    private static array $exp = [];
    private static array $log = [];
    private static bool $tablesInit = false;

    private static function initTables(): void
    {
        if (self::$tablesInit) return;
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$exp[$i] = $x;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D; // 285
            }
        }
        for ($i = 0; $i < 255; $i++) {
            self::$log[self::$exp[$i]] = $i;
        }
        self::$tablesInit = true;
    }

    // ----------------------------------------------------------------
    // Kapazitätstabelle (nur EC-Level M)
    // [version => [
    //   total_codewords,        // alle Codewörter (data + ec) zusammen
    //   ec_codewords_per_block, // EC-Codewörter pro Block
    //   [ [block_count, data_codewords_per_block], ... ]   // Blockgruppen
    // ]]
    // Quelle: ISO/IEC 18004 Tabelle 9
    // ----------------------------------------------------------------
    private const VERSION_INFO_M = [
        1  => [26,   10, [[1, 16]]],
        2  => [44,   16, [[1, 28]]],
        3  => [70,   26, [[1, 44]]],
        4  => [100,  18, [[2, 32]]],
        5  => [134,  24, [[2, 43]]],
        6  => [172,  16, [[4, 27]]],
        7  => [196,  18, [[4, 31]]],
        8  => [242,  22, [[2, 38], [2, 39]]],
        9  => [292,  22, [[3, 36], [2, 37]]],
        10 => [346,  26, [[4, 43], [1, 44]]],
    ];

    /** Alignment-Pattern-Positionen pro Version (Zentren). */
    private const ALIGNMENT_PATTERNS = [
        1  => [],
        2  => [6, 18],
        3  => [6, 22],
        4  => [6, 26],
        5  => [6, 30],
        6  => [6, 34],
        7  => [6, 22, 38],
        8  => [6, 24, 42],
        9  => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /**
     * Format-Info-Bits für EC-Level M (Bits 3-4 = 00) plus Maske 0-7,
     * inkl. BCH-Fehlerkorrektur und XOR mit 0x5412.
     * Quelle: ISO/IEC 18004 Tabelle C.1
     */
    private const FORMAT_INFO_M = [
        0 => 0x5412, 1 => 0x5125, 2 => 0x5E7C, 3 => 0x5B4B,
        4 => 0x45F9, 5 => 0x40CE, 6 => 0x4F97, 7 => 0x4AA0,
    ];

    /** Generator-Polynome (in α-Notation). $generator[$ecLen] = [α-Exponent, ...] */
    private static array $generatorCache = [];

    private static function generatorPoly(int $ecLen): array
    {
        if (isset(self::$generatorCache[$ecLen])) {
            return self::$generatorCache[$ecLen];
        }
        // Beginne mit (x - α^0) = [1, 1]  (α-Exp: [0, 0])
        $poly = [0, 0]; // Koeffizienten in α-Exponentenform
        for ($i = 1; $i < $ecLen; $i++) {
            // Multipliziere mit (x - α^i)
            $poly = self::polyMultiply($poly, [0, $i]);
        }
        return self::$generatorCache[$ecLen] = $poly;
    }

    /** Multipliziert zwei Polynome (Koeffizienten in α-Exponentenform). */
    private static function polyMultiply(array $a, array $b): array
    {
        $result = array_fill(0, count($a) + count($b) - 1, null);
        foreach ($a as $i => $aExp) {
            foreach ($b as $j => $bExp) {
                $term = ($aExp + $bExp) % 255;
                $idx = $i + $j;
                if ($result[$idx] === null) {
                    $result[$idx] = self::$exp[$term];
                } else {
                    $result[$idx] ^= self::$exp[$term];
                }
            }
        }
        // Zurück in α-Exponenten umrechnen
        foreach ($result as $k => $v) {
            $result[$k] = ($v === 0) ? 0 : self::$log[$v];
        }
        return $result;
    }

    /** Berechnet die Reed-Solomon-EC-Codewörter für einen Datenblock. */
    private static function computeEC(array $dataBytes, int $ecLen): array
    {
        $gen = self::generatorPoly($ecLen);
        $msg = array_merge($dataBytes, array_fill(0, $ecLen, 0));
        $dataLen = count($dataBytes);

        for ($i = 0; $i < $dataLen; $i++) {
            $coef = $msg[$i];
            if ($coef === 0) continue;
            $coefLog = self::$log[$coef];
            for ($j = 0; $j < count($gen); $j++) {
                $msg[$i + $j] ^= self::$exp[($gen[$j] + $coefLog) % 255];
            }
        }
        return array_slice($msg, $dataLen);
    }

    // ----------------------------------------------------------------
    // Hauptkodierung
    // ----------------------------------------------------------------

    /**
     * Wählt die kleinste Version, die die Daten in Byte-Modus mit EC-Level M aufnehmen kann.
     * Liefert [versionNumber, dataCapacityBytes].
     */
    private static function selectVersion(int $textBytes): array
    {
        // Header für Byte-Modus = 4 Bit (Modus) + Char-Count (8 Bit für V1-9, 16 Bit für V10+)
        foreach (self::VERSION_INFO_M as $v => $info) {
            $totalCodewords = $info[0];
            $ecPerBlock     = $info[1];
            $blocks         = 0;
            $dataCodewords  = 0;
            foreach ($info[2] as [$cnt, $dataPer]) {
                $blocks        += $cnt;
                $dataCodewords += $cnt * $dataPer;
            }
            $countLen = ($v <= 9) ? 8 : 16;
            $headerBits = 4 + $countLen;
            $dataBits   = $dataCodewords * 8;
            $available  = ($dataBits - $headerBits) >> 3; // verfügbare Daten-Bytes
            if ($available >= $textBytes) {
                return [$v, $dataCodewords];
            }
        }
        throw new RuntimeException("Text zu lang für QR-Code (max. ca. 270 Zeichen).");
    }

    /** Baut den Bitstream (Modus + Length + Daten + Terminator + Padding). */
    private static function buildDataBits(string $text, int $version, int $dataCodewords): array
    {
        $countLen = ($version <= 9) ? 8 : 16;
        $bits = [];

        // Modusindikator: 0100 = Byte-Modus
        self::appendBits($bits, 0b0100, 4);
        // Zeichenanzahl
        self::appendBits($bits, strlen($text), $countLen);
        // Datenbytes
        for ($i = 0; $i < strlen($text); $i++) {
            self::appendBits($bits, ord($text[$i]), 8);
        }
        // Terminator (bis zu 4 Nullbits)
        $totalBits = $dataCodewords * 8;
        $termBits = min(4, $totalBits - count($bits));
        self::appendBits($bits, 0, $termBits);
        // Auffüllen auf volles Byte
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        // Padding-Bytes 0xEC und 0x11 abwechselnd
        $bytes = self::bitsToBytes($bits);
        $padBytes = [0xEC, 0x11];
        $i = 0;
        while (count($bytes) < $dataCodewords) {
            $bytes[] = $padBytes[$i++ % 2];
        }
        return $bytes;
    }

    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function bitsToBytes(array $bits): array
    {
        $bytes = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $b = 0;
            for ($j = 0; $j < 8; $j++) {
                $b = ($b << 1) | ($bits[$i + $j] ?? 0);
            }
            $bytes[] = $b;
        }
        return $bytes;
    }

    /**
     * Verteilt die Datenbytes auf Blöcke, berechnet EC, und liefert den
     * verschachtelten finalen Bytestrom (Daten zuerst spaltenweise, dann EC spaltenweise).
     */
    private static function interleaveBlocks(array $dataBytes, int $version): array
    {
        $info = self::VERSION_INFO_M[$version];
        $ecPerBlock = $info[1];
        $blockSpec  = $info[2];

        $blocks    = []; // jedes Element: ['data' => [...], 'ec' => [...]]
        $dataIdx   = 0;
        foreach ($blockSpec as [$count, $dataLen]) {
            for ($k = 0; $k < $count; $k++) {
                $data = array_slice($dataBytes, $dataIdx, $dataLen);
                $dataIdx += $dataLen;
                $ec = self::computeEC($data, $ecPerBlock);
                $blocks[] = ['data' => $data, 'ec' => $ec];
            }
        }
        // Maximale Blockdatenlänge bestimmen (für die Verschachtelung)
        $maxDataLen = 0;
        foreach ($blocks as $b) {
            $maxDataLen = max($maxDataLen, count($b['data']));
        }

        $result = [];
        // Daten spaltenweise verschachteln
        for ($col = 0; $col < $maxDataLen; $col++) {
            foreach ($blocks as $b) {
                if (isset($b['data'][$col])) {
                    $result[] = $b['data'][$col];
                }
            }
        }
        // EC spaltenweise verschachteln
        for ($col = 0; $col < $ecPerBlock; $col++) {
            foreach ($blocks as $b) {
                $result[] = $b['ec'][$col];
            }
        }
        return $result;
    }

    // ----------------------------------------------------------------
    // Modulplatzierung
    // ----------------------------------------------------------------

    /**
     * Erstellt die Matrix mit allen Funktionsmustern (ohne Daten und ohne Maske).
     * Zellwerte: 0 = hell, 1 = dunkel, null = noch unbelegt
     * $reserved markiert reservierte Bereiche (true), die nicht für Daten genutzt werden.
     */
    private static function placeFunctionPatterns(int $version): array
    {
        $size = 17 + 4 * $version;
        $matrix   = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Drei Finder-Muster (oben-links, oben-rechts, unten-links) inkl. Separator
        $finderPositions = [[0, 0], [$size - 7, 0], [0, $size - 7]];
        foreach ($finderPositions as [$col, $row]) {
            self::placeFinder($matrix, $reserved, $row, $col, $size);
        }

        // Timing-Muster (Zeile 6 und Spalte 6)
        for ($i = 8; $i < $size - 8; $i++) {
            $val = ($i % 2 === 0) ? 1 : 0;
            $matrix[6][$i]   = $val;
            $matrix[$i][6]   = $val;
            $reserved[6][$i] = true;
            $reserved[$i][6] = true;
        }

        // Alignment-Muster
        $aps = self::ALIGNMENT_PATTERNS[$version];
        $apCount = count($aps);
        for ($i = 0; $i < $apCount; $i++) {
            for ($j = 0; $j < $apCount; $j++) {
                $row = $aps[$i];
                $col = $aps[$j];
                // Nicht über Finder-Mustern platzieren
                if ($reserved[$row][$col]) continue;
                self::placeAlignment($matrix, $reserved, $row, $col);
            }
        }

        // Dunkles Modul (immer dunkel)
        $matrix[4 * $version + 9][8] = 1;
        $reserved[4 * $version + 9][8] = true;

        // Reservierte Bereiche für Format-Info (ohne Werte zu setzen, kommt später)
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;          // horizontal oben-links
            $reserved[$i][8] = true;          // vertikal oben-links
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true; // horizontal oben-rechts
            $reserved[$size - 1 - $i][8] = true; // vertikal unten-links
        }

        // Versionsinfo (nur ab Version 7) — wir unterstützen nur bis 10
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $reserved[$size - 11 + $j][$i] = true;
                    $reserved[$i][$size - 11 + $j] = true;
                }
            }
        }

        return [$matrix, $reserved, $size];
    }

    private static function placeFinder(array &$matrix, array &$reserved, int $row, int $col, int $size): void
    {
        // 7x7 Finder + 1-Modul Separator
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
                $val = 0;
                if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                    // innerhalb des 7x7
                    if (($r === 0 || $r === 6 || $c === 0 || $c === 6) ||
                        ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                        $val = 1;
                    }
                }
                $matrix[$rr][$cc] = $val;
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function placeAlignment(array &$matrix, array &$reserved, int $row, int $col): void
    {
        // 5x5 Alignment-Muster, zentriert auf ($row, $col)
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $val = 0;
                if (max(abs($r), abs($c)) === 2 || ($r === 0 && $c === 0)) {
                    $val = 1;
                }
                $matrix[$row + $r][$col + $c] = $val;
                $reserved[$row + $r][$col + $c] = true;
            }
        }
    }

    /**
     * Schreibt die Datenbytes im Zigzag-Muster von unten-rechts in die Matrix.
     */
    private static function placeData(array &$matrix, array &$reserved, array $bytes, int $size): void
    {
        $bitIndex = 0;
        $totalBits = count($bytes) * 8;
        $direction = -1; // -1 = aufwärts, +1 = abwärts
        $col = $size - 1;

        while ($col > 0) {
            // Spalte 6 ist Timing-Pattern, überspringen
            if ($col === 6) $col--;

            $row = ($direction === -1) ? $size - 1 : 0;
            for ($i = 0; $i < $size; $i++) {
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if (!$reserved[$row][$cc] && $matrix[$row][$cc] === null) {
                        if ($bitIndex < $totalBits) {
                            $byte = $bytes[$bitIndex >> 3];
                            $bit  = ($byte >> (7 - ($bitIndex & 7))) & 1;
                            $matrix[$row][$cc] = $bit;
                            $bitIndex++;
                        } else {
                            $matrix[$row][$cc] = 0; // Rest-Bits (sollte nicht passieren bei korrekter Kapazität)
                        }
                    }
                }
                $row += $direction;
            }
            $direction = -$direction;
            $col -= 2;
        }
    }

    /** Wendet eine Maske (0-7) auf alle nicht-reservierten Module an. */
    private static function applyMask(array $matrix, array $reserved, int $mask, int $size): array
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($reserved[$r][$c]) continue;
                if (self::maskCondition($mask, $r, $c)) {
                    $matrix[$r][$c] ^= 1;
                }
            }
        }
        return $matrix;
    }

    private static function maskCondition(int $mask, int $r, int $c): bool
    {
        switch ($mask) {
            case 0: return (($r + $c) % 2) === 0;
            case 1: return ($r % 2) === 0;
            case 2: return ($c % 3) === 0;
            case 3: return (($r + $c) % 3) === 0;
            case 4: return ((intdiv($r, 2) + intdiv($c, 3)) % 2) === 0;
            case 5: return (($r * $c) % 2) + (($r * $c) % 3) === 0;
            case 6: return ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0;
            case 7: return ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0;
        }
        return false;
    }

    /** Bewertet eine Matrix nach den 4 QR-Strafkriterien (kleiner = besser). */
    private static function evaluatePenalty(array $matrix, int $size): int
    {
        $penalty = 0;

        // N1: Reihen/Spalten mit ≥5 gleichen aufeinanderfolgenden Modulen
        for ($r = 0; $r < $size; $r++) {
            $runColor = -1; $runLen = 0;
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) $penalty += 3;
                    elseif ($runLen > 5) $penalty++;
                } else {
                    $runColor = $matrix[$r][$c];
                    $runLen = 1;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            $runColor = -1; $runLen = 0;
            for ($r = 0; $r < $size; $r++) {
                if ($matrix[$r][$c] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) $penalty += 3;
                    elseif ($runLen > 5) $penalty++;
                } else {
                    $runColor = $matrix[$r][$c];
                    $runLen = 1;
                }
            }
        }

        // N2: 2x2-Blöcke gleicher Farbe
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $matrix[$r][$c];
                if ($matrix[$r][$c+1] === $v &&
                    $matrix[$r+1][$c] === $v &&
                    $matrix[$r+1][$c+1] === $v) {
                    $penalty += 3;
                }
            }
        }

        // N3: Finder-ähnliche Muster (1011101 mit 0000 davor oder dahinter)
        $patternA = [1,0,1,1,1,0,1,0,0,0,0];
        $patternB = [0,0,0,0,1,0,1,1,1,0,1];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c <= $size - 11; $c++) {
                $matchA = true; $matchB = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($matrix[$r][$c+$k] !== $patternA[$k]) $matchA = false;
                    if ($matrix[$r][$c+$k] !== $patternB[$k]) $matchB = false;
                    if (!$matchA && !$matchB) break;
                }
                if ($matchA) $penalty += 40;
                if ($matchB) $penalty += 40;
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r <= $size - 11; $r++) {
                $matchA = true; $matchB = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($matrix[$r+$k][$c] !== $patternA[$k]) $matchA = false;
                    if ($matrix[$r+$k][$c] !== $patternB[$k]) $matchB = false;
                    if (!$matchA && !$matchB) break;
                }
                if ($matchA) $penalty += 40;
                if ($matchB) $penalty += 40;
            }
        }

        // N4: Verhältnis dunkler Module
        $dark = 0;
        $total = $size * $size;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c] === 1) $dark++;
            }
        }
        $pct = ($dark * 100) / $total;
        $deviation = (int)(abs($pct - 50) / 5);
        $penalty += $deviation * 10;

        return $penalty;
    }

    /** Schreibt Format-Info-Bits an die zwei vorgesehenen Stellen. */
    private static function placeFormatInfo(array &$matrix, int $mask, int $size): void
    {
        $bits = self::FORMAT_INFO_M[$mask]; // 15-Bit-Wert

        // 15 Bits, MSB zuerst: bits[14] ... bits[0]
        $b = [];
        for ($i = 14; $i >= 0; $i--) {
            $b[] = ($bits >> $i) & 1;
        }

        // Position 1: links/oben um Finder oben-links
        // Bits 0-5 in Spalte 8, Reihen 0-5
        for ($i = 0; $i <= 5; $i++) {
            $matrix[$i][8] = $b[$i];
        }
        $matrix[7][8] = $b[6];
        $matrix[8][8] = $b[7];
        $matrix[8][7] = $b[8];
        // Bits 9-14 in Reihe 8, Spalten 5..0
        for ($i = 9; $i <= 14; $i++) {
            $matrix[8][14 - $i] = $b[$i];
        }

        // Position 2: rechts/unten um Finder oben-rechts und unten-links
        // Bits 0-7 entlang Reihe 8 von Spalte (size-1) nach (size-8)
        for ($i = 0; $i <= 7; $i++) {
            $matrix[8][$size - 1 - $i] = $b[$i];
        }
        // Bits 8-14 entlang Spalte 8 von Reihe (size-7) nach (size-1)
        for ($i = 8; $i <= 14; $i++) {
            $matrix[$size - 15 + $i][8] = $b[$i];
        }
    }

    // ----------------------------------------------------------------
    // Öffentliche API
    // ----------------------------------------------------------------

    /**
     * Erzeugt die finale Modulmatrix für einen Text.
     * Liefert ein 2D-Array (rows × cols) mit Werten 0/1.
     */
    public static function encode(string $text): array
    {
        self::initTables();

        [$version, $dataCodewords] = self::selectVersion(strlen($text));
        $dataBytes = self::buildDataBits($text, $version, $dataCodewords);
        $finalBytes = self::interleaveBlocks($dataBytes, $version);

        [$matrix, $reserved, $size] = self::placeFunctionPatterns($version);
        self::placeData($matrix, $reserved, $finalBytes, $size);

        // Beste Maske wählen
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = null;
        for ($m = 0; $m < 8; $m++) {
            $masked = self::applyMask($matrix, $reserved, $m, $size);
            self::placeFormatInfo($masked, $m, $size);
            $penalty = self::evaluatePenalty($masked, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $m;
                $bestMatrix = $masked;
            }
        }
        return $bestMatrix;
    }

    /**
     * Gibt einen QR-Code als SVG-String zurück.
     *
     * @param string $text Zu kodierender Text (UTF-8, max. ~270 Zeichen)
     * @param int    $size Pixelgröße der SVG-Ausgabe (Breite = Höhe)
     * @param int    $quietZone Anzahl heller Module als Rand (Standard: 4 nach Spec)
     * @param string $darkColor Farbe der dunklen Module (CSS-Wert)
     * @param string $lightColor Farbe des Hintergrunds (CSS-Wert)
     */
    public static function svg(
        string $text,
        int $size = 240,
        int $quietZone = 4,
        string $darkColor = '#111827',
        string $lightColor = '#ffffff'
    ): string {
        $matrix = self::encode($text);
        $modules = count($matrix);
        $totalModules = $modules + 2 * $quietZone;

        // Module-Pfad als ein einziger SVG-Pfad (kompakt)
        $path = '';
        for ($r = 0; $r < $modules; $r++) {
            for ($c = 0; $c < $modules; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $quietZone;
                    $y = $r + $quietZone;
                    $path .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        $bg = htmlspecialchars($lightColor, ENT_QUOTES);
        $fg = htmlspecialchars($darkColor,  ENT_QUOTES);

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" "
             . "viewBox=\"0 0 {$totalModules} {$totalModules}\" "
             . "width=\"{$size}\" height=\"{$size}\" "
             . "shape-rendering=\"crispEdges\" "
             . "role=\"img\" aria-label=\"QR-Code\">"
             . "<rect width=\"{$totalModules}\" height=\"{$totalModules}\" fill=\"{$bg}\"/>"
             . "<path d=\"{$path}\" fill=\"{$fg}\"/>"
             . "</svg>";
    }
}
