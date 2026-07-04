"""normalize_text.py — text normalization helpers for the verifier.

Provides comparison primitives used by compare_structures.py:
  - normalize_text(s): collapse whitespace, NFC Unicode, lowercase, drop math wrappers
  - fuzz_ratio(a, b): SequenceMatcher ratio in [0..1] after normalize
  - normalize_numeric_answer(s): canonicalize fill_blank numeric answers
    so "0.5" == "0,5" == "1/2"

Pure stdlib (no third-party deps). Imported by:
  - compare_structures.check_stem_fidelity
  - compare_structures.check_answer_letters (numeric path)
"""

from __future__ import annotations

import re
import unicodedata
from difflib import SequenceMatcher
from fractions import Fraction

# Mathpix-clean wrappers: $...$ and $$...$$ — strip outer $ for comparison.
# We keep INNER content (the LaTeX command) because that's the meaningful diff.
_LATEX_WRAPPER_RE = re.compile(r"\$+([^$]+?)\$+")

# Collapse runs of whitespace (incl. \n, \t) to a single space.
_WHITESPACE_RE = re.compile(r"\s+")

# LaTeX fraction command variants that render identically:
#   \dfrac{a}{b} == \tfrac{a}{b} == \cfrac{a}{b} == \frac{a}{b}
_LATEX_FRAC_VARIANTS = re.compile(r"\\(?:dfrac|tfrac|cfrac)\b")

# Vietnamese MD bold + Câu / Đáp án markers that the verifier adds to wrap
# question chunks. These are structural — strip before fuzz comparison.
_MD_BOLD_MARKERS = re.compile(r"\*\*")
_MD_LIST_BULLETS = re.compile(r"(?m)^\s*[-*]\s+")


def normalize_text(s: str | None) -> str:
    """Normalize text for fuzzy comparison.

    Steps (in order):
      1. None → "" (defensive)
      2. NFC Unicode (compose accents back to single codepoint)
      3. Normalize LaTeX fraction variants to \\frac
      4. Strip outer $...$ / $$...$$ wrappers (keep inner LaTeX)
      5. Strip MD bold (**…**) and list bullets (- / *)
      6. Collapse whitespace
      7. Lowercase (preserves Vietnamese accents — already NFC composed)
      8. Trim leading/trailing whitespace
    """
    if s is None:
        return ""
    s = unicodedata.normalize("NFC", s)
    s = _LATEX_FRAC_VARIANTS.sub(r"\\frac", s)
    s = _LATEX_WRAPPER_RE.sub(lambda m: m.group(1), s)
    s = _MD_BOLD_MARKERS.sub("", s)
    s = _MD_LIST_BULLETS.sub("", s)
    s = _WHITESPACE_RE.sub(" ", s)
    return s.strip().lower()


def fuzz_ratio(a: str | None, b: str | None) -> float:
    """Return SequenceMatcher ratio after normalize_text.

    Returns 1.0 when both inputs normalize to identical text, 0.0 when
    completely disjoint. Symmetric: fuzz_ratio(a, b) == fuzz_ratio(b, a).
    """
    na, nb = normalize_text(a), normalize_text(b)
    if not na and not nb:
        return 1.0
    return SequenceMatcher(None, na, nb).ratio()


def normalize_numeric_answer(s: str | None) -> str:
    """Canonicalize fill_blank numeric answer so "0.5" == "0,5" == "1/2".

    Vietnamese exams use comma as decimal separator. Fractions appear as
    "1/2". We parse via Fraction → canonical "1/2" form. Non-numeric
    answers (text words) pass through after lowercase+strip.
    """
    if s is None:
        return ""
    s = s.strip().replace(",", ".")
    try:
        return str(Fraction(s).limit_denominator(1000))
    except (ValueError, ZeroDivisionError):
        return s.lower()
