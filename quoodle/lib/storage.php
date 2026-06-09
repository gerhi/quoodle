<?php
require_once __DIR__ . '/lang.php';
/**
 * Simple JSON-file based storage for quizzes.
 * Each quiz is one file under data/quizzes/<id>.json
 *
 * Quiz JSON shape:
 *   {
 *     "id": "<16 hex>",
 *     "teacher_token": "<24 hex>",
 *     "title": "...",
 *     "created_at": "...",
 *     "questions": [
 *       { "question": "...", "correct": "...", "distractors": [...], "explanation": "..." },
 *       ...
 *     ],
 *     "stats": {
 *       "attempts": 0,
 *       "questions": [
 *         { "correct_count": 0, "total_count": 0, "choice_counts": { "<answer text>": N, ... } },
 *         ...
 *       ]
 *     }
 *   }
 */

function storage_dir(): string
{
    $dir = __DIR__ . '/../data/quizzes';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function generate_quiz_id(): string
{
    return bin2hex(random_bytes(8));   // 16 hex = 64 bits
}

function generate_teacher_token(): string
{
    return bin2hex(random_bytes(12));  // 24 hex = 96 bits
}

function save_quiz(array $quiz): array
{
    $id = generate_quiz_id();
    $token = generate_teacher_token();

    $quiz['id'] = $id;
    $quiz['teacher_token'] = $token;
    $quiz['created_at'] = date('c');
    $quiz['expires_at'] = date('c', strtotime('+30 days'));

    $statsQuestions = [];
    foreach ($quiz['questions'] as $_q) {
        $statsQuestions[] = [
            'correct_count' => 0,
            'total_count'   => 0,
            'choice_counts' => new stdClass(), // serializes as {} not []
        ];
    }
    $quiz['stats'] = [
        'attempts'  => 0,
        'questions' => $statsQuestions,
    ];

    $path = storage_dir() . '/' . $id . '.json';
    if (file_put_contents($path, json_encode($quiz, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Quiz-Datei konnte nicht geschrieben werden. Bitte Schreibrechte auf das data/-Verzeichnis prüfen.');
    }
    return ['id' => $id, 'teacher_token' => $token];
}

function load_quiz(string $id): ?array
{
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        return null;
    }
    $path = storage_dir() . '/' . $id . '.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return null;
    }
    // Auto-delete expired quizzes
    if (!empty($data['expires_at']) && strtotime($data['expires_at']) < time()) {
        @unlink($path);
        return null;
    }
    return $data;
}

/** Permanently deletes a quiz. Returns true if the file was removed. */
function delete_quiz(string $id): bool
{
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        return false;
    }
    $path = storage_dir() . '/' . $id . '.json';
    if (!is_file($path)) {
        return false;
    }
    return @unlink($path);
}

/**
 * Updates the expiry date for a quiz.
 * @param string|null $expiresAt ISO 8601 datetime string, or null for "never expires".
 */
function update_expiry(string $id, ?string $expiresAt): bool
{
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) return false;
    $path = storage_dir() . '/' . $id . '.json';
    if (!is_file($path)) return false;

    $fp = @fopen($path, 'c+');
    if (!$fp) return false;

    try {
        if (!flock($fp, LOCK_EX)) return false;
        rewind($fp);
        $raw = stream_get_contents($fp);
        $quiz = json_decode($raw, true);
        if (!is_array($quiz)) return false;

        $quiz['expires_at'] = $expiresAt;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($quiz, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        return true;
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

/**
 * Scans the data directory and deletes all expired quizzes.
 * Rate-limited: runs at most once per hour (tracked via data/.last_sweep).
 * Returns the number of quizzes deleted.
 */
function sweep_expired(): int
{
    $dir = storage_dir();
    $sweepFile = dirname($dir) . '/.last_sweep';

    // Rate limit: at most once per hour
    if (is_file($sweepFile) && filemtime($sweepFile) > time() - 3600) {
        return 0;
    }
    @touch($sweepFile);

    $deleted = 0;
    $now = time();
    foreach (glob($dir . '/*.json') as $file) {
        $raw = @file_get_contents($file);
        if ($raw === false) continue;
        $quiz = @json_decode($raw, true);
        if (!is_array($quiz)) continue;
        if (!empty($quiz['expires_at']) && strtotime($quiz['expires_at']) < $now) {
            if (@unlink($file)) $deleted++;
        }
    }
    return $deleted;
}

/**
 * Record one student attempt's results into the quiz's stats counters.
 * $perQuestion = [ ['user_answer' => '...', 'is_correct' => bool], ... ] in question order.
 *
 * Uses an exclusive file lock so concurrent submissions don't lose updates.
 */
function record_attempt(string $id, array $perQuestion): void
{
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) return;
    $path = storage_dir() . '/' . $id . '.json';
    if (!is_file($path)) return;

    $fp = @fopen($path, 'c+');
    if (!$fp) return;

    try {
        if (!flock($fp, LOCK_EX)) return;
        rewind($fp);
        $raw = stream_get_contents($fp);
        $quiz = json_decode($raw, true);
        if (!is_array($quiz)) return;

        // Backfill stats structure for older quiz files
        if (!isset($quiz['stats']) || !is_array($quiz['stats'])) {
            $quiz['stats'] = ['attempts' => 0, 'questions' => []];
        }
        while (count($quiz['stats']['questions']) < count($quiz['questions'])) {
            $quiz['stats']['questions'][] = [
                'correct_count' => 0,
                'total_count'   => 0,
                'choice_counts' => [],
            ];
        }

        $quiz['stats']['attempts'] = (int)($quiz['stats']['attempts'] ?? 0) + 1;

        foreach ($quiz['questions'] as $qi => $_q) {
            if (!isset($perQuestion[$qi])) continue;
            $entry      = $perQuestion[$qi];
            $userAnswer = (string)($entry['user_answer'] ?? '');
            $isCorrect  = !empty($entry['is_correct']);

            $quiz['stats']['questions'][$qi]['total_count']++;
            if ($isCorrect) {
                $quiz['stats']['questions'][$qi]['correct_count']++;
            }
            $cc = $quiz['stats']['questions'][$qi]['choice_counts'] ?? [];
            if (is_object($cc)) $cc = (array)$cc;
            if ($userAnswer !== '') {
                $cc[$userAnswer] = (int)($cc[$userAnswer] ?? 0) + 1;
            }
            $quiz['stats']['questions'][$qi]['choice_counts'] = $cc;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($quiz, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

function base_path(): string
{
    return rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
}

function origin_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function quiz_url(string $id): string
{
    return origin_url() . base_path() . '/quiz.php?id=' . urlencode($id);
}

function teacher_stats_url(string $id, string $token): string
{
    return origin_url() . base_path() . '/stats.php?id=' . urlencode($id) . '&t=' . urlencode($token);
}

function verify_teacher_token(array $quiz, string $token): bool
{
    $expected = $quiz['teacher_token'] ?? '';
    if ($expected === '' || $token === '') return false;
    return hash_equals((string)$expected, $token);
}
