"""
practice-pdf-to-md — convert a Vietnamese exam-answer-key PDF to Mathpix-style Markdown.

Pipeline:
  1) Open PDF with PyMuPDF (fitz).
  2) Per page, collect "answer highlight" rectangles (yellow-ish filled rects).
  3) Per page, collect text spans (text, bbox, font, size, color, bold flag).
  4) Group spans into reading-order lines, then aggregate lines into questions.
  5) Detect structure tokens: Phần I/II/III, Câu N:, options A./B./C./D., sub-statements a)/b)/c)/d).
  6) For each option/sub, check bbox overlap with any highlight rect → mark as "correct".
  7) Emit Markdown with YAML frontmatter and inline **Đáp án:** lines.

Output: a Markdown file ready for the existing `practice-md-to-json` skill.

Usage:
  python pdf_to_md.py <input.pdf> [<output.md>]

The default <output.md> is written alongside the input PDF (e.g. `practice-store/handle-file/<basename>.md` when the PDF lives in that folder).
"""

from __future__ import annotations

import io
import json
import re
import sys
from dataclasses import dataclass, field
from datetime import datetime, timezone, timedelta
from pathlib import Path
from typing import Iterable

import fitz  # PyMuPDF

# Local helpers
_HERE = Path(__file__).resolve().parent
import sys as _sys
_sys.path.insert(0, str(_HERE))
from symbol_font_map import normalize_symbol_chars  # noqa: E402


# ─── Constants ────────────────────────────────────────────────────────────────

# Generic "this fill is a highlight marker" classifier. A PDF answer-key
# generator may use any saturated color (yellow, pink, cyan, lime, lavender)
# as the answer marker. We reject only:
#   - white / page-background (R≈G≈B≈1)
#   - black / regular text fill (R≈G≈B≈0)
#   - grayscale (R==G==B) — used for table borders / shading
# Everything else with at least 25% saturation is a candidate marker; the
# extractor then keeps only the DOMINANT marker color per PDF (see
# `pick_dominant_marker` below) so a single stray red dot doesn't spoof results.
def _color_distance_to_gray(r: float, g: float, b: float) -> float:
    """Distance from a color to its nearest grayscale point (0..1)."""
    mean = (r + g + b) / 3
    return max(abs(r - mean), abs(g - mean), abs(b - mean))


def is_candidate_marker_fill(fill) -> bool:
    if fill is None:
        return False
    if len(fill) == 1:
        return False
    r, g, b = fill[0], fill[1], fill[2]
    # Skip near-white and near-black.
    if min(r, g, b) >= 0.95:
        return False
    if max(r, g, b) <= 0.10:
        return False
    # Skip grayscale (R≈G≈B).
    return _color_distance_to_gray(r, g, b) >= 0.20


def _quantize_color(fill, step: float = 0.1) -> tuple[float, float, float]:
    return tuple(round(c / step) * step for c in fill[:3])


# Reading order tolerance — two spans are considered "same line" if their
# vertical center distance is within this many points.
LINE_Y_TOLERANCE = 3.0

# Vietnamese question/section header regexes.
RE_SECTION_HEADER = re.compile(
    r"^\s*(PHẦN|Phần|PART|Part)\s+([IVXLCDM]+|\d+)\s*[:\.]?\s*(.*)$",
)
RE_QUESTION_START = re.compile(
    r"^\s*(Câu|Question|Bài)\s+(\d+)\s*[:\.]?\s*(.*)$",
    re.IGNORECASE,
)
# Options at line start. Bold capital letter followed by period.
RE_OPTION = re.compile(r"^\s*([A-D])\s*\.\s*(.*)$")
# True/false sub-statements: a) / (a) / a. — all separators allowed.
RE_SUB_STATEMENT = re.compile(
    r"^\s*(?:\(([a-d])\)|([a-d])\s*[.)])\s+(.+)$",
    re.IGNORECASE,
)
# Inline "Đáp án: X" written at end of fill_blank question stems.
# Number-style answers only: integer, decimal (Vietnamese comma or dot),
# negative sign, slash for fractions. Stop at the first non-numeric token so
# trailing decorations like "----HẾT----" don't end up inside the answer.
RE_INLINE_ANSWER = re.compile(
    r"Đáp\s*án\s*[:：]\s*(-?\d+(?:[.,]\d+)?(?:\s*/\s*\d+(?:[.,]\d+)?)?)",
    re.IGNORECASE,
)
# Per-question inline barem: "(0.5 điểm)" / "(1,5 đ)" / "(2 pt)". Captures
# the numeric value; caller multiplies by float and removes the matched
# substring from the question text.
RE_PER_QUESTION_POINTS = re.compile(
    r"\(\s*(\d+(?:[.,]\d+)?)\s*(?:điểm|đ|pt)\s*\)",
    re.IGNORECASE,
)
# Section names that mark a true/false group block.
TFG_SECTION_HINTS = ("đúng sai", "PHẦN II", "Phần II")


# Saigon timezone for ISO8601 stamping.
SAIGON_TZ = timezone(timedelta(hours=7))


# ─── Data classes ────────────────────────────────────────────────────────────

@dataclass
class Highlight:
    page: int
    bbox: tuple[float, float, float, float]  # x0, y0, x1, y1


@dataclass
class Span:
    page: int
    text: str
    bbox: tuple[float, float, float, float]
    font: str
    size: float
    color: int
    bold: bool


@dataclass
class Line:
    page: int
    y: float
    text: str
    bbox: tuple[float, float, float, float]
    spans: list[Span] = field(default_factory=list)
    highlighted: bool = False


@dataclass
class Question:
    number: int
    section_title: str
    section_index: int
    text_lines: list[Line] = field(default_factory=list)
    options: list[tuple[str, str, bool]] = field(default_factory=list)  # (letter, text, is_correct)
    sub_statements: list[tuple[str, str, bool]] = field(default_factory=list)
    qtype: str = "single_choice"
    free_answer: str | None = None  # for fill_blank — the highlighted answer text
    explicit_points: float | None = None  # set when source carries `Câu N. (X điểm)`
    group_index: int | None = None
    passage_index: int | None = None


# ─── PDF parsing ─────────────────────────────────────────────────────────────

def extract_highlights(doc: fitz.Document) -> tuple[list[Highlight], str]:
    """Collect filled rectangles whose fill = the dominant marker color in this PDF.

    Two-pass:
      1) Scan all pages, group candidate fills by quantized RGB, count rectangles
         attached to each group.
      2) Pick the most common group (> 3 rects) as "the marker"; keep only
         rectangles whose fill matches that color. If no group reaches the
         threshold, returns []. The caller then surfaces the "no markers" case.
    """
    color_count: dict[tuple[float, float, float], int] = {}
    rect_index: list[tuple[int, fitz.Rect, tuple[float, float, float]]] = []
    for pno, page in enumerate(doc):
        for drw in page.get_drawings():
            fill = drw.get("fill")
            if not is_candidate_marker_fill(fill):
                continue
            color_key = _quantize_color(fill)
            for item in drw["items"]:
                if item[0] != "re":
                    continue
                r = item[1]
                rect_index.append((pno, r, color_key))
                color_count[color_key] = color_count.get(color_key, 0) + 1
    if not color_count:
        return [], ""
    dominant, dominant_count = max(color_count.items(), key=lambda kv: kv[1])
    if dominant_count < 3:
        return [], ""
    chosen: list[Highlight] = [
        Highlight(page=pno, bbox=(r.x0, r.y0, r.x1, r.y1))
        for pno, r, ck in rect_index if ck == dominant
    ]
    label = "#" + "".join(f"{int(round(c * 255)):02X}" for c in dominant)
    return chosen, label


def extract_spans(doc: fitz.Document) -> list[Span]:
    """Collect every text span with its bbox + style.

    Applies Symbol-font Private-Use-Area normalization so Greek letters and
    math operators (Δ, ≠, →, ⇌, °, etc.) render correctly in downstream MD.
    """
    out: list[Span] = []
    for pno, page in enumerate(doc):
        d = page.get_text("dict", flags=fitz.TEXT_PRESERVE_LIGATURES | fitz.TEXT_PRESERVE_WHITESPACE)
        for block in d["blocks"]:
            if block["type"] != 0:
                continue
            for line in block["lines"]:
                for span in line["spans"]:
                    text = normalize_symbol_chars(span["text"])
                    if not text or text.isspace():
                        continue
                    font = span["font"]
                    out.append(
                        Span(
                            page=pno,
                            text=text,
                            bbox=tuple(span["bbox"]),
                            font=font,
                            size=span["size"],
                            color=span["color"],
                            bold="Bold" in font,
                        )
                    )
    return out


def group_into_lines(spans: list[Span]) -> list[Line]:
    """Cluster spans into lines using y-centroid tolerance."""
    by_page: dict[int, list[Span]] = {}
    for s in spans:
        by_page.setdefault(s.page, []).append(s)

    lines: list[Line] = []
    for pno in sorted(by_page):
        page_spans = by_page[pno]
        page_spans.sort(key=lambda s: ((s.bbox[1] + s.bbox[3]) / 2, s.bbox[0]))
        current: list[Span] = []
        current_y: float | None = None
        for s in page_spans:
            cy = (s.bbox[1] + s.bbox[3]) / 2
            if current_y is None or abs(cy - current_y) <= LINE_Y_TOLERANCE:
                current.append(s)
                current_y = cy if current_y is None else (current_y + cy) / 2
            else:
                lines.append(_build_line(current, pno))
                current = [s]
                current_y = cy
        if current:
            lines.append(_build_line(current, pno))
    return lines


def _join_spans_math_aware(spans: list[Span]) -> str:
    """Concatenate spans into text, wrapping super/subscripts with HTML tags.

    Heuristic per span:
      - "smaller" → span size < 80% of the line's dominant size.
      - "super" → smaller AND span vertical center sits ABOVE the line's
        dominant center (smaller y in PDF top-left origin).
      - "sub"   → smaller AND span center sits BELOW the line's dominant center.
    """
    if not spans:
        return ""
    sizes = [s.size for s in spans]
    dominant = max(set(sizes), key=sizes.count) if sizes else 12
    # Dominant centerline = median center-y of spans whose size is within 10% of dominant.
    main_centers = [
        (s.bbox[1] + s.bbox[3]) / 2 for s in spans if s.size >= dominant * 0.9
    ]
    if main_centers:
        main_y = sum(main_centers) / len(main_centers)
    else:
        main_y = sum((s.bbox[1] + s.bbox[3]) / 2 for s in spans) / len(spans)
    parts: list[str] = []
    for s in spans:
        cy = (s.bbox[1] + s.bbox[3]) / 2
        smaller = s.size < dominant * 0.8
        if smaller and cy < main_y - 1.0:
            parts.append(f"<sup>{s.text}</sup>")
        elif smaller and cy > main_y + 1.0:
            parts.append(f"<sub>{s.text}</sub>")
        else:
            parts.append(s.text)
    return "".join(parts)


def _build_line(spans: list[Span], page: int) -> Line:
    spans_sorted = sorted(spans, key=lambda s: s.bbox[0])
    text = _join_spans_math_aware(spans_sorted).strip()
    if not text:
        text = " ".join(s.text for s in spans_sorted).strip()
    x0 = min(s.bbox[0] for s in spans_sorted)
    y0 = min(s.bbox[1] for s in spans_sorted)
    x1 = max(s.bbox[2] for s in spans_sorted)
    y1 = max(s.bbox[3] for s in spans_sorted)
    return Line(
        page=page,
        y=(y0 + y1) / 2,
        text=text,
        bbox=(x0, y0, x1, y1),
        spans=spans_sorted,
    )


def mark_highlighted(lines: list[Line], highlights: list[Highlight]) -> None:
    """Mark each line as `highlighted=True` if its bbox overlaps any highlight rect."""
    by_page: dict[int, list[Highlight]] = {}
    for h in highlights:
        by_page.setdefault(h.page, []).append(h)
    for line in lines:
        for h in by_page.get(line.page, []):
            if _overlap(line.bbox, h.bbox):
                line.highlighted = True
                break


def _overlap(a: tuple[float, float, float, float], b: tuple[float, float, float, float]) -> bool:
    ax0, ay0, ax1, ay1 = a
    bx0, by0, bx1, by1 = b
    # Vertical overlap heuristic: line's vertical center inside rect's vertical span.
    line_cy = (ay0 + ay1) / 2
    if not (by0 - 1 <= line_cy <= by1 + 1):
        return False
    # Horizontal overlap: any intersection
    return ax1 > bx0 and bx1 > ax0


# ─── Metadata inference ──────────────────────────────────────────────────────

SUBJECT_KEYWORDS = [
    (r"TIẾNG ANH CHUYÊN|ANH CHUYÊN", "english_specialized"),
    (r"TIẾNG ANH|MÔN ANH|IELTS", "english"),
    (r"TOÁN CHUYÊN|CHUYÊN TOÁN", "math_specialized"),
    (r"MÔN TOÁN|MÔN: TOÁN|TOÁN HỌC", "math"),
    (r"NGỮ VĂN|MÔN VĂN|MÔN: VĂN", "literature"),
    (r"VẬT LÝ|VẬT LÍ|MÔN LÝ", "physics"),
    (r"HÓA HỌC|HOÁ HỌC|MÔN HÓA|MÔN HOÁ", "chemistry"),
    (r"SINH HỌC|MÔN SINH", "biology"),
    (r"LỊCH SỬ|MÔN SỬ", "history"),
    (r"ĐỊA LÝ|ĐỊA LÍ|MÔN ĐỊA", "geography"),
    (r"GIÁO DỤC CÔNG DÂN|GDCD", "civic_education"),
    (r"TIN HỌC|MÔN TIN", "informatics"),
    (r"KHOA HỌC TỰ NHIÊN|KHTN", "khtn"),
    (r"KINH TẾ.*PHÁP LUẬT", "economics_law"),
    (r"CÔNG NGHỆ|MÔN CÔNG NGHỆ", "technology"),
]


def infer_metadata(header_text: str, pdf_name: str) -> dict:
    """Infer subject/grade/exam_category/duration/year/title from the PDF header text."""
    header_upper = header_text.upper()
    subject: str | None = None
    for pattern, slug in SUBJECT_KEYWORDS:
        if re.search(pattern, header_upper):
            subject = slug
            break

    grade_level: int | None = None
    # Priority: "LỚP N" / "KHỐI N" / "LỚP CHUYÊN N" — these are explicit grade tags.
    m = re.search(r"\b(?:LỚP|KHỐI)(?:\s+CHUYÊN)?\s+(\d{1,2})\b", header_upper)
    if m:
        grade_level = int(m.group(1))
    elif re.search(r"TỐT NGHIỆP THPT|TN THPT", header_upper):
        grade_level = 12
    elif re.search(r"VÀO 10|VÀO THPT", header_upper):
        grade_level = 9
    elif re.search(r"VÀO 6", header_upper):
        grade_level = 5
    else:
        # "MÔN: HÓA HỌC 10" / "VẬT LÝ 10" / "TIẾNG ANH 12" — subject keyword followed
        # by 1–2 digit grade. Use the FIRST match to avoid grabbing year numbers.
        subj_grade = re.search(
            r"(?:HÓA HỌC|HOÁ HỌC|VẬT LÝ|VẬT LÍ|SINH HỌC|TIN HỌC|NGỮ VĂN|TIẾNG ANH|MÔN ANH|TOÁN|LỊCH SỬ|ĐỊA LÝ|ĐỊA LÍ|GDCD|KHTN|CÔNG NGHỆ)\s+(\d{1,2})\b",
            header_upper,
        )
        if subj_grade:
            grade_level = int(subj_grade.group(1))
    if grade_level is None and "THCS" in header_upper:
        grade_level = 9
    if grade_level is None and "THPT" in header_upper:
        grade_level = 12

    exam_category: str | None = None
    if re.search(r"TỐT NGHIỆP THPT|TN THPT", header_upper):
        exam_category = "thpt_graduation"
    elif re.search(r"VÀO\s*(LỚP\s*)?10|VÀO THPT", header_upper):
        exam_category = "thpt_entrance"
    elif re.search(r"CHUYÊN\s*(LỚP\s*)?6|VÀO 6 CHUYÊN", header_upper):
        exam_category = "specialized_6"
    elif re.search(r"CHUYÊN\s*(LỚP\s*)?10|VÀO 10 CHUYÊN", header_upper):
        exam_category = "specialized_10"
    elif re.search(r"ĐGNL|ĐÁNH GIÁ NĂNG LỰC", header_upper):
        exam_category = "dgnl"
    elif re.search(r"IELTS", header_upper):
        exam_category = "ielts"
    elif re.search(r"GIỮA KỲ|CUỐI KỲ|KIỂM TRA|KHẢO SÁT|HỌC KỲ", header_upper):
        exam_category = "grade_exam"

    duration: int | None = None
    m = re.search(r"THỜI GIAN[^0-9]*?(\d+)\s*PHÚT", header_upper)
    if m:
        duration = int(m.group(1))

    school_year: str | None = None
    m = re.search(r"(\d{4})\s*[-–—]\s*(\d{4})", header_text)
    if m:
        school_year = f"{m.group(1)}-{m.group(2)}"

    # Title composition. Strip year and "NĂM HỌC" tokens before reuse so we don't
    # repeat the year when composing the title with school_year separately.
    def _strip_year_tokens(s: str) -> str:
        s = re.sub(r"NĂM HỌC\s+\d{4}\s*[-–—]\s*\d{4}", "", s, flags=re.IGNORECASE)
        s = re.sub(r"\d{4}\s*[-–—]\s*\d{4}", "", s)
        return re.sub(r"\s+", " ", s).strip(" .,-—–")

    source: str | None = None
    # Stop the source string at MÔN:, ĐỀ THI, NĂM HỌC, or end of line so the
    # title doesn't bleed into adjacent header tokens.
    m = re.search(
        r"(SỞ GIÁO DỤC[^\n]+?|TRƯỜNG[^\n]+?|THPT[^\n]+?|TRUNG TÂM[^\n]+?)"
        r"(?=\s+(?:MÔN:|ĐỀ THI|NĂM HỌC|Mã đề)|$|\n)",
        header_text,
    )
    if m:
        source = _strip_year_tokens(m.group(1).strip())
    code: str | None = None
    m = re.search(r"MÃ ĐỀ[^0-9]*(\d+)", header_upper)
    if m:
        code = m.group(1)

    exam_type: str | None = None
    if exam_category == "thpt_graduation":
        exam_type = "Thi thử Tốt nghiệp THPT"
    elif exam_category == "thpt_entrance":
        exam_type = "Tuyển sinh vào 10"
    elif exam_category == "grade_exam":
        m = re.search(r"(KỲ THI[^\n]+|KIỂM TRA[^\n]+|KHẢO SÁT[^\n]+|HỌC KỲ[^\n]+)", header_text)
        if m:
            exam_type = _strip_year_tokens(m.group(1).strip().rstrip("."))
        else:
            exam_type = "Kiểm tra"

    parts = [p for p in [exam_type, source, school_year] if p]
    title = " — ".join(parts)
    if code:
        title = f"{title} — Mã {code}"
    if not title:
        title = Path(pdf_name).stem.replace("-", " ").title()

    semester: int | None = None
    if re.search(r"HỌC KỲ\s*(I|1)\b", header_upper):
        semester = 1
    elif re.search(r"HỌC KỲ\s*(II|2)\b", header_upper):
        semester = 2

    return {
        "title": title,
        "subject": subject,
        "grade_level": grade_level,
        "exam_category": exam_category,
        "duration_minutes": duration,
        "description": "",
        "instructions": "",
        "school_year": school_year,
        "semester": semester,
        "is_published": False,
        "specialized_subject": ("math" if subject == "math_specialized" else "english" if subject == "english_specialized" else None),
        "source_pdf": Path(pdf_name).name,
        "extracted_by": "practice-pdf-to-md",
        "extracted_at": datetime.now(SAIGON_TZ).isoformat(),
    }


# ─── Structure parsing ───────────────────────────────────────────────────────

def _is_tfg_section(title: str) -> bool:
    t = title.upper()
    if "ĐÚNG SAI" in t:
        return True
    # Match "Phần II" / "PART II" with word boundary so "Phần III" / "PART III" does not.
    return bool(re.search(r"\b(PHẦN|PART)\s+II\b", t))


def _is_hdc_document(header_text: str) -> bool:
    """A 'Hướng dẫn chấm' (HDC) document is a grading rubric, not a question
    paper. It uses Câu+rubric tables instead of A/B/C/D options."""
    return bool(re.search(r"H(?:Ư|Ƣ)ỚNG\s+DẪN\s+CHẤM|\bHDC\b", header_text, re.IGNORECASE))


def _is_fill_section(title: str) -> bool:
    return "TRẢ LỜI NGẮN" in title.upper() or "PHẦN III" in title.upper() or "PART III" in title.upper()


def _clean_section_title(text: str) -> str:
    """Trim trailing instructions like 'Thí sinh trả lời từ câu 1 đến câu N' from
    section headers so we keep the canonical name only."""
    t = re.sub(r"\bThí sinh trả lời.+$", "", text, flags=re.IGNORECASE).strip()
    t = re.sub(r"\s*\.\s*$", ".", t)
    return t.rstrip(" .") + "."


def parse_structure(lines: list[Line]) -> tuple[list[dict], list[Question], list[dict], list[dict]]:
    """Returns (sections, questions, passages, groups).

    Reading-comprehension blocks (long paragraphs before a cluster of MCQs that
    reference them) are emitted as `passages[]` entries. The cluster's
    questions get `passage_index` + `group_index` set so downstream consumers
    can render them under their parent passage.
    """
    sections: list[dict] = []
    questions: list[Question] = []
    passages: list[dict] = []
    groups: list[dict] = []
    current_section_index = -1
    current_section_title = ""
    current_q: Question | None = None
    in_option_block = False
    last_qnum_in_section = 0  # tracks the highest question number seen in current section
    # Passage detection state: collect contiguous lines BEFORE the next question
    # that aren't section headers / instructions. When the block is long enough
    # AND followed by ≥ 2 MCQs in quick succession, we materialise a passage.
    pending_passage_lines: list[Line] = []
    pending_passage_min_y_per_page: dict[int, float] = {}
    current_passage_index: int | None = None
    current_group_index: int | None = None
    questions_since_passage = 0

    def flush():
        nonlocal current_q
        if current_q:
            questions.append(current_q)
            current_q = None

    def maybe_materialize_passage():
        """If `pending_passage_lines` form a substantial block, emit it as a new
        passage + a fresh group, and set current_passage_index/current_group_index
        so the next questions attach to it."""
        nonlocal current_passage_index, current_group_index
        body = " ".join(l.text for l in pending_passage_lines).strip()
        if len(body) < 200:
            return
        # Skip purely instructional lines.
        if re.search(r"(Mark the letter|Read the following|Đọc đoạn văn|Thí sinh đọc|Choose the correct)", body, re.IGNORECASE) and len(body) < 400:
            return
        p_idx = len(passages)
        passages.append({"index": p_idx, "title": None, "content": body, "audio_url": None})
        g_idx = len(groups)
        groups.append({
            "index": g_idx,
            "section_index": current_section_index if current_section_index >= 0 else 0,
            "passage_index": p_idx,
            "title": None,
            "instruction": None,
        })
        current_passage_index = p_idx
        current_group_index = g_idx

    for line in lines:
        text = line.text.strip()
        if not text:
            continue
        # 1. Section header (Phần I / II / III)
        m = RE_SECTION_HEADER.match(text)
        if m and ("PHẦN" in text.upper() or "PART" in text.upper()):
            flush()
            cleaned = _clean_section_title(text)
            current_section_title = cleaned
            current_section_index = len(sections)
            sections.append({"index": current_section_index, "title": cleaned, "instruction": None})
            in_option_block = False
            last_qnum_in_section = 0
            # Section break ends the current passage / group scope.
            current_passage_index = None
            current_group_index = None
            pending_passage_lines.clear()
            continue
        # 2. Question start (Câu N: …)
        m = RE_QUESTION_START.match(text)
        if m:
            qnum = int(m.group(2))
            tail = m.group(3).strip()
            # Detect "exam restart" — when the new qnum drops below the highest we've
            # seen (e.g. PDF contains 2 mã đề and Q41 is followed by another Q1),
            # open a new section so the second exam keeps a distinct identity.
            if qnum < last_qnum_in_section and last_qnum_in_section >= 5:
                flush()
                current_section_index = len(sections)
                current_section_title = f"(Mã đề bổ sung) — Question {qnum} trở đi"
                sections.append({
                    "index": current_section_index,
                    "title": current_section_title,
                    "instruction": "Phần này đến từ mã đề khác trong cùng PDF.",
                })
                last_qnum_in_section = 0
                current_passage_index = None
                current_group_index = None
            flush()
            # Before opening the new question, materialize any pending passage.
            maybe_materialize_passage()
            pending_passage_lines.clear()
            if current_section_index < 0:
                current_section_index = 0
                current_section_title = "Phần I."
                sections.append({"index": 0, "title": "Phần I.", "instruction": None})
            current_q = Question(
                number=qnum,
                section_title=current_section_title,
                section_index=current_section_index,
                passage_index=current_passage_index,
                group_index=current_group_index,
            )
            # Per-question barem: scan the tail for "(X điểm)" / "(X đ)" / "(X pt)".
            # The match is removed from `tail` so the question text doesn't repeat
            # the barem annotation.
            pm = RE_PER_QUESTION_POINTS.search(tail)
            if pm:
                try:
                    current_q.explicit_points = float(pm.group(1).replace(",", "."))
                    tail = (tail[: pm.start()] + tail[pm.end() :]).strip()
                except ValueError:
                    pass
            last_qnum_in_section = max(last_qnum_in_section, qnum)
            if tail:
                current_q.text_lines.append(Line(
                    page=line.page, y=line.y, text=tail, bbox=line.bbox, spans=line.spans, highlighted=line.highlighted,
                ))
            in_option_block = False
            continue
        if current_q is None:
            # Plain text before the first / next question — accumulate as a
            # potential reading passage. Will be promoted to `passages[]` on
            # the next `Câu N` if the block crosses the size threshold.
            pending_passage_lines.append(line)
            continue
        # 3. Option line(s) — A./B./C./D. (multi-column tolerated)
        opt_matches = list(_split_options(line))
        if opt_matches:
            in_option_block = True
            for letter, opt_text, opt_highlighted in opt_matches:
                current_q.options.append((letter, opt_text, opt_highlighted))
            continue
        # 4. TFG sub-statement (only when we're under Phần II / "đúng sai")
        if _is_tfg_section(current_section_title):
            sm = RE_SUB_STATEMENT.match(text)
            if sm:
                letter = (sm.group(1) or sm.group(2) or "").lower()
                sub_text = sm.group(3).strip()
                current_q.sub_statements.append((letter, sub_text, line.highlighted))
                continue
        # 5. Continuation — body text or wrapped option/sub-statement text
        if in_option_block and current_q.options:
            last = current_q.options[-1]
            current_q.options[-1] = (last[0], (last[1] + " " + text).strip(), last[2] or line.highlighted)
        elif _is_tfg_section(current_section_title) and current_q.sub_statements:
            # TFG sub-statements often wrap across visual lines. The continuation
            # carries no `a)/b)/...` prefix so we append it to the previous sub.
            letter, prev_text, prev_hot = current_q.sub_statements[-1]
            current_q.sub_statements[-1] = (letter, (prev_text + " " + text).strip(), prev_hot or line.highlighted)
        else:
            current_q.text_lines.append(line)

    flush()
    _classify_questions(questions)
    return sections, questions, passages, groups


def _split_options(line: Line) -> Iterable[tuple[str, str, bool]]:
    """Return [(letter, text, highlighted), ...] for the options on this line.

    Many PDFs lay out A/B/C/D in 4 columns on a single visual line. We detect
    each option-start letter span (bold, single char + '.') and use the next
    letter's x-coordinate to slice the text. The `highlighted` flag for each
    option is computed by checking whether its own slice overlaps a highlight.
    """
    # Quick reject: line text must contain at least one option-letter pattern.
    if not re.search(r"\b[A-D]\s*\.", line.text):
        return []
    # Find each "X." letter span as an anchor.
    anchors: list[tuple[str, float]] = []
    for span in line.spans:
        t = span.text.strip()
        if re.fullmatch(r"[A-D]\.?", t) and span.bold:
            anchors.append((t.rstrip("."), span.bbox[0]))
    if len(anchors) < 1:
        return []
    anchors.sort(key=lambda a: a[1])
    out: list[tuple[str, str, bool]] = []
    line_x_end = line.bbox[2]
    for i, (letter, ax) in enumerate(anchors):
        x_start = ax
        x_end = anchors[i + 1][1] if i + 1 < len(anchors) else line_x_end + 1
        # Slice text by collecting spans whose bbox center falls in [x_start, x_end).
        slice_spans = [s for s in line.spans if x_start <= (s.bbox[0] + s.bbox[2]) / 2 < x_end]
        slice_text = "".join(s.text for s in slice_spans).strip()
        # Strip the leading "X." prefix to get the option body.
        slice_text = re.sub(rf"^\s*{re.escape(letter)}\.\s*", "", slice_text)
        # Highlighted if any sub-span sits inside a highlight rect or if the slice y-center is inside one.
        slice_bbox = (x_start, line.bbox[1], x_end - 1, line.bbox[3])
        out.append((letter, slice_text, _slice_highlighted(slice_bbox, line)))
    return out


def _slice_highlighted(slice_bbox: tuple[float, float, float, float], line: Line) -> bool:
    """A slice is highlighted if the line itself is highlighted AND the slice
    overlaps the line's highlighted region. Without per-rect data here we fall
    back to: any line.highlighted=True propagates to all of its slices; the
    re-check against rect bboxes is done by the caller in practice for accuracy."""
    return line.highlighted


def _classify_questions(questions: list[Question]) -> None:
    for q in questions:
        if q.sub_statements:
            q.qtype = "true_false_group"
        elif q.options:
            q.qtype = "single_choice"
        else:
            q.qtype = "fill_blank"
            # For fill_blank: probe the question body for "Đáp án: <num>" inline.
            body = " ".join(l.text for l in q.text_lines)
            m = RE_INLINE_ANSWER.search(body)
            if m:
                q.free_answer = m.group(1).strip().rstrip(".")


# ─── HDC (Hướng dẫn chấm) parser ─────────────────────────────────────────────
# HDC PDFs have a different shape: they're rubric documents where each "Câu"
# carries a paragraph of grading criteria and a per-criterion point value
# instead of A/B/C/D options. We emit each Câu as an `essay` question with the
# rubric text packed into `rubric_criteria`.

RE_HDC_POINTS_INLINE = re.compile(r"([0-9]+[,.]?[0-9]*)\s*(?:điểm|đ)\b", re.IGNORECASE)


def parse_hdc_structure(doc: fitz.Document) -> tuple[list[dict], list[Question]]:
    """Parse an HDC rubric document using PyMuPDF table extraction.

    HDC layout is tabular: each row has (Label, Yêu cầu cần đạt, Điểm). The
    label column carries `Câu N` (for Phần I), or `Mở bài / Thân bài / Kết
    bài` for Phần II's big essay rubric breakdown. We emit each labeled row
    as a separate `essay` question; empty-label rows append rubric text to
    the previously emitted question (table continuation across pages).
    """
    sections: list[dict] = []
    questions: list[Question] = []
    current_section_index = -1
    current_section_title = ""
    qnum_counter = 0

    def ensure_section(title: str | None = None) -> int:
        nonlocal current_section_index, current_section_title
        if title and (current_section_index < 0 or current_section_title != title):
            current_section_index = len(sections)
            current_section_title = title
            sections.append({"index": current_section_index, "title": title, "instruction": None})
        elif current_section_index < 0:
            current_section_index = 0
            current_section_title = "Phần I."
            sections.append({"index": 0, "title": "Phần I.", "instruction": None})
        return current_section_index

    def parse_points(cell: str) -> float:
        if not cell:
            return 0.0
        m = re.search(r"([0-9]+(?:[.,][0-9]+)?)\s*đ", cell)
        if m:
            try:
                return float(m.group(1).replace(",", "."))
            except ValueError:
                pass
        m = re.search(r"([0-9]+(?:[.,][0-9]+)?)", cell)
        return float(m.group(1).replace(",", ".")) if m else 0.0

    for pno, page in enumerate(doc):
        # Look for section headers as plain text BEFORE the table.
        text = page.get_text("text")
        for m in re.finditer(
            r"((?:PHẦN|Phần)\s+[IVX]+[^\n]+|I\.\s*ĐỌC[^\n]+|II\.\s*VIẾT[^\n]+)",
            text,
        ):
            title = _clean_section_title(m.group(1))
            if title.upper() not in {s["title"].upper() for s in sections}:
                current_section_index = len(sections)
                current_section_title = title
                sections.append({"index": current_section_index, "title": title, "instruction": None})

        tables = page.find_tables()
        for tab in tables.tables:
            rows = tab.extract()
            if not rows:
                continue
            for row in rows:
                if len(row) < 3:
                    continue
                label_raw, body_raw, points_raw = row[0] or "", row[1] or "", row[2] or ""
                label = normalize_symbol_chars(re.sub(r"\s+", " ", str(label_raw)).strip())
                body = normalize_symbol_chars(re.sub(r"\s+", " ", str(body_raw)).strip())
                points_text = normalize_symbol_chars(str(points_raw))
                # Skip header rows ("Câu | Yêu cầu cần đạt | Điểm" or "Phần | Nội dung cần đạt | Điểm")
                if re.fullmatch(r"Câu|Phần", label) and re.search(r"Yêu cầu|Nội dung", body):
                    continue
                # Continuation row (empty label) → append to last question's rubric.
                if not label and questions:
                    last = questions[-1]
                    if body:
                        last.free_answer = (last.free_answer or "") + " " + body
                    extra_pts = parse_points(points_text)
                    if extra_pts:
                        last._hdc_points = getattr(last, "_hdc_points", 0.0) + extra_pts  # type: ignore[attr-defined]
                    continue
                # Labeled row → new essay question.
                ensure_section()
                qnum_counter += 1
                q = Question(
                    number=qnum_counter,
                    section_title=current_section_title,
                    section_index=current_section_index,
                    qtype="essay",
                )
                # Prepend label (Câu N / Mở bài / etc.) into the rubric text so
                # the structural role is preserved.
                rubric = f"[{label}] {body}".strip() if body else f"[{label}]"
                q.free_answer = rubric
                q._hdc_points = parse_points(points_text)  # type: ignore[attr-defined]
                questions.append(q)

    if not sections:
        sections.append({"index": 0, "title": "Phần I.", "instruction": None})
    return sections, questions


def reattribute_option_highlights(questions: list[Question], highlights: list[Highlight], lines: list[Line]) -> None:
    """Second-pass refinement: for multi-option lines, re-check each option's
    sub-bbox against the highlight rects so we get per-letter accuracy."""
    by_page: dict[int, list[Highlight]] = {}
    for h in highlights:
        by_page.setdefault(h.page, []).append(h)

    # Build a map from question number → its option line(s).
    for q in questions:
        if q.qtype != "single_choice":
            continue
        # Re-walk: find the line(s) in `lines` that contain the option anchors for this question.
        # We trust the parse_structure pass produced correctly-numbered options; here we
        # re-check each option's letter span bbox vs rects on the same page.
        for li, line in enumerate(lines):
            if line.text.strip().startswith(f"Câu {q.number}") or line.text.strip().startswith(f"Câu {q.number}:"):
                start_idx = li
                break
        else:
            continue
        # Scan forward for option lines until next "Câu" or section header.
        for line in lines[start_idx + 1 :]:
            t = line.text.strip()
            if RE_QUESTION_START.match(t) or RE_SECTION_HEADER.match(t):
                break
            if not re.search(r"\b[A-D]\s*\.", t):
                continue
            # For each letter anchor on this line, compute its slice bbox.
            anchors = []
            for span in line.spans:
                txt = span.text.strip()
                if re.fullmatch(r"[A-D]\.?", txt) and span.bold:
                    anchors.append((txt.rstrip("."), span.bbox))
            anchors.sort(key=lambda a: a[1][0])
            if not anchors:
                continue
            for i, (letter, abox) in enumerate(anchors):
                x0 = abox[0]
                x1 = anchors[i + 1][1][0] if i + 1 < len(anchors) else line.bbox[2]
                y0, y1 = line.bbox[1], line.bbox[3]
                slice_box = (x0, y0, x1, y1)
                hot = False
                for h in by_page.get(line.page, []):
                    if _overlap(slice_box, h.bbox):
                        hot = True
                        break
                # Update q.options for the matching letter.
                for oi, (oletter, otext, _) in enumerate(q.options):
                    if oletter == letter:
                        q.options[oi] = (oletter, otext, hot)
                        break


# ─── MD emission ─────────────────────────────────────────────────────────────

YAML_KEYS = [
    "title", "subject", "grade_level", "exam_category", "duration_minutes",
    "description", "instructions", "school_year", "semester", "is_published",
    "specialized_subject", "source_pdf", "extracted_by", "extracted_at",
]


def yaml_frontmatter(meta: dict) -> str:
    out = ["---"]
    for k in YAML_KEYS:
        v = meta.get(k)
        if v is None:
            out.append(f"{k}: null")
        elif isinstance(v, bool):
            out.append(f"{k}: {'true' if v else 'false'}")
        elif isinstance(v, (int, float)):
            out.append(f"{k}: {v}")
        else:
            s = str(v).replace("\"", "\\\"")
            out.append(f"{k}: \"{s}\"")
    out.append("---")
    return "\n".join(out)


def emit_md(meta: dict, sections: list[dict], questions: list[Question]) -> str:
    buf = io.StringIO()
    buf.write(yaml_frontmatter(meta))
    buf.write("\n\n")
    buf.write(f"# {meta.get('title') or meta.get('source_pdf')}\n\n")

    questions_by_section: dict[int, list[Question]] = {}
    for q in questions:
        questions_by_section.setdefault(q.section_index, []).append(q)

    for s in sections:
        s_idx = s["index"]
        buf.write(f"## {s['title']}\n\n")
        for q in questions_by_section.get(s_idx, []):
            buf.write(f"**Câu {q.number}.** ")
            body = " ".join(l.text for l in q.text_lines).strip()
            buf.write(body + "\n\n")
            if q.qtype == "single_choice":
                for letter, otext, hot in q.options:
                    marker = " ← Đáp án" if hot else ""
                    buf.write(f"- {letter}. {otext}{marker}\n")
                correct = next((l for (l, _t, hot) in q.options if hot), None)
                if correct:
                    buf.write(f"\n**Đáp án: {correct}**\n\n")
                else:
                    buf.write("\n**Đáp án: (không phát hiện được)**\n\n")
            elif q.qtype == "true_false_group":
                for letter, stext, hot in q.sub_statements:
                    flag = "Đ" if hot else "S"
                    buf.write(f"- {letter}) {stext}  → **{flag}**\n")
                pairs = "; ".join(
                    f"{letter}) {'Đ' if hot else 'S'}"
                    for letter, _, hot in q.sub_statements
                )
                buf.write(f"\n**Đáp án:** {pairs}\n\n")
            elif q.qtype == "essay":
                pts = getattr(q, "_hdc_points", None)
                if pts:
                    buf.write(f"_Barem:_ **{pts}đ**\n\n")
                buf.write("_Rubric (sample answer):_\n")
                buf.write(q.free_answer or "(không phát hiện được)")
                buf.write("\n\n")
            else:  # fill_blank
                ans = q.free_answer or "(không phát hiện được)"
                buf.write(f"\n**Đáp án:** {ans}\n\n")

    return buf.getvalue()


# ─── CLI ─────────────────────────────────────────────────────────────────────

def emit_json(meta: dict, sections: list[dict], questions: list[Question],
              passages: list[dict] | None = None, groups: list[dict] | None = None) -> dict:
    """Build the {metadata, data} envelope the Laravel artisan importer expects.

    Default barem (when source carries no explicit per-question signal):
      - single_choice → 0.25 đ
      - true_false_group → 4 sub × 0.25 = 1.0 đ
      - fill_blank → 0.25 đ
      - essay → 1.0 đ (HDC overrides this with the extracted per-question barem)
    """
    points_default = {
        "single_choice": 0.25,
        "true_false_group": 1.0,
        "fill_blank": 0.25,
        "essay": 1.0,
        "multiple_choice": 0.25,
    }
    js_questions: list[dict] = []
    for q in questions:
        body = " ".join(l.text for l in q.text_lines).strip()
        # HDC questions have no text_lines — the rubric body sitting on free_answer
        # IS the question text.
        if not body and q.qtype == "essay" and q.free_answer:
            body = q.free_answer
        # Points priority: explicit inline (Priority 1) > HDC table (Priority 4) > template default.
        points = (
            q.explicit_points
            if q.explicit_points is not None
            else (getattr(q, "_hdc_points", None) or points_default.get(q.qtype, 0.25))
        )
        entry: dict = {
            "section_index": q.section_index,
            "group_index": q.group_index,
            "passage_index": q.passage_index,
            "type": q.qtype,
            "text": body,
            "points": points,
        }
        if q.qtype == "single_choice" or q.qtype == "multiple_choice":
            entry["options"] = [{"value": letter, "text": text} for letter, text, _ in q.options]
            correct = next((l for (l, _t, hot) in q.options if hot), None)
            entry["correct_answer"] = correct or ""
        elif q.qtype == "true_false_group":
            entry["sub_questions"] = [
                {"label": letter, "text": text, "correct_answer": "Đ" if hot else "S"}
                for letter, text, hot in q.sub_statements
            ]
        elif q.qtype == "fill_blank":
            entry["correct_answer"] = q.free_answer or ""
        elif q.qtype == "essay":
            # HDC source: the body itself IS the grading rubric. The Laravel
            # `RubricCriteriaExtractor::enrichForLiterature` job auto-parses
            # `sample_answer` into structured `rubric_criteria` rows at import
            # time when subject=literature.
            entry["sample_answer"] = q.free_answer or body
            entry["answer_input_mode"] = "text"
        js_questions.append(entry)
    return {
        "metadata": {
            **meta,
            "source_profile": "_inline",
            "generator": "practice-pdf-to-md@v1",
            "generated_at": datetime.now(SAIGON_TZ).isoformat(),
        },
        "data": {
            "passages": passages or [],
            "sections": sections,
            "groups": groups or [],
            "questions": js_questions,
        },
    }


# Subjects routed to the Mathpix high-fidelity lane (formula/diagram-heavy).
# The agent reads `detect_metadata()` BEFORE converting to pick the lane.
MATHPIX_SUBJECTS = {
    "math", "math_specialized",
    "physics", "chemistry", "biology", "geography",
    "khtn",  # Khoa học tự nhiên bundles lý/hoá/sinh — also math-heavy.
}


def detect_metadata(pdf_path: Path) -> dict:
    """Cheap page-1 inference used to ROUTE a PDF to the Mathpix vs PyMuPDF lane.

    Opens the PDF, reads the cover header, and infers subject/grade/category
    WITHOUT parsing the full question structure. Adds `use_mathpix` = True when
    the inferred subject is in MATHPIX_SUBJECTS, so the agent can branch on it.
    """
    doc = fitz.open(pdf_path)
    spans = extract_spans(doc)
    lines = group_into_lines(spans)
    header_text = "\n".join(l.text for l in lines if l.page == 0)[:1500]
    meta = infer_metadata(header_text, pdf_path.name)
    meta["document_kind"] = "hdc_rubric" if _is_hdc_document(header_text) else "exam_paper"
    meta["use_mathpix"] = meta.get("subject") in MATHPIX_SUBJECTS
    return meta


def build_answer_map(sections: list[dict], questions: list[Question], marker_color: str) -> dict:
    """Section-aware answer key extracted from the yellow-highlight pass.

    This is the sidecar consumed by the Mathpix hybrid lane: Mathpix owns the
    clean text/LaTeX MD (which carries NO color info), so the correct answers
    come from here. The MD→JSON step merges by (section ordinal, Câu number).
    """
    title_by_idx = {s["index"]: s["title"] for s in sections}
    items: list[dict] = []
    for q in questions:
        entry: dict = {
            "section_index": q.section_index,
            "section_title": title_by_idx.get(q.section_index, q.section_title),
            "number": q.number,
            "type": q.qtype,
        }
        if q.qtype in ("single_choice", "multiple_choice"):
            entry["answer"] = next((l for (l, _t, hot) in q.options if hot), None)
        elif q.qtype == "true_false_group":
            entry["answer"] = {
                letter: ("Đ" if hot else "S") for letter, _t, hot in q.sub_statements
            }
        elif q.qtype == "fill_blank":
            entry["answer"] = q.free_answer or None
        else:  # essay / unknown
            entry["answer"] = q.free_answer or None
        items.append(entry)
    detected = sum(1 for it in items if it["answer"] not in (None, "", {}))
    return {
        "marker_color": marker_color or None,
        "questions": len(questions),
        "detected_answers": detected,
        "answers": items,
    }


def convert(pdf_path: Path, out_path: Path, json_path: Path | None = None,
            answer_map_path: Path | None = None, write_md: bool = True) -> dict:
    doc = fitz.open(pdf_path)
    highlights, marker_color = extract_highlights(doc)
    spans = extract_spans(doc)
    lines = group_into_lines(spans)
    mark_highlighted(lines, highlights)

    # Build a header chunk from the first 25 lines on page 0 for inference.
    header_text = "\n".join(l.text for l in lines if l.page == 0)[:1500]
    meta = infer_metadata(header_text, pdf_path.name)

    is_hdc = _is_hdc_document(header_text)
    meta["document_kind"] = "hdc_rubric" if is_hdc else "exam_paper"
    passages: list[dict] = []
    groups: list[dict] = []
    if is_hdc:
        sections, questions = parse_hdc_structure(doc)
    else:
        sections, questions, passages, groups = parse_structure(lines)
        reattribute_option_highlights(questions, highlights, lines)

    # For fill_blank: when classifier didn't find an inline "Đáp án: N", fall back
    # to whatever text segments are highlighted inside the question body.
    for q in questions:
        if q.qtype == "fill_blank" and not q.free_answer:
            hot_lines = [l for l in q.text_lines if l.highlighted]
            if hot_lines:
                q.free_answer = " ".join(l.text for l in hot_lines).strip()

    md = emit_md(meta, sections, questions)
    if write_md:
        out_path.parent.mkdir(parents=True, exist_ok=True)
        out_path.write_text(md, encoding="utf-8")
    if json_path is not None:
        envelope = emit_json(meta, sections, questions, passages, groups)
        json_path.parent.mkdir(parents=True, exist_ok=True)
        json_path.write_text(json.dumps(envelope, ensure_ascii=False, indent=2), encoding="utf-8")
    if answer_map_path is not None:
        amap = build_answer_map(sections, questions, marker_color)
        answer_map_path.parent.mkdir(parents=True, exist_ok=True)
        answer_map_path.write_text(json.dumps(amap, ensure_ascii=False, indent=2), encoding="utf-8")

    def _has_answer(q: Question) -> bool:
        return (
            (q.qtype == "single_choice" and any(h for _, _, h in q.options))
            or (q.qtype == "true_false_group" and bool(q.sub_statements))
            or (q.qtype == "fill_blank" and bool(q.free_answer))
            or (q.qtype == "essay" and bool(q.free_answer))
        )
    detected = sum(1 for q in questions if _has_answer(q))
    warnings: list[str] = []
    if is_hdc:
        warnings.append(
            "HDC_RUBRIC_MODE: this document is a Hướng dẫn chấm (grading rubric). "
            "Each Câu was imported as `essay` with the rubric body packed into "
            "`sample_answer`. Subject=literature triggers automatic rubric_criteria "
            "extraction inside the artisan importer."
        )
    elif not highlights:
        warnings.append(
            "NO_ANSWER_MARKERS: 0 highlighted rectangles found across the PDF — "
            "this looks like an exam paper without an answer key. The MD lists "
            "every question with placeholder `(không phát hiện được)` slots. "
            "Import will succeed as a draft Practice that an admin can answer later."
        )
    elif detected < len(questions):
        warnings.append(
            f"INCOMPLETE: {len(questions) - detected} question(s) without a "
            f"detected answer. Inspect the MD before importing."
        )

    # Per-section coverage report. When a section has questions but 0 detected
    # answers, surface it explicitly so users notice that one half of a
    # multi-mã-đề PDF is missing markers.
    if not is_hdc:
        by_section: dict[int, list[Question]] = {}
        for q in questions:
            by_section.setdefault(q.section_index, []).append(q)
        for s in sections:
            qs = by_section.get(s["index"], [])
            if not qs:
                continue
            det = sum(1 for q in qs if _has_answer(q))
            if det == 0 and qs:
                warnings.append(
                    f"SECTION_UNANSWERED: section #{s['index']} '{s['title'][:60]}' "
                    f"has {len(qs)} question(s) but 0 detected answer(s)."
                )
    return {
        "pdf": str(pdf_path),
        "md": str(out_path) if write_md else None,
        "json": str(json_path) if json_path else None,
        "answer_map": str(answer_map_path) if answer_map_path else None,
        "pages": doc.page_count,
        "marker_color": marker_color or None,
        "highlights": len(highlights),
        "sections": len(sections),
        "questions": len(questions),
        "detected_answers": detected,
        "warnings": warnings,
        "metadata": meta,
    }


def main(argv: list[str]) -> int:
    if not argv:
        print(
            "usage: pdf_to_md.py <input.pdf> [<output.md>] [--json <out.json>] "
            "[--answer-map <out.answers.json>] [--no-md] [--detect-only]",
            file=sys.stderr,
        )
        return 2
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")
    pdf = Path(argv[0]).resolve()
    out: Path | None = None
    json_out: Path | None = None
    answer_map_out: Path | None = None
    write_md = True
    detect_only = False
    i = 1
    while i < len(argv):
        a = argv[i]
        if a == "--json":
            json_out = Path(argv[i + 1]).resolve()
            i += 2
            continue
        if a == "--answer-map":
            answer_map_out = Path(argv[i + 1]).resolve()
            i += 2
            continue
        if a == "--no-md":
            write_md = False
            i += 1
            continue
        if a == "--detect-only":
            detect_only = True
            i += 1
            continue
        if out is None:
            out = Path(a).resolve()
            i += 1
            continue
        i += 1
    # Routing helper for the agent: infer subject from page 1, decide the lane,
    # write nothing.
    if detect_only:
        print(json.dumps(detect_metadata(pdf), ensure_ascii=False, indent=2))
        return 0
    if out is None:
        out = pdf.parent / (pdf.stem + ".md")
    summary = convert(pdf, out, json_out, answer_map_out, write_md=write_md)
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
