---
description: ⚡⚡⚡ Classify questions into chương + bài học (deepest node) via the `interedu` MCP server using an Opus agent — incl. --refine to upgrade chương-level questions to bài học
argument-hint: [--subject=math] [--grade=12] [--limit=50] [--refine] [--dry-run] [--target=local|prod]
---

## Purpose

Replace the legacy nightly `questions:auto-classify` artisan cron (removed from `routes/console.php` on 2026-05-18). Classification is user-triggered via this slash command, delegated to the `question-classifier` agent (Opus ultrathink) which uses the PHP-native `interedu` MCP server (`app/Mcp/`) to read full question context and write classifications with an audit log.

Since the lesson upgrade (2026-07) the agent assigns the DEEPEST canonical node that fits: **bài học (L4)** when confident, else **chương (L3)**, else the **"Khác"** catch-all. `--refine` runs upgrade already-classified chương-level questions onto their chương's own bài học (one-way, service-enforced). Lessons are NEVER created by this flow — only pre-seeded lesson nodes are assignable.

## Variables

- ARGS: `$1` (raw arg string). Agent parses for `--subject=`, `--grade=`, `--limit=`, `--refine`, `--dry-run`, `--target=`.

## Workflow

Delegate to subagent `question-classifier` via Task tool. The agent:

1. Loads skill `question-classifier`.
2. Parses args (or uses defaults: no filter, limit=50, target=local).
3. Calls MCP `classification_stats` to scope (fresh backlog `missing_topic` + refine backlog `at_chapter_only`).
4. Calls `list_topics {group: "chapter", include_lessons: true}` once per bucket (cache the chương→bài học catalog in context).
5. Calls `find_questions` for the work-set: `needs_classification: true` (fresh) or `needs_lesson: true` (--refine).
6. Per question:
   - `get_question_context` (full payload incl. current topic group/parent)
   - `find_similar_questions` (grounding; lesson-level neighbors are the strongest bài học prior)
   - ULTRATHINK tier 1 (chương) → tier 2 (bài học inside it) → reason
   - `apply_classification` (write + audit log; `lesson_slug` when confident, `refine: true` on refine runs) — skipped if `--dry-run`
7. Emits markdown report (table with Chương + Bài học + Mức gán columns, summary incl. "chương thiếu bài học" list + stats delta).

## How to prompt the agent

When invoking the subagent, pass:
- The raw args string from `$1`.
- Reminder to use ULTRATHINK per question + follow the skill rubric (lesson bar HIGHER than chương bar; torn between 2 lessons → stop at chương).
- Reminder to call `list_topics {include_lessons: true}` ONCE per bucket (cache, don't re-call per question).
- Reminder that `apply_classification` REQUIRES a `reason` field — the tool rejects empty reason.
- Reminder that refine skips (`skipped_lesson_not_found`, …) are expected outcomes to record, not errors to retry.

**IMPORTANT**: Do NOT classify in the main thread — always delegate to `question-classifier`. The Opus budget is required for the per-question reasoning quality.

**IMPORTANT**: The `interedu` MCP server is auto-started by Claude Code via `.mcp.json` at project root. If the server fails to boot, surface the error from `php artisan mcp:serve` (test it directly to debug). `--target=prod` uses the HTTP server `interedu-prod` — cap `--limit ≤ 100` and dry-run first on new buckets.

**IMPORTANT**: The agent NEVER creates topics or lessons: unknown chương → "Khác" fallback; unknown bài học → question stays on the chương. The user curates the lesson catalog.

**IMPORTANT**: Sacrifice grammar for concision when reporting.
