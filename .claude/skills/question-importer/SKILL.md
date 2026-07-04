---
name: question-importer
description: Import individual questions from a .docx file (any subject — Hóa, Lý, Sinh, Toán…) into the question bank LIVE ON PRODUCTION (backend.phongthi.edu.vn) via the `scripts/importer.py` bridge to the deployed `/api/mcp/importer` endpoint (default target=prod; questions land as drafts is_published=false). Transcribes formulas/vectors/units to Mathpix-LaTeX with Claude vision (NEVER the Mathpix cloud API), crops complex structures/graphs/circuits/diagrams to R2, tags each question to its chủ đề + bài học (lesson topic_id resolved on the target by slug), classifies cognitive difficulty (1 Nhận biết / 2 Thông hiểu / 3 Vận dụng), and vision-verifies each imported question identical to the source before marking question_verifications=passed. Handles both single-topic workbooks and multi-chapter exam papers. Auto-loads for /questions:import or when importing questions from a .docx. QUESTIONS ONLY — never Practice.
license: MIT
---

# Question Importer

User-triggered import of the questions inside a Vietnamese `.docx` (theory/intro + question parts + `ĐÁP ÁN`) into `App\Models\Question` rows — one at a time, each tagged, difficulty-classified, and **vision-verified identical to the source** before it is marked verified. Subject-agnostic (Hóa, Lý, Sinh, Toán, …).

> **By DEFAULT the questions land LIVE on PRODUCTION** (`backend.phongthi.edu.vn`). Every importer tool call goes through the bridge `scripts/importer.py`, which talks JSON-RPC straight to the deployed prod `/api/mcp/importer` endpoint (`--target prod`, the default). Imported questions are drafts (`is_published=false`) for admin review/publishing — they do NOT need a separate "push to prod" step.

> These become LIVE bank questions. Correctness and visual fidelity > coverage. Never mark a question `passed` unless its rendered preview matches the original. QUESTIONS ONLY — do not create Practices/sections/exams.

## MCP tools — ALWAYS via the `importer.py` bridge (default target = PROD)

Do NOT assume native `mcp__interedu-importer__*` tools are available — the harness often does not index the MCP server at session start. Route EVERY importer call through the bridge instead (works every run; same JSON-RPC code path; pure stdlib, no venv needed). Run from the `scripts/` dir like the other helpers:
```bash
python importer.py <tool> <args.json>                # --target prod (DEFAULT → backend.phongthi.edu.vn/api/mcp/importer)
python importer.py <tool> <args.json> --target local # local .env DB — for a dry test only, NOT the real run
```
Write `<args.json>` with the Write tool first (keeps big base64 image payloads out of the model context). The 7 tools:
- `list_topics` — canonical chương/bài học tree for a subject+grade, to match each question (read).
- `find_similar_questions` — semantic neighbours (same subject+grade): (a) ground the topic/lesson of an exam question, (b) catch near-duplicates the exact-stem check misses (read).
- `upsert_lesson` — find-or-create a bài học node under a chapter; **returns the lesson `topic_id` ON THE TARGET DB** (write).
- `upload_question_image` — base64 crop → R2 → returns the global `cdn.phongthi.edu.vn` URL (write).
- `import_passage` — create a reading Passage (raw HTML) → returns `passage_id` (write; English/reading only).
- `import_question` — create the question (+ options / sub-items) in the global bank; accepts `passage_id` (write).
- `mark_question_verified` — write `question_verifications` status=passed + evidence (write).

> **One-call batch alternative:** `import_questions_json` (on the MAIN prod MCP `/api/mcp`, mirrors `practice_import_json`) takes the whole set + passages in ONE payload (`data.questions[]` with `topic_slug`, `verify` per item or `verify_all`). Prefer it for big batches **if it is deployed** (probe `tools/list` on `/api/mcp`); otherwise loop the per-question `import_question` above.

### Pushing to production — topic ids & reconcile (read before writing)
- **topic_id is per-DB.** A LOCAL lesson UUID is INVALID on prod. Resolve the lesson ON THE TARGET by SLUG: `upsert_lesson` (idempotent) with the chapter's `parent_topic_id` + lesson `name` + `slug` → use the **returned** `topic_id`. Chapter ids often match local↔prod, but **lesson ids differ**.
- **Image URLs are global** (R2/CDN) — the same `cdn.phongthi.edu.vn` URL works on prod, no re-upload needed.
- **Reconcile via the ids `import_question` returns + `get_question_context`**, NOT `classification_stats` (it counts `is_published=true` only → freshly-imported drafts never change its `total`).

## Setup (once per machine)
```bash
cd /Users/hnam/code/phongthi/backend-interedu/.claude/skills/question-importer/scripts
python3 -m venv .venv && . .venv/bin/activate && pip install -r requirements.txt && python -m playwright install chromium
```
Host already has `soffice` (LibreOffice) and `pdftoppm` (poppler).

## Pipeline

### 1. Locate + render the file to images
The source file lives in **`question-store/inbox/`** (drop-folder). If the user gave a path, use it; otherwise pick the newest file in `question-store/inbox/`. Render into a per-file subfolder of `question-store/work/`:
```bash
. .venv/bin/activate
python docx_to_pages.py "question-store/inbox/<file>" --outdir question-store/work/<file-slug> --dpi 200
```
Accepts `.docx`, `.doc`, `.pdf` (LibreOffice handles all). Read every `question-store/work/<file-slug>/page-*.png` with the Read tool — your vision is the OCR (no Mathpix API). Bump `--dpi` to 260–300 for files dense with small structures/graphs.

### 2. Detect subject, grade, and FILE MODE
From the cover/headings/content infer **subject** (chemistry/physics/biology/math/english/…) and **grade_level**. Then decide the mode:
- **Workbook mode** — the file is ONE chủ đề/chương (e.g. heading "Chủ đề 1. Ester - Lipid", a theory section then ôn luyện questions). All questions belong to that one chapter.
- **Exam mode** — the file is a full đề thi (e.g. "Phần I/II/III. … Thí sinh trả lời từ câu 1 đến…"), often spanning MANY chapters, and the `.docx` may concatenate SEVERAL đề. Questions belong to DIFFERENT chapters/lessons.
- **Skill-bank mode (English)** — the file is organized by MODULE (Phonetics, Stress, Tenses, Reading, …). Each module = a topic. See the **English & language subjects** section below for the full flow (underline/bold, error-ID, reading passages, answer detection).

### 3. Segment
Locate the question block(s); ignore intro/theory/hướng dẫn. Identify parts and map to types (labels vary — match by meaning):
- "Phần I / Phần 1 — Trắc nghiệm nhiều phương án lựa chọn" → `single_choice` (use `multiple_choice` only if it says chọn nhiều).
- "Phần II / Phần 2 — Trắc nghiệm đúng sai" → `true_false_group` (sub-items a/b/c/d, each answer `Đ` or `S`).
- "Phần III / Phần 3 — Trắc nghiệm trả lời ngắn" → `fill_blank` (`answer_type` `integer`/`decimal`).
- **"Phần IV / Tự luận / Bài tập tự luận" (hoặc cả đề chỉ có câu hỏi mở, không có A/B/C/D) → `essay`.** A tự luận câu has an open prompt and, in the `ĐÁP ÁN`/`Hướng dẫn chấm`, a worked **lời giải that ends in a đáp án** (and often a **biểu điểm**). **Do NOT skip it** — it is a real, importable question. Its answer lives in the lời giải, not in an options table.
Find each part's `ĐÁP ÁN` (answers + worked `lời giải`; for tự luận also the `Hướng dẫn chấm`/`biểu điểm`). In exam mode there may be several đề each with its own answer key — keep them paired.

### 4. Topic + lesson tagging
> Resolve every `topic_id` on the SAME target you import to (prod by default): run `list_topics` + `upsert_lesson` **through the bridge** and use the RETURNED lesson `topic_id`. Never reuse a topic_id from a different DB — lesson ids differ local↔prod.
- **Workbook mode:** `list_topics` → match the chủ đề heading to its chapter node. Propose the bài học list from the theory/section headings (cross-check the chapter's EXISTING lesson nodes via `list_topics` — the user curates the canonical lesson catalog, so prefer matching a pre-seeded bài học over creating a new one), **present the mapping for user approval**, then `upsert_lesson` each (capture each returned `topic_id`) and tag every question to its lesson.
- **Exam mode:** classify **each question individually**. For a question: `list_topics` (subject+grade) for the candidate chapters, optionally `find_similar_questions` on the stem to see which chương/bài học near-neighbours sit in, pick the best chapter, `upsert_lesson` its lesson if needed (use the returned `topic_id`), set that question's `topic_id`. **Present the per-question topic mapping for user approval before writing** (a compact table: câu → chương/bài học). If no chương genuinely fits, use the subject's "Khác" node rather than forcing one.

### 5. Per question (one at a time: extract → import → verify → mark)

> **FORMAT = HTML + LaTeX, NEVER Markdown.** The frontend renders stored content via `MathContent` = raw-HTML passthrough + KaTeX; it does **not** parse Markdown. So:
> - Math/formulas/vectors → LaTeX `$...$` (KaTeX). Images → `<img src="URL">` (NOT `![]()`). Bold → `<strong>…</strong>` (NOT `**`). Underline → `<u>…</u>`. Tables → `<table class="exam-tabular">…</table>`.
> - Never let `<u>`/`<strong>` wrap the ENTIRE field value (the sanitizer strips a tag that wraps the whole string) — keep surrounding text, e.g. `listen<u>s</u>`, not `<u>listens</u>`.

a. Transcribe stem + every option to **HTML + Mathpix-LaTeX** from the page image. NEVER call the Mathpix API. Notation guide:
   - Formulas/subscripts: `$CH_3COOC_2H_5$`, `$x_1$`, `$v_0$`. Exponents: `$1{,}8 \cdot 10^4$`.
   - **Vectors (Lý):** arrow-over-symbol → `$\vec{F}$`, `$\vec{F_1}$`; "véc-tơ AB" → `$\overrightarrow{AB}$`.
   - Greek/operators: `$\alpha, \omega, \lambda, \Delta$`; uncertainty `$R = 10{,}0 \pm 0{,}5$ cm`.
   - Units stay literal next to the value (J, Hz, N, Ω, °C, C, m/s); keep Vietnamese decimal comma.
   - **English:** preserve underline `<u>…</u>` and bold `<strong>…</strong>`; keep IPA glyphs verbatim (ˈlɪsnz, prəˈtekts, dʒʌmpt).
b. Anything that is a true picture — **chemical structure, reaction scheme, spectrum, GRAPH/đồ thị, mạch điện, sơ đồ thí nghiệm, hình vẽ, mind-map** — must be CROPPED, never LaTeX'd:
   ```bash
   python crop_region.py <work>/page-NN.png --bbox x1,y1,x2,y2 --out crop.png
   ```
   base64 it → `upload_question_image` → embed the returned URL as `<img src="URL">` in the field.
   - **Options that are diagrams:** when the 4 answer options are themselves figures (e.g. four wave/graph diagrams), crop EACH option separately and embed its `<img>` in that option's `value` (one upload per option).
   - Plain data tables → HTML `<table class="exam-tabular">`, NOT images.
c. Pull the correct answer + `explanation` (lời giải) from the part's `ĐÁP ÁN`. `single_choice` MUST have a `correct_answer`. `true_false_group` sub-items use `Đ`/`S`. `fill_blank` set `answer_type` (+ `tolerance` if a rounded decimal).
   - **`essay` (tự luận) — ALWAYS capture the answer from the lời giải; never import an essay answerless.** From the `ĐÁP ÁN`/`Hướng dẫn chấm` set as many as the source gives:
     - `correct_answer` = the **đáp án / kết quả cuối** when the tự luận has an objective final answer (Toán/Lý/Hoá/Sinh: the numeric/expression result, e.g. `x = 2; y = -3`, `S = 12\pi \text{ cm}^2`). Omit only when the prompt is genuinely open-ended (many Văn/GDCD essays have no single result).
     - `explanation` = the full worked **lời giải** (HTML + LaTeX).
     - `sample_answer` = the **bài làm mẫu** — the model answer shown to students (Văn: bài văn mẫu; Toán/khoa học: usually the same worked solution as `explanation`).
     - `rubric` / `rubric_criteria` = the **biểu điểm / thang điểm** if the source prints one (`rubric_criteria` = structured `[{name, max_score}]`; `rubric` = free-text).
     - The importer REQUIRES at least one of `sample_answer` / `rubric_criteria` / `correct_answer` for an essay — so a tự luận with a lời giải can never be dropped without its đáp án. If the source truly has no lời giải/đáp án at all, flag the câu and skip it (do not invent one).
d. **Classify difficulty** → `1`/`2`/`3` with a one-line `difficulty_reason` (rubric below).
e. Set the question's `topic_id` (its lesson, from step 4).
f. `import_question` with all fields. Capture `question_id`. (Do NOT send `default_points` — that column was removed.)
   - **If the result has `duplicate: true`**, the stem already exists (the returned id is the EXISTING row). Do **NOT** verify or `mark_question_verified` it — you didn't import it. Record it as a duplicate in the report, optionally note any discrepancy vs the source, and move on. Only continue to (g) for a freshly-created question (`duplicate: false`). Also run `find_similar_questions` on the stem first when you suspect a reworded near-duplicate.
g. **Verify** (loop below) — only for `duplicate: false`. On match → `mark_question_verified`. On repeated mismatch → leave unmarked and report.

### 6. Reconcile + report
Reconcile: spot-check a sample of the returned prod `question_id`s with `get_question_context` (right topic, answer, `<img>`, sub-items) — do NOT rely on `classification_stats` (drafts excluded). Then a table of every question: number (+ đề if exam mode), **prod question_id**, type, difficulty (+reason), chương/bài học, images (count), verified yes/no, duplicate yes/no. State the target (prod) + counts (new / duplicate / verified). List anything left unverified/flagged with the reason. Confirm the Mathpix API was never used.

## Difficulty rubric (→ `difficulty` 1/2/3)
- **1 = Nhận biết (recognition):** recall a definition/fact/formula/unit; single-step identify. Hóa: "Ethyl acetate thuộc loại hợp chất nào?" Lý: "Đơn vị của cường độ dòng điện là?"
- **2 = Thông hiểu (comprehension):** explain/compare/interpret; 1–2 step reasoning; read a simple graph; apply a single formula. Hóa: "Phát biểu nào không đúng về chất béo?" Lý: "Công của lực bằng 0 khi góc α bằng?"
- **3 = Vận dụng (application):** multi-step calculation / new context / combine ideas. Hóa: hiệu suất ester hoá; bài toán xà phòng hoá. Lý: bài toán hợp lực, biến thiên nội năng, đọc đồ thị va chạm rồi tính.
Judge per question from stem + solution complexity (the part is only a weak prior). NEVER assign level 4–5.
- **Essay (tự luận)** is almost always multi-step → usually **3 (Vận dụng)**; drop to 2 only for a short explain/define prompt.

## Verification loop (per question, before marking passed)
1. Build a JSON `{text, options, sub_items}` exactly as imported and render it:
   ```bash
   python render_preview.py --question q.json --out render.png
   ```
2. Crop the original region from the source page: `python crop_region.py <work>/page-NN.png --bbox ... --out original.png`.
3. Read BOTH images and compare: stem, every option/sub-item, formulas, vectors, units, tables, images — semantically AND visually identical.
4. On mismatch: fix the LaTeX / crop / option mapping (re-`import_question` with corrected fields, or fix and re-render), retry. **Max 3 attempts.**
5. On match: `mark_question_verified` with your `similarity` (0..1), `original_crop_url`/`rendered_url` (upload via `upload_question_image` to retain), `attempts`.
6. Still mismatched after 3 attempts: do NOT mark; report it as flagged with the specific diff.

> **Essay (tự luận) verify:** an essay renders **stem-only** (no options/sub-items are shown to students, and the đáp án is never rendered pre-submit) — so the image compare is stem fidelity only. Separately confirm the answer landed by reading the returned `question_id` with `get_question_context`: check `correct_answer`/`sample_answer`/`explanation`/`rubric` match the source lời giải before `mark_question_verified`.

## Hard rules
- **All importer tool calls go through `scripts/importer.py` (default `--target prod`)** — the questions land LIVE on production as drafts. There is no separate "push to prod" step; the import IS the push. (Use `--target local` only for a dry test.)
- **Resolve `topic_id` on the target by SLUG** via `upsert_lesson` (returned id) — never reuse a topic_id from another DB.
- **Reconcile via the ids `import_question` returns + `get_question_context`**, never `classification_stats` (counts `is_published=true` only → drafts excluded).
- QUESTIONS ONLY — never Practice.
- NEVER call the Mathpix cloud API; all transcription is your own vision.
- `school_id` stays NULL (global); `is_published` stays false (admin publishes after review).
- Subject + grade are detected from the file, not assumed.
- Output is **HTML + LaTeX, never Markdown**: images `<img>`, bold `<strong>`, underline `<u>`, tables `<table class="exam-tabular">`, math `$...$`.
- Graphs / circuits / experiment setups / structures → CROP. Inline math/vectors/units → LaTeX.
- **Tự luận = `essay`, never skipped:** when a câu has a worked lời giải ending in a đáp án, import it as `essay` and store the answer (`correct_answer` = kết quả cuối when objective, `explanation` = lời giải, `sample_answer` = bài mẫu, `rubric`/`rubric_criteria` = biểu điểm). The importer rejects an essay carrying none of `sample_answer`/`rubric_criteria`/`correct_answer`.
- Get user approval for the topic/lesson mapping (workbook: chapter→lessons; exam: per-question câu→chương; English: module→topic) before writing nodes.

## English & language subjects

English files are usually a **skill bank organized by MODULE** (Phonetics, Stress, Tenses, Passive, Reading, Find-mistake, Sentence transformation, …) — treat each **MODULE as a topic** (match/`upsert_lesson` under the English chapter, with approval). Each module has theory + Exercises + answers: **import the Exercises, skip the theory**. Almost every English item is `single_choice` (4 options A–D).

**Answer detection (no single ĐÁP ÁN table).** The correct answer appears as: an option in **bold**, a line "Đáp án là X", a highlighted "Question N. **X**" key, or an explanation. Read whichever form is present from the page image and set `correct_answer`.

**Per-type handling:**
- **Phonetics / Stress:** options are words; underline the relevant part — `proce<u>ss</u>`, `<u>a</u>ged` — and keep IPA verbatim. The question asks which underlined part / stress differs.
- **Closest / Opposite meaning:** underline the target word inside the stem — `He is very <u>diligent</u>.`; options are 4 candidate words.
- **Find mistake (error identification):** the stem is one sentence with FOUR underlined labelled segments; render them as `<u>…</u>` and make options A–D = those four segments (verbatim). `correct_answer` = the wrong segment's letter.
- **Sentence transformation / combination:** options A–D are full candidate sentences.
- **Grammar/word-form/communication:** sentence + blank (`_____`) + 4 options.

**Reading (passages):**
1. `import_passage` with the passage `title` + `content` (HTML, paragraphs as `<p>…</p>`; keep any underlined target words) → get `passage_id`.
2. Each question for that passage → `import_question` with `passage_id` set. Two sub-shapes: **cloze** (numbered blanks 1–5: stem can be the blank context, 4 word/phrase options) and **comprehension** (Q6–17: best title, reference of "They"/a word's meaning — underline that word, detail questions). All `single_choice`.
3. `find_similar_questions`/`list_topics` → tag reading questions to the Reading module topic.

**T/F/Not Given:** if a reading item is genuinely True/False/Not Given (not 4-option), use type `tfng` with `correct_answer` ∈ `true`/`false`/`not_given` (and `ynng` → `yes`/`no`/`not_given`). NOTE: the frontend renderer for tfng/ynng is not built yet (it will fall back to essay) — only emit these when the source truly is T/F/NG; prefer `single_choice` when the source gives A–D options.

**Verify (English):** render via the HTML harness and image-compare — confirm underlines, bold, IPA glyphs and (for reading) the passage all match the source before `mark_question_verified`.
