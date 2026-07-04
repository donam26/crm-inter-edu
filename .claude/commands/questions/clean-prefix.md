---
description: ⚡⚡⚡ Strip a redundant leading "Câu N" enumeration label from question stems (Question.text) via the `interedu` MCP server using an Opus agent
argument-hint: [--subject=math] [--grade=12] [--limit=50] [--dry-run] [--target=local|prod]
---

## Purpose

Many imported questions render as `Câu 1. Cho hàm số…` — the inline "Câu N" duplicates the number the platform already shows in the UI. This command removes ONLY that leading label from `App\Models\Question.text`, delegated to the `question-prefix-cleaner` agent (Opus ultrathink) which uses the PHP-native `interedu` MCP server (`app/Mcp/`).

The text mutation is DETERMINISTIC — `strip_question_prefix` runs a fixed server-side regex, so the body can never drift. The agent only decides WHETHER to strip each question (true label vs content vs two merged đề).

## Variables

- ARGS: `$1` (raw arg string). Agent parses for `--subject=`, `--grade=`, `--limit=`, `--dry-run`, `--target=`.

## Workflow

Delegate to subagent `question-prefix-cleaner` via Task tool. The agent:

1. Loads skill `question-prefix-cleaner`.
2. Parses args (defaults: no filter, limit=50, target=local).
3. Calls `find_questions({needs_prefix_strip: true, …})` for the work-set.
4. Per question:
   - `strip_question_prefix({id, reason, dry_run:true})` → authoritative before/after
   - ULTRATHINK → strip a true label; skip `would_empty_stem` / merged / prefix-is-content
   - `strip_question_prefix({id, reason})` (real write + audit) — skipped if `--dry-run`
5. Emits markdown report (table + summary).

## How to prompt the agent

When invoking the subagent, pass:
- The raw args string from `$1`.
- Reminder to ULTRATHINK per question + follow the skill rubric.
- Reminder that the strip is server-side regex — NEVER pass replacement text.
- Reminder that `strip_question_prefix` REQUIRES a `reason` field.
- Reminder to SKIP (never strip) when a second "Câu N" appears later in the stem (two merged đề).

**IMPORTANT**: Do NOT clean in the main thread — always delegate to `question-prefix-cleaner`.

**IMPORTANT**: The `strip_question_prefix` tool + the `needs_prefix_strip` filter are NEW. PROD (`interedu-prod`) only exposes them after the backend is redeployed and config cache cleared. If the agent reports the tool missing on `--target=prod`, deploy first.

**IMPORTANT**: On `--target=prod`, run `--dry-run` FIRST on any new (subject, grade) bucket, and cap `--limit ≤ 100`.

**IMPORTANT**: Sacrifice grammar for concision when reporting.
