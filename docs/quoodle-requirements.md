# Quoodle — Requirements Specification

**Version:** 1.0
**Date:** April 2026
**Purpose:** Technology-agnostic specification for reimplementation in any stack.

---

## 1. Product Overview

### 1.1 Purpose

Quoodle is a web-based quiz tool for formative assessment in education. Educators create single-choice quizzes by uploading a spreadsheet. Students take quizzes anonymously in a browser and receive immediate feedback with explanations. Educators see aggregated analytics showing which questions were difficult and which distractors were most attractive.

### 1.2 Design Philosophy

The following principles are non-negotiable and must guide all implementation decisions:

- **Privacy by design.** No personally identifiable information is collected, stored, or transmitted. No user accounts. No tracking. Only anonymous aggregate counters.
- **Zero external dependencies at runtime.** No CDN includes, no external APIs, no third-party services called during normal operation. QR codes are generated locally. The application must function on an air-gapped network.
- **Single-directory deployment.** The entire application must be distributable as a folder that can be copied to a server. No build step, no package manager required at deploy time.
- **No database server required.** Storage must use the filesystem (e.g., JSON files, SQLite, or equivalent). An administrator must be able to back up or delete data by copying or removing files.

### 1.3 Naming

The product name is **Quoodle** (pronounced "KOO-dl"). The brand mark is the letter **Q** in a small rounded square, followed by the word "Quoodle."

---

## 2. Actors

| Actor | Description |
|---|---|
| **Educator** | Creates quizzes, distributes links, views analytics, exports results. Has no account — identified only by possession of a secret teacher URL. |
| **Student** | Takes a quiz via a public URL. Fully anonymous — no login, no identifier of any kind. |
| **Administrator** | Has filesystem access to the server. Manages deployment, deletes old quizzes, configures web server. Not an in-app role. |

---

## 3. Data Model

### 3.1 Quiz Object

Each quiz is a single persistent record (e.g., a JSON file). Fields:

| Field | Type | Description |
|---|---|---|
| `id` | string (16 hex chars) | Unique public identifier. Generated from 8 random bytes. Used in the student-facing URL. |
| `teacher_token` | string (24 hex chars) | Secret token for the educator URL. Generated from 12 random bytes. Never exposed to students. |
| `title` | string (max 200 chars) | Quiz title, set by the educator at upload time. |
| `created_at` | ISO 8601 datetime | Timestamp of quiz creation. |
| `expires_at` | ISO 8601 datetime or `null` | Auto-deletion date. Default: 1 month after `created_at`. Set to `null` for no auto-deletion. Editable by the educator via the stats page. |
| `questions` | array of Question | Ordered list of questions (see 3.2). |
| `stats` | Stats object | Aggregated anonymous statistics (see 3.3). |

### 3.2 Question Object

| Field | Type | Description |
|---|---|---|
| `question` | string | The question text. May contain line breaks. |
| `correct` | string | The single correct answer (exact text). |
| `distractors` | array of string | 1 to 5 incorrect answer options. |
| `explanation` | string | Explanatory text shown to students after submission. May be empty. |

### 3.3 Stats Object

| Field | Type | Description |
|---|---|---|
| `attempts` | integer | Total number of completed quiz submissions. |
| `questions` | array of QuestionStats | One entry per question, in the same order as `questions`. |

### 3.4 QuestionStats Object

| Field | Type | Description |
|---|---|---|
| `correct_count` | integer | How many times this question was answered correctly. |
| `total_count` | integer | How many times this question was answered in total. |
| `choice_counts` | map (string → integer) | Keys are the exact answer texts (correct and distractors). Values are how often each was selected. |
| `time_spent_sum` | float | Sum of all individual times-on-task for this question, in seconds. Used to compute the average: `time_spent_sum / total_count`. |
| `time_spent_max` | float | Maximum time any single student spent on this question, in seconds. |

### 3.5 Stats-Level Fields (in addition to 3.3)

| Field | Type | Description |
|---|---|---|
| `attempts` | integer | Total number of completed quiz submissions. |
| `questions` | array of QuestionStats | One entry per question, in the same order as `questions`. |
| `focus_lost_sum` | integer | Sum of all focus-loss events across all attempts. Used to compute average: `focus_lost_sum / attempts`. |
| `focus_lost_max` | integer | Maximum number of focus-loss events in any single attempt. |

### 3.6 What Is NOT Stored

The following must **never** be stored in the quiz record or any other persistent store:

- Individual answer sequences (which student chose what)
- Individual per-question timings (only sums and maxima are stored; individual values cannot be reconstructed)
- Individual focus-loss counts per attempt (only the sum and maximum across all attempts are stored)
- IP addresses
- User-agent strings
- Session identifiers or tokens
- Timestamps of individual submissions
- Cookies or any cross-session identifier

The system stores only the **aggregate counters and sums** listed above. It must be technically impossible to reconstruct individual submissions from the stored data.

---

## 4. Functional Requirements

### 4.1 Quiz Creation (Educator Flow)

#### FR-01: Upload Form

The landing page displays a form with two fields:

- **Quiz title** (text input, required, max 200 characters)
- **Quiz file** (file input, required, accepts `.xlsx` and `.csv`)

Maximum upload size: 2 MB (configurable).

#### FR-02: File Parsing

The system must parse two formats:

**Excel (.xlsx):**
- Read the first worksheet only.
- Handle both shared-string and inline-string cell storage (these are the two ways Excel and libraries store text in the XML).
- Use only built-in capabilities (e.g., ZIP + XML parsing). No external spreadsheet library.

**CSV:**
- Auto-detect delimiter: semicolon (common in European locales) vs. comma.
- Handle UTF-8 encoding. Tolerate BOM.

**Column layout (both formats):**

| Column | Content |
|---|---|
| A (first) | Question text |
| B (second) | Correct answer |
| C through second-to-last | Distractors (1 to 5 columns; empty cells are ignored) |
| Last column | Explanation |

- Row 1 is a header row (ignored for content, used to determine column count).
- Row 2+ are questions.
- Fully empty rows are skipped.

#### FR-03: Validation

The parser must reject files with clear errors:

- Fewer than 4 columns (need at least: question, correct, one distractor, explanation).
- A row with an empty question or empty correct answer.
- A row with zero non-empty distractor cells.
- Fewer than 2 rows (header + at least one question).

Error messages must be user-facing and specific (e.g., "Row 5: Question and correct answer are required.").

#### FR-04: Quiz Storage

On successful parsing:

1. Generate `id` (64-bit random, hex-encoded → 16 chars).
2. Generate `teacher_token` (96-bit random, hex-encoded → 24 chars).
3. Initialize empty stats (all counters at 0).
4. Persist the quiz record.
5. Redirect to the share page.

#### FR-05: Share Page

Displays two sections:

**Student section:**
- Public quiz URL: `{base}/quiz.php?id={id}` (or equivalent route).
- QR code encoding this URL (see section 9).
- Copy-to-clipboard button.
- "Preview quiz" link (opens student quiz in new tab).

**Teacher section:**
- Private results URL: `{base}/stats.php?id={id}&t={teacher_token}` (or equivalent).
- QR code encoding this URL.
- Copy-to-clipboard button.
- "Open results" link (opens stats page in new tab).
- Clear visual warning that this link must not be shared with students.

The share page itself is protected by the teacher token (URL parameter `t`). Access without a valid token returns 404.

#### FR-06: Downloadable Templates

The landing page offers download links for:

- An Excel template (`.xlsx`) with a formatted header row and 3 sample questions.
- A CSV template (`.csv`) with the same content.

---

### 4.2 Quiz Taking (Student Flow)

#### FR-07: Quiz Access

Students access the quiz via `{base}/quiz.php?id={id}` (or equivalent). No authentication. If the ID is invalid, show a user-friendly error.

#### FR-08: One Question at a Time

Questions are presented **one at a time** in a stepper interface:

- A **progress indicator** shows the current question number, total count, and a visual progress bar (e.g., "Question 3 of 8" with a filled bar at 37.5%).
- **Previous** and **Next** buttons navigate between questions.
- The **Previous** button is hidden on the first question.
- The **Next** button is replaced by a **Submit** button on the last question.
- Clicking **Next** without selecting an answer shows a validation message and does not advance.
- Clicking **Submit** without selecting an answer on the last question shows a validation message and does not submit.
- The entire quiz is a single HTML form. All answers are submitted at once (not question by question).
- Navigating back to a previous question preserves the previously selected answer.

#### FR-08a: Immediate Correctness Feedback and Delay on Incorrect Answers

When the student selects an answer and clicks **Next** (or **Submit** on the last question):

1. The system **checks the answer against the correct answer client-side** and provides immediate visual feedback:
   - **Correct answer:** The selected choice is briefly highlighted in green (✓). The student can proceed immediately (or the quiz auto-advances after a short visual confirmation, e.g., 0.5 seconds).
   - **Incorrect answer:** The selected choice is highlighted in red (✗) and the correct answer is revealed in green (✓). The **Next / Submit button is disabled for 4 seconds**, during which a visible countdown is shown (e.g., "Wait 4s…", "3s…", "2s…", "1s…"). After the delay, the button re-enables and the student can proceed.

2. This behavior serves two pedagogical purposes:
   - Students who answer correctly experience a flow state with minimal interruption.
   - Students who answer incorrectly are forced to pause and look at the correct answer, preventing rapid guessing through the quiz without engagement.

3. **Implementation note:** Since the correct answer must be known client-side for instant checking, the correct answers are embedded in the page (e.g., in hidden fields or a JavaScript data structure). This is acceptable because Quoodle is a formative self-assessment tool, not a summative exam. A student who inspects the page source to find answers defeats only their own learning. The spec explicitly accepts this trade-off.

4. **During the 4-second delay**, the student may review the correct answer but cannot change their selection for that question. The answer is locked once confirmed.

5. The delay duration (4 seconds) should be defined as a configurable constant, not hardcoded in multiple places.

#### FR-08b: Time-on-Task Tracking

The client must track the **time spent on each question** in seconds (wall-clock time from the moment the question becomes visible to the moment the student confirms their answer by clicking Next/Submit).

- Time is measured per question using JavaScript (e.g., `performance.now()` or `Date.now()`).
- Time spent during the 4-second incorrect-answer delay counts toward the question's time.
- If the student navigates back to a previous question and changes their answer, the additional time is added to that question's total (cumulative).
- Per-question times are stored in hidden form fields (e.g., `<input type="hidden" name="times[0]" value="12.3">`) and submitted with the form.
- Times are recorded in seconds with one decimal place precision.

#### FR-08c: Focus-Loss Tracking

The client must track how many times the student **switches away from the quiz** during the attempt:

- A focus-loss event is triggered whenever the browser tab or window loses focus. Use the [Page Visibility API](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API) (`document.visibilitychange` event) or the `window.blur` event.
- The total count of focus-loss events for the entire attempt is stored in a hidden form field (e.g., `<input type="hidden" name="focus_lost" value="3">`) and submitted with the form.
- Only the count is tracked, not the timestamps or durations of the focus losses.
- The time during which the tab is not visible does **not** count toward the per-question time-on-task (the timer should pause when the tab is hidden and resume when it becomes visible again).

#### FR-09: Answer Shuffling

On each page load, the order of answer choices (correct + distractors) for each question is randomized. The shuffle must be different across page loads so that sharing an answer-position sequence ("A, C, B, D") is ineffective.

#### FR-10: Submission and Grading

On form submission:

1. For each question, compare the submitted answer text to the stored correct answer (exact string match).
2. Count correct answers.
3. Record the attempt in the stats (see FR-14).
4. Display the feedback page (see FR-11).

The submission is a standard POST request. No JavaScript-based submission is required (the form must work without JS for the submission step, even if the stepper UI requires JS).

#### FR-11: Feedback Page

After submission, the student sees:

**Score banner:**
- Large display: "{correct} / {total}" (e.g., "5 / 8").
- Percentage: "{percent}% correct."

**Attempt summary (below score banner):**
- **Total time:** The sum of all per-question times, formatted as minutes and seconds (e.g., "Total time: 4 min 23 s").
- **Focus losses:** The number of times the student switched away from the quiz tab (e.g., "Tab switches: 2"). If zero, this line may be omitted or shown as "Tab switches: 0 ✓".

**Per-question feedback (all questions, scrollable):**

For each question:
- Question number and a status badge ("Correct" in green or "Incorrect" in red).
- **Time spent** on this question (e.g., "18.4 s"), displayed as a subtle label next to the question number.
- The question text.
- All answer choices listed. The correct answer is highlighted in green with a ✓ marker. If the student's answer was wrong, it is highlighted in red with a ✗ marker and labeled "your answer." Unchosen distractors are shown in neutral styling.
- The educator's explanation, if non-empty, displayed in a visually distinct block (e.g., blue-bordered callout).

A "Try again" link returns to the quiz.

---

### 4.3 Analytics (Educator Flow)

#### FR-12: Results Page

Accessible at `{base}/stats.php?id={id}&t={token}` (or equivalent). Access without a valid token returns 404.

**Summary cards (top):**
- Total attempts (count).
- Number of questions.
- Average percentage correct across all questions (or "—" if no attempts yet).
- Average time per attempt (sum of all per-question times, averaged across attempts), formatted as "Ø {m} min {s} s".
- Average tab switches per attempt (e.g., "Ø 1.3 tab switches"). If the average is ≤ 0.5, display in green; if > 2, display in amber/red as a signal that many students may have looked up answers.

**Per-question breakdown:**

For each question:
- Question number and text.
- Fraction and percentage correct (e.g., "34/56 · 61%").
- **Average time on this question** (e.g., "Ø 14.2 s") and **maximum time** (e.g., "max 48.1 s"), displayed as subtle labels.
- A horizontal bar visualizing the correct percentage, color-coded:
  - Green: ≥ 75%
  - Amber/orange: 50–74%
  - Red: < 50%
- A **per-choice breakdown** listing every answer option:
  - A ✓ marker for the correct answer.
  - The answer text.
  - A mini progress bar showing the share of students who chose it.
  - Count and percentage (e.g., "23 · 41%").
  - The correct answer's row is visually highlighted (e.g., green background).

**Empty state:** If no attempts have been submitted yet, show a friendly message instead of the breakdown.

**Action buttons:**
- Refresh (reloads the page to see new submissions).
- Open student quiz (link to the public quiz URL).
- Excel export (see FR-15).
- CSV export (see FR-16).
- Export buttons are only shown if at least one attempt has been recorded.

**Expiry management (below action buttons or in a sidebar/card):**
- Current expiry date with countdown (see FR-18, display rules 8–9).
- Controls to change the expiry: date input and/or quick buttons (+1 month, +3 months, never). See FR-18.

**Danger zone (visually separated, at the bottom):**
- Delete quiz button (see FR-17).

#### FR-13: Results Page — Unknown Answers

If the `choice_counts` map contains keys that don't match any known answer option (defensive case — should not occur in normal operation), display them at the bottom of the question's breakdown with a "?" marker and "(unknown answer)" label.

#### FR-14: Stats Recording

When a student submits a quiz:

1. Increment `stats.attempts` by 1.
2. For each question:
   - Increment `questions[i].total_count` by 1.
   - If the answer was correct, increment `questions[i].correct_count` by 1.
   - Increment `questions[i].choice_counts[answer_text]` by 1 (create the key if it doesn't exist).
   - Add the submitted per-question time to `questions[i].time_spent_sum`.
   - Update `questions[i].time_spent_max` if the submitted time exceeds the current maximum.
3. Add the submitted focus-loss count to `stats.focus_lost_sum`.
4. Update `stats.focus_lost_max` if the submitted count exceeds the current maximum.
5. Persist the updated quiz record.

**Input validation:** The server must validate the submitted time and focus values. Times must be non-negative numbers; the focus count must be a non-negative integer. Invalid or missing values should be treated as 0 (do not reject the entire submission).

**Concurrency:** Multiple students may submit simultaneously (e.g., 200 students in a lecture hall). The update mechanism must be atomic or use locking to prevent lost updates. In a file-based implementation, use exclusive file locks. In a database implementation, use transactions or atomic increments.

**Best effort:** Stats recording must not block or fail the student's feedback page. If recording fails (e.g., due to a transient lock timeout), the student should still see their feedback.

---

### 4.4 Data Export (Educator Flow)

#### FR-15: Excel Export

Endpoint: `{base}/export?id={id}&t={token}&format=xlsx` (or equivalent). Protected by teacher token.

Generates an `.xlsx` file with three worksheets:

**Sheet 1: "Summary"**

| Field | Value |
|---|---|
| Title | {quiz title} |
| Total attempts | {number} |
| Number of questions | {number} |
| Average correct (%) | {number} |
| Average time per attempt | {seconds} |
| Average tab switches per attempt | {number} |
| Maximum tab switches in a single attempt | {number} |
| Created | {date} |

**Sheet 2: "Questions"**

| Nr. | Question | Correct answer | Attempts | Correct | Correct (%) | Avg time (s) | Max time (s) |
|---|---|---|---|---|---|---|---|
| 1 | {text} | {text} | {n} | {n} | {pct} | {avg} | {max} |

**Sheet 3: "Answer Options"**

| Question Nr. | Question | Answer | Is correct | Count | Share (%) |
|---|---|---|---|---|---|
| 1 | {text} | {text} | yes/no | {n} | {pct} |

One row per answer option per question. Includes both the correct answer and all distractors.

The `.xlsx` must be generated without external libraries (use the ZIP + XML approach — an `.xlsx` is a ZIP archive containing XML files).

Header rows should be bold. The filename should follow the pattern: `Quoodle_{sanitized_title}_{date}.xlsx`.

#### FR-16: CSV Export

Same data as the Excel export, but in a single CSV file with section headers. Use semicolon as delimiter (European locale compatibility). Include a UTF-8 BOM (`\xEF\xBB\xBF`) so Excel auto-detects the encoding.

Filename: `Quoodle_{sanitized_title}_{date}.csv`.

### 4.5 Quiz Lifecycle (Educator Flow)

#### FR-17: Manual Quiz Deletion

The stats page (educator view) must include a **Delete quiz** action. Behavior:

1. The delete button is visually separated from the other action buttons (e.g., placed at the bottom of the page, styled as a danger/destructive action — red outline or red text, not a primary button).
2. Clicking the button shows a **confirmation step** before deletion. Two acceptable patterns:
   - A confirmation dialog (JavaScript `confirm()` is acceptable for simplicity), or
   - An inline confirmation panel that replaces the button with "Are you sure? This cannot be undone." and two buttons: "Yes, delete" (destructive) and "Cancel."
3. Upon confirmation, the server:
   - Verifies the teacher token (same authorization as the stats page).
   - Deletes the quiz record (e.g., removes the JSON file).
   - Redirects to the landing page with a brief success message (e.g., flash message or URL parameter `?deleted=1`).
4. After deletion:
   - The student quiz URL returns a 404 ("quiz not found").
   - The teacher stats URL returns a 404.
   - The share page URL returns a 404.
   - Any export URLs return a 404.
5. Deletion is **permanent and irreversible**. There is no recycle bin or undo.

#### FR-18: Auto-Deletion Date

Each quiz has an expiration date (`expires_at`) that controls automatic deletion.

**Setting the expiry:**

1. At creation time, `expires_at` is set to **1 month (30 days) after `created_at`** by default.
2. On the stats page, the educator sees the current expiry date and can:
   - **Extend** it (e.g., by selecting a new date via a date input, or by clicking a "+1 month" / "+3 months" / "+1 year" quick button).
   - **Remove** it entirely (set to "never expires" / `null`).
   - **Shorten** it (set it to an earlier date, including today for immediate expiration on next cleanup).
3. Changing the expiry date requires the teacher token (same authorization as the stats page). The change is submitted as a POST request and the page reloads with the updated value.
4. On the share page, the expiry date is also displayed (read-only) so the educator is aware when creating the quiz.

**Enforcement:**

5. **On access (lazy check):** Whenever `load_quiz(id)` is called, the system checks whether `expires_at` is non-null and in the past. If so, the quiz file is deleted immediately and the function returns null (quiz not found). This ensures that expired quizzes are cleaned up without requiring a cron job or background process.
6. **Periodic sweep (optional, recommended):** On the landing page, the system may additionally scan the data directory for expired quiz files and delete them. To avoid performance overhead on every page load, this sweep should be rate-limited (e.g., at most once per hour, tracked via a simple timestamp file like `data/.last_sweep`).
7. **Grace period:** There is no grace period. Once `expires_at` passes, the next access triggers deletion.

**Display:**

8. On the stats page, the expiry date is shown in a clearly visible location (e.g., near the quiz title or in the summary cards area). The display must include:
   - The expiry date formatted in the user's locale (e.g., "15. Juli 2026" / "July 15, 2026").
   - A human-readable countdown (e.g., "in 23 days" / "in 23 Tagen"), color-coded:
     - Green: more than 14 days remaining.
     - Amber: 3–14 days remaining.
     - Red: fewer than 3 days remaining.
     - If `null`: display "No expiry" / "Kein Ablaufdatum" in neutral styling.
9. On the share page, the expiry date is shown as a read-only note below the quiz title (e.g., "This quiz expires on July 15, 2026.").

---

## 5. Security Requirements

#### SEC-01: Quiz ID Entropy

Quiz IDs must be generated from a cryptographically secure random source (e.g., `/dev/urandom`, `crypto.getRandomValues`, `random_bytes`). Minimum: 64 bits (16 hex characters).

#### SEC-02: Teacher Token Entropy

Teacher tokens must be generated from a cryptographically secure random source. Minimum: 96 bits (24 hex characters). The token is separate from and independent of the quiz ID.

#### SEC-03: Path Traversal Prevention

Quiz IDs must be validated against a strict pattern (e.g., `^[a-f0-9]{16}$`) before being used in any filesystem or database operation. No user-supplied string may be used directly in a file path.

#### SEC-04: Timing-Safe Token Comparison

Teacher token comparison must use a constant-time comparison function (e.g., `hash_equals`, `crypto.timingSafeEqual`) to prevent timing attacks.

#### SEC-05: Output Encoding

All user-supplied content rendered in HTML must be escaped to prevent XSS. This includes quiz titles, question texts, answer texts, and explanations.

#### SEC-06: Upload Validation

- Only `.xlsx` and `.csv` file extensions are accepted.
- Maximum file size: 2 MB (configurable).
- The file content is parsed and validated; the raw upload is not persisted.

#### SEC-07: Internal Directories

Directories containing application code, data files, and language files must not be directly accessible via the web server. Use web server configuration (e.g., `.htaccess`, nginx `deny` rules) or place them outside the document root.

#### SEC-08: No SQL

If a database is used, parameterized queries are required. The reference implementation uses no database at all, eliminating SQL injection by construction.

---

## 6. Privacy Requirements

#### PRIV-01: No Personal Data in Quiz Records

Quiz records must never contain: IP addresses, user-agent strings, session IDs, timestamps of individual submissions, usernames, email addresses, or any other personally identifiable information.

#### PRIV-02: Aggregate-Only Statistics

Only the counters defined in section 3.3 and 3.4 are stored. It must be impossible to reconstruct which specific combination of answers belongs to a single submission.

#### PRIV-03: No Tracking Cookies

The application must not set tracking cookies, session cookies, or analytics cookies. The only permitted cookies are:

- `lang` — stores the user's language preference (`de` or `en`). Max-age: 1 year.
- `theme` — stores the user's theme preference (`light` or `dark`). Max-age: 1 year.

Both cookies contain no personal data and are not used for identification.

#### PRIV-03a: Time and Focus Data

Per-question time-on-task and focus-loss counts are collected client-side and submitted with the quiz form. On the server, only **aggregate values** are stored (sums and maxima across all attempts). Individual per-attempt time profiles and focus-loss counts are displayed to the submitting student on their feedback page but are **not persisted**. It must be impossible to reconstruct an individual student's timing or focus pattern from the stored aggregates.

#### PRIV-04: No External Requests

During normal operation, the application must not make or trigger any HTTP requests to external servers. This includes:

- No CDN-hosted scripts, fonts, or stylesheets.
- No externally hosted QR code generators.
- No analytics services.
- No tracking pixels.

All assets (CSS, JS, fonts, QR codes) must be served from the same origin or generated inline.

#### PRIV-05: Privacy Policy Template

The application must include a template privacy policy that accurately describes its actual data processing behavior. The template must clearly state:

- What data is stored (anonymous counters only).
- What cookies are set and why.
- That no external services are contacted.
- That no personal data is collected from students.
- Placeholders for operator-specific information (name, address, hosting provider).

#### PRIV-06: Legal Notice Template

The application must include a template legal notice (Impressum) with clearly marked placeholders for operator details.

---

## 7. User Interface Specifications

### 7.1 Layout

- Maximum content width: 760px (880px for the stats page).
- Centered layout with comfortable padding.
- Mobile-responsive: all pages must be usable on a 320px-wide screen.
- No framework dependency (no Bootstrap, Tailwind build step, etc.). Plain CSS.

### 7.2 Header

Fixed across all pages. Contains:

- **Brand mark** (left): Letter "Q" in a small colored square + "Quoodle" text. Links to the landing page.
- **Navigation** (right): "New quiz" link, language switcher (see 8.2), dark mode toggle (see 7.6).

### 7.3 Footer

Fixed across all pages. Contains:

- Links to legal notice and privacy policy.
- Tagline: "Quoodle — runs on plain PHP, no database required." (or equivalent in current language).

### 7.4 Card Component

Primary content container. White background (dark: dark surface), subtle border, small border-radius (12px), minimal shadow. Padding: ~32px. Margin-bottom between cards: ~20px.

### 7.5 Color Palette (Light Mode)

| Variable | Value | Usage |
|---|---|---|
| Background | `#f7f8fa` | Page background |
| Surface | `#ffffff` | Cards, inputs |
| Border | `#e5e7eb` | Card borders, input borders |
| Text | `#111827` | Primary text |
| Text muted | `#6b7280` | Secondary text, hints |
| Primary | `#4f46e5` | Buttons, links, active elements |
| Primary hover | `#4338ca` | Button hover state |
| Primary soft | `#eef2ff` | Selected radio background |
| Success | `#15803d` | Correct answer text |
| Success bg | `#f0fdf4` | Correct answer background |
| Danger | `#b91c1c` | Incorrect answer text |
| Danger bg | `#fef2f2` | Incorrect answer background |
| Info bg | `#eff6ff` | Explanation block background |

### 7.6 Dark Mode

A complete dark palette must be provided. Requirements:

- All colors defined as CSS custom properties (variables), switched via a `data-theme="dark"` attribute on `<html>`.
- The theme preference is stored in a cookie (`theme`).
- **FOUC prevention:** An inline `<script>` in the `<head>` must read the cookie and set the `data-theme` attribute *before* the first paint. This prevents a flash of the light theme on page load.
- A toggle button in the header switches between light and dark mode.
- The toggle button shows a sun icon (☀) in light mode and a moon icon (☾) in dark mode.
- QR codes must retain a white background in dark mode (otherwise they are not scannable).
- Contrast ratios should meet WCAG AA (4.5:1 for normal text, 3:1 for large text).

### 7.7 Typography

- System font stack: `-apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", Roboto, Helvetica, Arial, sans-serif`.
- Base size: 16px. Line height: 1.55.
- Headings: tight letter-spacing (`-0.015em`), semibold/bold.
- Monospace (for URLs, code): `ui-monospace, SFMono-Regular, Menlo, monospace`.

### 7.8 Interactive Elements

**Buttons:**
- Primary: filled with primary color, white text, 8px border-radius.
- Secondary: white/surface background, border, dark text.
- Ghost: transparent background, border, used for "Previous" in stepper.

**Radio buttons (quiz choices):**
- Each choice is a bordered card-like row with the radio input and text.
- Hover: subtle background change.
- Selected: primary-colored border and soft primary background (via CSS `:has(input:checked)`; provide JS fallback for older browsers).

**Copy buttons:**
- On click: copy URL to clipboard, change button text to "Copied" / "Kopiert" for 1.5 seconds, then revert.

---

## 8. Internationalization (i18n)

### 8.1 Architecture

All user-facing strings are externalized into language files. The application ships with two languages: **German (de)** and **English (en)**.

A translation function `t(key)` returns the translated string for the current language. A plural function `tp(key, n)` handles singular/plural forms. The language file format uses pipe-separated forms: `"question|questions"`.

### 8.2 Language Detection Cascade

Language is determined in this order of priority:

1. **URL parameter** `?lang=xx` — if present and supported, sets a cookie and uses this language.
2. **Cookie** `lang` — if present and supported, uses this language.
3. **`Accept-Language` HTTP header** — first supported language found.
4. **Fallback:** `de` (German).

### 8.3 Language Switcher

In the page header, both language codes are displayed (e.g., "DE EN"). The active language is bold/non-clickable. The inactive language is a link that adds `?lang=xx` to the current URL, preserving all other query parameters (e.g., `?id=...&t=...&lang=en`).

### 8.4 Scope

The following pages are fully translated: landing page, upload errors, share page, quiz page (including stepper UI), feedback page, stats page, header, and footer.

The legal pages (Impressum, Datenschutzerklärung) remain in German only, as they address German legal requirements.

### 8.5 Adding Languages

Adding a new language must require only creating a new language file (e.g., `fr.php` or `fr.json`) and adding the language code to the list of supported languages. No other application code should need modification.

---

## 9. QR Code Generation

### 9.1 Requirements

QR codes are generated **server-side** and embedded inline in the HTML (as SVG or inline image). No external service is contacted.

### 9.2 Specification

- Standard: ISO/IEC 18004.
- Mode: Byte mode (for URLs).
- Error correction: Level M (~15% recovery).
- Versions: 1–10 (sufficient for URLs up to ~270 characters).
- Output: SVG preferred (scalable, no image library required). PNG acceptable if SVG is not feasible.
- Quiet zone: 4 modules (per specification).
- Colors: dark modules in near-black (`#111827`), background white (`#ffffff`).

### 9.3 Sizing

- Student QR code: 240×240px display size.
- Teacher QR code: 180×180px display size.

---

## 10. File Format Specifications (Input)

### 10.1 Excel (.xlsx)

An `.xlsx` file is a ZIP archive containing XML. The parser must:

1. Open the ZIP.
2. Read `xl/sharedStrings.xml` (if present) to build the shared strings table.
3. Read the first worksheet (`xl/worksheets/sheet1.xml` or the first `sheet*.xml`).
4. Handle the default XML namespace (the spreadsheet namespace `http://schemas.openxmlformats.org/spreadsheetml/2006/main` must be handled correctly — either by namespace-aware parsing or by stripping the default namespace).
5. For each cell, determine the value based on the `t` attribute:
   - `t="s"` → shared string (lookup index in shared strings table).
   - `t="inlineStr"` → inline string (read `<is><t>` child).
   - `t="b"` → boolean.
   - No `t` attribute → numeric (read `<v>` as string).
6. Map cell references (e.g., "C5") to zero-based column indices.
7. Return a 2D array of trimmed strings.

### 10.2 CSV

1. Read the file as UTF-8.
2. Auto-detect delimiter: count semicolons vs. commas in the first line; use whichever is more frequent.
3. Parse using standard CSV rules (quoted fields, escaped quotes).
4. Trim all cell values.
5. Return a 2D array of strings (same shape as the Excel parser output).

---

## 11. Non-Functional Requirements

#### NFR-01: No Build Step

The application must be deployable by copying files to a server. No compilation, transpilation, bundling, or package installation may be required at deploy time.

#### NFR-02: No Runtime Dependencies

The application must not require any software beyond the language runtime and web server (e.g., PHP + Apache, or Node.js + Express, or Python + any WSGI server). No database server, no Redis, no message queue.

#### NFR-03: File Size

The total application size (all files, uncompressed) should be under 200 KB excluding downloadable templates.

#### NFR-04: Page Load Performance

All pages must load in under 2 seconds on a typical shared hosting environment. No heavy JavaScript frameworks. Minimal CSS (single file, under 25 KB).

#### NFR-05: Browser Support

Must work in the current and previous major version of: Chrome, Firefox, Safari, Edge, and Samsung Internet. Must be usable (core functionality) without JavaScript for the feedback page; the stepper UI on the quiz page may require JavaScript.

#### NFR-06: Mobile-First

All pages must be fully usable on mobile devices (minimum width: 320px). Touch targets must be at least 44×44px.

#### NFR-07: Concurrent Users

The stats recording mechanism must handle at least 200 simultaneous submissions without data loss (e.g., a full lecture hall submitting at once).

---

## 12. Acceptance Criteria / Test Scenarios

### TC-01: Happy Path — Complete Flow

1. Open landing page.
2. Upload the provided Excel template with 3 sample questions.
3. Verify: share page shows two QR codes and two URLs.
4. Open the student URL.
5. Answer all 3 questions (2 correct, 1 wrong).
6. Verify: feedback page shows "2 / 3" and "67% correct."
7. Verify: the incorrect question shows the correct answer in green and the student's wrong answer in red.
8. Verify: explanations are shown for all 3 questions.
9. Open the teacher URL.
10. Verify: stats page shows 1 attempt, and per-question counters match.
11. Repeat steps 4–6 with different answers.
12. Refresh the teacher URL. Verify: 2 attempts, updated counters.

### TC-02: CSV Upload

1. Upload the CSV template.
2. Verify: same behavior as Excel upload.

### TC-03: Invalid File

1. Upload a `.txt` file. Verify: error message, no quiz created.
2. Upload an `.xlsx` with only a header row. Verify: error message.
3. Upload an `.xlsx` with a row missing the correct answer. Verify: error message naming the row.

### TC-04: Stepper Navigation

1. Open a quiz with 5 questions.
2. Verify: only question 1 is visible. "Previous" is hidden.
3. Click "Next" without selecting an answer. Verify: validation message, no advance.
4. Select an answer, click "Next." Verify: question 2 appears.
5. Click "Previous." Verify: question 1 appears with the previously selected answer preserved.
6. Navigate to question 5. Verify: "Next" is replaced by "Submit."
7. Submit. Verify: feedback page shows all 5 answers.

### TC-05: Answer Shuffling

1. Open the same quiz URL twice (two tabs or page refreshes).
2. Verify: the order of answer choices for at least one question is different between the two loads.

### TC-06: Language Switching

1. Open the landing page. Verify: German is the default.
2. Click "EN" in the header. Verify: all UI text switches to English. URL contains `?lang=en`.
3. Navigate to another page. Verify: English is retained (cookie).
4. Open a new incognito window with a browser set to English locale. Verify: English is auto-detected.

### TC-07: Dark Mode

1. Open any page. Verify: light theme.
2. Click the theme toggle. Verify: dark theme. Cookie is set.
3. Refresh the page. Verify: dark theme persists. No flash of light theme.
4. Verify: QR codes on the share page have white backgrounds (scannable).

### TC-08: Concurrent Submissions

1. Create a quiz with 1 question.
2. Simulate 50 simultaneous form submissions (e.g., via a script or load testing tool).
3. Open the stats page. Verify: exactly 50 attempts recorded. No data lost.

### TC-09: Export

1. Create a quiz and submit 3 attempts with varying answers.
2. Download the Excel export. Verify: 3 worksheets with correct data.
3. Download the CSV export. Verify: opens correctly in Excel with proper encoding (umlauts, semicolons).

### TC-10: Security

1. Attempt to access `stats.php?id={valid_id}` without the `t` parameter. Verify: 404.
2. Attempt to access `stats.php?id={valid_id}&t=wrong_token`. Verify: 404.
3. Attempt path traversal: `quiz.php?id=../../etc/passwd`. Verify: 404 (ID validation rejects it).
4. Insert `<script>alert(1)</script>` as a quiz title. Verify: rendered as escaped text, not executed.

### TC-11: Teacher Token Non-Guessability

1. Note the student URL for a quiz (contains only `id`).
2. Verify: knowing the `id` does not allow constructing the teacher URL. The `teacher_token` is independent and generated from a separate random source.

### TC-12: Immediate Correctness Feedback

1. Open a quiz with 3 questions.
2. Select the **correct** answer for question 1. Click "Next."
3. Verify: the selected answer is briefly highlighted in green (✓). The quiz advances immediately (or after a brief ~0.5s visual confirmation).
4. Select an **incorrect** answer for question 2. Click "Next."
5. Verify: the selected answer is highlighted in red (✗). The correct answer is revealed in green (✓). The "Next" button is disabled.
6. Verify: a visible countdown ("4s…", "3s…", "2s…", "1s…") is shown.
7. Verify: the "Next" button re-enables after exactly 4 seconds.
8. Click "Next." Verify: question 3 appears.

### TC-13: Delay — Cannot Bypass

1. On an incorrect answer, while the countdown is active, verify:
   - Clicking the disabled "Next" button does nothing.
   - Pressing Enter does not advance.
   - The student cannot change their answer selection for this question.
2. After the countdown completes, verify the student can proceed normally.

### TC-14: Time-on-Task Tracking

1. Open a quiz with 2 questions.
2. Spend approximately 10 seconds on question 1 (select an answer and click Next).
3. Spend approximately 5 seconds on question 2 (select an answer and submit).
4. Verify: the feedback page shows per-question times approximately matching (within ±2s of the actual time spent).
5. Verify: the feedback page shows a total time that is the sum of both per-question times.
6. Open the stats page. Verify: average time per question is populated and plausible.

### TC-15: Time Pauses on Tab Switch

1. Open a quiz. Start question 1.
2. Switch to another browser tab for 10 seconds.
3. Switch back. Select an answer and proceed.
4. Verify: the time reported for question 1 does **not** include the 10 seconds spent on the other tab.

### TC-16: Focus-Loss Tracking

1. Open a quiz with 3 questions.
2. Switch away from the tab 3 times during the quiz (at any point).
3. Submit the quiz.
4. Verify: the feedback page shows "Tab switches: 3."
5. Open the stats page. Verify: the focus-loss aggregate is updated (e.g., if this is the only attempt, average = 3, max = 3).

### TC-17: Export Includes Time and Focus Data

1. Create a quiz. Submit 2 attempts with different timings.
2. Download the Excel export.
3. Verify: the Summary sheet includes average time per attempt and average/max tab switches.
4. Verify: the Questions sheet includes "Avg time (s)" and "Max time (s)" columns with plausible values.

### TC-18: Manual Quiz Deletion

1. Create a quiz. Note the student URL and teacher URL.
2. Open the stats page (teacher URL).
3. Click "Delete quiz."
4. Verify: a confirmation prompt appears. Cancel it. Verify: the quiz still exists.
5. Click "Delete quiz" again. Confirm.
6. Verify: redirected to the landing page with a success message.
7. Open the student URL. Verify: 404 (quiz not found).
8. Open the teacher stats URL. Verify: 404.
9. Open the share page URL. Verify: 404.

### TC-19: Manual Deletion Requires Token

1. Attempt to send a DELETE/POST request to the delete endpoint without a valid teacher token.
2. Verify: 404 (not 200 or 403 — do not reveal that the quiz exists).

### TC-20: Auto-Expiry Default

1. Create a quiz.
2. Open the stats page.
3. Verify: the expiry date is displayed and is approximately 30 days in the future.
4. Verify: the countdown is shown in green (>14 days).

### TC-21: Auto-Expiry Enforcement

1. Create a quiz. Manually edit the quiz file to set `expires_at` to a past date (e.g., yesterday).
2. Open the student quiz URL. Verify: 404 (quiz auto-deleted on access).
3. Verify: the quiz file no longer exists on disk.

### TC-22: Extend Expiry

1. Create a quiz. Open the stats page.
2. Click "+1 month." Verify: the expiry date updates to ~60 days from creation.
3. Click "Never." Verify: the expiry shows "No expiry" / "Kein Ablaufdatum."
4. Set a specific date via the date picker. Verify: the expiry updates accordingly.

### TC-23: Expiry Display on Share Page

1. Create a quiz. Open the share page.
2. Verify: the expiry date is shown as a read-only note (e.g., "This quiz expires on [date]").

---

## 13. Pages Summary

| Route | Access | Purpose |
|---|---|---|
| `/` or `/index` | Public | Landing page with upload form and format documentation. |
| `/upload` | POST only | Processes file upload, creates quiz, redirects to share page. |
| `/share?id=X&t=T` | Teacher token | Displays both URLs and QR codes. |
| `/quiz?id=X` | Public | Student-facing quiz with stepper navigation. |
| `/submit` | POST only | Grades answers, records stats, displays feedback. |
| `/stats?id=X&t=T` | Teacher token | Analytics dashboard with per-question breakdown. |
| `/stats` (POST actions) | Teacher token | Updates expiry date (`action=set_expiry`) or deletes quiz (`action=delete`). |
| `/export?id=X&t=T&format=xlsx` | Teacher token | Downloads results as Excel. |
| `/export?id=X&t=T&format=csv` | Teacher token | Downloads results as CSV. |
| `/impressum` | Public | Legal notice (template). |
| `/datenschutz` or `/privacy` | Public | Privacy policy (template). |

---

## 14. Glossary

| Term | Definition |
|---|---|
| **Distractor** | An incorrect answer option in a single-choice question, designed to be plausible. |
| **Expiry date** | The date after which a quiz is automatically deleted on next access. Default: 30 days after creation. Can be changed or removed by the educator. |
| **Focus loss** | An event where the student's browser tab or window loses visibility (e.g., switching to another tab, minimizing the window). Tracked via the Page Visibility API. |
| **Formative assessment** | Low-stakes evaluation used to monitor learning and provide feedback, not for grading. |
| **Stepper** | A UI pattern showing one item at a time with forward/back navigation and a progress indicator. |
| **Teacher token** | A secret random string that grants access to the analytics and export pages for a specific quiz. |
| **Time-on-task** | Wall-clock time a student spends actively viewing and answering a specific question, excluding time when the tab is not visible. |
| **FOUC** | Flash of unstyled content — a brief display of default styling before the intended styles load. |

---

*End of specification.*
