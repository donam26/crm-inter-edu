---
description: ⚡⚡⚡ Solve unanswered questions (web-first + self-verify) and write answers via the `interedu` MCP server using an Opus agent
argument-hint: [--subject=math] [--grade=9] [--limit=20] [--dry-run] [--force] [--target=local|prod]
---

## Purpose

Fill the ANSWER gap on `App\Models\Question` rows that are published but missing a `correct_answer` (or sub-item answers / essay rubric). Delegated to the `question-solver` agent (Opus, ultrathink) which uses the PHP-native `interedu` MCP server (`app/Mcp/`) to read full question context and the new `set_question_answer` tool to write — but only after finding an existing answer on the web AND verifying it with an independent self-solve.

## Variables

- ARGS: `$1` (raw arg string). Agent parses `--subject=`, `--grade=`, `--limit=`, `--dry-run`, `--force`, `--target=`.

## Workflow

Delegate to subagent `question-solver` via Task tool. The agent:

1. Loads skill `question-solver`.
2. Parses args (defaults: no filter, limit=50, target=local).
3. Calls MCP `find_questions({needs_answer: true})` for the work-set.
4. Per question:
   - `get_question_context` (full payload incl. option keys + sub-item keys)
   - `find_similar_questions({scope: "answered"})` (answer-format template)
   - WebSearch/WebFetch trusted sources (loigiaihay first) → existing answer + solution
   - Self-solve independently → compare
   - Confidence gate: write via `set_question_answer` ONLY if web ∧ self-solve agree; else skip + report
5. Emits markdown report (table + summary).

## How to prompt the agent

When invoking the subagent, pass:
- The raw args string from `$1`.
- Reminder to ULTRATHINK per question and follow the skill's confidence gate.
- Reminder: **web answer FIRST, self-solve to VERIFY** — never write on self-solve alone.
- Reminder that `set_question_answer` REQUIRES `reason` + `sources` (≥1 real URL) + `confidence` — the tool rejects empty.
- Reminder: write the option **key** (letter) into `correct_answer` for choice types, not the option value.

**IMPORTANT**: Do NOT solve in the main thread — always delegate to `question-solver`. The Opus budget is required for per-question reasoning + verification quality.

**IMPORTANT**: These are LIVE questions — a wrong answer mis-grades students. Correctness > coverage. Skipping is the correct outcome when an answer can't be verified.

**IMPORTANT**: `--target=prod` writes to the PRODUCTION DB. The backend MCP changes (the `set_question_answer` tool) must be DEPLOYED to `backend.phongthi.edu.vn` first, or the prod server won't expose the tool. Run `--dry-run` first on any new bucket.

**IMPORTANT**: Sacrifice grammar for concision when reporting.
