#!/usr/bin/env python3
"""verify_json_vs_pdf.py — Deterministic verifier for practice-importer output.

CLI:
  python verify_json_vs_pdf.py
      --md   practice-store/handle-file/1101.md
      --json practice-store/result/1101.json
      --out  practice-store/result/1101.verify.json
      [--pdf practice-store/handle-file/1101.pdf]   # informational only
      [--strict]   # exit 1 on SOFT_FAIL too (default: only HARD_FAIL exits 1)
      [--quiet]    # suppress stdout summary

Exit codes:
  0 — PASS (or only WARN/INFO)
  1 — HARD_FAIL (or SOFT_FAIL when --strict)
  2 — script error (file not found, JSON malformed, etc.)

Ground truth: the MD file in handle-file/ (byte-identical from PyMuPDF).
Subject under test: the JSON file in result/ (LLM-emitted).

The PDF path is informational only — recorded in the report so consumers
can trace provenance, but the verifier does NOT re-run pdf_to_md.py.
(That envelope misclassifies some MCQ types — see Phase 02 deviation note.)
"""

from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timezone, timedelta
from pathlib import Path

# Make sibling modules importable when called as a script.
_HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(_HERE))
from md_parser import parse as parse_md  # noqa: E402
from compare_structures import run_all, Finding  # noqa: E402

SAIGON_TZ = timezone(timedelta(hours=7))
VERIFIER_VERSION = "1.0.0"


def _classify(findings: list[Finding]) -> tuple[str, dict]:
    """Bucket findings by severity → return (overall_status, counts)."""
    counts = {"HARD": 0, "SOFT": 0, "WARN": 0, "INFO": 0}
    for f in findings:
        counts[f.severity] = counts.get(f.severity, 0) + 1
    if counts["HARD"]:
        status = "HARD_FAIL"
    elif counts["SOFT"]:
        status = "SOFT_FAIL"
    else:
        status = "PASS"
    return status, counts


def main(argv: list[str]) -> int:
    p = argparse.ArgumentParser(description="Verify Practice JSON against source MD.")
    p.add_argument("--md", required=True, type=Path, help="MD file in handle-file/")
    p.add_argument("--json", required=True, type=Path, help="JSON file in result/")
    p.add_argument("--out", required=True, type=Path, help="Verify report output path")
    p.add_argument("--pdf", type=Path, help="(optional) source PDF — informational only")
    p.add_argument("--strict", action="store_true", help="Exit 1 on SOFT_FAIL too")
    p.add_argument("--quiet", action="store_true", help="Suppress stdout summary")
    args = p.parse_args(argv)

    if not args.md.exists():
        print(f"ERROR: MD file not found: {args.md}", file=sys.stderr)
        return 2
    if not args.json.exists():
        print(f"ERROR: JSON file not found: {args.json}", file=sys.stderr)
        return 2

    try:
        md_truth = parse_md(args.md.read_text(encoding="utf-8"))
    except Exception as e:
        print(f"ERROR: MD parse failed: {e}", file=sys.stderr)
        return 2

    try:
        json_envelope = json.loads(args.json.read_text(encoding="utf-8"))
    except json.JSONDecodeError as e:
        print(f"ERROR: JSON parse failed: {e}", file=sys.stderr)
        return 2

    json_data = json_envelope.get("data", {})
    if not isinstance(json_data, dict):
        print("ERROR: JSON missing 'data' object", file=sys.stderr)
        return 2

    try:
        args.out.parent.mkdir(parents=True, exist_ok=True)
    except OSError as e:
        print(f"ERROR: cannot create output dir {args.out.parent}: {e}", file=sys.stderr)
        return 2

    results = run_all(md_truth, json_data)
    findings = [f for r in results for f in r.findings]
    status, counts = _classify(findings)

    report = {
        "verifier_version": VERIFIER_VERSION,
        "verified_at": datetime.now(SAIGON_TZ).isoformat(),
        "source": {
            "pdf": str(args.pdf) if args.pdf else None,
            "md": str(args.md),
            "json": str(args.json),
        },
        "summary": {
            "status": status,
            "hard_fail_count": counts["HARD"],
            "soft_fail_count": counts["SOFT"],
            "warning_count": counts["WARN"],
            "info_count": counts["INFO"],
            "md_question_count": md_truth["question_count"],
            "json_question_count": len(json_data.get("questions", [])),
        },
        "checks": [
            {"name": r.name, "passed": r.passed, "finding_count": len(r.findings)}
            for r in results
        ],
        "findings": [f.to_dict() for f in findings],
    }
    try:
        args.out.write_text(
            json.dumps(report, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )
    except OSError as e:
        print(f"ERROR: cannot write report {args.out}: {e}", file=sys.stderr)
        return 2

    if not args.quiet:
        print(json.dumps(report["summary"], ensure_ascii=False))

    if counts["HARD"]:
        return 1
    if counts["SOFT"] and args.strict:
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
