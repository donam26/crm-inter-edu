---
name: question-classifier
description: Classify Vietnamese education questions into the DEEPEST canonical topic node — bài học (L4 lesson) when one exists under the chosen chương, else the chương (L3 chapter), else the "Khác" catch-all — using the `interedu` MCP server (PHP-native, under `app/Mcp/`). Also covers REFINE runs that upgrade chương-level questions onto their chương's own bài học. Replaces the legacy `questions:auto-classify` artisan cron with a user-triggered Opus agent that grounds every decision in MCP tool calls (list_topics, get_question_context, find_similar_questions, apply_classification, classification_stats). Auto-loads when the question-classifier agent runs, when handling /questions:classify, or when work touches question taxonomy / topic_id.
license: MIT
---

# Question Classifier

User-triggered classification of `App\Models\Question` rows. Drops the rigid nightly `gpt-4o-mini` batch in favour of an Opus agent that reads full question context (text + options + correct_answer + explanation + passage + similar classified neighbors) and writes one classification at a time via MCP.

> Note: the skill-tag system was removed — classification is **topic-only**. Since the lesson upgrade (2026-07), the assigned node is the DEEPEST that fits: **bài học (L4)** when confident, else **chương (L3)**, else **"Khác"**. This matches the granularity the `question-importer` produces, so the bank converges on lesson-level filtering.

## Taxonomy (shared with the importer)

```
L1 grade (Lớp 12)
└─ L2 subject (Toán)                 lop-12--toan-hoc
   └─ L3 chapter (Chương)            lop-12--toan-hoc--nguyen-ham-tich-phan
      └─ L4 lesson (Bài học, lá)     lop-12--toan-hoc--nguyen-ham-tich-phan--nguyen-ham
```

`Question.topic_id` points at ONE node. The bank filter UI expands subtrees (a chương filter also catches lesson-tagged questions), so lesson-level assignment is strictly better when correct. Lessons are seeded by the importer / curated by the user — **the classifier NEVER creates one**.

## Pipeline

```
/questions:classify  ──►  question-classifier agent (Opus, ultrathink ×2 tiers)
                                  │
                                  │ MCP tool calls (stdio local / HTTP prod)
                                  ▼
                          interedu MCP server (PHP, `app/Mcp/`)
                                  │
                                  ▼
                          QuestionAutoClassifierService::applyToQuestion
                          (lesson → chương → "Khác" ladder; one-way refine)
                                  │
                                  ▼
                          Question.topic_id
                          + structured audit log (channel=classification)
```

## When to use

- User runs `/questions:classify` (with optional filters).
- User asks "classify the questions", "phân loại đề", "fill missing topics", "chia theo bài học", etc.
- User wants to re-classify a specific bucket (subject + grade).
- User wants to UPGRADE already-classified chương-level questions to bài học granularity → `--refine`.

## MCP tools (interedu server)

All tools live in `app/Mcp/Tools/Questions/` and are exposed by `php artisan mcp:serve`. Schemas live in each Tool's `inputSchema()` — agent can also call `tools/list` to inspect.

| Tool | Purpose | When to call |
|---|---|---|
| `classification_stats` | Coverage stats: `missing_topic`, granularity split `at_lesson` / `at_chapter_only` / `at_other`, by_subject, by_grade | Run start (scope + refine backlog) + run end (verify delta) |
| `list_topics` | Canonical QuestionTopic rows. `{group: "chapter", include_lessons: true}` nests each chương's bài học as `lessons: [{id, slug, name}]` | Once per (subject, grade) bucket at start — cache in context |
| `find_questions` | Paginate questions by status. `needs_classification: true` = missing topic (fresh runs); `needs_lesson: true` = topic NULL **or** chương-level, "Khác" excluded (refine runs) | At start to get the work-set |
| `get_question_context` | Full payload for ONE question (text + options + answer + explanation + passage + current topic incl. `group` + `parent`) | Per question, before classifying |
| `find_similar_questions` | Top-N already-classified neighbors via MySQL FULLTEXT; neighbor topic carries `group` + `parent` (chương), so a lesson-level neighbor is the strongest bài học prior | Per question, after `get_question_context` |
| `apply_classification` | WRITE — assign `topic_slug` (+ optional `lesson_slug` to land on the bài học). `refine: true` upgrades chương → its own lesson child. `fallback_other: true` → "Khác". Requires `reason` | Per question, after ULTRATHINK decision |

CLI inspector for ad-hoc debugging:
```bash
php artisan mcp:invoke --tool=classification_stats --args='{"subject":"math","grade_level":12}'
php artisan mcp:invoke --tool=list_topics --args='{"subject":"math","grade_level":12,"group":"chapter","include_lessons":true}'
```

## Classification rubric

### Tier 1 — chương (1 per question, REQUIRED if you write at all)

1. **Always prefer an existing canonical chương slug from the cached catalog.**
2. Use full slug verbatim (e.g. `lop-12--toan-hoc--nguyen-ham-tich-phan`), not the bare suffix.
3. **NEVER fabricate a specific new chương.** When the question fits NO existing chương, set `fallback_other: true` (omit `topic_slug`) → the per-(subject, grade) **"Khác"** catch-all, created on first use. No readable question is left without a topic.
4. If unsure between two existing chương: pick the one with HIGHER overlap with the neighbor topics from `find_similar_questions`.
5. A neighbor whose topic is "Khác" is NOT positive evidence — it means "unclassified". Never let it pull a classifiable question into "Khác".

### Tier 2 — bài học (optional, only inside the Tier-1 chương)

1. Candidates = the chosen chương's `lessons` array from the cache. **Nothing else is proposable**; the service rejects cross-chương lessons.
2. **Confidence bar is HIGHER than Tier 1** — a wrong bài học is worse than an honest chương-level stop.
3. Strong signals, in order:
   - a neighbor with `topic.group == "lesson"` whose `topic.parent` is the same chương and similarity is high → near-certainty for that lesson;
   - the chương has exactly **1 bài học** → assign it (trivial mapping — common in Hóa/Lý);
   - the stem's core concept matches one lesson name unambiguously (e.g. "nguyên hàm" vs "tích phân" vs "ứng dụng tích phân").
4. Stop at the chương (omit `lesson_slug`) when: the chương has **0 bài học** (report it), you are torn between ≥2 lessons (name both in `reason`), or the signal is weak.
5. Slug forms accepted: full lesson slug (preferred, from the cache) or bare suffix; `lesson_name` matches case-insensitively. All resolve strictly UNDER the chương.

### Refine runs (`--refine`)

- Work-set: `find_questions {needs_lesson: true}` — questions with NULL topic or a chương-level topic ("Khác" excluded).
- Write: `apply_classification {id, lesson_slug, refine: true, reason}` — no `topic_slug`; the question's current chương is the anchor.
- The service is the guard, not the agent: it refuses `refine` unless current topic is a non-"Khác" chương AND the lesson exists directly under it. Skip statuses (`skipped_lesson_not_found`, `skipped_not_chapter`, `skipped_topic_is_other`) are expected outcomes — record and continue.
- >40% `skipped_lesson_not_found` in a bucket ⇒ its lesson catalog is incomplete → stop early, report which chương need seeding (the user curates lesson lists; the classifier never seeds).

### Signals to weigh (in order of strength)

1. **Question text + LaTeX**: e.g. `$\int_0^1 …$` → integral; `f'(x)=0` → critical points.
2. **Options + correct_answer**: e.g. options are intervals → optimization; options are matrices → linear algebra.
3. **Explanation**: explicit chain of reasoning often names the chương/bài.
4. **Passage** (if attached): reading-comprehension subject is the passage itself.
5. **Similarity grounding** (`find_similar_questions`): if 3 of 3 neighbors share a real topic X with high similarity_score, that's near-certainty. Lesson-level neighbors ground BOTH tiers at once. (Neighbors on "Khác" count for nothing.)

### Confidence + reason field (audit trail)

Every `apply_classification` call MUST include a `reason` string citing the signals:
- `"integral stem; neighbor #1 (sim 0.82, lesson nguyen-ham under same chương) → lesson nguyen-ham"`
- `"explanation walks through u-substitution; chương confident, torn between nguyen-ham and tich-phan → stop at chương"`
- `"general study-skills question; no Toán 12 chương fits → Khác"` (when `fallback_other: true`)

Topic confidence ladder: confident bài học → assign it; confident only chương → assign the chương; otherwise (no chương fits, or low topic confidence on a still-readable stem) → `fallback_other: true` → "Khác". Never skip a readable question just because no chương matched. Reserve SKIP — record `{id, skipped: true, reason}` — for genuinely unusable rows (corrupted/empty stem, or already classified at lesson level).

## Run modes

- **Default (`/questions:classify`)** — classify questions missing a topic; agent reports at end.
- **`--refine`** — upgrade chương-level questions onto their chương's own bài học (`needs_lesson` work-set + `refine: true` writes).
- **`--dry-run`** — agent computes proposals + emits report, but skips `apply_classification` calls. Use to preview before committing (fresh AND refine).
- **`--limit=N`** — cap questions processed this run (default 50; prod cap 100).
- **`--subject=X` / `--grade=N`** — scope filters.
- **`--target=local|prod`** — MCP server selection (prod = HTTP, dry-run first on new buckets).

## Output report (end of run)

Per-question table + summary:
```
| #  | Question (preview)             | Chương                  | Bài học            | Mức gán  |
|----|--------------------------------|-------------------------|--------------------|----------|
| 1  | Tính ∫₀¹ x²dx                  | nguyen-ham-tich-phan    | nguyen-ham         | lesson   |
| 2  | Cho hàm số bậc ba…             | khao-sat-ham-so         | — (2 ứng viên)     | chapter  |
| 3  | (no chương fits)               | → Khác                  | —                  | other    |
| 4  | (corrupted stem)               | —                       | —                  | skipped  |

Summary: 38 lesson / 7 chapter-only / 4 Khác / 1 skipped. Chương thiếu bài học: [dao-dong-co].
Stats delta: at_lesson 120→158, at_chapter_only 96→60.
Audit: storage/logs/classification.log (channel=classification), one entry per apply incl. `status` + `assigned_level`.
```

## Constraints

- NEVER overwrite an existing `topic_id` outside the refine path — and refine only moves chương → its OWN lesson child (`applyToQuestion` enforces it).
- NEVER propose a lesson outside the chosen chương's `lessons` array — the service would refuse it anyway.
- NEVER create a bài học — pre-seeded lessons only. The user curates the lesson catalog.
- NEVER apply without a `reason` field (validated by the tool).
- NEVER classify more than `--limit` questions per run.
- NEVER skip a question silently — every skip MUST appear in the final report with its reason.
- NEVER fabricate a chương — a non-matching question goes to "Khác" via `fallback_other: true`, never a new topic row.
- NEVER call MCP write tools before reading the canonical catalog (`list_topics {include_lessons: true}`).
- NEVER bypass `find_similar_questions` — even when it returns 0 neighbors, the call itself is the grounding step.

## See also

- `app/Mcp/Tools/Questions/` — tool implementations
- `app/Services/QuestionAutoClassifierService.php::applyToQuestion` — the lesson→chương→"Khác" ladder + one-way refine the write tool delegates to (`resolveExistingChapter`, `resolveExistingLesson`, `resolveOrCreateOtherChapter`, `refineToLesson`)
- `app/Enums/TagGroup.php` — topic group taxonomy (grade, subject, chapter, lesson, …)
- `routes/console.php` — nightly cron was REMOVED, see comment block there
- `plans/260518-1654-question-classifier-mcp-agent/plan.md` — original implementation plan
- `plans/260620-1719-khac-fallback-topic.md` — "Khác" catch-all fallback design + change log
- `plans/reports/analysis-260703-1007-classifier-lesson-upgrade.md` — lesson-granularity upgrade design (this feature)
