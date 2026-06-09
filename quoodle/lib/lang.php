<?php
/**
 * Minimales i18n-System.
 * Sprache wird ermittelt aus: ?lang=xx → Cookie → Accept-Language → Fallback 'de'.
 * Aufruf: t('key') liefert die Übersetzung oder den Key selbst als Fallback.
 */

function detect_language(): string
{
    $supported = ['de', 'en'];

    // 1. URL-Parameter (setzt auch Cookie)
    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $lang = $_GET['lang'];
        setcookie('lang', $lang, ['expires' => time() + 86400 * 365, 'path' => '/', 'samesite' => 'Lax']);
        return $lang;
    }

    // 2. Cookie
    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $supported, true)) {
        return $_COOKIE['lang'];
    }

    // 3. Accept-Language Header
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    foreach ($supported as $s) {
        if (stripos($accept, $s) !== false) {
            return $s;
        }
    }

    // 4. Fallback
    return 'de';
}

/** @var array<string, string> Globales Wörterbuch */
$_TRANSLATIONS = [];
$_LANG = detect_language();

$langFile = __DIR__ . '/../lang/' . $_LANG . '.php';
if (is_file($langFile)) {
    $_TRANSLATIONS = require $langFile;
}

/**
 * Übersetzt einen Schlüssel. Gibt den Schlüssel selbst zurück, wenn keine Übersetzung existiert.
 */
function t(string $key): string
{
    global $_TRANSLATIONS;
    return $_TRANSLATIONS[$key] ?? $key;
}

function current_lang(): string
{
    global $_LANG;
    return $_LANG;
}

/** Plural: t-Schlüssel hat Format "singular|plural", Auswahl nach $n. */
function tp(string $key, int $n): string
{
    $val = t($key);
    $parts = explode('|', $val, 2);
    if (count($parts) === 2) {
        return $n === 1 ? $parts[0] : $parts[1];
    }
    return $val;
}

/** Erzeugt die URL zum Umschalten der Sprache, behält alle anderen GET-Parameter. */
function lang_switch_url(string $lang): string
{
    $params = $_GET;
    $params['lang'] = $lang;
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    return $path . '?' . http_build_query($params);
}
