---
name: practice-verifier
description: Verify that a Practice JSON (output of practice-md-to-json) faithfully matches the source Markdown (byte-identical from PyMuPDF). Layer-1 deterministic Python script runs 7 checks — counts, answer_letters, image_urls, section_titles, points_sum, stem_fidelity, text_field_naming — and emits a `*.verify.json` report. Layer-2 LLM judgment (planned in Phase 03) handles SOFT_FAIL ambiguity. Auto-loads when the practice-importer agent runs Step 5.5, when /practice:verify is invoked, or when work touches practice-store/result/*.verify.json or .claude/skills/practice-verifier/.
license: MIT
---

# Practice Verifier

Deterministic verifier sitting between Stage 5 (JSON written) and Stage 6 (MCP import) of the practice ingestion pipeline. Blocks import when the LLM-emitted JSON diverges from the byte-identical MD ground truth.

## Pipeline position

```
PDF ──► pdf_to_md.py ──► MD ──► LLM convert (Opus) ──► JSON ──► verify_json_vs_pdf.py ──► report
                                                                       │
                                                                       ├── PASS ──► MCP import
                                                                       ├── SOFT ──► (Phase 03) LLM judgment → retry max 2x
                                                                       └── HARD ──► STOP + report
```

## Trust model

- **MD ground truth** — `practice-store/handle-file/*.md`. Body is byte-identical from PyMuPDF (the practice-importer agent's hard rule guarantees no body mutation when prepending YAML frontmatter).
- **Subject under test** — `practice-store/result/*.json`, emitted by Claude Opus 4.7 with the practice-md-to-json skill rules.
- **PDF** — informational only; recorded in the report's `source.pdf` field for traceability. The verifier does NOT re-run `pdf_to_md.py` because its JSON envelope misclassifies certain MCQ types (empirical: 1101.pdf marks Câu 1-6 as fill_blank instead of single_choice).

## CLI

```bash
python .claude/skills/practice-verifier/scripts/verify_json_vs_pdf.py \
    --md   practice-store/handle-file/<basename>.md \
    --json practice-store/result/<basename>.json \
    --out  practice-store/result/<basename>.verify.json \
    [--pdf practice-store/handle-file/<basename>.pdf]   # informational only
    [--strict]   # exit 1 on SOFT_FAIL too (default: only HARD_FAIL exits 1)
    [--quiet]    # suppress stdout summary
```

Exit codes: `0` PASS / `1` HARD_FAIL (or SOFT_FAIL with --strict) / `2` script error.

## 8 deterministic checks

| Check | Severity on fail | What it catches |
|---|---|---|
| `counts` | HARD | Question count mismatch — silent-drop / question-skip |
| `answer_letters` | SOFT | Per-Q correct_answer disagrees with MD `**Đáp án:** X` (single_choice / multiple_choice / fill_blank only) |
| `subquestion_answers` | SOFT | For true_false_group, per-sub `Đ`/`S` in JSON disagrees with MD `a) Đ; b) S; …` |
| `image_urls` | HARD | A `cdn.mathpix.com` URL in MD is missing from JSON anywhere |
| `section_titles` | HARD | Section title in JSON not verbatim from MD `## …` heading; also count mismatch |
| `points_sum` | HARD | Any question has non-positive points; optional total mismatch (SOFT) |
| `stem_fidelity` | HARD (fuzz<0.75) / SOFT (fuzz<0.92) | Question text fuzz-distance from MD stem (skips empty-stem fill_blank-in-passage cases) |
| `text_field_naming` | HARD | `questions[].content` key (must be `text`); empty text + no options/subs (backend silent-drop trigger) |

Excluded types: `essay`, `hdc`, `open_ended` (NON_GRADABLE_TYPES) are skipped from answer-letter checks.

## Failure classification

| Severity | Retry policy | Examples |
|---|---|---|
| HARD | ❌ never (STOP) | counts off, image dropped, key naming bug, section renamed, deep paraphrase |
| SOFT | ✅ allowed (Phase 03 LLM judgment, max 2x) | answer letter flip, stem fuzz 0.75-0.92 |
| WARN | ❌ (log only) | minor formatting diffs (future check categories) |
| INFO | ❌ (silent) | metadata-only diffs |

## Report shape (`*.verify.json`)

```json
{
  "verifier_version": "1.0.0",
  "verified_at": "2026-05-25T00:42:55+07:00",
  "source": {
    "pdf": "practice-store/handle-file/1101.pdf",
    "md":  "practice-store/handle-file/1101.md",
    "json": "practice-store/result/1101.json"
  },
  "summary": {
    "status": "PASS | SOFT_FAIL | HARD_FAIL",
    "hard_fail_count": 0,
    "soft_fail_count": 0,
    "warning_count": 0,
    "info_count": 0,
    "md_question_count": 40,
    "json_question_count": 40
  },
  "checks": [
    {"name": "counts",            "passed": true,  "finding_count": 0},
    {"name": "answer_letters",    "passed": true,  "finding_count": 0},
    {"name": "image_urls",        "passed": true,  "finding_count": 0},
    {"name": "section_titles",    "passed": true,  "finding_count": 0},
    {"name": "points_sum",        "passed": true,  "finding_count": 0},
    {"name": "stem_fidelity",     "passed": true,  "finding_count": 0},
    {"name": "text_field_naming", "passed": true,  "finding_count": 0}
  ],
  "findings": []
}
```

Each `findings[]` entry carries `{check, severity, message, q_num?, expected?, got?, fuzz?}`.

## Files in this skill

- `SKILL.md` — this spec
- `scripts/normalize_text.py` — NFC + LaTeX + whitespace normalization, `fuzz_ratio()`, `normalize_numeric_answer()`
- `scripts/md_parser.py` — extract `{question_count, questions[], section_titles[], image_urls}` from MD
- `scripts/compare_structures.py` — 7 check functions returning `CheckResult` lists
- `scripts/verify_json_vs_pdf.py` — CLI entrypoint, builds and writes the report

## Validation status

| Mutation | Expected | Got |
|---|---|---|
| baseline 1101 (no mutation) | PASS | PASS ✓ |
| delete Q23 | HARD_FAIL (counts) | HARD_FAIL ✓ |
| flip Q5 answer | SOFT_FAIL (answer_letters) | SOFT_FAIL ✓ |
| Q15 text→content key | HARD_FAIL (text_field_naming) | HARD_FAIL ✓ |
| rename "Phần I." → "TASK 1" | HARD_FAIL (section_titles) | HARD_FAIL ✓ |
| paraphrase Q12 stem | HARD_FAIL (stem_fidelity) | HARD_FAIL ✓ |
| zero Q1 points | HARD_FAIL (points_sum) | HARD_FAIL ✓ |
| synthetic TFG baseline | PASS | PASS ✓ |
| TFG flip sub-c Đ↔S | SOFT_FAIL (subquestion_answers) | SOFT_FAIL ✓ |
| `Câu 1:` colon variant | PASS | PASS ✓ |

Image-drop mutation skipped — 1101.md has 0 images. Will be covered when a Math/Physics fixture with Mathpix images is added (Phase 06).

## When to use

- `practice-importer` agent during Step 5.5 (post-JSON, pre-import) — Phase 04.
- Standalone audit: `/practice:verify <practice_id>` (Phase 05).
- Regression test in `tests/Feature/PracticeVerifier/` (Phase 06).

## When NOT to use

- Input was MD only (no PDF). Verifier still works — it only uses MD as ground truth — but the absence of `--pdf` arg is normal.
- Pre-import, after MANUAL admin edits via the UI. Use `/practice:verify <id>` instead, which reads the DB Practice (Phase 05 requires backend export command).

## See also

- `.claude/skills/practice-pdf-to-md/SKILL.md` — Stage 0
- `.claude/skills/practice-md-to-json/SKILL.md` — Stage 1-5
- `.claude/agents/practice-importer.md` — orchestrator (Phase 04 will add Step 5.5 wiring)
- `plans/260525-0027-practice-verifier-module/plan.md` — full implementation plan
