"""md_parser.py — extract ground-truth structure from Mathpix-style MD.

Input: the *.md file in practice-store/handle-file/, produced by
`practice-pdf-to-md` (PyMuPDF). The MD body is byte-identical with what
PyMuPDF extracted (the practice-importer agent's hard rule guarantees
the body stays untouched when YAML frontmatter is prepended).

Output: a dict with the structural facts the verifier needs:
  - question_count: int
  - questions: list[dict] — per-question {number, stem, correct_answer, options_inline, options_listed}
  - section_titles: list[str] — verbatim from `## …` headings
  - image_urls: set[str] — every cdn.mathpix.com URL
  - has_yaml_frontmatter: bool
  - body: str — exam body (frontmatter stripped)

Imported by verify_json_vs_pdf.py.
"""

from __future__ import annotations

import re

# YAML frontmatter delimiters — leading "---" then any content then "---".
_FRONTMATTER_RE = re.compile(r"\A---\s*\n(.*?)\n---\s*\n", re.DOTALL)

# Section heading: "## Phần I.", "## PHẦN III.", "## Part 2.", etc.
_SECTION_RE = re.compile(r"(?m)^##\s+(.+?)\s*$")

# Question marker: "**Câu N.**" (Vietnamese) or "**Question N.**" (English).
# Some Mathpix outputs use ":" or no separator at all — accept both.
# Captures: question number.
_QUESTION_MARKER_RE = re.compile(
    r"\*\*(?:Câu|Question|Bài)\s+(\d+)\s*[.:]?\*\*",
    re.IGNORECASE,
)

# Answer line per question:
#   "**Đáp án:** X"          → single_choice / fill_blank
#   "**Đáp án:** a) Đ; b) Đ" → true_false_group
# Capture group: everything AFTER the marker, trimmed.
_ANSWER_LINE_RE = re.compile(
    r"\*\*Đáp\s*án\s*[:：]?\*\*\s*(.+?)\s*$",
    re.MULTILINE | re.IGNORECASE,
)

# Mathpix image URLs anywhere in the body.
_IMAGE_URL_RE = re.compile(r"https://cdn\.mathpix\.com/[^\s)\"'>]+")

# Option / sub-statement anchor — first occurrence terminates the stem.
# Handles two patterns:
#   MCQ option:        `A.` / `B.` / `C.` / `D.`  (followed by whitespace)
#   TFG sub-statement: `a)` / `b)` / `c)` / `d)`  (followed by whitespace)
# Both must sit at line start (with optional `-` bullet) or at chunk start.
_OPTION_ANCHOR_RE = re.compile(
    r"(?m)(?:^\s*-?\s*|\A\s*)(?:[A-D]\.|[a-d]\))\s+",
)


def strip_frontmatter(text: str) -> tuple[str, bool]:
    """Strip YAML frontmatter if present. Returns (body, had_frontmatter)."""
    m = _FRONTMATTER_RE.match(text)
    if not m:
        return text, False
    return text[m.end():], True


def extract_section_titles(body: str) -> list[str]:
    """Return verbatim section heading text in document order."""
    return [m.group(1).strip() for m in _SECTION_RE.finditer(body)]


def extract_image_urls(body: str) -> set[str]:
    """Return set of unique Mathpix image URLs found in body."""
    return set(_IMAGE_URL_RE.findall(body))


def _parse_question_chunk(num: int, chunk: str) -> dict:
    """Parse one question chunk (text between **Câu N.** and next marker).

    Returns dict with: number, stem, correct_answer.

    Stem extraction takes everything BEFORE the earliest option anchor
    (`A.` / `B.` / `C.` / `D.` at the start of a line or bullet, OR at
    chunk start). When no option anchors exist, cut before **Đáp án:**.

    Empty/whitespace-only stems are returned as "" — typical for fill-blank
    questions whose "real" stem is the surrounding passage (the verifier's
    check_stem_fidelity skips when stem is empty).
    """
    ans_m = _ANSWER_LINE_RE.search(chunk)
    correct = ans_m.group(1).strip() if ans_m else ""

    first_opt_m = _OPTION_ANCHOR_RE.search(chunk)
    if first_opt_m:
        cutoff = first_opt_m.start()
    elif ans_m:
        cutoff = ans_m.start()
    else:
        cutoff = len(chunk)

    stem = chunk[:cutoff].strip()
    return {
        "number": num,
        "stem": stem,
        "correct_answer": correct,
    }


def extract_questions(body: str) -> list[dict]:
    """Split MD body into question chunks at **Câu N.** boundaries."""
    markers = list(_QUESTION_MARKER_RE.finditer(body))
    if not markers:
        return []

    out: list[dict] = []
    for i, m in enumerate(markers):
        start = m.end()
        end = markers[i + 1].start() if i + 1 < len(markers) else len(body)
        num = int(m.group(1))
        chunk = body[start:end]
        out.append(_parse_question_chunk(num, chunk))
    return out


def parse(md_text: str) -> dict:
    """Top-level entry point — parse a full MD file into the ground-truth dict.

    Returns a stable schema the verifier's checks consume:
      {
        "has_yaml_frontmatter": bool,
        "body": str,
        "section_titles": list[str],
        "image_urls": set[str],
        "question_count": int,
        "questions": list[{number, stem, correct_answer, options_inline, options_listed}],
      }
    """
    body, has_fm = strip_frontmatter(md_text)
    questions = extract_questions(body)
    return {
        "has_yaml_frontmatter": has_fm,
        "body": body,
        "section_titles": extract_section_titles(body),
        "image_urls": extract_image_urls(body),
        "question_count": len(questions),
        "questions": questions,
    }
