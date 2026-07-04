---
description: ⚡⚡⚡ Sanitize SYSTEM practice titles — strip ONLY web sources (thuvienhoclieu, toanmath…) + textbook brands (Cánh Diều, KNTT…), KEEP school/Sở + all exam info — via the `interedu` MCP server using an Opus agent
argument-hint: [--subject=math] [--grade=7] [--limit=50] [--dry-run] [--target=local|prod] [--all]
---

## Purpose

Rewrite the **title** of **system** practices (`school_id IS NULL`) so it no longer advertises the
**web source** it was downloaded from (`thuvienhoclieu`, `toanmath`, `vietjack`…) or the **textbook
brand** (`Cánh Diều`, `KNTT`, `CTST`, `Global Success`) — and **keep everything else verbatim**:
school names, Sở/Phòng GD, and all exam info (loại đề, kỳ thi + học kỳ, môn, lớp, năm học, mã/số đề).
This is a MINIMAL strip (no reformat — `GK1` stays `GK1`, casing unchanged). Delegated to the
`practice-title-sanitizer` agent (Opus) on the PHP-native `interedu` MCP server (`app/Mcp/`). NEVER
touches school-owned đề; the rename OVERWRITES the title (old title kept in the audit log).

## Variables

- ARGS: `$1` (raw arg string). Agent parses `--subject=`, `--grade=`, `--limit=`, `--dry-run`, `--target=`, `--all`.

## Workflow

Delegate to subagent `practice-title-sanitizer` via the Task tool. The agent:

1. Loads skill `practice-title-sanitizer` (source+brand blocklist + minimal-strip rubric + report format).
2. Parses args (defaults: no filter, limit=50, target=local).
3. Calls MCP `find_practices` with `title_contains=<web-source + textbook-brand seed>` (or `needs_type:false` when `--all`) → work-set (system-only) + `total_remaining`. (School markers are NOT in the seed — those titles are kept.)
4. Per practice:
   - Reads the title from the find result (no extra fetch).
   - ULTRATHINK → original title minus the web/brand tokens (school/Sở/exam info kept) + the stripped-token list + reason.
   - `rename_practice` (overwrites the title, audit-logs old→new, refuses if the new title still contains a source/brand token) — skipped if `--dry-run`, if the title has no web/brand token, or if stripping leaves no exam info.
5. Emits a markdown report (table + summary).

## How to prompt the agent

When invoking the subagent, pass:
- The raw args string from `$1`.
- Reminder: MINIMAL strip — remove ONLY web sources + textbook brands; KEEP school/Sở + all exam info verbatim (no abbreviation-expansion, no case change, no reorder).
- Reminder that `rename_practice` REQUIRES a `reason`, OVERWRITES the title (old title kept only in the audit log), and that the agent must pass the source+brand blocklist as `forbidden_substrings`.
- Reminder to SKIP (not invent) when stripping leaves no meaningful exam info, and to SKIP titles that have no web/brand token at all.

**IMPORTANT**: Do NOT sanitize in the main thread — always delegate to `practice-title-sanitizer`.

**IMPORTANT**: The `interedu` MCP server is auto-started via `.mcp.json`. For `--target=prod`, the new
tools (`rename_practice` + the `title_contains` filter on `find_practices`) must be DEPLOYED to the
prod MCP server first; if a prod run reports "tool not found", deploy then retry.

**IMPORTANT**: Only system practices (school_id=NULL) are ever in scope — school-owned đề are excluded
at the query level and refused at the write level.

**IMPORTANT**: Unlike the type classifier, this tool OVERWRITES the title. The first run on any new
bucket SHOULD be `--dry-run` to preview the old→new titles before persisting.

**IMPORTANT**: Sacrifice grammar for concision when reporting.
