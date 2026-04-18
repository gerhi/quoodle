<?php
/**
 * Quoodle i18n — language detection and translation helpers.
 *
 * Priority: ?lang= param → cookie → Accept-Language header → 'de' fallback.
 */

const QUOODLE_LANGS = ['de', 'en'];

function i18n_init(): string {
    $lang = null;

    // 1. URL parameter
    if (isset($_GET['lang']) && in_array($_GET['lang'], QUOODLE_LANGS, true)) {
        $lang = $_GET['lang'];
        setcookie('lang', $lang, ['expires' => time() + 365*86400, 'path' => '/', 'samesite' => 'Lax']);
    }

    // 2. Cookie
    if ($lang === null && isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], QUOODLE_LANGS, true)) {
        $lang = $_COOKIE['lang'];
    }

    // 3. Accept-Language header
    if ($lang === null && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $part) {
            $code = strtolower(trim(explode(';', $part)[0]));
            $short = substr($code, 0, 2);
            if (in_array($short, QUOODLE_LANGS, true)) { $lang = $short; break; }
        }
    }

    // 4. Fallback
    $lang = $lang ?? 'de';

    $file = __DIR__ . "/../lang/{$lang}.php";
    if (!file_exists($file)) $file = __DIR__ . '/../lang/de.php';
    $GLOBALS['__quoodle_strings'] = require $file;
    $GLOBALS['__quoodle_lang']    = $lang;

    return $lang;
}

function current_lang(): string {
    return $GLOBALS['__quoodle_lang'] ?? 'de';
}

/**
 * Translate a key. Returns key itself if not found.
 */
function t(string $key): string {
    return $GLOBALS['__quoodle_strings'][$key] ?? $key;
}

/**
 * Translate with plural. Pipe-separated: "singular|plural"
 */
function tp(string $key, int $n): string {
    $str   = t($key);
    $parts = explode('|', $str, 2);
    return ($n === 1 || count($parts) < 2) ? $parts[0] : $parts[1];
}

/**
 * Returns query string for the current page with lang= overridden.
 */
function lang_url(string $lang): string {
    $params = $_GET;
    $params['lang'] = $lang;
    return '?' . http_build_query($params);
}
