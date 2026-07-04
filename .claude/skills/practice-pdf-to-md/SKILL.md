---
name: practice-pdf-to-md
description: Convert a Vietnamese exam PDF into Mathpix-style Markdown for the `practice-md-to-json` skill. Two lanes — (A) MATHPIX API (`mathpix_pdf.py`) for math/lý/hoá/sinh/địa where 2D math fidelity matters, paired with a PyMuPDF yellow-highlight answer-key sidecar; (B) local PyMuPDF heuristic (`pdf_to_md.py`) for the rest. Auto-loads when the `practice-importer` agent receives a `.pdf` input, when handling `/practice:import some.pdf`, or when work touches `practice-store/handle-file/*.pdf → practice-store/handle-file/*.md` conversion.
license: MIT
---

# Practice PDF → MD

End-to-end PDF ingestion lane that sits in front of the existing `practice-md-to-json` skill. Input is a Vietnamese exam PDF (in `practice-store/handle-file/`); output is a Mathpix-style Markdown file written next to it (`<basename>.md`), ready for the next pipeline stage.

## Two lanes — route by subject

The agent runs `pdf_to_md.py <pdf> --detect-only` first; the printed `use_mathpix` flag picks the lane.

| Lane | Subjects | Tool | Strength |
|---|---|---|---|
| **A — Mathpix** | `math`, `math_specialized`, `physics`, `chemistry`, `biology`, `geography`, `khtn` | `mathpix_pdf.py` (Mathpix v3 PDF API) + `pdf_to_md.py --answer-map` | Real 2D math: `$\frac{a}{b}$`, sub/superscripts, tables, figure URLs |
| **B — PyMuPDF** | everything else (english, literature, history, GDCD, …) + any HDC rubric | `pdf_to_md.py` | Self-contained, no API cost; strong yellow-highlight answer detection |

**Why a hybrid for Lane A:** Mathpix gives the cleanest LaTeX MD but its output has **no color info**, so it cannot tell which option is highlighted (the answer key). PyMuPDF can. So Lane A = Mathpix MD (text/structure) **+** a PyMuPDF `*.answers.json` sidecar (the answer key), merged downstream in `practice-md-to-json`.

### Mathpix credentials

`mathpix_pdf.py` reads, in priority order:
1. env `MATHPIX_APP_ID` / `MATHPIX_APP_KEY`
2. `.claude/skills/practice-pdf-to-md/config/mathpix.json` (gitignored — copy from `mathpix.example.json`)

The config file is git-ignored via `config/.gitignore`; the real key NEVER lands in git. Mathpix is async + **paid per page** — only Lane A subjects hit it.

### Lane A commands

```bash
# 0. route
python .claude/skills/practice-pdf-to-md/scripts/pdf_to_md.py <pdf> --detect-only
# 1. clean LaTeX MD (Mathpix) — body only, agent prepends YAML from step 0
python .claude/skills/practice-pdf-to-md/scripts/mathpix_pdf.py <pdf> <basename>.md
# 2. answer-key sidecar (PyMuPDF yellow pass, no heuristic MD written)
python .claude/skills/practice-pdf-to-md/scripts/pdf_to_md.py <pdf> --answer-map <basename>.answers.json --no-md
```

The rest of this doc describes the **PyMuPDF lane (B)** + the shared answer-detection machinery that also powers the Lane A sidecar.

## Pipeline position (Lane B)

```
PDF (đáp án bôi vàng)        practice-pdf-to-md          practice-md-to-json
─────────────────────►  pdf_to_md.py  ───────►  *.md  ──────────────────────►  *.json  ──►  Practice
                       (PyMuPDF + pdftotext layout)                              (Opus 4.7)        (artisan)
```

The script:

1. Opens the PDF with PyMuPDF (`fitz`).
2. Walks every page's vector drawings and keeps the filled rectangles whose fill ≈ yellow (`R ≥ 0.85, G ≥ 0.85, B ≤ 0.35`). Yellow is the marker we observed on real exam answer keys — it's deliberate, persistent across generators, and never collides with the page background.
3. Extracts every text span with its bbox + font + size + bold flag.
4. Groups spans into reading-order lines (vertical-center tolerance 3pt).
5. Marks each line as `highlighted=True` when its vertical center sits inside any yellow rect.
6. Walks lines in reading order and detects structural tokens:
   - `Phần I / II / III` (or `PART I / II / III`) → section break.
   - `Câu N:` → new question, attach to current section.
   - `A.` / `B.` / `C.` / `D.` anchored at bold span starts → MCQ option. For multi-column layouts (4 options on one visual line) we slice the line at each anchor's x-coordinate and re-check each slice's sub-bbox against the yellow rects, so per-letter accuracy survives.
   - `a) / (a) / a.` only when current section is Phần II (`đúng sai`) → TFG sub-statement.
7. For `fill_blank` (Phần III) questions, reads the inline `Đáp án: <value>` typed at the end of the question stem. When that's missing, falls back to any highlighted text segment inside the question body.
8. Infers metadata (`title`, `subject`, `grade_level`, `exam_category`, `duration_minutes`, `school_year`, `semester`) from the first ~25 lines of page 1 using the same Vietnamese keyword table the `practice-md-to-json` skill uses.
9. Emits Markdown with YAML frontmatter on top, one `## section`, one `**Câu N.**` per question, options as a list with `← Đáp án` on the highlighted one, and a final `**Đáp án:** <X>` line that the next skill reads.

## When to use

- The agent receives a `.pdf` file path (via `/practice:import some.pdf` or by inspecting `practice-store/handle-file/*.pdf`).
- A user drops a teacher's answer-key PDF into `practice-store/handle-file/` and asks "convert this".

## When NOT to use

- The PDF has no text layer (pure scan). `pdftotext` returns empty → STOP, surface to user. Adding OCR (Tesseract) is out of scope for this skill.
- The answer key uses a marker other than yellow background fill (e.g. red font color, bold-only). The `is_highlight_fill` function only accepts yellow-ish fills today; broaden it explicitly if a new exam style appears.
- The PDF is mixed-language (the YAML inference table covers Vietnamese subjects only).

## Highlight detection

The extractor performs **color-agnostic dominant-marker detection**, not a hard-coded yellow check. The classifier accepts any saturated fill color (R≠G≠B by at least 0.2, not near-white, not near-black) as a candidate. We then group candidates by quantized RGB across the whole document and keep ONLY the most common group (≥ 3 rectangles) as "the marker". Stray colored dots / page decorations cannot spoof the result.

Real samples we've tested:

| PDF | Detected marker | Notes |
|---|---|---|
| `dap-an-hoa-10-online.pdf` | `#FFFF00` (yellow) | Chemistry grade 10 — 51 rects, 28/28 answers |
| `de-anh-10-2026.pdf` | `#FFFF00` (yellow) | English grade 10 — 40 rects on mã đề 101 only |
| `de-vat-li-10.pdf` | `null` | Physics 10 exam paper, no answer key — surfaces `NO_ANSWER_MARKERS` |
| `de-toan-12-2025-2026.pdf` | `null` | Math 12 exam paper, no answer key — surfaces `NO_ANSWER_MARKERS` |
| `hdc-van-10-25-26.pdf` | `null` | Literature rubric (HDC), out-of-scope for MCQ extraction |

Encoding paths a PDF can use for "this is the correct answer":

| Encoding | Where it appears | Handler |
|---|---|---|
| Filled rectangle behind text (`page.get_drawings`) | All observed exams | YES — primary path (color-agnostic) |
| `/Highlight` annotation (`page.first_annot`) | Adobe-Acrobat markup | Not seen yet; add a branch when encountered |
| Non-default text fill color on the answer letter | Math exams that "color the letter red" | Not seen yet; would require per-span color filter |
| No marker at all | Unannotated exam papers | YES — surfaces `NO_ANSWER_MARKERS` warning |

When you encounter a PDF with `marker_color: null` AND `detected_answers == 0`, that's not a bug — the PDF genuinely has no answer key. Either supply a marked answer-key PDF, or extract structure-only (the MD will still list every question, just with `(không phát hiện được)` placeholders).

## Math / LaTeX fidelity

Vietnamese chemistry & physics PDFs frequently use the legacy **Adobe Symbol font** to render Greek letters and math operators. Symbol places its glyphs at Private-Use-Area codepoints `U+F020 – U+F0FF` instead of proper Unicode (e.g. `Δ` is stored as `U+F044`, `≠` as `U+F0B9`, `→` as `U+F0AE`). When extracted naively, these come out as tofu / blank squares.

The extractor applies `normalize_symbol_chars()` (see `scripts/symbol_font_map.py`) to every text span. The full Greek alphabet (uppercase + lowercase) plus the common math operators (`±`, `°`, `≤`, `≥`, `≠`, `≈`, `→`, `⇔`, `√`, `∂`, `∫`, `∑`, `∏`, `∈`, `∉`, `∇`, etc.) are mapped to their canonical Unicode. Codepoints we haven't tabulated are kept verbatim so they're easy to grep for and extend.

2D math positioning (subscripts / superscripts / fraction bars) is NOT reconstructed by the PyMuPDF lane — the spans are joined in reading order, so an equation like `Δ_rH°_298 = −571.68 kJ` may render as `ΔrH°298 = −571.68 kJ`. **This is exactly why math/lý/hoá/sinh/địa route to Lane A (Mathpix)** — Mathpix reconstructs the 2D math into proper LaTeX (`$\Delta_{r}H^{\circ}_{298}$`). The PyMuPDF lane stays the default only for text-heavy subjects (english, literature, history, …) where 2D math is rare.

## Multi-exam PDFs

If a single PDF concatenates two or more exam codes (e.g. `Mã đề 101` followed by `Mã đề 102`, both with their own `Question 1 … Question 40`), the extractor opens a new section when the question number resets. The second exam appears under a `(Mã đề bổ sung) — Question N trở đi` synthetic section with an instruction note. Reviewers can split the JSON into two Practice imports if they prefer separate Practice rows.

## HDC (Hướng dẫn chấm) rubric documents

When the PDF header contains `Hướng dẫn chấm` / `HDC`, the extractor switches to **HDC mode** and uses PyMuPDF's table API (`page.find_tables()`) to parse the rubric grid. Each labeled row (`Câu N` for Phần I, `Mở bài / Thân bài / Kết bài` for Phần II's big essay) becomes one `essay` question. The "Yêu cầu cần đạt" / "Nội dung cần đạt" cell becomes the question `text`, and the "Điểm" cell sets `points`. Unlabeled rows (table continuations across pages) append rubric text to the previous question. `meta.document_kind = "hdc_rubric"` and warning `HDC_RUBRIC_MODE` are emitted.

Subject `literature` triggers `RubricCriteriaExtractor::enrichForLiterature` inside the artisan importer, which parses the rubric body into structured `rubric_criteria` rows for downstream grading.

## Per-section answer-coverage warnings

For every section, the extractor counts how many questions have detected answers. A section with ≥1 question but 0 detected answers emits a `SECTION_UNANSWERED` warning. Common cases:

- Multi-mã-đề PDF where one mã đề has yellow highlights and the other doesn't.
- Exam paper without answer key (every section will surface unanswered).
- Phần II/III in a partial answer-key where only Phần I is marked.

The warnings are advisory — the import still succeeds. Admin can edit the Practice afterward to fill missing answers.

## Structure-only import (unannotated exam papers)

When `marker_color: null` AND `highlights: 0`, the document is an exam paper without an answer key (Lí 10, Toán 12 samples). Each question gets `correct_answer = ""` and `is_published: false` (draft mode) so the Practice exists in the system but is gated from learners until an admin fills in the answers manually. The extractor still surfaces every Câu, options, and section structure — useful for sharing the exam paper to students for self-practice.

## Question type detection

| Section | Default type | Trigger |
|---|---|---|
| Phần I / PART I | `single_choice` | Has 4 `A./B./C./D.` options |
| Phần II / "đúng sai" | `true_false_group` | Has 4 `a./b./c./d.` sub-statements |
| Phần III / "trả lời ngắn" | `fill_blank` | No options, inline `Đáp án: N` |

Sections without a yellow highlight on the expected target (e.g. Q5 in Phần I has zero highlighted options) emit a `WARN` line and a placeholder `**Đáp án: (không phát hiện được)**` so the user notices.

## Output MD shape

```markdown
---
title: "…"
subject: chemistry
grade_level: 10
exam_category: grade_exam
duration_minutes: 50
school_year: "2025-2026"
description: ""
instructions: ""
semester: null
is_published: false
specialized_subject: null
source_pdf: "dap-an-hoa-10-online.pdf"
extracted_by: practice-pdf-to-md
extracted_at: 2026-05-19T10:27:13+07:00
---

# <title>

## Phần I. Câu trắc nghiệm nhiều phương án lựa chọn.

**Câu 1.** Số oxi hóa là một số đại số…

- A. Hóa trị.
- B. Điện tích. ← Đáp án
- C. Khối lượng.
- D. Số hiệu.

**Đáp án: B**

…

## PHẦN II. Câu trắc nghiệm đúng sai.

**Câu 1.** Javel là chất oxi hóa mạnh…

- a) NaClO là chất giúp Javel có tính oxi hóa.  → **Đ**
- b) Số oxi hóa của Cl trong NaClO là +1  → **Đ**
- c) Ứng dụng của nước Javel…  → **Đ**
- d) Trong phản ứng trên Cl2 vừa là chất oxi hóa, vừa là chất khử.  → **S**

**Đáp án:** a) Đ; b) Đ; c) Đ; d) S

…

## PHẦN III. Câu trắc nghiệm trả lời ngắn.

**Câu 1.** Cho phương trình hoá học… Đáp án: 10

**Đáp án:** 10
```

The `**Đáp án: X**` (single_choice) and `**Đáp án:** a) Đ; b) Đ; …` (TFG) and `**Đáp án:** N` (fill_blank) lines are the explicit signals the `practice-md-to-json` skill reads to fill `correct_answer` and `sub_questions[].correct_answer`.

## Running the script

```bash
python .claude/skills/practice-pdf-to-md/scripts/pdf_to_md.py <input.pdf> [<output.md>]
```

- `<output.md>` defaults to the same folder as the input PDF (`<pdf-dir>/<basename>.md`). The agent always passes an explicit path resolving to `practice-store/handle-file/<basename>.md`.
- Prints a JSON summary to stdout: `{pdf, md, json, answer_map, pages, highlights, sections, questions, detected_answers, metadata}`.
- Exits with code 0 on success; non-zero only on file-not-found.

When `detected_answers < questions` the script still writes the MD but the un-detected slots get `**Đáp án: (không phát hiện được)**`. The downstream agent treats those as blockers and surfaces them to the user.

### CLI modes

| Flag | Effect | Used by |
|---|---|---|
| (default) | write `<output.md>` heuristic Markdown | Lane B |
| `--json <out.json>` | also write the directly-importable JSON envelope | Lane B |
| `--detect-only` | print page-1 metadata (`subject`, `grade_level`, `use_mathpix`, `document_kind`); write nothing | lane routing (both lanes) |
| `--answer-map <out.json>` | write the section-aware answer-key sidecar | Lane A hybrid |
| `--no-md` | suppress the heuristic MD (parse only) — combine with `--answer-map` | Lane A hybrid |

`mathpix_pdf.py <input.pdf> [<output.md>] [--timeout 300] [--poll-interval 3]` — Lane A only. Prints `{pdf, md, pdf_id, pages, chars, status, image_urls, warnings}`. On timeout it prints the `pdf_id` so the `.md` can be re-fetched without re-uploading (re-billing).

## Dependencies

- Python 3.9+ (uses union types and dataclasses).
- `pymupdf` (a.k.a. `fitz`) ≥ 1.20 — install via `python -m pip install pymupdf` if missing.
- No system dependency on `pdftotext` (we extract text via PyMuPDF directly).
- `mathpix_pdf.py` uses **stdlib only** (`urllib`) — no `requests` dependency. Needs network + Mathpix credentials (see "Mathpix credentials" above).

## Files in this skill

- `SKILL.md` — this spec.
- `scripts/pdf_to_md.py` — PyMuPDF extractor (Lane B) + `--detect-only` routing + `--answer-map` sidecar.
- `scripts/mathpix_pdf.py` — Mathpix v3 PDF API client (Lane A).
- `scripts/symbol_font_map.py` — Adobe-Symbol-font PUA → Unicode map (Lane B).
- `config/mathpix.example.json` — credential template (committed).
- `config/mathpix.json` — real credentials (gitignored, never committed).

## See also

- `.claude/skills/practice-md-to-json/SKILL.md` — the next stage (MD → JSON → import), incl. the answer-map merge rule.
- `.claude/agents/practice-importer.md` — the agent that orchestrates the full pipeline (route → PDF→MD → JSON → Practice).
- `.claude/commands/practice/import.md` — the slash command entry point.
