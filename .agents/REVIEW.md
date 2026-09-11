# GOWA Laravel Package — Code Review Guidelines & Quality Gates

This document defines the official Code Review policy for the **GOWA Laravel Package** (`gowa-laravel`).
It serves as the technical validation constitution for human reviewers and autonomous agents (Antigravity, Claude Code, CodeRabbit CLI, Codex, OpenCode).

---

## 1. Code Review Philosophy: High Signal (Zero Bikeshedding)

1. **Focus on What Matters**: The review acts as the **guardian against bugs, database leakage in stateless mode, webhook security flaws, compatibility breaks, and performance regressions**. We avoid bikeshedding over cosmetic code style — formatting is strictly the responsibility of automated tooling (`composer lint` / `vendor/bin/php-cs-fixer`).
2. **Rigorous Verification Against Code**: Never raise an issue based solely on inspecting an isolated diff chunk. Always inspect adjacent code, verify PHP 8.3+ and Laravel 10–13 compatibility, and cross-reference SDK and GOWA server behavior before raising a finding. False positives erode trust in reviews.
3. **Actionable Suggestions**: Every finding must explain **why** it is a problem, **what the real impact** is, and provide a **concrete, ready-to-apply code solution**.

---

## 2. Severity Taxonomy

Every review comment must be classified into one of the following 4 levels:

### 🔴 Critical (Merge / Release Blocking)
- **Security**: Bypassing webhook HMAC verification (`verifyWebhookSignature`), leaking secrets or auth headers in audit logs/exceptions, unvalidated remote media downloads.
- **Database Leakage in Stateless Mode**: Direct queries or model lookups that crash or perform unconfigured SQL when `GOWA_STATELESS=true` or database tables do not exist.
- **Fatal Compatibility Break**: Incompatibility with PHP 8.3 or supported Laravel versions (Laravel 10–13), missing or invalid service provider bindings.
- **Runtime Crash**: Syntax errors, nonexistent imports, unhandled type mismatches (`TypeError`, `QueryException` in stateless contexts).
- **Contract Break**: Breaking public API on `Gowa` facade, `PendingMessage`, or event classes without deprecation or a major version bump.

### 🟠 Major (Must Be Fixed Before Merge)
- **Stateless & Driver-Only Integrity**: Missing safeguards when handling instance resolution, webhook auditing, or notification channel dispatching without database models.
- **Webhook & Event Reliability**: Incorrect parsing of webhook payloads, unhandled message types, or regression in signature verification.
- **Test Quality & Coverage**: Missing Pest tests in `tests/Unit/` or `tests/Feature/` for new options, methods, or configuration behaviors.
- **Architectural Violation**: Hardcoding table names or direct model lookups ignoring `config('gowa.models.*')` configuration.

### 🟡 Minor (Robustness & Maintainability)
- **Defensive Coding**: Missing null-safe operators (`?->`) or missing defensive array access on optional payload fields.
- **Edge Cases**: Missing fallback values for optional device IDs or missing support for alternative coordinate formats (`degreesLatitude` vs `latitude`).
- **Documentation Parity**: Incomplete or missing documentation in `README.md` and `README.pt.md` for new configuration options or helpers.

### 💡 Suggestion / Nitpick (Optional / Non-Blocking)
- Readability improvements, helper method naming consistency, or PHPDoc array shape enhancements.

---

## 3. Quality Gates — GOWA Laravel (`src/` — PHP 8.3+, Laravel 10–13, Pest 3.x)

### 🔒 Security & Webhook Authentication
- [ ] **HMAC Verification**: Webhook requests must always be verified using `X-Hub-Signature-256` (or fallback signature headers) before processing payloads.
- [ ] **Secret Isolation & Auditing**: Instance secrets take precedence over global secrets. Webhook call audits (`gowa_webhook_calls`) must strip credential headers (`Authorization`, `X-Gowa-Secret`, `X-Api-Key`).
- [ ] **Stateless Verification**: In stateless mode, webhook validation must use the global secret (`gowa.webhook.secret`) without querying database instance models.

### ⚡ Stateless & Driver-Only Mode (`GOWA_STATELESS=true`)
- [ ] **Zero Database Queries**: When stateless mode is active, package service provider must skip migration loading, bypass `GowaInstance` lookups, and disable automatic database synchronization listeners.
- [ ] **Safe Model Fallbacks**: If package database tables do not exist, runtime code in `PendingMessage`, `GowaChannel`, and `GowaWebhookController` must not throw `QueryException`.
- [ ] **Explicit Exceptions**: If a device ID is required and neither provided nor set in configuration, throw a clear `InvalidArgumentException` before attempting network or database operations.

### 🧩 SDK Compatibility & Event Ergonomics
- [ ] **`gowa-php/sdk` v1.5.0 Alignment**: Utilize typed DTOs (`LocationPayload`, `LiveLocationPayload`, `PollPayload`, `EventPayload`, `OrderPayload`, `ContactCard`) and `MessageType` enums.
- [ ] **Webhook Event Objects**: Accept both `array` and `WebhookEvent` instances in controller and event handlers.
- [ ] **Resilient Coordinates**: Support both protobuf keys (`degreesLatitude`, `degreesLongitude`) and normalized keys (`latitude`, `longitude`).

### 🧪 Automated Testing (Pest 3.x & Orchestra Testbench)
- [ ] **Feature Tests**: Validate end-to-end webhook flows, stateless mode, database auto-sync, and notification delivery using `Orchestra\Testbench`.
- [ ] **Unit Tests**: Cover isolated model behavior, HMAC signature verification, facade bindings, and fluent message construction.
- [ ] **100% Passing Suite**: All tests must pass cleanly via `composer test` (`vendor/bin/pest`).

### 🎨 Code Style & Standards
- [ ] **PHP-CS-Fixer Clean**: The codebase must pass cleanly with zero warnings under `composer lint` (`vendor/bin/php-cs-fixer fix --dry-run --diff`).
- [ ] **Strict Typing**: Every PHP file must begin with `declare(strict_types=1);`.
- [ ] **Bilingual Docs**: New features or config keys must be documented in both `README.md` and `README.pt.md`.
- [ ] **Conventional Commits**: Commit messages must adhere to conventional commit prefixes (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).

---

## 4. Standard Format for Review Findings

When reporting review findings, adhere to the following template:

```markdown
### [SEVERITY] `path/to/File.php:Line` — Descriptive Title

**Problem:**
Clear explanation of the bug, database leakage, vulnerability, or compatibility issue.

**Root Cause:**
```php
// problematic code snippet
```

**Suggested Fix:**
```php
// corrected code, ready to apply
```
```
