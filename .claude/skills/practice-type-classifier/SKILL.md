---
name: practice-type-classifier
description: Classify the type fields (Phòng hiển thị `room_type` + Loại kỳ thi `exam_type`/`exam_category`) of SYSTEM practices (school_id=NULL) from the practice TITLE, via the `interedu` MCP server. Carries the title→type rubric, MCP tool catalog, and report format. Auto-loads when the practice-type-classifier agent runs, when handling /practices:classify-types, or when work touches Practice room_type/exam_type backfill.
---

# Practice Type Classifier

Fill the **Phòng hiển thị** (`room_type`) + **Loại kỳ thi** (`exam_type`/`exam_category`) of
**system** practices that are still unclassified, by reading the **title**. System-only,
idempotent (blanks only), title-driven, per-practice. A wrong type persists in the DB and
poisons the student-facing filter UI — when the title gives no clear signal, **SKIP**.

## Scope (who gets classified)

A practice is in-scope ONLY if ALL hold (the `find_practices` tool enforces this):
- `school_id IS NULL` (system exam — NOT a school's own exam), AND
- it is missing a type field: `room_type` NULL, OR `review_room` without `exam_type`, OR
  `mock_exam_room` without `exam_category`.

Never set `online_exam_room` (that room type is for school-created exams).

## MCP tool catalog (server = interedu-local | interedu-prod)

| Tool | Dir | Purpose |
|---|---|---|
| `find_practices` | READ | Work-set of system practices needing type. Args: `subject?`, `grade_level?`, `needs_type?` (default true), `limit?`, `offset?`. Returns `{total_remaining, returned, practices:[{id,title,subject,grade_level,room_type,exam_type,exam_category,school_year}]}`. |
| `set_practice_type` | WRITE | Fill blanks on ONE system practice. Args: `id`*, `room_type?`, `exam_type?`, `exam_category?`, `school_year?`, `semester?`, `reason`*. Refuses school-owned; never overwrites; auto-derives `semester` from `exam_type`. Returns `applied_fields`/`skipped_fields`. |

`reason` is REQUIRED on every write — cite the exact title fragment.

## Rubric — step 1: `room_type` (Phòng hiển thị)

Decide between two values (system exams are only these two):

**→ `review_room` (Luyện đề)** — đề ôn theo lớp/học kỳ. Signals (case/diacritic-insensitive):
- "giữa kì/kỳ", "cuối kì/kỳ", "giữa học kì", "cuối học kì"
- GK1 / CK1 / GK2 / CK2 (and spaced/lowercase variants)
- "học kì I/II/1/2", "kiểm tra giữa/cuối", "cuối năm", "khảo sát chất lượng" theo lớp
- This is the DEFAULT for an ordinary per-grade/semester đề.

**→ `mock_exam_room` (Phòng Thi thử)** — đề thi thử các kỳ thi lớn. Signals:
- "thi thử", "tốt nghiệp THPT", "THPT QG / Quốc gia"
- "tuyển sinh", "vào (lớp) 10", "thi vào 10"
- "đánh giá năng lực", "ĐGNL", "HSA", "APT", "TSA", "VACT"
- "tuyển sinh ... chuyên", "thi vào ... chuyên"
- "IELTS"

### Disambiguation priority (IMPORTANT)
- An explicit **GK/CK/giữa-cuối-kì** signal ⇒ `review_room`, **even if** the title also names a
  "trường chuyên" or a province Sở GD&ĐT — those are *provenance*, not the exam kind.
  - e.g. `de-giua-ki-1-toan-10-...-truong-thpt-chuyen-vi-thanh` → `review_room` + `midterm_1`
    (NOT specialized).
- Treat "chuyên" as `mock_exam_room`/`specialized_*` ONLY when the title is about ENTRY
  ("tuyển sinh / thi vào ... chuyên"), not a midterm/final taken at a chuyên school.
- If BOTH a big-exam signal and a GK/CK signal appear and you cannot resolve which dominates → SKIP.

## Rubric — step 2a: `exam_type` (when `review_room`)

| Title signal | exam_type |
|---|---|
| giữa kì 1 / giữa kỳ 1 / GK1 / giữa học kì 1 | `midterm_1` |
| cuối kì 1 / cuối kỳ 1 / CK1 / (cuối) học kì 1 / học kì I | `final_1` |
| giữa kì 2 / giữa kỳ 2 / GK2 / giữa học kì 2 | `midterm_2` |
| cuối kì 2 / cuối kỳ 2 / CK2 / (cuối) học kì 2 / học kì II / cuối năm | `final_2` |

(`semester` is auto-derived by the tool: GK1/CK1→1, GK2/CK2→2 — don't pass it unless the title
states a semester but no exam phase.)

## Rubric — step 2b: `exam_category` (when `mock_exam_room`)

| Title signal | exam_category |
|---|---|
| tốt nghiệp THPT / THPT QG / Quốc gia | `thpt_graduation` |
| tuyển sinh (vào) 10 / thi vào 10 | `thpt_entrance` |
| đánh giá năng lực / ĐGNL / HSA / APT / TSA / VACT | `dgnl` |
| (tuyển sinh / vào) lớp 10 chuyên | `specialized_10` |
| (tuyển sinh / vào) lớp 6 chuyên | `specialized_6` |
| IELTS | `ielts` |
| thi theo lớp nhưng đặt ở phòng thi thử (rare) | `grade_exam` |

## Rubric — step 3: `school_year`

Regex `\d{4}\s*[-–]\s*\d{4}` in the title → `school_year` (normalise to `YYYY-YYYY`, e.g.
"2022-2023"). If absent, don't pass `school_year`.

## Confidence / skip policy

- Write ONLY when a clear keyword maps to a value. Partial is fine: you may set `room_type`
  alone if the phase is unclear, OR `exam_type` alone if `room_type` was already correct.
- No recognisable signal, or conflicting signals you can't resolve → **SKIP** and list it in the
  report. A blank field is recoverable; a wrong one silently mislabels the đề for students.
- Trust the tool's idempotency: if you propose a value for a field that already has one, the tool
  reports it under `skipped_fields` — that's expected, not an error.

## Report format

```markdown
## Practice type report — subject={subject}, grade={grade}, limit={N}, target={local|prod}

| #  | P-id (short) | Title (truncated)              | room_type   | exam_type/category | year      | Confidence |
|----|--------------|--------------------------------|-------------|--------------------|-----------|------------|
| 1  | a1c0...7650  | Đề giữa kì 1 Toán 10 2025-2026 | review_room | midterm_1          | 2025-2026 | high       |
| 2  | a178...8ac3  | (skipped — no exam signal)     | —           | —                  | —         | low        |

Summary:
- Applied: N    | Skipped: M    | total_remaining after run: …
- Audit: storage/logs/laravel.log channel=mcp ([mcp.practice.set_type])
```

## Deployment note

`find_practices` + `set_practice_type` are PHP MCP tools in `app/Mcp/Tools/Practices/`. They work
on `interedu-local` immediately, but `--target=prod` requires them to be **deployed to the prod MCP
server** first (same as `set_question_answer`). If a prod run reports "tool not found", deploy then retry.
