---
name: question-solver
description: Solve Vietnamese education questions that are MISSING an answer (`App\Models\Question` rows) and write the answer + step-by-step solution to the bank via the `interedu` MCP server. Web-answer-FIRST (loigiaihay, vietjack, …), VERIFIED by an independent self-solve; writes ONLY when both agree (high confidence). Per-subject formatting (LaTeX/Mathpix for math/science, plain text for English, sample_answer+rubric for literature). Auto-loads when the question-solver agent runs, when handling /questions:solve, or when work touches answering/solving unanswered questions + set_question_answer.
license: MIT
---

# Question Solver

User-triggered solving of `App\Models\Question` rows that are **published but missing an answer**. An Opus agent reads full question context, self-solves independently (and cross-checks the web), and writes the answer + lời giải one question at a time via MCP **only at high confidence** — a verified deterministic self-solve for objective problems, or web-source + self-solve agreement for judgment-heavy ones (see the confidence gate). It can also **correct a wrong stored answer** or a **corrupted stem** when confirmed against the source. Everything uncertain is skipped and surfaced for a human.

> These are LIVE questions: filling `correct_answer` makes them gradeable immediately. A wrong answer mis-grades students. Correctness > coverage, always.

## Pipeline

```
/questions:solve  ──►  question-solver agent (Opus, ultrathink)
                              │  WebSearch / WebFetch (existing answer, FIRST)
                              │  MCP tool calls (stdio/http JSON-RPC)
                              ▼
                      interedu MCP server (PHP, `app/Mcp/`)
                              │
                              ▼
                      QuestionAnswerService::applyAnswer  /  ::correctText
                      (idempotency + per-type validation + stem-repair + audit)
                              │
                              ▼
                      Question.correct_answer (incl. essay) / explanation /
                      sample_answer / rubric_criteria / text (stem fix)
                      + QuestionSubItem.correct_answer
                      + before+after audit log (channel=answer_solving)
```

## When to use

- User runs `/questions:solve` (with optional filters).
- User asks "giải các câu chưa có đáp án", "fill missing answers", "solve unanswered questions".

DO NOT use this skill to create new questions or whole practices — that is `practice-importer` / `practice_import_json`. This skill only fills the ANSWER gap on questions that already exist.

## MCP tools (interedu server)

All live in `app/Mcp/Tools/Questions/`; schemas in each Tool's `inputSchema()` (or call `tools/list`).

| Tool | Purpose | When to call |
|---|---|---|
| `find_questions` | Work-set. Use `needs_answer: true` → questions missing a type-appropriate answer | At start (scope + work-set) |
| `get_question_context` | FULL payload for ONE question (text + options[key,value] + sub_questions[key] + passage + current answer) | Per question, before solving |
| `find_similar_questions` | Use `scope: "answered"` → neighbors that already have an answer | Per question — copy the answer FORMAT (LaTeX style, key convention, explanation shape) |
| `set_question_answer` | WRITE — answer + solution + audit (`reason`, `sources`, `confidence`). Idempotent (skips if already answered unless `force`). Essays accept `correct_answer` too (the final đáp án); overwriting a differing one needs `force` (else returns `protected_fields`) | Per question, ONLY at high confidence |
| `correct_question_text` | WRITE — fix a CORRUPTED stem (`text`) when a clean stored answer is impossible for the literal đề (dropped number, flipped sign, garbled LaTeX). Audited + reversible | Only when stem corruption is confirmed against the source đề |

CLI inspector for ad-hoc debugging:
```bash
php artisan mcp:invoke --tool=find_questions --args='{"subject":"math","grade_level":9,"needs_answer":true,"limit":5}'
php artisan mcp:invoke --tool=get_question_context --args='{"id":"<uuid>"}'
```

## Solving workflow (per question)

1. **Context** — `get_question_context({id})`. Read the stem, options (each `{key,value}` — `key` is the letter you must write), sub_questions (each has a `key`), passage. ULTRATHINK what is being asked.
2. **Format template** — `find_similar_questions({id, scope: "answered", limit: 3})`. Note how solved neighbors format `correct_answer` + `explanation` (LaTeX delimiters, key convention). Mirror it.
3. **Web answer FIRST** — WebSearch + WebFetch trusted sources (see below). Extract the answer + worked solution. **Verify the source is for THIS exact question** (numbers/wording match), not a look-alike.
4. **Self-solve INDEPENDENTLY** — solve from the stem yourself (ULTRATHINK), without anchoring on the web answer. Then compare.
5. **Confidence gate** (below) → write or skip.
6. **Format per subject** (below) → build the payload.
7. **Write** — `set_question_answer(...)` with `confidence: "high"`, `sources: [urls]`, `reason`. Unless `--dry-run`.

## Confidence gate — the ONLY thing protecting prod answers

`high` confidence is REQUIRED to write (the tool rejects anything lower). What earns `high` depends on whether the answer is **provable** or a matter of **judgment**:

### Tier A — objective / deterministic
math · physics · chemistry · arithmetic · equation/system-solving · simplification · unit conversion · probability with a given sample space · single_choice/fill_blank with a computable value.

The answer is provably unique. An **independent self-solve** (ULTRATHINK, double-checked) that yields exactly ONE unambiguous answer is sufficient for `high`. Attach ≥1 real reference page (the exact đề if you find it, else a worked-method page) to `sources` for the audit. An exact-question web source is NOT required.
- If a web source DISAGREES with a careful deterministic self-solve → the web is often wrong (seen this run: `64,06×6,9` mis-stated as `441,614`; correct `442,014`). RE-VERIFY by hand; if still certain, write the self-solve answer and note the web disagreement in `reason`.

### Tier B — judgment / interpretation
literature · open-ended word problems where the setup can be misread · geometry proofs needing construction · anything depending on a figure you cannot fully see.

Misreading is plausible, so keep the strict gate: a **trusted web source for THIS exact question** AND an **agreeing self-solve**.

| Situation | Decision |
|---|---|
| Tier A: deterministic self-solve → one answer (+ a real method/đề ref) | **high → write** |
| Tier B: web ✓ AND self-solve == web | **high → write** |
| Tier B: web ✓ AND self-solve ≠ web | CONFLICT → skip + flag |
| Tier B: no trusted web answer | skip + flag |
| Genuinely ambiguous / under-specified / needs an unseen figure | skip + flag (NEVER guess) |

Rule of thumb: write only what you can **prove** (Tier A) or **verify against the exact source** (Tier B). Never write on a hunch, and never lower the bar just to "improve coverage".

## Trusted web sources (priority order)

1. **loigiaihay.com** (primary — URL patterns already mapped for Toán 4/5/6 KNTT; see project memory).
2. vietjack.com, tuyensinh247.com, hoc247.net, vungoi.vn, khoahoc.vietjack.com.
3. Subject-official textbook solution sites for the exact bộ sách (KNTT / Cánh Diều / Chân trời sáng tạo).

Rules: prefer ≥1 source that shows the FULL worked solution (not just a letter). Record every URL you used in `sources`. If sources disagree with each other, treat as low confidence → skip.

## Per-type answer mapping

| Type | What to write |
|---|---|
| `single_choice` | `correct_answer` = the option **key** (e.g. `"A"`). Validated against option keys. |
| `multiple_choice` | `correct_answer` = keys, e.g. `"A,C"`. |
| `fill_blank` | `correct_answer` = the value (LaTeX allowed); optional `answer_type`/`tolerance` for numeric. |
| `true_false_group` | `sub_answers` = `[{key, correct_answer:"Đ"|"S", explanation?}]` (one per sub-item). Parent `correct_answer` stays null. |
| `essay` | `sample_answer` = lời giải/model answer; `rubric_criteria` = structured rubric. ALSO set `correct_answer` = the final/objective đáp án when the essay has one (many imported essays carry a short-answer string there shown to students). Overwriting a DIFFERENT existing essay `correct_answer` needs `force=true`. |

Always also send `explanation` = step-by-step lời giải (except literature essays, where the body lives in sample_answer/rubric).

## Fixing wrong stored answers & corrupted stems

The work-set includes essays imported with a final-answer string in `correct_answer` but no lời giải — while solving you also VERIFY that stored answer. Two repair paths, both audited (channel `answer_solving`) + reversible:

- **Stored answer AGREES with your self-solve** → just backfill the lời giải (`sample_answer` + `explanation`); no `force`.
- **Wrong stored answer (essay)** — web + self-solve confidently contradict it (seen: stored `6m`, correct `7,5m`):
  → `set_question_answer({id, correct_answer:<correct>, sample_answer:<lời giải>, explanation, confidence:"high", sources, reason, force:true})`. `force:true` is REQUIRED — without it the old value is protected and returned in `protected_fields`.
- **Corrupted stem** — a clean/canonical stored answer is mathematically IMPOSSIBLE for the literal stem → the import dropped/garbled the đề (seen: "4 giờ" should be "4 giờ 48 phút"; `+2√x/(4−x)` should be `−2√x/(4−x)`):
  → Confirm the intended stem against the **source đề** (web). If confirmed: `correct_question_text({id, text:<full corrected stem>, confidence:"high", sources, reason})`, THEN write the matching lời giải via `set_question_answer`. If you canNOT confirm the intended stem from a source → do NOT guess; flag for a human.

## Per-subject formatting ("lưu chuẩn phương thức")

Follow the same conventions as `practice-md-to-json` so new answers match existing ones.

| Subject group | `correct_answer` | `explanation` / body |
|---|---|---|
| math, physics, chemistry, biology, khtn, geography | letter (MC) or LaTeX `$...$` (fill_blank) | step-by-step with LaTeX `$...$` / `$$...$$`; keep any `cdn.mathpix.com` image refs |
| english | letter / value | Vietnamese explanation; plain text |
| literature | (null) | `sample_answer` = bài văn mẫu; `rubric_criteria` structured |
| history, civic_education, informatics, … | value | plain text |

Mirror the LaTeX/key style of the `scope:"answered"` neighbors. Never invent a different convention.

## Run modes (args)

- `--subject=X` / `--grade=N` — scope filters.
- `--limit=N` — cap questions this run (default 50; prod ≤ 100).
- `--dry-run` — compute + report, do NOT call `set_question_answer`.
- `--force` — overwrite an EXISTING answer (default off = skip already-answered). Use only to correct known-bad answers.
- `--target=local` (default) / `--target=prod`.

## Output report (end of run)

```
## Solve report — subject={subject}, grade={grade}, limit={N}

| #  | Q-id (short) | Preview                | Type          | Answer | Source            | Decision        |
|----|--------------|------------------------|---------------|--------|-------------------|-----------------|
| 1  | a1b3...4833 | Tính $\int_0^1 x^2dx$  | fill_blank    | $1/3$  | loigiaihay.com/…  | written (high)  |
| 2  | a1b3...4912 | Câu hỏi …              | single_choice | —      | —                 | skip: no source |
| 3  | a1b3...51aa | Câu hỏi …              | single_choice | —      | vietjack/…        | skip: CONFLICT  |

Summary: 1 written, 2 skipped (1 no-source, 1 conflict). Audit: storage/logs/answer-solving-*.log (channel=answer_solving).
```

## Constraints (NEVER violate)

- NEVER write at less than `high` confidence. `high` = a verified deterministic self-solve (Tier A) OR a trusted exact-question web source AND an agreeing self-solve (Tier B). Never write on a hunch.
- NEVER call `set_question_answer` / `correct_question_text` without `reason` + `sources` (≥1 URL) + `confidence` — the tools reject empty.
- NEVER overwrite an existing answer unless `force` is set (this also applies to fixing a wrong essay `correct_answer`; without `force` it is protected and returned in `protected_fields`).
- NEVER guess a corrupted stem — only call `correct_question_text` when the intended đề is confirmed by a source; otherwise flag for a human.
- NEVER skip silently — every skip/flag MUST appear in the report with its reason (no-source / conflict / ambiguous / needs-image / wrong-answer-flagged).
- NEVER write the option `value` (content) into `correct_answer` for choice types — write the `key` (letter).
- NEVER process more than `--limit` per run.
- NEVER fabricate a source URL — `sources` must be real pages you actually fetched.
- WHEN `--target=prod`: cap `--limit ≤ 100`; ⚠️ TARGET=PROD banner on every progress update; `[PROD]` on the summary; recommend `--dry-run` first on a new bucket.

## Audit & rollback

Every write logs a `before`+`after` snapshot to channel `answer_solving` (`storage/logs/answer-solving-*.log`). To roll back a run: read the log, collect `question_id` + the `before` snapshot, and restore:

```bash
php artisan tinker --execute="\$q=App\Models\Question::find('<uuid>'); \$q->correct_answer=null; \$q->explanation=null; \$q->save();"
```

## See also

- `app/Mcp/Tools/Questions/SetQuestionAnswerTool.php` — the write tool
- `app/Services/QuestionAnswerService.php::applyAnswer` — idempotency + validation + audit
- `.claude/skills/practice-md-to-json/SKILL.md` — the LaTeX/Mathpix + answer-format conventions to mirror
- `.claude/skills/question-classifier/SKILL.md` — sibling MCP-grounded agent (same house style)
- `docs/question-solver-agent-design.md` — design of record
- `plans/260605-1836-question-solver-agent/plan.md` — implementation plan
