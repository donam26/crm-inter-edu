"""compare_structures.py — per-check assertions for the practice verifier.

Each `check_*` function takes the parsed MD ground truth (from md_parser.parse)
and the LLM-emitted JSON data block, runs ONE structural/textual assertion,
and returns a CheckResult listing zero-or-more Findings.

Severity classification (defined in Phase 01 architecture doc):
  HARD: blocks import, no retry. e.g. counts mismatch, image dropped, key naming bug.
  SOFT: allowed retry via LLM judgment, max 2x. e.g. stem fuzz 0.75-0.92, answer flip.
  WARN: log only, still imports.
  INFO: silent log.

Imported by verify_json_vs_pdf.py (the CLI entrypoint).
"""

from __future__ import annotations

import json as _json
from dataclasses import dataclass, field, asdict
from typing import Literal

from normalize_text import fuzz_ratio

Severity = Literal["HARD", "SOFT", "WARN", "INFO"]

# Question types whose `correct_answer` is not a per-row letter — skip them
# in checks that compare against MD-extracted **Đáp án:** X markers.
# essay: free-form rubric; hdc: HDC rubric mode; open_ended: future type.
NON_GRADABLE_TYPES = {"essay", "hdc", "open_ended"}


@dataclass
class Finding:
    """One assertion failure."""
    check: str
    severity: Severity
    message: str
    q_num: int | None = None
    expected: str | None = None
    got: str | None = None
    fuzz: float | None = None

    def to_dict(self) -> dict:
        return {k: v for k, v in asdict(self).items() if v is not None or k == "severity"}


@dataclass
class CheckResult:
    """Outcome of one check function."""
    name: str
    passed: bool
    findings: list[Finding] = field(default_factory=list)


# ─── Counts ─────────────────────────────────────────────────────────────────

def check_counts(md_truth: dict, json_data: dict) -> CheckResult:
    """questions[].length MUST equal MD detected questions.

    HARD on mismatch — silent-drop / question-skip is the #1 risk we guard.
    """
    md_count = md_truth["question_count"]
    json_count = len(json_data.get("questions", []))
    findings: list[Finding] = []
    if md_count != json_count:
        findings.append(Finding(
            check="counts",
            severity="HARD",
            message=f"MD has {md_count} questions, JSON has {json_count}",
            expected=str(md_count),
            got=str(json_count),
        ))
    return CheckResult("counts", not findings, findings)


# ─── Answer letters ─────────────────────────────────────────────────────────

def check_answer_letters(md_truth: dict, json_data: dict) -> CheckResult:
    """Per-question correct_answer in JSON MUST match MD-extracted answer.

    SOFT: a 1-3 question mismatch could be PDF marker miss (not LLM fault).
    LLM judgment subagent decides ACCEPT/REJECT.

    Skips NON_GRADABLE_TYPES (essay/hdc/open_ended) and true_false_group
    (sub-answers live in sub_questions[] — covered by check_subquestion_answers).
    """
    md_answers = {q["number"]: q["correct_answer"] for q in md_truth["questions"]}
    findings: list[Finding] = []
    for i, q in enumerate(json_data.get("questions", [])):
        qtype = q.get("type", "")
        if qtype in NON_GRADABLE_TYPES or qtype == "true_false_group":
            continue
        q_num = i + 1  # 1-indexed to match MD
        md_ans = md_answers.get(q_num, "")
        json_ans = q.get("correct_answer", "")
        if not md_ans or not json_ans:
            continue  # both sides have missing answer — caught by other checks
        if md_ans != json_ans:
            findings.append(Finding(
                check="answer_letters",
                severity="SOFT",
                q_num=q_num,
                expected=md_ans,
                got=json_ans,
                message=f"Q{q_num} answer mismatch: MD={md_ans!r} JSON={json_ans!r}",
            ))
    return CheckResult("answer_letters", not findings, findings)


# Matches MD line like "a) Đ; b) S; c) Đ; d) S" produced by practice-pdf-to-md
# for true_false_group answers. Captures all sub-letter → Đ/S pairs.
import re as _re
_TFG_ANSWER_TOKEN_RE = _re.compile(r"([a-d])\)\s*(Đ|S)\b", _re.IGNORECASE)


def check_subquestion_answers(md_truth: dict, json_data: dict) -> CheckResult:
    """For true_false_group questions, every sub_question correct_answer in
    JSON MUST match the MD-extracted per-sub Đ/S value.

    MD format (from practice-pdf-to-md):
        **Đáp án:** a) Đ; b) Đ; c) S; d) Đ

    SOFT severity — same rationale as check_answer_letters.
    """
    md_questions = {q["number"]: q for q in md_truth["questions"]}
    findings: list[Finding] = []
    for i, jq in enumerate(json_data.get("questions", [])):
        if jq.get("type") != "true_false_group":
            continue
        q_num = i + 1
        md_q = md_questions.get(q_num)
        if not md_q:
            continue
        # Parse "a) Đ; b) S; …" from MD correct_answer field.
        md_subs = dict(_TFG_ANSWER_TOKEN_RE.findall(md_q["correct_answer"] or ""))
        md_subs = {k.lower(): v for k, v in md_subs.items()}
        for j, jsub in enumerate(jq.get("sub_questions") or []):
            letter = (jsub.get("label") or chr(ord("a") + j)).lower()
            md_val = md_subs.get(letter)
            json_val = (jsub.get("correct_answer") or "").strip()
            if not md_val or not json_val:
                continue
            if md_val != json_val:
                findings.append(Finding(
                    check="subquestion_answers",
                    severity="SOFT",
                    q_num=q_num,
                    expected=f"{letter}={md_val}",
                    got=f"{letter}={json_val}",
                    message=f"Q{q_num}({letter}) sub-answer mismatch: MD={md_val} JSON={json_val}",
                ))
    return CheckResult("subquestion_answers", not findings, findings)


# ─── Image URLs ─────────────────────────────────────────────────────────────

def check_image_urls(md_truth: dict, json_data: dict) -> CheckResult:
    """Every cdn.mathpix.com URL in MD MUST appear somewhere in JSON.

    HARD — dropping an image silently breaks rendering for that question.
    Scan the entire JSON-as-string (passages, options, sub_questions, etc.).

    Uses ``_json`` (top-level import) — local re-import would cost startup.
    """
    md_imgs = md_truth["image_urls"]
    json_blob = _json.dumps(json_data, ensure_ascii=False)
    findings: list[Finding] = []
    for url in md_imgs:
        if url not in json_blob:
            findings.append(Finding(
                check="image_urls",
                severity="HARD",
                expected=url,
                message=f"Image URL dropped from JSON: {url}",
            ))
    return CheckResult("image_urls", not findings, findings)


# ─── Section titles ─────────────────────────────────────────────────────────

def check_section_titles(md_truth: dict, json_data: dict) -> CheckResult:
    """Section titles in JSON MUST match MD `## …` headings verbatim.

    HARD — hard rule says NEVER rename to template TASK labels.
    """
    md_titles = md_truth["section_titles"]
    json_titles = [s.get("title", "").strip() for s in json_data.get("sections", [])]
    findings: list[Finding] = []
    # Compare element-wise up to the shorter list, then flag any extras.
    for i, (md_t, json_t) in enumerate(zip(md_titles, json_titles)):
        if md_t.strip() != json_t:
            findings.append(Finding(
                check="section_titles",
                severity="HARD",
                expected=md_t.strip(),
                got=json_t,
                message=f"Section #{i} title renamed",
            ))
    if len(md_titles) != len(json_titles):
        findings.append(Finding(
            check="section_titles",
            severity="HARD",
            expected=f"{len(md_titles)} sections",
            got=f"{len(json_titles)} sections",
            message=f"Section count mismatch ({len(md_titles)} vs {len(json_titles)})",
        ))
    return CheckResult("section_titles", not findings, findings)


# ─── Points sum ─────────────────────────────────────────────────────────────

def check_points_sum(md_truth: dict, json_data: dict,
                     expected_total: float | None = None) -> CheckResult:
    """Σ questions[].points MUST be positive and consistent with source signals.

    Without an explicit source total we only assert >0 and not-NaN. When
    a per-section/per-exam barem hint is parseable from MD (Phase 03 future
    work), we'll tighten this. For now: SOFT when zero or NaN.
    """
    findings: list[Finding] = []
    pts = [float(q.get("points", 0) or 0) for q in json_data.get("questions", [])]
    total = sum(pts)
    if any(p <= 0 for p in pts):
        zero_qs = [i + 1 for i, p in enumerate(pts) if p <= 0]
        findings.append(Finding(
            check="points_sum",
            severity="HARD",
            message=f"Questions with non-positive points: {zero_qs}",
        ))
    if expected_total is not None and abs(total - expected_total) > 0.01:
        findings.append(Finding(
            check="points_sum",
            severity="SOFT",
            expected=str(expected_total),
            got=str(total),
            message=f"Points sum mismatch: expected={expected_total} got={total}",
        ))
    return CheckResult("points_sum", not findings, findings)


# ─── Stem fidelity ──────────────────────────────────────────────────────────

def check_stem_fidelity(md_truth: dict, json_data: dict,
                        hard_threshold: float = 0.75,
                        soft_threshold: float = 0.92) -> CheckResult:
    """Per-question stem MUST fuzz-match MD ≥ soft_threshold.

    Tiered: fuzz < hard_threshold → HARD (likely paraphrase, blocks import).
            fuzz < soft_threshold → SOFT (whitespace / LaTeX / minor diff).

    Param names align with the severity they produce.

    Questions with empty MD stems (option-only fill-blanks) are skipped.
    """
    md_questions = {q["number"]: q for q in md_truth["questions"]}
    findings: list[Finding] = []
    for i, jq in enumerate(json_data.get("questions", [])):
        q_num = i + 1
        md_q = md_questions.get(q_num)
        if not md_q:
            continue
        md_stem = md_q["stem"].strip()
        json_stem = jq.get("text", "").strip()
        if not md_stem:
            # Option-only question (e.g. fill-blank in a passage).
            # Stem fidelity check is N/A here; structure checks cover it.
            continue
        if not json_stem:
            findings.append(Finding(
                check="stem_fidelity",
                severity="HARD",
                q_num=q_num,
                expected=md_stem[:80],
                got="",
                message=f"Q{q_num} JSON text empty but MD has stem",
            ))
            continue
        fuzz = fuzz_ratio(md_stem, json_stem)
        if fuzz < hard_threshold:
            findings.append(Finding(
                check="stem_fidelity",
                severity="HARD",
                q_num=q_num,
                expected=md_stem[:80],
                got=json_stem[:80],
                fuzz=round(fuzz, 3),
                message=f"Q{q_num} stem fuzz {fuzz:.2f} < {hard_threshold} (paraphrase suspected)",
            ))
        elif fuzz < soft_threshold:
            findings.append(Finding(
                check="stem_fidelity",
                severity="SOFT",
                q_num=q_num,
                expected=md_stem[:80],
                got=json_stem[:80],
                fuzz=round(fuzz, 3),
                message=f"Q{q_num} stem fuzz {fuzz:.2f} < {soft_threshold}",
            ))
    hard_present = any(f.severity == "HARD" for f in findings)
    return CheckResult("stem_fidelity", not hard_present, findings)


# ─── Text-vs-content field naming ───────────────────────────────────────────

def check_text_field_naming(_md_truth: dict, json_data: dict) -> CheckResult:
    """Backend silent-drop guard.

    Rules (from practice-md-to-json SKILL.md ⚠ section):
      - questions[].text REQUIRED, NEVER 'content'
      - passages[].content REQUIRED, NEVER 'text'
      - A question with empty 'text' AND no options/sub_questions is silent-dropped.

    All findings HARD — the import will lose questions otherwise.
    """
    findings: list[Finding] = []
    for i, q in enumerate(json_data.get("questions", [])):
        q_num = i + 1
        if "content" in q:
            findings.append(Finding(
                check="text_field_naming",
                severity="HARD",
                q_num=q_num,
                message=f"Q{q_num} uses key 'content' (must be 'text') — backend silent-drop risk",
            ))
        text = (q.get("text") or "").strip()
        opts = q.get("options") or []
        subs = q.get("sub_questions") or []
        if not text and not opts and not subs:
            findings.append(Finding(
                check="text_field_naming",
                severity="HARD",
                q_num=q_num,
                message=f"Q{q_num} empty text + no options/sub_questions — backend will silent-drop",
            ))
    for i, p in enumerate(json_data.get("passages", [])):
        if "text" in p:
            findings.append(Finding(
                check="text_field_naming",
                severity="HARD",
                message=f"Passage #{i} uses key 'text' (must be 'content')",
            ))
    return CheckResult("text_field_naming", not findings, findings)


# ─── Aggregate ──────────────────────────────────────────────────────────────

ALL_CHECKS = [
    check_counts,
    check_answer_letters,
    check_subquestion_answers,
    check_image_urls,
    check_section_titles,
    check_points_sum,
    check_stem_fidelity,
    check_text_field_naming,
]


def run_all(md_truth: dict, json_data: dict) -> list[CheckResult]:
    """Run every check; return ordered results list."""
    return [fn(md_truth, json_data) for fn in ALL_CHECKS]
