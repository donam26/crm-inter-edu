"""
Adobe Symbol-font Private-Use-Area → Unicode mapping.

Microsoft Word / many Vietnamese exam PDFs render Greek letters and math
symbols using the legacy "Symbol" font, which stores its glyphs in the
Private-Use codepoints F000–F0FF instead of proper Unicode (Greek and
Mathematical Operators). When we extract text with PyMuPDF we receive these
private codepoints verbatim; downstream renderers see them as tofu/blanks.

This module exposes `normalize_symbol_chars(s)` which substitutes the known
codepoints with their Unicode equivalents so chemistry / physics equations
read correctly in the produced Markdown.
"""

# Source: Adobe Symbol encoding table, cross-checked with Unicode equivalents.
# Only well-known mappings are included; unknown codepoints are left untouched
# (a deliberate choice — silently dropping unknowns would corrupt formulas).
SYMBOL_MAP: dict[int, str] = {
    # Basic ASCII duplicates that Symbol re-encodes
    0xF020: " ",   0xF021: "!",   0xF023: "#",   0xF025: "%",   0xF026: "&",
    0xF028: "(",   0xF029: ")",   0xF02A: "∗",   0xF02B: "+",   0xF02C: ",",
    0xF02D: "−",   0xF02E: ".",   0xF02F: "/",
    0xF030: "0",   0xF031: "1",   0xF032: "2",   0xF033: "3",   0xF034: "4",
    0xF035: "5",   0xF036: "6",   0xF037: "7",   0xF038: "8",   0xF039: "9",
    0xF03A: ":",   0xF03B: ";",   0xF03C: "<",   0xF03D: "=",   0xF03E: ">",
    0xF03F: "?",

    # Greek uppercase
    0xF041: "Α",  0xF042: "Β",  0xF043: "Χ",  0xF044: "Δ",  0xF045: "Ε",
    0xF046: "Φ",  0xF047: "Γ",  0xF048: "Η",  0xF049: "Ι",  0xF04A: "ϑ",
    0xF04B: "Κ",  0xF04C: "Λ",  0xF04D: "Μ",  0xF04E: "Ν",  0xF04F: "Ο",
    0xF050: "Π",  0xF051: "Θ",  0xF052: "Ρ",  0xF053: "Σ",  0xF054: "Τ",
    0xF055: "Υ",  0xF056: "ς",  0xF057: "Ω",  0xF058: "Ξ",  0xF059: "Ψ",
    0xF05A: "Ζ",

    # Greek lowercase
    0xF061: "α",  0xF062: "β",  0xF063: "χ",  0xF064: "δ",  0xF065: "ε",
    0xF066: "φ",  0xF067: "γ",  0xF068: "η",  0xF069: "ι",  0xF06A: "ϕ",
    0xF06B: "κ",  0xF06C: "λ",  0xF06D: "μ",  0xF06E: "ν",  0xF06F: "ο",
    0xF070: "π",  0xF071: "θ",  0xF072: "ρ",  0xF073: "σ",  0xF074: "τ",
    0xF075: "υ",  0xF076: "ϖ",  0xF077: "ω",  0xF078: "ξ",  0xF079: "ψ",
    0xF07A: "ζ",

    # Symbols and operators (the ones commonly seen in Vietnamese chemistry/physics)
    0xF0A0: " ",   0xF0AE: "→",  0xF0AF: "↓",  0xF0B0: "°",  0xF0B1: "±",
    0xF0B2: "″",   0xF0B3: "≥",  0xF0B4: "×",  0xF0B5: "∝",  0xF0B6: "∂",
    0xF0B7: "·",   0xF0B8: "÷",  0xF0B9: "≠",  0xF0BA: "≡",  0xF0BB: "≈",
    0xF0BC: "…",   0xF0BD: "⎮",  0xF0BE: "⎯",  0xF0BF: "↵",
    0xF0C0: "ℵ",   0xF0C1: "ℑ",  0xF0C2: "ℜ",  0xF0C3: "℘",
    0xF0C4: "⊗",   0xF0C5: "⊕",  0xF0C6: "∅",  0xF0C7: "∩",  0xF0C8: "∪",
    0xF0C9: "⊃",   0xF0CA: "⊇",  0xF0CB: "⊄",  0xF0CC: "⊂",  0xF0CD: "⊆",
    0xF0CE: "∈",   0xF0CF: "∉",  0xF0D0: "∠",  0xF0D1: "∇",
    0xF0D2: "®",   0xF0D3: "©",  0xF0D4: "™",  0xF0D5: "∏",  0xF0D6: "√",
    0xF0D7: "⋅",   0xF0D8: "¬",  0xF0D9: "∧",  0xF0DA: "∨",  0xF0DB: "⇔",
    0xF0DC: "⇐",   0xF0DD: "⇑",  0xF0DE: "⇒",  0xF0DF: "⇓",
    0xF0E5: "∑",   0xF0F2: "∫",

    # Reversible-reaction arrows seen in chemistry equations
    0xF071A: "⇌",  # not strictly Symbol, but some generators emit this

    # MathType-style ligature blocks (best-effort)
    0xF8E8: "⌠",  0xF8E9: "⌡",
}


def normalize_symbol_chars(text: str) -> str:
    """Replace every Private-Use-Area codepoint we know with its Unicode glyph.

    Unknown PUA codepoints are kept as-is (caller can grep `\\uF0..` to find
    the gaps and extend the table).
    """
    if not text:
        return text
    out_chars = []
    for ch in text:
        cp = ord(ch)
        if 0xF000 <= cp <= 0xF8FF and cp in SYMBOL_MAP:
            out_chars.append(SYMBOL_MAP[cp])
        else:
            out_chars.append(ch)
    return "".join(out_chars)
