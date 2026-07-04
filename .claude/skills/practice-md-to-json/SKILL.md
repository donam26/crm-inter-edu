---
name: practice-md-to-json
description: Convert LaTeX-clean Mathpix Markdown exam files (under practice-store/handle-file/) into standardized exam JSON written to practice-store/result/, then import them into the Laravel Practice system via `php artisan practice:import-from-json`. Replaces the external tool-practice FastAPI converter. Auto-loads when the practice-importer agent runs, when handling /practice:import, or when work touches practice-store/handle-file/*.md → practice-store/result/*.json conversion → Practice creation pipeline.
license: MIT
---

# Practice MD → JSON → Import

End-to-end skill for replacing the legacy `tool-practice/exam-converter` (FastAPI: Mathpix OCR → OpenAI) with an in-Claude conversion lane that runs against MD files already cleaned to Mathpix Markdown format inside `practice-store/handle-file/`.

## Folder layout

```
practice-store/
├── .gitignore          # tracked; ignores everything except .gitkeep + the two subfolders
├── handle-file/        # INPUT MDs (raw or with YAML frontmatter) + intermediate MDs from PDF→MD — gitignored content
│   └── .gitkeep
└── result/             # OUTPUT JSON ({ metadata, data }) — gitignored content
    └── .gitkeep
```

Both subfolders ship in git as empty anchors via `.gitkeep`. The contents (PDFs, MDs, JSONs) are gitignored and never committed.

## Pipeline overview

```
practice-store/handle-file/<name>.md  ──►  Claude (Opus 4.7, max effort)  ──►  practice-store/result/<name>.json
   (YAML frontmatter +                         reads:                              { metadata, data }
    Mathpix Markdown body)                     - _base.txt (universal rules)
                                       - <subject>/grade_<N>.txt
                                       - <subject>/grade_<N>.json
                                                                  │
                                                                  ▼
                                  MCP tool: mcp:<server>:practice_import_json
                                                                  │
                                                                  ▼
                                          Practice + Passages + Sections + Groups
                                              + Question bank rows + pivot rows
```

The skill does NOT call Mathpix, does NOT call OpenAI. The MDs in `practice-store/handle-file/` are assumed already Mathpix-clean (LaTeX `$…$`, tables, image URLs `https://cdn.mathpix.com/...` preserved).

## When to use

- User runs `/practice:import` (with or without a filename argument).
- User asks "import the practice", "convert this MD to a Practice", "đẩy đề vào hệ thống".
- User drops a new MD into `practice-store/handle-file/` and triggers the agent.

DO NOT use this skill to OCR a raw PDF/image yourself. For PDFs the upstream `practice-pdf-to-md` skill produces the MD — via the **Mathpix lane** (`mathpix_pdf.py`) for math/lý/hoá/sinh/địa, or the **PyMuPDF lane** (`pdf_to_md.py`) for the rest. This skill assumes Markdown-in, JSON-out.

## Mathpix hybrid lane — answer-key merge (NEW)

For the science subjects, the MD comes from Mathpix (clean LaTeX, tables, `cdn.mathpix.com` images) but carries **NO answer-highlight info** — Mathpix output has no color. The correct answers are extracted separately by the PyMuPDF yellow-highlight pass into a sidecar:

```
practice-store/handle-file/<basename>.answers.json
{
  "marker_color": "#FFFF00",        // null when the PDF has no answer key
  "detected_answers": 28,
  "answers": [
    { "section_index": 0, "section_title": "Phần I.", "number": 1, "type": "single_choice", "answer": "B" },
    { "section_index": 1, "section_title": "PHẦN II.", "number": 1, "type": "true_false_group", "answer": {"a":"Đ","b":"Đ","c":"Đ","d":"S"} },
    { "section_index": 2, "section_title": "PHẦN III.", "number": 1, "type": "fill_blank", "answer": "10" }
  ]
}
```

When this sidecar is present, MERGE it into the Mathpix-MD questions:

| Question type | Field to set | From sidecar |
|---|---|---|
| `single_choice` / `multiple_choice` | `correct_answer` | `answer` (a letter `A`–`D`) |
| `true_false_group` | each `sub_questions[].correct_answer` | `answer[<label>]` (`Đ`/`S`) |
| `fill_blank` | `correct_answer` | `answer` (string) |

Merge key: `(section ordinal among Phần headers, Câu number)`. Rules:
- The **Mathpix MD is authoritative** for text/LaTeX/options/structure. The **sidecar is authoritative ONLY for `correct_answer`**.
- `answer == null` → leave `correct_answer` empty, keep `is_published: false`, emit `WARN: Câu N (section S) has no detected answer — left blank for admin`.
- Sidecar Câu count ≠ MD Câu count → align by Câu number where possible, WARN on each unmatched Câu. NEVER drop a question to force alignment.
- No sidecar at all (PyMuPDF lane, or a `.md` was dropped directly) → answers come from the explicit `**Đáp án:** X` lines already in the MD body, as before.

## Practice metadata (auto-inferred from MD header by default)

The user drops a raw Markdown file into `practice-store/handle-file/` — NO YAML frontmatter required. The agent reads the Vietnamese exam header (cover page) and infers every metadata field, then writes the inferred YAML frontmatter back into the MD (in place, inside `handle-file/`) so subsequent runs are deterministic.

If the user has manually pre-populated YAML frontmatter, it WINS over inference (no override) — user authority is absolute.

### Final metadata shape (what the JSON `metadata` block carries)

```yaml
title: "Đề thi thử Tốt nghiệp THPT — Sở GD Hưng Yên 2025-2026 — Mã 1147"
subject: english              # math | physics | chemistry | biology | history | geography
                              # | english | literature | civic_education | informatics
                              # | khtn | economics_law | technology
                              # | math_specialized | english_specialized
grade_level: 12               # int 1..14 (13=Đại học, 14=Khác)
room_type: review_room        # review_room (Luyện đề) | mock_exam_room (Phòng Thi thử)
                              #  → drives "Phòng hiển thị" in admin. Giữa/cuối kỳ ⇒ review_room.
exam_type: final_2            # midterm_1 | final_1 | midterm_2 | final_2 | null
                              #  → "Loại kỳ thi" — ONLY for room_type=review_room (giữa/cuối kỳ)
exam_category: thpt_graduation   # grade_exam | thpt_entrance | thpt_graduation
                                 # | specialized_6 | specialized_10 | dgnl | ielts
                                 #  → ONLY for room_type=mock_exam_room (kỳ thi lớn)
duration_minutes: 50          # int (minutes)
description: ""               # optional
instructions: ""              # optional — student-facing instruction block
school_year: "2025-2026"      # optional
semester: null                # 1 | 2 | null (auto from exam_type)
is_published: false           # default false (draft)
specialized_subject: null     # when subject ∈ {math_specialized, english_specialized}
```

### Inference rules (apply in this exact order)

**Subject** — scan MD for the first match (case-insensitive, Vietnamese accents allowed):

| Source keyword | → subject |
|---|---|
| `TIẾNG ANH CHUYÊN`, `ANH CHUYÊN` | `english_specialized` |
| `TIẾNG ANH`, `MÔN ANH`, `IELTS` | `english` |
| `TOÁN CHUYÊN`, `CHUYÊN TOÁN` | `math_specialized` |
| `MÔN TOÁN`, `MÔN: TOÁN`, `TOÁN HỌC` | `math` |
| `NGỮ VĂN`, `MÔN VĂN`, `MÔN: VĂN` | `literature` |
| `VẬT LÝ`, `VẬT LÍ`, `MÔN LÝ` | `physics` |
| `HÓA HỌC`, `HOÁ HỌC`, `MÔN HÓA`, `MÔN HOÁ` | `chemistry` |
| `SINH HỌC`, `MÔN SINH` | `biology` |
| `LỊCH SỬ`, `MÔN SỬ` | `history` |
| `ĐỊA LÝ`, `ĐỊA LÍ`, `MÔN ĐỊA` | `geography` |
| `GIÁO DỤC CÔNG DÂN`, `GDCD` | `civic_education` |
| `TIN HỌC`, `MÔN TIN` | `informatics` |
| `KHOA HỌC TỰ NHIÊN`, `KHTN` | `khtn` |
| `KINH TẾ PHÁP LUẬT`, `KINH TẾ & PHÁP LUẬT` | `economics_law` |
| `CÔNG NGHỆ`, `MÔN CÔNG NGHỆ` | `technology` |

**Grade level** — first match wins:

| Source pattern | → grade_level |
|---|---|
| `LỚP N` or `Lớp N` (N ∈ 1..12) | `N` |
| `LỚP CHUYÊN N` | `N` |
| `TỐT NGHIỆP THPT`, `TN THPT` | `12` |
| `TUYỂN SINH VÀO LỚP 10`, `VÀO 10`, `VÀO THPT` | `9` |
| `TUYỂN SINH VÀO LỚP 6`, `VÀO 6` | `5` |
| `ĐGNL`, `ĐÁNH GIÁ NĂNG LỰC`, `HSA`, `APT`, `VACT`, `TSA` | `12` |
| `THPT` (no grade) | `12` |
| `THCS` (no grade) | `9` |

**Exam category** — first match wins:

| Source pattern | → exam_category |
|---|---|
| `TỐT NGHIỆP THPT`, `TN THPT` | `thpt_graduation` |
| `TUYỂN SINH VÀO LỚP 10`, `VÀO THPT`, `VÀO 10` | `thpt_entrance` |
| `CHUYÊN LỚP 6`, `CHUYÊN 6`, `VÀO 6 CHUYÊN` | `specialized_6` |
| `CHUYÊN LỚP 10`, `CHUYÊN 10`, `VÀO 10 CHUYÊN` | `specialized_10` |
| `ĐGNL`, `ĐÁNH GIÁ NĂNG LỰC` | `dgnl` |
| `IELTS` | `ielts` |
| `GIỮA KỲ`, `CUỐI KỲ`, `KIỂM TRA`, `HỌC KỲ` | `grade_exam` |
| (none of above) | omit |

**Exam type + Room type** — drives "Loại kỳ thi" + "Phòng hiển thị" in admin. Derive together (first match wins). A semester-style school exam (giữa/cuối kỳ) is a **Luyện đề** (`review_room`); a big standardized exam is a **Phòng Thi thử** (`mock_exam_room`):

| Source pattern | → exam_type | → semester | → room_type |
|---|---|---|---|
| `GIỮA KỲ 1`, `GIỮA KÌ 1`, `GIỮA HỌC KỲ 1`, `GIỮA KỲ I` | `midterm_1` | `1` | `review_room` |
| `CUỐI KỲ 1`, `CUỐI KÌ 1`, `HỌC KỲ 1`, `HỌC KÌ I`, `HK1` | `final_1` | `1` | `review_room` |
| `GIỮA KỲ 2`, `GIỮA KÌ 2`, `GIỮA HỌC KỲ 2`, `GIỮA KỲ II` | `midterm_2` | `2` | `review_room` |
| `CUỐI KỲ 2`, `CUỐI KÌ 2`, `HỌC KỲ 2`, `HỌC KÌ II`, `HK2` | `final_2` | `2` | `review_room` |
| `exam_category` ∈ {`thpt_graduation`, `thpt_entrance`, `specialized_6`, `specialized_10`, `dgnl`, `ielts`} | omit | `null` | `mock_exam_room` |
| (giữa/cuối kỳ but semester unclear) | omit | `null` | `review_room` |
| (none of above) | omit | `null` | `review_room` |

Rules:
- `exam_type` is set **only** when `room_type = review_room` (a giữa/cuối kỳ exam). For big exams it stays `null` — the kỳ thi is carried by `exam_category` instead.
- `room_type` is **never** omitted — always emit `review_room` or `mock_exam_room`.
- "Giữa kỳ" → `midterm_*`; "Cuối kỳ"/"Học kỳ" → `final_*`. Semester suffix (1/2) usually appears in the title; if only "Học kỳ I/II" is present treat I=1, II=2.
- Note "Tốt nghiệp THPT" is **not** an `exam_type` — it is `exam_category = thpt_graduation` + `room_type = mock_exam_room`.

**Duration** — first match wins:
- `Thời gian làm bài N phút` → `duration_minutes = N`
- `Thời gian: N phút` → `duration_minutes = N`
- `N phút (không kể thời gian phát đề)` → `duration_minutes = N`

**School year** — `NĂM HỌC YYYY-YYYY` → `school_year = "YYYY-YYYY"`

**Title** — construct from header tokens:
- Pick `[source]` from `SỞ GIÁO DỤC ...`, `TRƯỜNG ...`, or `TRUNG TÂM ...` line.
- Pick `[exam type]` from the heading (e.g. `KỲ THI THỬ TỐT NGHIỆP THPT LẦN 2`).
- Pick `[code]` from `MÃ ĐỀ: NNNN` if present.
- Pick `[year]` from `NĂM HỌC ...`.
- Compose: `"<exam type> — <source> <year> — Mã <code>"` (drop missing tokens, no double dash).

Example: source `english.md` with header `SỞ GIÁO DỤC VÀ ĐÀO TẠO HUNG YÊN`, `KỲ THI THỬ TỐT NGHIỆP THPT LẦN 2`, `NĂM HỌC 2025-2026`, `MÃ ĐỀ: 1147` →
`"Thi thử Tốt nghiệp THPT Lần 2 — Sở GD Hưng Yên 2025-2026 — Mã 1147"`

**Specialized subject** — when inferred subject ∈ {`math_specialized`, `english_specialized`}, set `specialized_subject` to its non-specialized counterpart (`math` or `english`).

**Defaults** — when a field can't be inferred:
- `description`, `instructions` → empty string.
- `semester` → `null`.
- `is_published` → `false`.
- `exam_category` → omit (not required).
- `room_type` → `review_room` (never omit).
- `exam_type` → omit unless a giữa/cuối kỳ pattern matched.

### Validation rules (still enforced after inference)

- `subject` MUST be in the Subject enum list above.
- `grade_level` MUST be int in 1..14.
- `exam_category` (if present) MUST be in the ExamCategory enum list.
- If `subject` couldn't be inferred from the MD header → STOP, surface to user, do NOT guess.

### Backfill the inferred YAML into the MD

After inference + before conversion, the agent writes the YAML block at the top of the MD file. This makes future re-runs deterministic and lets the user inspect / edit the inferred metadata. Block format:

```markdown
---
title: "…"
subject: english
grade_level: 12
room_type: review_room
exam_type: final_2
exam_category: thpt_graduation
duration_minutes: 50
school_year: "2025-2026"
description: ""
instructions: ""
semester: null
is_published: false
specialized_subject: null
inferred_by: claude-opus-4-7
inferred_at: 2026-05-18T16:30:00+07:00
---

(then the original MD body unchanged)
```

If the MD already starts with `---` then YAML frontmatter is already present — use it verbatim, do NOT re-infer or overwrite.

## Conversion rules

Reuse the existing profile assets — do NOT duplicate them:

1. **Universal rules** — read `tool-practice/templates/profiles/_base.txt`. This file defines:
   - Absolute rules (preserve 100% content, no paraphrase, no translation, keep LaTeX, keep image URLs)
   - Top-level schema `{passages, sections, groups, questions}` (NEVER add or rename keys)
   - Per-field semantics for passages / sections / groups / questions
   - The 5 question `type` values: `single_choice`, `multiple_choice`, `true_false_group`, `fill_blank`, `essay`
   - `answer_input_mode` rules (text vs image for literature essays)
   - The DO-NOT list

2. **Subject + grade addendum** — read `tool-practice/templates/profiles/<subject>/grade_<grade>.txt`. This defines the format detection rules (e.g. English THPT 2024+ has 40 single_choice × 0.25đ), the section/task shape, the points/barem rules, and subject-specific gotchas.

3. **Sample JSON** — read `tool-practice/templates/profiles/<subject>/grade_<grade>.json`. The output JSON `data` block MUST match this shape exactly.

4. **Fallback** — if `<subject>/grade_<grade>.{txt,json}` does NOT exist, use `tool-practice/templates/profiles/_generic/{system_prompt.txt,sample.json}`. Warn the user that no specialised profile exists.

5. **Aliases**: `math_specialized` → falls back to `math/`; `english_specialized` → falls back to `english/`.

## Per-question barem extraction (source ALWAYS wins over template defaults)

The template defaults (`tool-practice/templates/profiles/<subject>/grade_<N>.txt`) often hardcode rigid point structures (e.g. English THPT 2024+ = `0.25/câu × 40 = 10.0`). Real exams break this — school exams mix MCQ (0.25đ) with essay (2.0đ), specialized exams total 20đ, ĐGNL HSA totals 150. The agent MUST sweep the source MD for explicit barem signals BEFORE applying any template default.

Apply these 6 patterns in strict priority order. Higher-priority match wins; conflicts emit a WARN line citing both values.

### Priority 1 — Inline annotation on the question line

Regex (case-insensitive, Vietnamese accents allowed):
- `Câu\s+\d+\s*\.?\s*\((\d+(?:[.,]\d+)?)\s*điểm\)`
- `Câu\s+\d+\s*\.?\s*\((\d+(?:[.,]\d+)?)\s*đ\)`
- `Question\s+\d+\s*\.?\s*\((\d+(?:[.,]\d+)?)\s*pt\)`
- `Bài\s+\d+\s*\.?\s*\((\d+(?:[.,]\d+)?)\s*điểm\)`

Examples:
- `Câu 5. (0.5 điểm)` → `points = 0.5`
- `Câu 12 (2 điểm):` → `points = 2.0`
- `Bài 3. (1,5 điểm)` → `points = 1.5` (comma decimal)

Strict requirement: the question number MUST be followed by `.` or whitespace before the parenthesis. Bare `Câu 5 (5 điểm)` without separator is ambiguous (5 = question number or 5đ?) — skip and fall through.

### Priority 2 — Sub-question annotation (for `true_false_group`)

- `a)\s*\(0?\.25\s*đ\)\s*statement` → `sub_questions[0].points = 0.25`
- `b)\s*\((\d+(?:[.,]\d+)?)\s*điểm\)\s*statement` → per-sub

When sub-questions carry individual barem, the parent question's `points` = sum of sub-points.

### Priority 3 — Section/Part-level barem

Section header declares total, individual questions are silent:
- `Phần I. Trắc nghiệm (4 điểm)` followed by N questions → distribute 4/N evenly per question.
- `PART II. WRITING (3 điểm)` followed by 1 essay → that essay's `points = 3.0`.
- `I. ĐỌC HIỂU (5,0 điểm)` followed by 10 short-answers → 0.5 each.

Rule: even split unless source has secondary signals (per-question weighting). Agent does NOT guess weighted distribution.

### Priority 4 — HDC table (Hướng dẫn chấm) at end of exam

Typical shapes:
```
| Câu  | 1   | 2   | 3   | 4   | … |
| Điểm | 0.5 | 1.0 | 0.5 | 2.0 | … |
```
Or per-line:
```
Câu 1: 0.5 điểm
Câu 2: 1.0 điểm
```

If both inline (priority 1) and HDC (priority 4) exist, priority 1 wins BUT agent emits:
`WARN: question N has inline barem X điểm but HDC table says Y điểm — used inline (priority 1).`

### Priority 5 — Explicit total declaration

- `Tổng điểm: 20` → exam total = 20.0
- `Thang điểm 100` / `Thang điểm: 100` → exam out of 100
- `Tổng: 150` (HSA) → total = 150

When source declares total but per-question barem is silent → distribute total evenly across questions. Emit WARN:
`WARN: source declares total = X but no per-question barem; distributed X/N evenly.`

### Priority 6 — Template default (LAST resort)

Only when patterns 1-5 are all silent. Use the template's hardcoded default (e.g. `english/grade_12.txt` says 0.25/câu × 40 = 10.0). Emit WARN:
`WARN: source MD has no explicit barem signal; applied template default = <X>/câu (total = <Y>).`

The user reviews this preview in the admin UI and can override before publish.

### Conflict resolution table

| Signals present | Winner | WARN? |
|---|---|---|
| Only inline (P1) | inline | no |
| Only HDC (P4) | HDC | no |
| Inline + HDC agree | inline | no |
| Inline + HDC disagree | inline | yes (cite both) |
| Only section barem (P3) | section/N | no |
| Section + inline disagree | inline | yes (cite both) |
| Only total (P5) | total/N | yes (no per-q) |
| Nothing | template default | yes |

## Format-agnostic section/group derivation

Templates illustrate the COMMON case (e.g. English THPT 2024+ TASK 1-6). Real exams diverge — school exams have `PHẦN ĐỌC HIỂU` + `PHẦN VIẾT`, ĐGNL exams have `KHOA HỌC` + `TƯ DUY ĐỊNH LƯỢNG` + `NGÔN NGỮ`, IELTS has 4 skills, etc.

Rules:
- Section titles MUST be copied verbatim from source headings (`PHẦN I.`, `Part 2.`, `I. ĐỌC HIỂU`, `WRITING`, etc.). NEVER rename to TASK 1 / TASK 2 / etc. just because the template uses those labels.
- Template's prescribed structure is GUIDANCE only — if source has fewer/more/different sections, follow source structure.
- A single section can hold MIXED question types (e.g. `PHẦN I` containing both `single_choice` and `fill_blank`). Type detection is per-question from the question shape, NOT per-section assumption.
- `groups[]` declared only when ≥2 questions share context (passage / audio / sub-heading) inside the same section. Don't create singleton groups.
- If the template lists TASK shapes that don't appear in source → skip them silently, do NOT force-fit.
- If source has section types not in the template → still emit them as plain `sections[]` entries with the source's verbatim title.

## Total flexibility (no hardcoded 10.0 assumption)

DO NOT assume `Σ points = 10.0`. The agent reads the actual total from source signals:
1. Explicit `Tổng điểm: X` / `Thang điểm X` → that's the total.
2. Sum of explicit per-question barems → that's the total.
3. Sum of explicit per-section barems → that's the total.

Known non-10 totals:
- ĐGNL HSA = 150 (50 q × 3.0)
- ĐGNL APT / VACT / TSA = 150
- Đề chuyên = 20 (10 q × 2.0)
- Trường KT custom = anywhere 5..40

The artisan validates `points > 0` per question but accepts ANY positive total. The Laravel `AdminPracticeService::rescalePointsToExpectedTotal` ONLY rescales when total ∈ [8.5, 11.5] for specific subject/grade combos — totals far from 10 are passed through unchanged.

## Question type extensions (current limits + workarounds)

Backend `QuestionType` enum supports only 5 types: `single_choice`, `multiple_choice`, `true_false_group`, `fill_blank`, `essay`. Adding new types (matching, ordering, drag_drop, band_score) requires backend migration + frontend renderer + grading logic — OUT OF SCOPE for this skill.

Temporary encodings while waiting for backend support:

| Real type | Encode as | Encoding |
|---|---|---|
| Matching (A→1, B→2, C→3, D→4) | `single_choice` | Each `option` = one permutation (e.g. `"A-1, B-2, C-3, D-4"`, `"A-2, B-1, C-4, D-3"`, …). `correct_answer` = letter of correct permutation. |
| Ordering (sentences a-e in order) | `single_choice` | Each `option` = one ordering (e.g. `"c-a-e-d-b"`). `correct_answer` = letter of correct order. |
| Drag-drop (fill in N slots) | `fill_blank` | `text` includes numbered slots `(1) ___ (2) ___ (3) ___`. `correct_answer` = `"1=apple; 2=banana; 3=cherry"` (semicolon-separated, slot-prefixed). |
| IELTS band score | (BLOCKED) | Backend has no band-scoring field. Agent must STOP and surface: "IELTS imports blocked — needs backend `scoring_mode` field. See plans/{future} backend track." |

When encoding a workaround, the agent MUST set `metadata.encoded_as_workaround` on the JSON `metadata` block (e.g. `"encoded_as_workaround": "matching"`) so a future backend migration can promote these questions to real types without re-parsing source.

For matching/ordering encoded as `single_choice`, the `options[]` listing all permutations can be long (4! = 24 for matching 4 pairs). Cap at 4 plausible options (correct + 3 distractors) — agent emits a WARN if it had to prune.

## Output JSON file shape

Write to `practice-store/result/<basename>.json` (sibling folder of the source MD's `handle-file/`):

```json
{
  "metadata": {
    "title": "...",
    "subject": "english",
    "grade_level": 12,
    "room_type": "mock_exam_room",
    "exam_type": null,
    "exam_category": "thpt_graduation",
    "duration_minutes": 50,
    "description": "",
    "instructions": "",
    "school_year": "2025-2026",
    "semester": null,
    "is_published": false,
    "specialized_subject": null,
    "source_md": "english.md",
    "source_profile": "english/grade_12",
    "generated_at": "2026-05-18T16:16:00+07:00",
    "generator": "claude-opus-4-7"
  },
  "data": {
    "passages":  [ { "index": 0, "title": "...", "content": "<p>…</p>", "audio_url": null } ],
    "sections":  [ { "index": 0, "title": "PART I.", "instruction": null } ],
    "groups":    [ { "index": 0, "section_index": 0, "passage_index": 0, "title": "…", "instruction": null } ],
    "questions": [ { "section_index": 0, "group_index": 0, "passage_index": 0,
                     "type": "single_choice", "text": "...",
                     "options": [{ "value": "A", "text": "…" }, …],
                     "correct_answer": "A", "explanation": null,
                     "points": 0.25 } ]
  }
}
```

The artisan command reads `metadata` for Practice fields and `data` for the import pipeline (mirrors `ExamConverterService::buildPreview`).

## ⚠ CRITICAL — `text` vs `content` field naming (silent-drop pitfall)

The schema uses TWO distinct content keys. Mixing them is the #1 cause of silently lost questions:

| Where | Key | Holds |
|---|---|---|
| `passages[]` | **`content`** | Full passage body (HTML / Markdown) |
| `questions[]` | **`text`** | Question stem |
| `questions[].options[]` | **`text`** | Each option's label content |
| `questions[].sub_questions[]` | **`text`** | Each Đ/S sub-statement |

**Why it matters:** `QuestionMigrationService::migratePractice()` reads `$item['text']` to create the bank `Question` row. When `text` is empty AND there are no `options`/`sub_questions`, the question is silently dropped (`$stats['skipped']++; continue`). NO error surfaces; the import "succeeds" with fewer questions than the JSON declared.

This bug bit Toán 10 / Toán 11 / Lý 10 / Lý 11 (2026-05) — every `fill_blank` question was written with key `content` instead of `text`; all 6 fill_blank per practice silently vanished, total points off by 3.0đ per practice.

**Rules:**
- `questions[N].text` — REQUIRED for every question. Never use `content` here.
- `passages[N].content` — REQUIRED for every passage. Never use `text` here.
- For `fill_blank` where the prompt is short ("Tính giá trị biểu thức …"), `text` MUST still be populated — the import service has NO fallback to `content`/`question_text`.
- Before writing the JSON file, grep the output for `"content":` inside `questions[]` — if present, you've poisoned the import.

## Self-validation checklist (MUST run before writing the JSON file)

| # | Check | Action on failure |
|---|---|---|
| 1 | YAML frontmatter parses; required keys present | Stop, ask user to fix MD |
| 2 | `subject` ∈ Subject enum; `grade_level` ∈ 1..14 | Stop, surface enum |
| 3 | Top-level JSON keys = exactly `{passages, sections, groups, questions}` | Re-emit |
| 4 | Every numbered question in the MD has an entry in `questions[]` (count match) | Re-convert the missed range |
| 5 | Every `question.points > 0` (no nulls, no zeros) | Re-extract from barem; if absent in source, surface to user |
| 6 | `Σ questions[].points` ≈ expected total for this subject/grade (typically 10.0 ± 0.01) | Re-extract; if off >15%, surface |
| 7 | Every `question.section_index` matches an entry in `sections[]` (or is null when `sections == []`) | Fix |
| 8 | Every `question.group_index` (if not null) matches a `groups[].index`; that group's `section_index` MUST equal the question's `section_index` | Fix — backend will reject mismatch |
| 9 | Every `question.passage_index` (if not null) matches a `passages[].index` | Fix |
| 10 | For `single_choice` / `multiple_choice`: `options[]` has 4 entries `A/B/C/D` | Re-extract |
| 11 | For `true_false_group`: `sub_questions[]` has 4 entries (a/b/c/d) with `correct_answer ∈ {Đ, S}` | Re-extract |
| 12 | LaTeX preserved (no math expression was translated to plain text) | Re-emit raw `$…$` |
| 13 | All Mathpix image URLs preserved (none dropped) — appear in `passages[].content` or `questions[].images[]` | Re-emit |
| 14 | Barem source-tracking: for every question, agent knows which priority (1-6) provided its `points` value. If priority 6 (template default) was used → WARN line emitted to user citing the default applied. | Re-sweep source for missed signals |
| 15 | Total accuracy: `Σ questions[].points` matches source-declared total (if present via Priority 5 `Tổng/Thang điểm: X`) OR matches sum of source-declared section barems (Priority 3). If neither source signal exists, total is whatever the per-question barems sum to — accept it (no 10.0 forcing). | Re-extract |
| 16 | Section titles copied verbatim from source headings — agent did NOT rename to template TASK labels | Re-extract titles |
| 17 | If any question was encoded via workaround (matching/ordering/drag-drop), `metadata.encoded_as_workaround` is set on the JSON metadata block | Add marker |
| 18 | **Field-naming sanity**: every `questions[]` entry has non-empty `text` (NOT `content` / NOT `question_text`). Every `passages[]` entry has non-empty `content` (NOT `text`). Run: `grep '"content":' file.json` — if any match falls inside a `questions[]` block, FIX before writing. Backend silently drops `fill_blank` with empty `text` and no options/sub_questions. | Rename keys; re-grep until clean |
| 19 | **Persistence sanity-check after import**: run `php artisan tinker --execute="$p=App\Models\Practice::find('<new_id>'); echo $p->practiceQuestions()->count();"` — the row count MUST equal `questions[].length`. If not, a question was silently dropped (almost always Check 18) — fix the JSON and re-import. | Re-fix JSON, delete bad Practice, re-import |

If any check fails, FIX before writing — never write half-bad JSON to disk.

## Import — via MCP tool (agent path)

The practice-importer agent persists via the MCP tool `practice_import_json` (not artisan). The tool wraps the same `PracticeJsonImportService` byte-for-byte.

```yaml
tool: mcp:<server>:practice_import_json   # <server> = interedu-local or interedu-prod
args:
  metadata: <object from JSON's "metadata">
  data:     <object from JSON's "data">
  dry_run:  false                          # true → validate only
  creator_id: <uuid, optional>             # defaults to env MCP_DEFAULT_CREATOR_ID
```

Returns `{ practice_id, title, total_questions, total_points, admin_url }`.

The artisan command `php artisan practice:import-from-json` still exists for the admin UI / manual ops, but the agent MUST NOT shell out to it.

The artisan command:
1. Reads `metadata` → Practice fields.
2. Validates the `data` block (schema + points + index integrity, mirrors the converter contract).
3. Wraps the persistence in `DB::transaction`:
   - Creates `Practice` row (with `questions: []` seed for the legacy NOT NULL column).
   - Creates `Passage` rows; tracks `passageIdByIndex`.
   - Runs `ExamConverterImageNormalizer` over passages + questions (md image syntax → `<img>` tags).
   - Runs `RubricCriteriaExtractor::enrichForLiterature` when `subject = literature`.
   - Calls `AdminPracticeService::importQuestions($practice, json_encode($questions))` to land the questions JSON.
   - Calls `QuestionMigrationService::migratePractice($practice, skipDedup: true)` to land bank rows + pivot.
   - Creates `PracticeSection` rows via `PracticeBuilderService::createSection`; tracks `sectionIdByIndex`.
   - Creates `PracticeGroup` rows via `PracticeBuilderService::createGroup`; tracks `groupIdByGroupIndex`.
   - Re-assigns each `PracticeQuestion` pivot to its declared `(section_id, group_id)` using `pq.order - 1` as the AI index (matches converter semantics).
   - Applies `QuestionAutoClassifierService` when `topic_slug` / `topic_name` / `tags` are present on questions.
4. Returns Practice ID + total questions + total points + admin URL.

This mirrors `ExamImportController::commit()` line-for-line so the result is byte-equivalent to the legacy flow.

## Failure recovery

- Validation error → fix the MD or the JSON, re-run.
- Artisan throws inside the transaction → nothing persisted, safe to retry.
- Artisan succeeds but admin sees wrong rendering → check `ExamConverterImageNormalizer` (image URLs) or `RubricCriteriaExtractor` (literature rubric); both run inside the artisan and log warnings to `storage/logs/laravel.log`.

## Files in this skill

- `SKILL.md` — this file (the spec)
- (No additional scripts/references — the conversion rules live in `tool-practice/templates/profiles/` and the import logic lives in `app/Console/Commands/ImportPracticeFromJsonCommand.php`.)

## See also

- `tool-practice/templates/profiles/_base.txt` — universal conversion rules
- `tool-practice/templates/profiles/<subject>/grade_<grade>.{txt,json}` — subject+grade specific
- `app/Services/ExamConverter/ExamConverterService.php::buildPreview` — reference for the persistence shape
- `app/Http/Controllers/Admin/ExamImportController.php::commit` — reference for the section/group assignment pipeline
- `app/Console/Commands/ImportPracticeFromJsonCommand.php` — the artisan command this skill drives
