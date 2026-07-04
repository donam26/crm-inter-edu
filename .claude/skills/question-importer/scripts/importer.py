#!/usr/bin/env python3
"""Unified bridge to the question-importer MCP tools — the ONE reliable way the
skill calls import_question / upsert_lesson / upload_question_image /
import_passage / mark_question_verified / list_topics / find_similar_questions.

Why a script instead of native `mcp__interedu-importer__*` tools: the Claude Code
harness indexes MCP servers only at SESSION START, and the stdio importer server
is frequently NOT indexed (ToolSearch finds nothing even though `claude mcp list`
shows it Connected). This bridge has no such dependency — it talks JSON-RPC
directly, so it works every run.

Targets:
  --target prod  (DEFAULT) → POST https://backend.phongthi.edu.vn/api/mcp/importer
                              (the deployed prod importer endpoint — questions land
                               LIVE on production, as drafts: is_published=false)
  --target local           → spawn `php artisan mcp:serve-importer` (writes the
                              LOCAL .env DB; use only for a dry test)

Usage:
  python importer.py <tool> <args_json_file>            # default --target prod
  python importer.py <tool> -            < args.json     # read args from stdin
  python importer.py <tool> a.json --target local
  python importer.py <tool> a.json --url <custom-url>

Prints the tool's inner result JSON to stdout. Exits non-zero on RPC / tool error.

PROD GOTCHAS (read before pushing):
  • topic_id is per-DB — a LOCAL lesson UUID is INVALID on prod. Resolve the lesson
    on the TARGET by SLUG: call upsert_lesson (idempotent) with the chapter's
    parent_topic_id + the lesson name + slug → use the RETURNED topic_id. Chapter
    ids often match local↔prod, but LESSON ids differ.
  • Image URLs are global (R2/CDN) → the same cdn.phongthi.edu.vn URL works on prod.
  • Reconcile by the ids import_question returns + get_question_context, NOT by
    classification_stats (it counts is_published=true only → drafts don't show).
"""
import argparse
import json
import subprocess
import sys
import urllib.request

PROD_URL = "https://backend.phongthi.edu.vn/api/mcp/importer"
PROJECT_DIR = "/Users/hnam/code/phongthi/backend-interedu"


def _unwrap(result: dict) -> str:
    content = result.get("content") or []
    inner = content[0]["text"] if content and content[0].get("type") == "text" else json.dumps(result)
    if result.get("isError"):
        sys.stderr.write("TOOL ERROR: " + inner + "\n")
        sys.exit(1)
    return inner


def call_prod(tool: str, args: dict, url: str) -> str:
    body = {"jsonrpc": "2.0", "id": 2, "method": "tools/call",
            "params": {"name": tool, "arguments": args}}
    req = urllib.request.Request(
        url, data=json.dumps(body, ensure_ascii=False).encode("utf-8"),
        headers={"Content-Type": "application/json", "Accept": "application/json",
                 "User-Agent": "Mozilla/5.0 (question-importer)"}, method="POST")
    with urllib.request.urlopen(req, timeout=120) as r:
        d = json.loads(r.read().decode("utf-8"))
    if "error" in d:
        sys.stderr.write("RPC ERROR: " + json.dumps(d["error"], ensure_ascii=False) + "\n")
        sys.exit(1)
    return _unwrap(d.get("result", {}))


def call_local(tool: str, args: dict) -> str:
    msgs = [
        {"jsonrpc": "2.0", "id": 1, "method": "initialize",
         "params": {"protocolVersion": "2024-11-05", "capabilities": {},
                    "clientInfo": {"name": "importer", "version": "1"}}},
        {"jsonrpc": "2.0", "method": "notifications/initialized"},
        {"jsonrpc": "2.0", "id": 2, "method": "tools/call",
         "params": {"name": tool, "arguments": args}},
    ]
    stdin = "\n".join(json.dumps(m, ensure_ascii=False) for m in msgs) + "\n"
    proc = subprocess.run(["php", "artisan", "mcp:serve-importer"],
                          input=stdin.encode("utf-8"), stdout=subprocess.PIPE,
                          stderr=subprocess.PIPE, cwd=PROJECT_DIR, timeout=180)
    for line in proc.stdout.decode("utf-8", "replace").splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            d = json.loads(line)
        except Exception:
            continue
        if d.get("id") == 2:
            if "error" in d:
                sys.stderr.write("RPC ERROR: " + json.dumps(d["error"], ensure_ascii=False) + "\n")
                sys.exit(1)
            return _unwrap(d.get("result", {}))
    sys.stderr.write("NO RESPONSE\nSTDERR:\n" + proc.stderr.decode("utf-8", "replace")[:3000] + "\n")
    sys.exit(1)


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("tool")
    ap.add_argument("args_file", help="path to JSON args, or - for stdin")
    ap.add_argument("--target", choices=["prod", "local"], default="prod")
    ap.add_argument("--url", default=PROD_URL, help="override prod endpoint URL")
    a = ap.parse_args()
    raw = sys.stdin.read() if a.args_file == "-" else open(a.args_file, encoding="utf-8").read()
    args = json.loads(raw) if raw.strip() else {}
    out = call_local(a.tool, args) if a.target == "local" else call_prod(a.tool, args, a.url)
    print(out)


if __name__ == "__main__":
    main()
