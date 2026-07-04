#!/usr/bin/env python3
"""Download a recovered source image (or take a local file) → base64 → upload to R2
via the deployed prod importer endpoint (`upload_question_image`) → print the new
public CDN url on stdout.

This is the ONE write step of the Mathpix image-refill recovery loop. The agent has
already (1) found the source page by question text and (2) vision-verified the image
matches the dead slot before calling this.

Usage:
  python refill_upload.py --src-url "https://site/img.jpg" \
      --question-uuid <uuid> --topic-slug <slug> [--index 1] [--ext png] \
      [--referer "https://site/"]
  python refill_upload.py --file /tmp/cropped.png --question-uuid <uuid> --topic-slug <slug>

On success prints exactly:  OK <new_r2_url>
On failure prints:          ERR <reason>   (exit 1)
"""
import argparse
import base64
import json
import sys
import urllib.request

PROD_IMPORTER = "https://backend.phongthi.edu.vn/api/mcp/importer"
UA = "Mozilla/5.0 (image-refill)"


def fetch(url: str, referer: str | None) -> bytes:
    headers = {"User-Agent": UA}
    if referer:
        headers["Referer"] = referer
    req = urllib.request.Request(url, headers=headers, method="GET")
    with urllib.request.urlopen(req, timeout=60) as r:
        return r.read()


def upload(b64: str, question_uuid: str, topic_slug: str, index: int, ext: str) -> str:
    body = {
        "jsonrpc": "2.0", "id": 2, "method": "tools/call",
        "params": {"name": "upload_question_image", "arguments": {
            "image_base64": b64, "topic_slug": topic_slug,
            "question_uuid": question_uuid, "index": index, "ext": ext,
        }},
    }
    req = urllib.request.Request(
        PROD_IMPORTER, data=json.dumps(body).encode("utf-8"),
        headers={"Content-Type": "application/json", "Accept": "application/json", "User-Agent": UA},
        method="POST")
    with urllib.request.urlopen(req, timeout=120) as r:
        d = json.loads(r.read().decode("utf-8"))
    if "error" in d:
        raise RuntimeError("RPC: " + json.dumps(d["error"], ensure_ascii=False))
    result = d.get("result", {})
    content = result.get("content") or []
    text = content[0]["text"] if content and content[0].get("type") == "text" else json.dumps(result)
    if result.get("isError"):
        raise RuntimeError("TOOL: " + text)
    payload = json.loads(text)
    url = payload.get("url")
    if not url:
        raise RuntimeError("no url in response: " + text[:300])
    return url


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--src-url")
    ap.add_argument("--file")
    ap.add_argument("--question-uuid", required=True)
    ap.add_argument("--topic-slug", required=True)
    ap.add_argument("--index", type=int, default=1)
    ap.add_argument("--ext", choices=["png", "jpg"], default="png")
    ap.add_argument("--referer")
    a = ap.parse_args()

    try:
        if a.file:
            with open(a.file, "rb") as f:
                raw = f.read()
        elif a.src_url:
            raw = fetch(a.src_url, a.referer)
        else:
            print("ERR need --src-url or --file")
            sys.exit(1)
        if len(raw) < 200:
            print(f"ERR image too small ({len(raw)} bytes) — likely a 404/placeholder")
            sys.exit(1)
        b64 = base64.b64encode(raw).decode("ascii")
        url = upload(b64, a.question_uuid, a.topic_slug, a.index, a.ext)
        print("OK " + url)
    except Exception as e:  # noqa: BLE001
        print("ERR " + str(e)[:400])
        sys.exit(1)


if __name__ == "__main__":
    main()
