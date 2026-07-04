"""
mathpix_pdf — convert a PDF to clean Mathpix Markdown via the Mathpix v3 PDF API.

This is the high-fidelity lane for math/formula/diagram-heavy subjects
(toán, lý, hoá, sinh, địa). Unlike the local PyMuPDF heuristic extractor
(`pdf_to_md.py`), Mathpix reconstructs 2D math (fractions, sub/superscripts),
tables, and figures, emitting LaTeX `$…$` / `$$…$$` and `cdn.mathpix.com`
image URLs — exactly the "Mathpix-clean" shape the downstream
`practice-md-to-json` skill expects.

Flow (Mathpix v3 PDF API):
  1) POST /v3/pdf  (multipart: file + options_json)            → { pdf_id }
  2) GET  /v3/pdf/{pdf_id}    (poll until status == completed)  → progress
  3) GET  /v3/pdf/{pdf_id}.md                                  → Markdown text

Credentials (in priority order):
  1) env MATHPIX_APP_ID / MATHPIX_APP_KEY
  2) .claude/skills/practice-pdf-to-md/config/mathpix.json  (gitignored)

Stdlib only — no `requests` dependency. Multipart is hand-encoded.

Usage:
  python mathpix_pdf.py <input.pdf> [<output.md>] [--timeout 300] [--poll-interval 3]

Prints a JSON summary to stdout:
  { pdf, md, pdf_id, pages, chars, status, image_urls, warnings }
Exits non-zero on credential / network / Mathpix error.
"""

from __future__ import annotations

import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

_HERE = Path(__file__).resolve().parent
_CONFIG = _HERE.parent / "config" / "mathpix.json"

MATHPIX_BASE = "https://api.mathpix.com/v3/pdf"

# Mathpix conversion options tuned for Vietnamese exam papers.
DEFAULT_OPTIONS = {
    "conversion_formats": {"md": True},
    "math_inline_delimiters": ["$", "$"],
    "math_display_delimiters": ["$$", "$$"],
    "rm_spaces": True,
    "enable_tables_fallback": True,
}

_MULTIPART_BOUNDARY = "----practiceImporterMathpixBoundary7MA4YWxkTrZu0gW"
_IMAGE_URL_RE = re.compile(r"https://cdn\.mathpix\.com/[^\s)\"'>]+")


# ─── Credentials ─────────────────────────────────────────────────────────────

def load_credentials() -> tuple[str, str]:
    """Resolve (app_id, app_key) from env first, then the skill config file."""
    app_id = os.environ.get("MATHPIX_APP_ID")
    app_key = os.environ.get("MATHPIX_APP_KEY")
    if not (app_id and app_key) and _CONFIG.exists():
        try:
            data = json.loads(_CONFIG.read_text(encoding="utf-8"))
        except json.JSONDecodeError as e:
            raise SystemExit(f"mathpix config is not valid JSON ({_CONFIG}): {e}")
        app_id = app_id or data.get("app_id")
        app_key = app_key or data.get("app_key")
    if not app_id or not app_key:
        raise SystemExit(
            "Mathpix credentials missing. Set env MATHPIX_APP_ID / MATHPIX_APP_KEY, "
            f"or copy {_CONFIG.parent / 'mathpix.example.json'} → {_CONFIG} and fill them."
        )
    return app_id, app_key


# ─── HTTP helpers (stdlib only) ──────────────────────────────────────────────

def _auth_headers(app_id: str, app_key: str) -> dict[str, str]:
    return {"app_id": app_id, "app_key": app_key}


def _encode_multipart(fields: dict[str, str], file_field: str,
                      filename: str, file_bytes: bytes) -> tuple[bytes, str]:
    """Hand-encode a multipart/form-data body. Returns (body, content_type)."""
    b = _MULTIPART_BOUNDARY
    out = bytearray()
    for name, value in fields.items():
        out += f"--{b}\r\n".encode()
        out += f'Content-Disposition: form-data; name="{name}"\r\n\r\n'.encode()
        out += str(value).encode("utf-8")
        out += b"\r\n"
    out += f"--{b}\r\n".encode()
    out += (
        f'Content-Disposition: form-data; name="{file_field}"; '
        f'filename="{filename}"\r\n'
    ).encode()
    out += b"Content-Type: application/pdf\r\n\r\n"
    out += file_bytes
    out += b"\r\n"
    out += f"--{b}--\r\n".encode()
    return bytes(out), f"multipart/form-data; boundary={b}"


def _http_json(req: urllib.request.Request, timeout: int) -> dict:
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", "replace")[:500]
        raise SystemExit(f"Mathpix HTTP {e.code} on {req.full_url}: {body}")
    except urllib.error.URLError as e:
        raise SystemExit(f"Mathpix network error on {req.full_url}: {e.reason}")


def _http_text(url: str, headers: dict[str, str], timeout: int) -> str:
    req = urllib.request.Request(url, headers=headers, method="GET")
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return resp.read().decode("utf-8")
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", "replace")[:500]
        raise SystemExit(f"Mathpix HTTP {e.code} fetching {url}: {body}")
    except urllib.error.URLError as e:
        raise SystemExit(f"Mathpix network error fetching {url}: {e.reason}")


# ─── Mathpix v3 PDF API ──────────────────────────────────────────────────────

def submit_pdf(pdf_path: Path, app_id: str, app_key: str,
               options: dict | None = None, timeout: int = 60) -> str:
    """POST the PDF, return the assigned pdf_id."""
    opts = json.dumps(options or DEFAULT_OPTIONS)
    body, content_type = _encode_multipart(
        fields={"options_json": opts},
        file_field="file",
        filename=pdf_path.name,
        file_bytes=pdf_path.read_bytes(),
    )
    headers = _auth_headers(app_id, app_key)
    headers["Content-Type"] = content_type
    req = urllib.request.Request(MATHPIX_BASE, data=body, headers=headers, method="POST")
    result = _http_json(req, timeout)
    pdf_id = result.get("pdf_id")
    if not pdf_id:
        raise SystemExit(f"Mathpix did not return a pdf_id: {json.dumps(result)[:400]}")
    return pdf_id


def poll_until_done(pdf_id: str, app_id: str, app_key: str,
                    timeout: int = 300, interval: float = 3.0) -> dict:
    """Poll GET /v3/pdf/{id} until status == completed (or error / timeout)."""
    headers = _auth_headers(app_id, app_key)
    url = f"{MATHPIX_BASE}/{pdf_id}"
    deadline = time.monotonic() + timeout
    last: dict = {}
    while time.monotonic() < deadline:
        req = urllib.request.Request(url, headers=headers, method="GET")
        last = _http_json(req, timeout=30)
        status = last.get("status", "")
        pct = last.get("percent_done")
        pages = last.get("num_pages")
        print(
            f"[mathpix] {pdf_id} status={status}"
            + (f" {pct}%" if pct is not None else "")
            + (f" ({pages}p)" if pages else ""),
            file=sys.stderr,
        )
        if status == "completed":
            return last
        if status == "error":
            raise SystemExit(
                f"Mathpix processing error: {last.get('error', '')} "
                f"{json.dumps(last.get('error_info', {}))[:300]}"
            )
        time.sleep(interval)
    raise SystemExit(
        f"Mathpix timed out after {timeout}s (last status={last.get('status')!r}). "
        f"pdf_id={pdf_id} — re-fetch later with GET {MATHPIX_BASE}/{pdf_id}.md"
    )


def fetch_md(pdf_id: str, app_id: str, app_key: str, timeout: int = 120) -> str:
    """Fetch the rendered Markdown. Retries briefly — the .md endpoint can lag
    a beat behind the top-level `completed` status."""
    headers = _auth_headers(app_id, app_key)
    url = f"{MATHPIX_BASE}/{pdf_id}.md"
    last_err: SystemExit | None = None
    for attempt in range(5):
        try:
            text = _http_text(url, headers, timeout)
            if text.strip():
                return text
        except SystemExit as e:
            last_err = e
        time.sleep(2)
    if last_err:
        raise last_err
    raise SystemExit(f"Mathpix returned empty Markdown for pdf_id={pdf_id}")


# ─── Orchestration ───────────────────────────────────────────────────────────

def convert(pdf_path: Path, out_path: Path, *, timeout: int = 300,
            interval: float = 3.0, options: dict | None = None) -> dict:
    app_id, app_key = load_credentials()
    print(f"[mathpix] uploading {pdf_path.name} …", file=sys.stderr)
    pdf_id = submit_pdf(pdf_path, app_id, app_key, options=options)
    status = poll_until_done(pdf_id, app_id, app_key, timeout=timeout, interval=interval)
    md = fetch_md(pdf_id, app_id, app_key)

    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(md, encoding="utf-8")

    image_urls = sorted(set(_IMAGE_URL_RE.findall(md)))
    warnings: list[str] = []
    if not md.strip():
        warnings.append("EMPTY_MD: Mathpix returned no Markdown content.")
    # Mathpix carries NO color/highlight info — answer keys (yellow fills) must
    # come from the PyMuPDF answer-map sidecar (pdf_to_md.py --answer-map).
    warnings.append(
        "NO_ANSWER_INFO: Mathpix output has no answer-highlight data. Pair with "
        "`pdf_to_md.py <pdf> --answer-map <out.answers.json>` and merge in MD→JSON."
    )
    return {
        "pdf": str(pdf_path),
        "md": str(out_path),
        "pdf_id": pdf_id,
        "pages": status.get("num_pages"),
        "chars": len(md),
        "status": status.get("status"),
        "image_urls": image_urls,
        "warnings": warnings,
    }


def main(argv: list[str]) -> int:
    if not argv or argv[0] in ("-h", "--help"):
        print(
            "usage: mathpix_pdf.py <input.pdf> [<output.md>] "
            "[--timeout 300] [--poll-interval 3]",
            file=sys.stderr,
        )
        return 2
    pdf = Path(argv[0]).resolve()
    if not pdf.exists():
        print(f"error: file not found: {pdf}", file=sys.stderr)
        return 1
    out: Path | None = None
    timeout = 300
    interval = 3.0
    i = 1
    while i < len(argv):
        a = argv[i]
        if a == "--timeout":
            timeout = int(argv[i + 1]); i += 2; continue
        if a == "--poll-interval":
            interval = float(argv[i + 1]); i += 2; continue
        if out is None:
            out = Path(a).resolve(); i += 1; continue
        i += 1
    if out is None:
        out = pdf.parent / (pdf.stem + ".md")
    summary = convert(pdf, out, timeout=timeout, interval=interval)
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
