#!/usr/bin/env python3
"""Merge per-subagent ledger parts into the master ledgers (idempotent).

Each recovery subagent writes recovered.part-K.csv / misses.part-K.csv to avoid append
races. This folds them into the master recovered.csv / misses.csv:
  - dedupe by old_url (master row wins, so re-merging is safe);
  - a successfully recovered old_url is removed from misses (success overrides a miss);
  - merged part files are renamed to *.merged so they are not folded twice.

Usage: python merge_parts.py [--data-dir <data>]
"""
import argparse
import csv
import glob
import os

REC_HEADER = ["old_url", "new_url", "source_url", "owner_id", "confidence"]
MISS_HEADER = ["old_url", "owner_id", "text_preview", "reason"]


def read(path: str) -> list[dict]:
    if not os.path.isfile(path):
        return []
    with open(path, newline="", encoding="utf-8") as f:
        return [row for row in csv.DictReader(f) if (row.get("old_url") or "").strip()]


def write(path: str, header: list[str], rows: list[dict]) -> None:
    with open(path, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=header, quoting=csv.QUOTE_MINIMAL)
        w.writeheader()
        for r in rows:
            w.writerow({k: r.get(k, "") for k in header})


def main() -> None:
    here = os.path.dirname(os.path.abspath(__file__))
    default_data = os.path.abspath(os.path.join(here, "../../../../plans/260630-1127-mathpix-image-refill/data"))
    ap = argparse.ArgumentParser()
    ap.add_argument("--data-dir", default=default_data)
    a = ap.parse_args()
    d = a.data_dir

    rec: dict[str, dict] = {r["old_url"]: r for r in read(os.path.join(d, "recovered.csv"))}
    miss: dict[str, dict] = {r["old_url"]: r for r in read(os.path.join(d, "misses.csv"))}

    merged_files = []
    for p in sorted(glob.glob(os.path.join(d, "recovered.part-*.csv"))):
        for r in read(p):
            rec.setdefault(r["old_url"], r)  # master/earlier wins
        merged_files.append(p)
    for p in sorted(glob.glob(os.path.join(d, "misses.part-*.csv"))):
        for r in read(p):
            miss.setdefault(r["old_url"], r)
        merged_files.append(p)

    # Success overrides a miss for the same URL.
    for u in list(miss.keys()):
        if u in rec:
            del miss[u]

    write(os.path.join(d, "recovered.csv"), REC_HEADER, list(rec.values()))
    write(os.path.join(d, "misses.csv"), MISS_HEADER, list(miss.values()))

    for p in merged_files:
        os.rename(p, p + ".merged")

    print(f"recovered (master): {len(rec)} | misses (master): {len(miss)} | parts folded: {len(merged_files)}")


if __name__ == "__main__":
    main()
