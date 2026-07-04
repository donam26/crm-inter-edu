---
description: ⚡⚡⚡ Classify practice type fields (Phòng hiển thị + Loại kỳ thi) of SYSTEM practices from their title, via the `interedu` MCP server using an Opus agent
argument-hint: [--subject=math] [--grade=7] [--limit=50] [--dry-run] [--target=local|prod]
---

## Purpose

Backfill the **Phòng hiển thị** (`room_type`) and **Loại kỳ thi** (`exam_type`/`exam_category`) of
**system** practices (`school_id IS NULL`) that were imported without them — by reading the practice
**title**. Delegated to the `practice-type-classifier` agent (Opus), which uses the PHP-native
`interedu` MCP server (`app/Mcp/`). NEVER touches school-owned đề; never overwrites existing values.

## Variables

- ARGS: `$1` (raw arg string). Agent parses `--subject=`, `--grade=`, `--limit=`, `--dry-run`, `--target=`.

## Workflow

Delegate to subagent `practice-type-classifier` via the Task tool. The agent:

1. Loads skill `practice-type-classifier`.
2. Parses args (defaults: no filter, limit=50, target=local).
3. Calls MCP `find_practices` (needs_type=true) → work-set (system-only) + `total_remaining`.
4. Per practice:
   - Reads the title from the find result (no extra fetch).
   - ULTRATHINK → room_type + (exam_type | exam_category) + school_year + reason.
   - `set_practice_type` (fills blanks + audit log) — skipped if `--dry-run` or the title has no clear signal.
5. Emits a markdown report (table + summary).

## How to prompt the agent

When invoking the subagent, pass:
- The raw args string from `$1`.
- Reminder to ULTRATHINK per practice + follow the skill rubric (disambiguation: GK/CK beats a "chuyên" school name).
- Reminder that `set_practice_type` REQUIRES a `reason` and only fills blanks (idempotent).
- Reminder to SKIP (not guess) when the title has no recognisable exam signal.

**IMPORTANT**: Do NOT classify in the main thread — always delegate to `practice-type-classifier`.

**IMPORTANT**: The `interedu` MCP server is auto-started via `.mcp.json`. For `--target=prod`, the new
tools (`find_practices`, `set_practice_type`) must be DEPLOYED to the prod MCP server first; if a prod
run reports "tool not found", deploy then retry.

**IMPORTANT**: Only system practices (school_id=NULL) are ever in scope — school-owned đề are excluded
at the query level and refused at the write level.

**IMPORTANT**: Sacrifice grammar for concision when reporting.
