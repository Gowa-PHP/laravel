---
name: code-review
description: Executes a complete code review of a PR, branch, or local changes against the GOWA Laravel REVIEW.md. Integrates CodeRabbit CLI (--agent), multi-axis auditing (PHP 8.3+ Compatibility, Laravel Quality Gates, Security & HMAC, Stateless Mode Integrity, and Pest Testing), local automated validation (composer test and composer lint), filters false positives, and posts detailed review comments to GitHub PRs via gh. Trigger whenever the user asks to "do code review", "review PR", "run coderabbit on PR", "code review", or when validating branches before merge.
---

# Code Review Skill — GOWA Laravel Package

This skill acts as the **guardian against bugs, database leakage in stateless mode, webhook security flaws, compatibility breaks, and performance regressions** in the GOWA Laravel Package (`gowa-laravel`).
It consolidates project guidelines (`.agents/REVIEW.md`, `AGENTS.md`), runs the **CodeRabbit CLI (`--agent`)** engine, validates code using local tools (`composer lint`, `composer test`), audits changes across multiple axes, and optionally publishes review comments directly to GitHub via `gh`.

---

## Execution Flow

```
[1. Resolve Target] ──> [2. Gather Context & Spec] ──> [3. Execute CodeRabbit CLI]
                                                                  │
[6. Publish / Report] <── [5. High-Signal Filtering] <── [4. Multi-Axis Audit & Tests]
```

---

## Step 1: Resolve Target and Prepare Diff

Identify what needs to be reviewed based on the user's input:

1. **Pull Request (e.g., `PR #7` or URL):**
   ```bash
   gh pr view <PR> --json number,title,body,baseRefName,headRefName,headRefOid,commits
   ```
   - Identify the base commit and the HEAD commit SHA of the PR.
   - Obtain the exact diff:
     ```bash
     git diff origin/<BASE_BRANCH>...<HEAD_SHA>
     ```

2. **Local Branch vs Base (e.g., `feat/...` against `main`):**
   ```bash
   git log origin/main..HEAD --oneline
   git diff origin/main...HEAD
   ```

3. **Uncommitted Local Changes (Work in Progress):**
   ```bash
   git diff
   ```

---

## Step 2: Context, Spec, and Guidelines

Load the necessary references:

1. **Project Guidelines:**
   - Read `.agents/REVIEW.md` (severity rules and Laravel-specific quality gates).
   - Check `AGENTS.md` for architectural rules and repository conventions.

2. **Spec / Intent Identification:**
   - If reviewing a PR: read the full PR description and any linked issues (`Refs #...` or `Closes #...`).
   - The review must verify whether the implementation **faithfully fulfills the original intent** without leaving loose ends, database leaks, or regressions.

---

## Step 3: CodeRabbit CLI (with Transparent Native Fallback)

Before calling CodeRabbit, verify whether the CLI is installed and authenticated:

```bash
if command -v coderabbit >/dev/null 2>&1 && coderabbit auth status >/dev/null 2>&1; then
    echo "CODERABBIT_READY"
else
    echo "CODERABBIT_UNAVAILABLE"
fi
```

### Scenario A: CodeRabbit Installed and Authenticated
Run the CodeRabbit CLI in structured agent mode (`--agent`):
```bash
# For committed branch/PR changes:
coderabbit review --committed --base <BASE_BRANCH> --agent

# For uncommitted local edits:
coderabbit review --uncommitted --agent
```
Collect the emitted findings to cross-reference during the multi-axis audit.

> **IMPORTANT — Watch Out for Local Working Tree State:**
> The CodeRabbit CLI reads the local file system. If uncommitted changes exist in the workspace that are not part of the PR under review, filter out and discard any findings caused by those unrelated local edits.

### Scenario B: CodeRabbit Absent or Unauthenticated (Automatic Fallback)
- **NEVER halt or fail the review.**
- The agent assumes **100% of the audit autonomously**, analyzing the diff directly against the `.agents/REVIEW.md` quality gates in Steps 4 and 5.
- Simply include an informational note in the final report footer:
  > ℹ️ *CodeRabbit CLI was not detected or not authenticated in this environment. The review was successfully conducted by the agent's native Quality Gates engine.*

---

## Step 4: Local Automated Checks

Before concluding the analysis, run local mechanical checks:

```bash
# 1. Check style and syntax (PHP CS Fixer)
composer lint

# 2. Run the complete test suite (Pest 3.x)
composer test
```

Any failures in tests or the linter must be flagged with **Major** or **Critical** severity.

---

## Step 5: Multi-Axis Audit & Quality Gates

In addition to CodeRabbit findings and automated test results, audit the diff against the 5 axes from `.agents/REVIEW.md`:

### Axis 1: PHP 8.3+ & Laravel Compatibility
- **PHP 8.3+ Baseline:** Does the code strictly follow PHP 8.3+ conventions?
- **Strict Types:** Does every new or edited PHP file include `declare(strict_types=1);` at the top?
- **Laravel Framework Integration:** Are service provider bindings, facades, notifications, and events registered without breaking across Laravel 10–13?

### Axis 2: Stateless Mode Integrity (`GOWA_STATELESS=true`)
- **Zero Database Queries:** When stateless mode is enabled, are migrations ignored, instance queries bypassed, and auto-sync listeners disabled?
- **No Missing Table Exceptions:** Can messages be sent via `Gowa::to()` and received via webhooks without crashing when tables do not exist?
- **Explicit Diagnostics:** Are clear exceptions thrown when required configuration (e.g. device ID) is missing?

### Axis 3: Security & Webhook Authentication
- **HMAC Signatures:** Are webhooks validated with `verifyWebhookSignature` using constant-time string comparison (`hash_equals`)?
- **Secret Isolation:** Does the instance secret take precedence over global secret? Is global secret used in stateless mode?
- **Audit Sanitization:** Are sensitive headers (`Authorization`, `X-Gowa-Secret`) redacted from `gowa_webhook_calls`?

### Axis 4: SDK Alignment & Event Ergonomics
- **SDK v1.5.0 DTOs:** Are `LocationPayload`, `LiveLocationPayload`, `PollPayload`, `EventPayload`, `OrderPayload`, and `ContactCard` utilized cleanly?
- **Coordinate Flexibility:** Are `degreesLatitude`/`degreesLongitude` and `latitude`/`longitude` resolved transparently?
- **WebhookEvent Support:** Does the webhook controller accept both `array` and `WebhookEvent` instances?

### Axis 5: Documentation Parity & Commits
- **Bilingual Documentation:** Are new features, config keys, and event helpers documented in both `README.md` and `README.pt.md`?
- **Conventional Commits:** Do commit messages follow standard prefixes (`feat:`, `fix:`, `docs:`, `test:`, `chore:`)?

---

## Step 6: High-Signal Filtering (Zero Bikeshedding)

Apply quality filtering before producing the report:

- **Keep:**
  - 🔴 **Critical**: Security vulnerabilities (SSRF, HMAC bypass, leaked secrets), database crashes in stateless mode, syntax/PHP errors.
  - 🟠 **Major**: Broken webhook routing, real database leakage, failing tests, linter failures.
  - 🟡 **Minor**: Missing defensive null-checks, untested branches, missing docs.
- **Discard:**
  - Cosmetic code style issues that PHP-CS-Fixer fixes automatically via `composer lint:fix`.
  - Purely theoretical suggestions that do not impact library behavior or reliability.
  - False positives caused by unrelated files.

---

## Step 7: Publishing and Presentation

### 1. Terminal / Chat Output
Present the summary categorized by severity following the `REVIEW.md` taxonomy:
- Executive summary of the review
- Table or list of findings containing:
  - Exact file path and line number with clickable link
  - Clear description of the issue and its real-world impact
  - Code snippet with the recommended fix

### 2. GitHub PR Publication (when requested or with `--post-comments` flag)
Submit the review directly to the GitHub PR using the GitHub CLI:

```bash
cat << 'JSON' > /tmp/pr_review.json
{
  "commit_id": "HEAD_SHA",
  "body": "## 🤖 Code Review — PR #NUMBER\n\nSUMMARY",
  "event": "COMMENT",
  "comments": [
    {
      "path": "src/Webhook/Events/GowaMessageReceived.php",
      "line": 35,
      "side": "RIGHT",
      "body": "**[Major] Finding title**\n\nDescription..."
    }
  ]
}
JSON

gh api repos/OWNER/REPO/pulls/NUMBER/reviews --input /tmp/pr_review.json
rm -f /tmp/pr_review.json
```
