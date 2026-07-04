#!/usr/bin/env python3
"""Marathon batcher for the Mathpix image-refill recovery.

Reads the work-list (targets.json) MINUS everything already done (old_urls present in
the master recovered.csv / misses.csv), clusters the remaining work by source exam
(image `job`), and splits it into N disjoint batch files — one per recovery subagent.
Each batch keeps whole exams together so a found source is reused across siblings.

Resumable: re-run any time; finished old_urls are never reassigned.

Usage:
  python make_batches.py --batches 3 --exams-per-batch 5 [--out-dir <data>]
Outputs <out-dir>/batch-1.json … batch-N.json and prints a coverage summary.
"""
import argparse
import csv
import json
import os


def load_done(path: str) -> set:
    done = set()
    if os.path.isfile(path):
        with open(path, newline="", encoding="utf-8") as f:
            r = csv.DictReader(f)
            for row in r:
                u = (row.get("old_url") or "").strip()
                if u:
                    done.add(u)
    return done


def main() -> None:
    here = os.path.dirname(os.path.abspath(__file__))
    default_data = os.path.abspath(os.path.join(here, "../../../../plans/260630-1127-mathpix-image-refill/data"))
    ap = argparse.ArgumentParser()
    ap.add_argument("--data-dir", default=default_data)
    ap.add_argument("--batches", type=int, default=3, help="how many batch files to emit THIS wave")
    ap.add_argument("--images-per-batch", type=int, default=8, help="image cap per batch (subagent context budget)")
    a = ap.parse_args()

    targets = json.load(open(os.path.join(a.data_dir, "targets.json"), encoding="utf-8"))["targets"]
    done = load_done(os.path.join(a.data_dir, "recovered.csv")) | load_done(os.path.join(a.data_dir, "misses.csv"))

    # Filter each target to its still-unresolved images; drop empties.
    remaining = []
    for t in targets:
        imgs = [im for im in t["images"] if im["old_url"] not in done]
        if imgs:
            remaining.append({**t, "images": imgs})

    # Cluster remaining targets by exam (job of first remaining image; 'no-job' fallback).
    by_exam: dict[str, list] = {}
    for t in remaining:
        job = t["images"][0].get("job") or "no-job"
        by_exam.setdefault(job, []).append(t)

    # Order exams by (a) subject YIELD then (b) size — wave-1 evidence: physics/chem/
    # bio/tech FIGURES recover well; geography/economics "biểu đồ" CHARTS are recent,
    # data-specific, and almost never online → near-certain misses. Do the high-yield
    # exams first (and bigger exams first within a tier — more images per source-find),
    # so a limited budget produces the most RECOVERED images; charts drain last.
    # Per-subject yield rank (wave-1/2 evidence): physics circuits/graphs + chemistry
    # apparatus/structures recover best (standard figures online); technology/math next;
    # biology genetics (pedigrees/pathways) are bespoke + recent → lower; geography/
    # economics charts are near-zero (mostly auto-routed to misses already).
    RANK = {"physics": 0, "chemistry": 1, "technology": 2, "math": 3, "biology": 4}

    def exam_key(kv):
        _job, tgts = kv
        rank = min((RANK.get(t.get("subject"), 5) for t in tgts), default=5)
        size = sum(len(t["images"]) for t in tgts)
        return (rank, -size)  # best-yield subjects first; bigger exams first within a rank

    exams = sorted(by_exam.items(), key=exam_key)
    ordered = [t for _job, tgts in exams for t in tgts]

    # Greedily pack into image-capped bins, DEDUPING by old_url across the whole run:
    # duplicate questions share old_urls, so processing a url twice means redundant
    # searches + R2 uploads that collapse on merge. Each old_url goes to exactly one
    # batch (via its first-seen owner); the remap later fixes every duplicate at apply.
    cap = a.images_per_batch
    assigned: set[str] = set()
    bins: list[list] = []
    cur: list = []
    cur_imgs = 0
    unique_total = 0
    for t in ordered:
        fresh = [im for im in t["images"] if im["old_url"] not in assigned]
        if not fresh:
            continue
        for im in fresh:
            assigned.add(im["old_url"])
        unique_total += len(fresh)
        n = len(fresh)
        if cur and cur_imgs + n > cap:
            bins.append(cur)
            cur, cur_imgs = [], 0
        cur.append({**t, "images": fresh})
        cur_imgs += n
    if cur:
        bins.append(cur)

    total_imgs = unique_total
    wave = bins[: a.batches]
    assigned_imgs = 0
    for k, b in enumerate(wave, 1):
        out = os.path.join(a.data_dir, f"batch-{k}.json")
        json.dump(b, open(out, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
        bi = sum(len(t["images"]) for t in b)
        assigned_imgs += bi
        print(f"batch-{k}.json: {len(b)} targets, {bi} images")

    print(f"\nremaining exams: {len(exams)} | remaining images: {total_imgs} | total bins needed: {len(bins)}")
    print(f"this wave: {assigned_imgs} images across {len(wave)} batches")
    print(f"still queued after this wave: {total_imgs - assigned_imgs} images / ~{max(0, len(bins) - len(wave))} more bins")


if __name__ == "__main__":
    main()
