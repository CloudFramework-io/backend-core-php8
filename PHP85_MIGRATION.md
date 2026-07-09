# PHP 8.5 Compatibility Migration

**Branch:** `php85-migration` (from `development`, v8.4.51)
**Date:** 2026-07-09
**Verification toolchain:** PHP 8.5.8 (docker `php:8.5-cli`), PHP 8.4.13 / 8.3.19 / 8.1.32 (local), `php -l` with `error_reporting=E_ALL` over `src/`, `scripts/`, `install/` and root `run*.php` (292 files, vendor/ excluded).

## Executive summary

The framework codebase was swept against the official PHP 8.5 migration guide (deprecations + backward incompatible changes) plus the still-pending PHP 8.2–8.4 deprecations. After the fixes in this branch:

- **0 syntax errors and 0 compile-time deprecations** under PHP 8.5 (`php -l`, `E_ALL`) across the whole tree.
- **0 runtime deprecations** when bootstrapping `Core7` and loading the patched Twig classes under PHP 8.5.
- The tree still lints clean under PHP 8.4 (**the supported minimum version**) and 8.3 (the technical parse floor); it does NOT parse under PHP 8.1/8.2 — this was already true before this branch.

## Fixes applied (first-party code)

| Deprecation | Introduced in | Files | Fix |
|---|---|---|---|
| `case` statement terminated with `;` | 8.5 | `src/api/_dbmodels.php:22` | `case null;` → `case null:` |
| Predefined `$http_response_header` variable | 8.5 | `src/Core7.php` (CoreRequest), `src/class/Opauth.php`, `runapi.php` | Use `http_get_last_response_headers()` when available. Note: the 8.5 deprecation fires at **compile time** on any literal reference, so the PHP < 8.5 fallback reads the variable dynamically (`${'http_response_header'}`) — verified working on 8.1–8.4. |
| `curl_close()` / handles freed automatically | 8.5 | `src/Core7.php`, `src/class/Firebase.php`, `src/class/Opauth/TwitterStrategy.php`, `runapi.php` | Calls removed (`unset($handle)` / `= null`); `curl_close()` has been a no-op since PHP 8.0. |
| `utf8_encode()` / `utf8_decode()` | 8.2 | `src/api/_dsproxy.php` | Replaced with `mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1')` and inverse — byte-identical, wire-compatible with older framework versions. |
| Dead `create_function()` in comment block | (hygiene) | `src/class/CloudSQL.php` | Commented-out block removed. |
| Missing `php` constraint | (hygiene) | `composer.json` | Added `"php": ">=8.4"` (supported minimum per project policy). |

## Fixes applied (vendored libraries — minimal patch, no version upgrade)

Vendored **Twig 1.x** (`src/lib/Twig/`) and **Parsedown** (`src/lib/Parsedown.php`) concentrated all remaining compile-time deprecations (30 unique):

- **Implicit nullable parameters** (deprecated 8.4, fatal in PHP 9): explicit `?Type` added in 20 files (`Environment`, `Error`, `Error/Loader`, `NodeVisitor/SafeAnalysis`, `Node/{Embed,For,If,Include,Module}`, `Node/Expression/{Array,BlockReference,GetAttr,Test,Test/Defined}`, `Parser`, `Sandbox/*Error` ×3, `Test/NodeTestCase`, `Parsedown`).
  - Parameters positioned **before a required parameter** were converted to `?Type $x` **without** a default: adding `= null` there would trigger the separate "optional parameter before required" deprecation (empirically confirmed with `Node/Module.php`). PHP already treated those defaults as unusable, so behavior is unchanged.
- **Optional-before-required parameters**: unusable defaults removed in `Node/{Embed,Include}` (`$only`, `$ignoreMissing`), `Node/Expression/BlockReference` (`$asString`), `Node/Module` (`$parent`).
- **Tentative return types** (8.1): `#[\ReturnTypeWillChange]` added to `Util/TemplateDirIterator::{current,key}()`, `Markup::count()`, `Profiler/Profile::getIterator()`.
- **`Serializable` interface** (deprecated 8.1): `Profiler/Profile` now also implements `__serialize()`/`__unserialize()`, which silences the deprecation while keeping the legacy interface for backward compatibility.

## Findings — no action needed for 8.5

Verified clean by grep/lint against the full PHP 8.5 deprecation list: non-canonical casts (`(integer)` etc.), `settype`, `__sleep`/`__wakeup`, `mysqli_execute`, `MHASH_*`, `DATE_RFC7231`, backtick operator, `finfo_close`/`xml_parser_free`/`imagedestroy`/`socket_set_timeout`, `readdir`/`closedir` with null, `${var}` string interpolation (the only hits are shell text inside strings in `scripts/_setup.php`), `chr()`/`ord()` misuse, PDO driver-specific constants.

## Findings — pre-existing, out of scope for this branch

1. **Supported minimum PHP version is 8.4** (project policy); the technical parse floor is 8.3 (typed class constants in `src/class/Buckets.php:43`; standalone `false`/`true` types in `src/Core7.php:8414` and `src/class/RESTful.php:702` need 8.2+). The tree does not parse under 8.1/8.2 — pre-existing. `composer.json` now declares `>=8.4`. Consider updating `install/app-dist.yaml` (`runtime: php81`) to `php84`+ in a follow-up.
2. **Dynamic properties** (deprecated 8.2, removed in PHP 9): already remediated in previous framework versions; no dynamic-property notices were observed bootstrapping `Core7` and loading the patched Twig classes under 8.5 `E_ALL`. Exhaustive confirmation across all 408 classes via static analysis (PHPStan level 0+ or Rector) remains a phase-2 routine check, ideally in CI.
3. **Twig 1.x / Parsedown upgrade**: this branch patches signatures only. A move to `twig/twig` v3 via composer (adapting `src/class/RenderTwig.php`) remains the long-term fix, and is the only path that removes the deprecated `Serializable` interface usage entirely (PHP 9 risk).
4. **`eval()` usage** in `src/class/CFOs.php:1254`, `src/class/RenderTwig.php:47` and `src/api/_eval.php`: not a compatibility issue, but a standing security concern (ISO 27001) — review separately.
5. **PHP 8.5 behavioral changes to watch in integration tests** (no static indicator either way): weak comparison of incomparable objects now follows `(bool)$object`; `printf` family without precision no longer resets it; `$_SESSION` keys containing `|` emit a warning; `array`/`callable` no longer valid in `class_alias()`.

## Recommended phase 2

1. PHPStan (start at level 1) + PHPCompatibility ruleset wired into CI on this branch.
2. Run the platform test suite (`php runtest.php`) against a PHP 8.5 environment with real GCP credentials.
3. Deploy a canary app on App Engine `runtime: php85` (available on GAE standard) pointing at this branch.
4. Plan the Twig 1.x → Twig 3 upgrade.
