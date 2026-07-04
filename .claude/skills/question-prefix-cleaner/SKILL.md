---
name: question-prefix-cleaner
description: Strip a redundant leading "Câu N" enumeration label ("Câu 1.", "Câu 12:", "Câu 3)", "Câu hỏi 5") from the start of a Question stem (`App\Models\Question.text`) via the `interedu` MCP server (PHP-native, under `app/Mcp/`). The strip is computed SERVER-SIDE by a fixed regex — the agent only decides WHETHER to strip per question (real label vs content vs two merged questions). Auto-loads when the question-prefix-cleaner agent runs, when handling /questions:clean-prefix, or when work touches removing "Câu N" prefixes from question text.
license: MIT
---

# Question Prefix Cleaner

User-triggered cleanup of `App\Models\Question` rows whose stem (`text`) starts with a redundant Vietnamese enumeration label — `Câu 1.`, `Câu 12:`, `Câu 3)`, `Câu hỏi 5` — left over from import. The platform already numbers questions in the UI, so the inline "Câu N" is duplicate noise. This agent removes ONLY that leading label, never the content.

> The text mutation is DETERMINISTIC: `strip_question_prefix` runs a fixed regex server-side (`QuestionAnswerService::stripLeadingQuestionPrefix`). The agent never supplies replacement text, so the body can NEVER drift. The agent's job is the JUDGMENT call — strip a true label, skip when the "Câu N" is content or the seam of two merged questions.

## Pipeline

```
/questions:clean-prefix  ──►  question-prefix-cleaner agent (Opus, ultrathink)
                                  │ MCP tool calls (HTTP/stdio JSON-RPC)
                                  ▼
                          interedu MCP server (PHP, `app/Mcp/`)
                                  │
                                  ▼
                          QuestionAnswerService::stripLeadingPrefix
                          (regex strip + would-empty guard + audit)
                                  │
                                  ▼
                          Question.text  (leading "Câu N" removed)
                          + audit log (channel=question_editing, reversible)
```

## What to STRIP vs KEEP

| Stem starts with… | Action | Result |
|---|---|---|
| `Câu 1. Cho hàm số…` | STRIP | `Cho hàm số…` |
| `Câu 12: Tính tích phân` | STRIP | `Tính tích phân` |
| `Câu 3) Giải phương trình` | STRIP | `Giải phương trình` |
| `Câu hỏi 4 - Nêu định nghĩa` | STRIP | `Nêu định nghĩa` |
| `CÂU 5. Đáp án nào đúng` | STRIP | `Đáp án nào đúng` |
| `Cho tam giác ABC…` (no label) | KEEP | unchanged → `no_prefix` |
| `Câu lạc bộ thể thao…` (no number) | KEEP | unchanged → `no_prefix` |
| `Câu 1.` (whole stem IS the label) | SKIP | content is in image/options → `would_empty_stem` |
| `Câu 1. … rồi Câu 2. …` (two đề merged) | SKIP | manual fix — never strip half a merge |

**Strip ONLY the leading label.** Everything after it stays verbatim — LaTeX `$...$`, image refs, points like `(2 điểm)`, sub-labels like `(NB)`. Do NOT reformat, retag, or re-answer. This is a minimal strip.

## Scope

- ONLY `Question.text` (the main stem). NOT sub-items, NOT passages, NOT options.
- ONLY the leading `Câu N` / `Câu hỏi N` label. NOT `Bài N`, `Question N`, or bare numbers `1.` — out of scope by design (riskier; the user limited this run to "Câu N").
- `is_published = true` rows only (the finder filters these).
- Idempotent: a re-run strips nothing already stripped (`no_prefix`).

## MCP tools (interedu server)

All under `app/Mcp/Tools/Questions/`. Server is `interedu-local` (stdio, local DB) or `interedu-prod` (HTTP, production DB) — resolve once from `--target`.

| Tool | Purpose | When to call |
|---|---|---|
| `find_questions` | Work-set. Use `needs_prefix_strip: true` (+ subject/grade) → only stems leading with "Câu N". Returns id + 200-char preview. | Run start |
| `strip_question_prefix` | The workhorse. `{id, reason, dry_run?}`. Server regex-strips the label; returns before/after + `written`/`skipped_reason`. `dry_run:true` previews WITHOUT writing. | Per question — dry_run to confirm, then real write |
| `get_question_context` | Full payload (text + options + answer + passage) for ONE question. | Only when the preview is ambiguous (suspected merge / content-in-image) |

CLI inspector for ad-hoc debugging:
```bash
php artisan mcp:invoke --tool=find_questions --args='{"subject":"math","grade_level":12,"needs_prefix_strip":true,"limit":20}'
php artisan mcp:invoke --tool=strip_question_prefix --args='{"id":"<uuid>","reason":"test","dry_run":true}'
```

## Strip rubric (ULTRATHINK per question)

1. Call `strip_question_prefix({id, reason, dry_run:true})` → authoritative `before` (full original stem) + `after` (what the write would produce).
2. Read the `before` text in full and decide:
   - **STRIP** — the leading `Câu N` is a pure enumeration label and the `after` stem reads as a complete, coherent question on its own. Apply.
   - **SKIP `would_empty_stem`** — the tool already refused (the label WAS the whole stem). The real content is an image/options → leave for a human.
   - **SKIP merged** — a SECOND `Câu \d` appears later in `before` (two đề concatenated). Stripping only the first label is wrong → skip, flag for manual split.
   - **SKIP prefix-is-content** — rare: the leading "Câu …" is genuinely part of the sentence (e.g. a grammar question ABOUT the word "Câu"). Skip.
3. `reason` is REQUIRED on every call (dry-run and write). Cite the exact label removed, e.g. `removed redundant "Câu 12." enumeration label`.

## Run modes

- **Default** — strip every confirmed label in the work-set, in batches; report at end.
- **`--dry-run`** — call `strip_question_prefix` with `dry_run:true` only; emit the report; NO writes. Use to preview a new (subject, grade) bucket first.
- **`--limit=N`** — cap questions this run (default 50; on prod ≤ 100).
- **`--subject=X` / `--grade=N`** — scope filters (passed to `find_questions`).
- **`--target=local|prod`** — which MCP server (default local).

## Output report (end of run)

```markdown
## Prefix-clean report — subject={subject}, grade={grade}, limit={N}, target={local|prod}

| #  | Q-id (short) | Before (stem preview)            | After                      | Status   |
|----|--------------|----------------------------------|----------------------------|----------|
| 1  | a1b3…4833    | Câu 1. Cho hàm số y=x²           | Cho hàm số y=x²            | stripped |
| 2  | a1b3…4912    | Câu 1.                           | —                          | skipped (would_empty_stem) |
| 3  | a1b3…4a01    | Câu 1. … rồi Câu 2. …            | —                          | skipped (merged đề) |

Summary: 44 stripped, 3 skipped (2 would_empty_stem, 1 merged), 0 no_prefix.
Audit: storage/logs/question-editing.log (channel=question_editing), one entry per strip (before+after, reversible).
```

## Constraints

- NEVER pass replacement text — the strip is server-side regex only. You decide go/no-go.
- NEVER call `strip_question_prefix` without a `reason` (the tool rejects empty reason).
- NEVER strip past the first label — a second "Câu N" in the stem means merged đề → skip.
- NEVER process more than `--limit` questions per run.
- NEVER skip a question silently — every skip MUST appear in the report with its `skipped_reason`.
- NEVER touch sub-items, passages, options, answers, topics, or anything but the leading `Câu N` of `text`.
- ON `--target=prod`: cap `--limit ≤ 100`, run `--dry-run` first on any new bucket, prepend ⚠️ TARGET=PROD to progress, prefix `[PROD]` on the summary.

## See also

- `app/Mcp/Tools/Questions/StripQuestionPrefixTool.php` — the write tool
- `app/Mcp/Tools/Questions/FindQuestionsTool.php` — `needs_prefix_strip` filter
- `app/Services/QuestionAnswerService.php::stripLeadingPrefix` + `::stripLeadingQuestionPrefix` — strip + audit (single source of truth for the regex)
- `config/logging.php` — `question_editing` audit channel
