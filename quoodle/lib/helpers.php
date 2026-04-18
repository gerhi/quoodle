<?php
/**
 * Quoodle helpers — utility functions required by every page.
 *
 * Included FIRST by every entry-point script.
 * Bootstraps i18n, sets security headers, provides e(), redirect(), not_found(), etc.
 */

declare(strict_types=1);

// ── Security headers ──────────────────────────────────────────────────────────
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:;");
}

// ── Autoload sibling lib files ────────────────────────────────────────────────
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db.php';

// ── HTML escaping ─────────────────────────────────────────────────────────────

/** Escape a value for safe HTML output. */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── URL helpers ───────────────────────────────────────────────────────────────

/**
 * Base URL of this Quoodle installation (no trailing slash).
 * Works behind most reverse-proxies.
 */
function get_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $scheme . '://' . $host . $path;
}

/** Redirect to a relative path and exit. */
function redirect(string $location): void {
    header('Location: ' . $location);
    exit;
}

// ── Validation ────────────────────────────────────────────────────────────────

/** Validate a 16-char hex quiz ID. */
function validate_id(string $id): bool {
    return (bool)preg_match('/^[a-f0-9]{16}$/', $id);
}

/** Validate a hex teacher token (24-48 hex chars). */
function validate_token(string $token): bool {
    return (bool)preg_match('/^[a-f0-9]{24,48}$/', $token);
}

/** Timing-safe token comparison (wraps db_check_token). */
function safe_token_compare(string $stored, string $supplied): bool {
    return hash_equals($stored, $supplied);
}

// ── Error pages ───────────────────────────────────────────────────────────────

/** Send HTTP 404 and render a minimal error page, then exit. */
function not_found(): void {
    http_response_code(404);
    // layout.php is included by the caller, but may not be loaded yet
    if (function_exists('render_header')) {
        render_header(t('common.not_found'));
        echo '<main><div class="card"><p>' . e(t('common.not_found')) . '</p>'
            . '<p><a href="index.php" class="btn btn-secondary">' . e(t('common.back_home')) . '</a></p></div></main>';
        render_footer();
    } else {
        echo '<!doctype html><html><head><title>404</title></head><body>'
            . '<h1>404 – Nicht gefunden</h1>'
            . '<p><a href="index.php">Zurück zur Startseite</a></p>'
            . '</body></html>';
    }
    exit;
}

/** Render a simple error card and exit. */
function show_error(string $message): void {
    if (function_exists('render_header')) {
        render_header(t('common.error'));
        echo '<main><div class="card error-card">'
            . '<h1>' . e(t('common.error')) . '</h1>'
            . '<p>' . e($message) . '</p>'
            . '<p><a href="index.php" class="btn btn-secondary">' . e(t('common.back_home')) . '</a></p>'
            . '</div></main>';
        render_footer();
    } else {
        echo '<!doctype html><html><head><title>Error</title></head><body>'
            . '<p>' . e($message) . '</p>'
            . '</body></html>';
    }
    exit;
}

// ── ID / Token generation ─────────────────────────────────────────────────────

/** Generate a cryptographically random 64-bit quiz ID (16 hex chars). */
function generate_id(): string {
    return bin2hex(random_bytes(8));
}

/** Generate a cryptographically random 96-bit teacher token (24 hex chars). */
function generate_token(): string {
    return bin2hex(random_bytes(12));
}

/** Sanitise a string for use in filenames. */
function safe_filename(string $s): string {
    $s = preg_replace('/[^\w\-]/', '_', $s);
    return substr($s, 0, 40);
}

/** Format a duration in seconds as e.g. "2:34" (mm:ss) or "1:02:34" (h:mm:ss). */
function format_duration(int $sec): string {
    if ($sec < 0) $sec = 0;
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    if ($h > 0) {
        return sprintf('%d:%02d:%02d', $h, $m, $s);
    }
    return sprintf('%d:%02d', $m, $s);
}
