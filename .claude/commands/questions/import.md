---
description: ⚡⚡⚡ Import questions from a .docx (any subject) into the question bank via the `interedu-importer` MCP server (questions only, not practice)
argument-hint: <path-to-docx>
---

## Purpose

Import every question inside a Vietnamese `.docx` (any subject — Hóa, Lý, Sinh, Toán…; single-topic workbook OR multi-chapter exam) into the global question bank, delegated to the `question-importer` agent (Opus) which uses the standalone PHP-native `interedu-importer` MCP server (`php artisan mcp:serve-importer`). The agent renders the docx to images, transcribes formulas/vectors/units to Mathpix-LaTeX with its own vision (NO Mathpix API), crops complex structures/graphs/circuits/diagrams to R2, tags each question to its chương + bài học, classifies cognitive difficulty (Nhận biết / Thông hiểu / Vận dụng), and vision-verifies each question is identical to the source before marking `question_verifications=passed`. QUESTIONS ONLY — never Practice.

## Variables

- ARGS: `$1` = path to the source file (`.docx`/`.doc`/`.pdf`). If empty, the agent uses the newest file in `question-store/inbox/`; if that is empty too, ask the user for it.

## Where to put the file

Drop the source file in **`question-store/inbox/`** (e.g. `question-store/inbox/english.DOC`), then run `/questions:import question-store/inbox/english.DOC` (or just `/questions:import`). Render artifacts go to `question-store/work/` (gitignored). Source files are gitignored — only the folder structure is tracked.

## Workflow

Delegate to subagent `question-importer` via the Task tool. The agent:

1. Loads skill `question-importer`.
2. Renders the docx → page PNGs (`docx_to_pages.py`) and reads them (vision).
3. Detects subject + grade + **file mode** (workbook vs exam) and segments the question parts (Phần I/II/III → `single_choice` / `true_false_group` / `fill_blank`); locates each `ĐÁP ÁN` block.
4. Tags topics — workbook: match the chủ đề + propose bài học; exam: classify EACH câu to its chương/bài học via `list_topics` + `find_similar_questions`. **Waits for your approval of the mapping**, then `upsert_lesson` as needed.
5. Per question (one at a time): transcribe → crop+`upload_question_image` for structures/graphs/circuits (and per-option diagrams) → classify difficulty → `import_question` (skip+report if duplicate) → render+vision-verify vs the original → `mark_question_verified` on match (else flag).
6. Reports a per-question table (đề, type, difficulty, chương/bài học, images, verified, duplicate) and confirms the Mathpix API was never used.

Pre-req: the `interedu-importer` MCP server must be connected in this session (entry in `.mcp.json`). If its tools are unavailable, tell the user to reload MCP servers.
