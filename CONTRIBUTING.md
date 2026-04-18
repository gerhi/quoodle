# Contributing to Quoodle

Thank you for considering a contribution to Quoodle! This document explains
how to report bugs, propose patches, and get support.

Issues and pull requests are accepted in **English or German**; the project
documentation is primarily English, but German comments and discussions are
welcome.

---

## Contents

- [How do I get help?](#how-do-i-get-help)
- [Reporting a bug](#reporting-a-bug)
- [Proposing a feature](#proposing-a-feature)
- [Contributing code](#contributing-code)
- [Development environment](#development-environment)
- [Coding style](#coding-style)
- [Tests](#tests)
- [Commit messages](#commit-messages)
- [Translations](#translations)
- [Reporting security vulnerabilities](#reporting-security-vulnerabilities)
- [Code of conduct](#code-of-conduct)
- [License of your contributions](#license-of-your-contributions)

---

## How do I get help?

- **Usage questions** (How do I deploy this on my shared host? Why isn't
  Quoodle recognising all columns in my spreadsheet?) → open a
  [Discussion](https://github.com/gerhi/quoodle/discussions).
- **Bug suspicion** → open an [Issue](https://github.com/gerhi/quoodle/issues).
- **Security vulnerabilities** → *not publicly*; see
  [Reporting security vulnerabilities](#reporting-security-vulnerabilities).

---

## Reporting a bug

Before opening an issue:

1. **Search existing issues**, including closed ones. The problem may
   already have been discussed.
2. **Reproduce against the current `main` branch.** The bug may already
   be fixed.
3. **Run `diagnose.php`** (if Quoodle is deployed) and check that every
   environment check is green. Many "bugs" turn out to be missing PHP
   extensions or incorrect file permissions.

A good bug report contains:

- **Quoodle version** (Git tag or commit hash).
- **PHP version** and enabled extensions (`php -m`).
- **Operating system** and web server (Apache / Nginx / PHP built-in).
- **Step-by-step reproduction**.
- **Expected vs. actual behaviour**.
- **Screenshots** or console output if relevant.
- **Contents of the host's `error_log`** if accessible.

If the bug concerns an uploaded file: attach an **anonymised test file**
that triggers the problem. Please no real student or teacher data.

---

## Proposing a feature

Quoodle has a deliberately narrow scope. Before investing effort in a
patch, verify that your proposal aligns with the project's
[design philosophy](README.md#what-quoodle-is-not):

- **Likely in scope:** better error messages, additional languages,
  accessibility improvements, more robust parsers, additional export
  formats.
- **Likely out of scope:** user accounts, live-quiz mode with real-time
  scoreboards, integration with external services (CDNs, analytics,
  cloud storage), outsourcing QR code generation, any feature that
  requires tracking.

When in doubt: open a **Discussion** first and sketch out the idea
before writing code. This avoids the unfortunate situation of
submitting a substantial pull request that has to be rejected on
scope grounds.

---

## Contributing code

### Workflow

1. **Fork** the repository on GitHub.
2. **Clone** your fork locally.
3. **Create a branch** off `main` with a descriptive name:
   - `fix/csv-parser-bom-handling`
   - `feat/french-translation`
   - `docs/readme-deployment-section`
4. **Commit** your changes (see [Commit messages](#commit-messages)).
5. **Write tests** if your patch changes logic.
6. **Push** to your fork.
7. **Open a pull request** against `main` of the upstream repository.

### Pull request checklist

Before submitting:

- [ ] The code runs locally without errors (`diagnose.php` is green).
- [ ] You have manually tested the changed flow.
- [ ] Automated tests (if present) pass.
- [ ] The coding style (below) is respected.
- [ ] Existing features are not broken.
- [ ] The PR description explains **what** and **why**, not just how.
- [ ] Screenshots are included for UI changes.
- [ ] Language files are updated when new strings are introduced
      (both German **and** English).
- [ ] Obsolete files (`lib/layout.php`, `bootstrap.php`, similar) are
      not reintroduced.

### What will not be accepted

- Pull requests without an explanatory description.
- Mass-reformatting changes mixed into a substantive patch.
- Dependencies on external PHP libraries (Composer, etc.) — the
  project deliberately runs without a `composer.json`.
- Introduction of build steps (Node, npm, webpack, TypeScript
  compilation) — Quoodle must remain installable by copying files.
- Code that collects or stores personal data during quiz creation,
  participation, or analysis beyond the minimum described in
  `docs/quoodle-requirements.md`.
- Integration of external services (analytics, fonts, CDNs, tracking
  pixels).

---

## Development environment

The simplest local setup:

```bash
git clone https://github.com/gerhi/quoodle.git
cd quoodle
php -S localhost:8000 -t .
```

PHP's built-in web server is sufficient for development. Required:

- **PHP 8.0 or newer** with the extensions `pdo_sqlite`, `zip`,
  `simplexml`, `mbstring`.
- **A browser**, ideally with DevTools open for JavaScript debugging.

No IDE configuration is shipped — use what you know. PHPStorm, VS Code
(with PHP Intelephense), Vim — they all work.

For **quick testing**: upload one of the bundled templates from the
`templates/` folder to create a quiz, then use the teacher URL to see
the stats page.

---

## Coding style

The project follows a pragmatic variant of
[PSR-12](https://www.php-fig.org/psr/psr-12/). Key points:

- **4 spaces** indentation, no tabs.
- **`declare(strict_types=1);`** as the first line in every new
  `.php` file.
- **All output** is escaped via `e()` (from `lib/helpers.php`) — no
  exceptions.
- **SQL** exclusively via prepared statements (`$pdo->prepare()`).
- **Tokens and IDs** generated with `random_bytes()`, never
  `rand()` / `mt_rand()`.
- **Timing-safe comparison** via `hash_equals()` (or the wrapper
  `safe_token_compare()`), never `===` on tokens.
- **New functions** get a PHPDoc block documenting parameters, return
  value, and non-obvious side effects.
- **Comments** in English or German, but **consistent per file**. Do
  not migrate existing files to the other language as a side effect.
- **CSS:** custom properties instead of hard-coded colours. All colours
  live in `:root` and `[data-theme="dark"]`.
- **JavaScript:** vanilla JS, no framework, no build tools. Write
  ES2017-compatible code (runs in all reasonably recent browsers
  without transpilation).

---

## Tests

Automated tests are currently **sparse** and being built up. A patch
introducing a PHPUnit test suite would be very welcome.

Tests are wanted for:

- **`lib/qr.php`** — round-trip test: generate a QR code, decode it
  with an external library (permitted in test context only!),
  assert input == output.
- **`lib/csv_parser.php`** — edge cases: BOM detection, mixed
  delimiters, quoting with embedded quotes and line breaks, empty
  cells.
- **`lib/xlsx_parser.php`** — parse test against a known sample XLSX,
  assertion on the returned data structure.
- **`lib/xlsx_writer.php`** — build test, cross-checked by parsing
  the produced file.
- **`lib/db.php`** — `db_record_submission()` under concurrency
  (parallel processes), assertion that no updates are lost.

Manual tests before submitting a PR:

- [ ] Quiz upload with the bundled XLSX template
- [ ] Quiz upload with the bundled CSV template
- [ ] Quiz traversal with JavaScript enabled
- [ ] Quiz traversal **without** JavaScript (bulk-submit fallback)
- [ ] Teacher stats page with 0 submissions (empty state)
- [ ] Teacher stats page with several submissions
- [ ] XLSX export opens in LibreOffice/Excel without warnings
- [ ] CSV export opens in Excel with correct umlauts (BOM test)
- [ ] Dark mode toggle persists across page loads
- [ ] Language switch preserves all query parameters

---

## Commit messages

Short imperative summary (50 characters), blank line, then details if
needed. Examples:

```
Fix CSV parser to handle trailing empty lines

The parser previously threw on files with trailing \r\n, because
the empty final row was treated as a malformed question.
```

```
Add French translation

New lang/fr.php with full string coverage. Added 'fr' to
QUOODLE_LANGS in lib/i18n.php and a switcher entry in layout.php.
```

```
Prevent XSS in question titles on stats page

Question titles were echoed without escaping in one branch of
stats.php. All three branches now use e().
```

No [Gitmoji](https://gitmoji.dev/), no Conventional Commit prefixes —
just clear English or German.

---

## Translations

This is the easiest way to contribute!

1. Copy `lang/de.php` to `lang/XX.php` (XX = ISO 639-1 code for your
   language, e.g. `fr` for French).
2. Translate the right-hand side of each array entry. **Do not change
   the keys (left-hand side)!**
3. Add the language code to `QUOODLE_LANGS` in `lib/i18n.php`.
4. Add a switcher entry in `layout.php`.
5. Open a pull request.

Translation notes:

- **Plural strings** follow the format `"singular|plural"` — the pipe
  is the separator.
- **`%d`** and other `sprintf` placeholders must remain and stay in
  the correct order.
- **The legal pages** (`impressum.php`, `datenschutz.php`) are
  intentionally kept in German because they address German legal
  requirements. Do not translate them — each installation has to
  adapt these texts to its own operator details anyway.

---

## Reporting security vulnerabilities

**Please do not report security vulnerabilities through public GitHub
issues.**

Instead, email `quoodle@gerrithirschfeld.de` directly.

A security vulnerability could be, for example:

- Path traversal through file uploads or ID parameters.
- SQL injection (should not exist thanks to prepared statements, but a
  proof-of-concept is still valuable).
- XSS through missing escaping.
- Auth bypass that allows use of a teacher token without knowledge of
  it.
- Timing attacks on the token comparison.
- Leakage of teacher tokens through the HTTP `Referer` header or
  access logs.

You will typically receive a response within one week. We coordinate
disclosure and credit with you.

---

## Code of conduct

This project follows the
[Contributor Covenant v2.1](https://www.contributor-covenant.org/version/2/1/code_of_conduct/).
Respectful, friendly behaviour is expected — including toward
first-time contributors and non-native speakers.

Unacceptable behaviour can be reported to the maintainers at the
security contact address above. Violations may result in warnings, or
temporary or permanent exclusion from the project.

---

## License of your contributions

By submitting a pull request, you agree that your contribution is
released under the same license as the rest of the project (see
[LICENSE](LICENSE)).

---

Thank you for contributing!
