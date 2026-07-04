---
description: ⚡⚡⚡ Convert a practice-store/handle-file/*.{pdf,md} into JSON (written to practice-store/result/) and import as a Practice via php artisan
argument-hint: [file.pdf | file.md]
---

## Purpose

End-to-end practice ingestion. Accepts EITHER:

- **`.pdf`** — runs `practice-pdf-to-md` first, routing by subject (detected from page 1):
  - **toán / lý / hoá / sinh / địa (+ KHTN)** → **Mathpix v3 PDF API** (`mathpix_pdf.py`) for clean LaTeX MD, paired with a PyMuPDF yellow-highlight answer-key sidecar (`--answer-map`). Best 2D-math fidelity.
  - **everything else** → local PyMuPDF heuristic (`pdf_to_md.py`) producing a Mathpix-style MD.
  Then continues with the MD→JSON→Practice pipeline.
- **`.md`** — a Mathpix-clean Markdown exam file (with or without YAML frontmatter). Skips straight to MD→JSON→Practice.

## Variables

- TARGET: `$1` (optional). May be a `.pdf` or `.md` file path resolved against `practice-store/handle-file/`. If omitted, list `practice-store/handle-file/*.{pdf,md}` and ask which one (or process all in series).
- SUBJECT_DETECT: `python .claude/skills/practice-pdf-to-md/scripts/pdf_to_md.py practice-store/handle-file/<pdf> --detect-only` → reads `use_mathpix` to pick the lane.
- MATHPIX_EXTRACTOR (Lane A — toán/lý/hoá/sinh/địa): `python .claude/skills/practice-pdf-to-md/scripts/mathpix_pdf.py practice-store/handle-file/<pdf> practice-store/handle-file/<basename>.md` + `python .claude/skills/practice-pdf-to-md/scripts/pdf_to_md.py practice-store/handle-file/<pdf> --answer-map practice-store/handle-file/<basename>.answers.json --no-md`
- PDF_EXTRACTOR (Lane B — others): `python .claude/skills/practice-pdf-to-md/scripts/pdf_to_md.py practice-store/handle-file/<pdf> practice-store/handle-file/<basename>.md --json practice-store/result/<basename>.json`
- IMPORT_CMD: `php artisan practice:import-from-json practice-store/result/<basename>.json --force`

## Folder layout

```
practice-store/
├── .gitignore          # tracked; ignores everything except .gitkeep + the two subfolders
├── handle-file/        # INPUT (.pdf / .md) + intermediate MD from PDF extractor — gitignored content
│   └── .gitkeep
└── result/             # OUTPUT (.json) ready for php artisan — gitignored content
    └── .gitkeep
```

The folders ship in git as empty anchors via `.gitkeep`; their contents (PDFs, MDs, JSONs) are gitignored and never committed.

## Workflow (full-auto — no manual YAML needed)

Delegate to the `practice-importer` subagent via the `Task` tool with `subagent_type=practice-importer`. The agent runs end-to-end without asking the user any questions:

1. **Step 0 (if `.pdf`)**: Run `practice-pdf-to-md` — first `--detect-only` to read `use_mathpix`, then branch:
   - **Lane A (math/lý/hoá/sinh/địa)**: Mathpix v3 PDF API → clean LaTeX MD (no answer info); PyMuPDF `--answer-map` → yellow-highlight answer-key sidecar; merge in MD→JSON.
   - **Lane B (others)**: PyMuPDF reads page drawings, detects yellow-filled rects (RGB ≈ (1,1,0)) → marks each line of text whose vertical center sits inside one of those rects as "highlighted = the correct answer".
   - Walks structure: `Phần I/II/III` → sections; `Câu N:` → questions; `A./B./C./D.` → options; `a./b./c./d.` (only in `Phần II / "đúng sai"`) → TFG sub-statements.
   - Per question, the single highlighted option becomes `correct_answer`. Per Phần II sub-statement, highlighted → Đ, else → S. Per Phần III, inline `Đáp án: N` → `correct_answer`.
   - Writes `practice-store/handle-file/<basename>.md` with YAML frontmatter (subject/grade/exam_category auto-inferred) and the body in Mathpix MD with explicit `**Đáp án:** X` markers.
   - If `detected_answers < questions`, surfaces the missing list to the user before proceeding (no silent placeholders).

2. **Step 1**: Load `practice-md-to-json` skill, locate the target MD.

3. **Resolves metadata**:
   - If MD already starts with `---` → uses existing YAML frontmatter (user authority).
   - Else → AUTO-INFERS title/subject/grade/duration/year/category from the exam header.

4. **Conversion**: Converts MD body → JSON matching the legacy converter shape (`{passages, sections, groups, questions}`) — ULTRATHINK before emitting, preserve 100% of the source content.

5. **Self-validates** against the skill's checklist (question count, points sum, option count, sub_questions completeness, LaTeX + image URLs preserved).

6. **Writes** `practice-store/result/<basename>.json`.

7. **Imports**: `php artisan practice:import-from-json practice-store/result/<basename>.json --force`.

8. **Reports** Practice ID + admin URL + warning list (if any).

## How to prompt the agent

When invoking the subagent, pass:
- The target file path (PDF or MD; or instruction to scan + ask when multiple files exist with no arg).
- Reminder: full-auto path — infer metadata silently, do NOT ask the user for confirmation.
- Reminder to use ULTRATHINK and to follow the self-validation checklist.
- Reminder to ONLY touch `practice-store/handle-file/*.md` (intermediate MD + YAML prepend) and `practice-store/result/*.json` (final output), and run the one artisan command above.

**IMPORTANT**: Do NOT implement the conversion yourself in the main thread — always delegate to `practice-importer`. The agent runs on Opus 4.7 with max effort which is required for the conversion quality budget.

**IMPORTANT**: If the user hasn't provided a path AND `practice-store/handle-file/` has zero `.pdf` or `.md` files, surface that to the user and stop (do not invent content).

**IMPORTANT**: If subject can't be inferred AND no manual YAML present, the agent must STOP and surface — do NOT default-guess `subject`.

**IMPORTANT**: For PDF input, if the script reports `detected_answers < questions`, the agent must surface the missing question numbers before proceeding to MD→JSON. The user may need to manually edit the MD to add the missing `**Đáp án:** X` lines.

**IMPORTANT**: Sacrifice grammar for concision when reporting.
