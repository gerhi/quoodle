<?php
/**
 * Quoodle database layer — SQLite, WAL mode, no external dependencies.
 */

function db_connect(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $path = __DIR__ . '/../data/quoodle.db';
    $dir  = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA synchronous=NORMAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('PRAGMA busy_timeout=5000');

    $pdo->exec('CREATE TABLE IF NOT EXISTS quizzes (
        id            TEXT PRIMARY KEY,
        teacher_token TEXT NOT NULL,
        title         TEXT NOT NULL,
        created_at    TEXT NOT NULL,
        questions     TEXT NOT NULL,
        stats         TEXT NOT NULL
    )');

    return $pdo;
}

// ── CRUD ──────────────────────────────────────────────────────────────────────

function db_create_quiz(array $quiz): void {
    $pdo = db_connect();
    $stmt = $pdo->prepare(
        'INSERT INTO quizzes (id, teacher_token, title, created_at, questions, stats)
         VALUES (:id, :teacher_token, :title, :created_at, :questions, :stats)'
    );
    $stmt->execute([
        ':id'            => $quiz['id'],
        ':teacher_token' => $quiz['teacher_token'],
        ':title'         => $quiz['title'],
        ':created_at'    => $quiz['created_at'],
        ':questions'     => json_encode($quiz['questions'], JSON_UNESCAPED_UNICODE),
        ':stats'         => json_encode($quiz['stats'],     JSON_UNESCAPED_UNICODE),
    ]);
}

function db_get_quiz(string $id): ?array {
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) return null;
    $pdo  = db_connect();
    $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['questions'] = json_decode($row['questions'], true);
    $row['stats']     = json_decode($row['stats'],     true);
    return $row;
}

/**
 * Atomically record one submission.
 * Returns true on success, false if we couldn't acquire a lock (student still gets feedback).
 */
function db_record_submission(string $id, array $answers, int $elapsed_sec = 0, int $tab_switches = 0): bool {
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) return false;
    $pdo     = db_connect();
    $retries = 10;

    for ($try = 0; $try < $retries; $try++) {
        try {
            $pdo->exec('BEGIN IMMEDIATE');

            $stmt = $pdo->prepare('SELECT stats, questions FROM quizzes WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) { $pdo->exec('ROLLBACK'); return false; }

            $stats     = json_decode($row['stats'],     true);
            $questions = json_decode($row['questions'], true);

            // Defensive: top-level aggregates may be missing on pre-v1.1 quizzes
            $stats['attempts']            = ($stats['attempts']            ?? 0) + 1;
            $stats['total_time_seconds']  = ($stats['total_time_seconds']  ?? 0) + max(0, $elapsed_sec);
            $stats['total_tab_switches']  = ($stats['total_tab_switches']  ?? 0) + max(0, $tab_switches);
            if ($tab_switches > 0) {
                $stats['attempts_with_tab_switch'] = ($stats['attempts_with_tab_switch'] ?? 0) + 1;
            } else {
                $stats['attempts_with_tab_switch'] = $stats['attempts_with_tab_switch'] ?? 0;
            }

            foreach ($questions as $i => $q) {
                $chosen = $answers[$i] ?? null;
                if ($chosen === null) continue;

                $stats['questions'][$i]['total_count']++;
                if ($chosen === $q['correct']) {
                    $stats['questions'][$i]['correct_count']++;
                }
                // Validate chosen is a known answer to avoid key injection
                $known = array_merge([$q['correct']], $q['distractors']);
                if (in_array($chosen, $known, true)) {
                    $stats['questions'][$i]['choice_counts'][$chosen] =
                        ($stats['questions'][$i]['choice_counts'][$chosen] ?? 0) + 1;
                }
            }

            $upd = $pdo->prepare('UPDATE quizzes SET stats = ? WHERE id = ?');
            $upd->execute([json_encode($stats, JSON_UNESCAPED_UNICODE), $id]);
            $pdo->exec('COMMIT');
            return true;

        } catch (PDOException $e) {
            try { $pdo->exec('ROLLBACK'); } catch (\Throwable $_) {}
            if ($try < $retries - 1) {
                usleep(5000 * (2 ** $try)); // exponential back-off: 5 ms, 10 ms, …
            }
        }
    }
    return false;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Build a fresh stats object for a new quiz.
 */
function db_empty_stats(array $questions): array {
    $qstats = [];
    foreach ($questions as $q) {
        $choices = [];
        foreach (array_merge([$q['correct']], $q['distractors']) as $opt) {
            $choices[$opt] = 0;
        }
        $qstats[] = [
            'correct_count' => 0,
            'total_count'   => 0,
            'choice_counts' => $choices,
        ];
    }
    return [
        'attempts'                 => 0,
        'total_time_seconds'       => 0,
        'total_tab_switches'       => 0,
        'attempts_with_tab_switch' => 0,
        'questions'                => $qstats,
    ];
}

/**
 * Generate a cryptographically secure hex token.
 */
function db_random_hex(int $bytes): string {
    return bin2hex(random_bytes($bytes));
}

/**
 * Timing-safe teacher token comparison.
 */
function db_check_token(string $stored, string $supplied): bool {
    return hash_equals($stored, $supplied);
}
