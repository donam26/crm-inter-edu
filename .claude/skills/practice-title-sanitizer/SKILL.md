---
name: practice-title-sanitizer
description: Sanitize the TITLE of SYSTEM practices (school_id=NULL) so it no longer advertises the WEB source it was downloaded from (thuvienhoclieu, toanmath, vietjack…) or the TEXTBOOK brand (Cánh Diều, KNTT, CTST, Global Success). KEEPS everything else verbatim — school names, Sở/Phòng GD, and all exam info (loại đề, kỳ thi, môn, lớp, năm, mã đề). Carries the source blocklist, the minimal-strip rubric, the MCP tool catalog, and the report format. Auto-loads when the practice-title-sanitizer agent runs, when handling /practices:sanitize-titles, or when work touches Practice title cleanup.
---

# Practice Title Sanitizer

Remove from a **system** practice title the two kinds of provenance the admin does not want shown —
**(1) pirate/aggregator WEB sources** (`thuvienhoclieu.com`, `toanmath.com`…) and **(2) TEXTBOOK
brand names** (`Cánh Diều`, `KNTT`, `CTST`, `Global Success`) — and **keep everything else exactly as
written**. This is a MINIMAL strip, not a reformat: school names, Sở/Phòng GD, abbreviations (GK1),
casing, wording and order all stay. System-only, title-driven, per-practice. The rename OVERWRITES the
title (old title survives only in the audit log). If a title has no source/brand token, **SKIP** it.

## What to STRIP vs KEEP

| | Examples |
|---|---|
| ❌ STRIP (provenance) | web/aggregator sites: `thuvienhoclieu(.com)`, `toanmath(.com)`, `loigiaihay`, `vietjack`, `hoc247`, `vndoc`, `123doc`, `doctailieu`, `download.vn`, `hocmai`, `tuyensinh247`; bare domains `*.com/.vn/.net`; file ext `.pdf/.docx/.doc`. **Textbook brands**: `Cánh Diều`, `Kết Nối Tri Thức (với cuộc sống)`, `KNTT`, `Chân Trời Sáng Tạo`, `CTST`, `Global Success`, `Friends Plus`, `i-Learn Smart World`, `Explore English`, `English Discovery`. |
| ✅ KEEP (verbatim) | **School names** (`Trường …`, `THPT …`, `THCS …`, `Liên trường …`, `Tiểu học …`) and **authorities** (`Sở GD&ĐT …`, `Sở GD …`, `Phòng GD&ĐT …`, `PGD …`) — these are wanted, especially on đề thi thử. ALL exam info: loại đề, kỳ thi + học kỳ (incl. `GK1`/`CK2` as written), môn, lớp, năm học, mã/số đề. |

> School and authority names are NO LONGER provenance to remove — the admin keeps them. Only WEB
> sources and TEXTBOOK brands are stripped.

## Scope (which practices)

In-scope ONLY if `school_id IS NULL` (system đề — NEVER a school's own đề; both `find_practices` and
`rename_practice` enforce this). A title is worth renaming only if it contains a WEB-source or
TEXTBOOK-brand token (left column above); a title that has only a school/Sở name (or is already clean)
is left untouched.

## MCP tool catalog (server = interedu-local | interedu-prod)

| Tool | Dir | Purpose |
|---|---|---|
| `find_practices` | READ | Work-set. Pass `title_contains: [<source+brand tokens>]` to pull only system practices whose title contains any of them (case-insensitive). Also `subject?`, `grade_level?`, `limit?`, `offset?`. Returns `{total_remaining, returned, practices:[{id,title,subject,grade_level,…}]}`. |
| `rename_practice` | WRITE | Rename ONE system practice. Args: `id`*, `title`* (the cleaned title), `forbidden_substrings?` (pass the source+brand blocklist — the tool REFUSES if the new title still contains one), `reason`*. Refuses school-owned; OVERWRITES; no-ops an unchanged title; logs old→new. |

`reason` is REQUIRED on every write — cite the tokens you stripped.

## Source+brand blocklist (strip these — also the `title_contains` seed)

**Web / aggregator sources** (case/diacritic-insensitive): `thuvienhoclieu`, `thuvienhoclieu.com`,
`toanmath`, `toanmath.com`, `loigiaihay`, `vietjack`, `hoc247`, `vndoc`, `123doc`, `doctailieu`,
`download.vn`, `hocmai`, `tuyensinh247`, plus any bare domain (`.com / .vn / .net`) and file
extensions `.pdf`, `.docx`, `.doc`.

**Textbook brands / chương trình**: `Cánh Diều`, `Kết Nối Tri Thức (với cuộc sống)`, `KNTT`,
`Chân Trời Sáng Tạo`, `CTST`, `Global Success`, `Friends Plus`, `i-Learn Smart World`,
`Explore English`, `English Discovery`.

(Do NOT seed `title_contains` with school markers like `THPT`/`Trường`/`Sở GD` — those titles are kept.)

## Minimal-strip rubric (ULTRATHINK per đề)

1. **Remove** every web-source and textbook-brand token found above, together with the now-dangling
   separators/whitespace around them (`-`, `–`, `|`, `:`, `()`, trailing `.com`, stray `_`, double spaces).
2. **Keep everything else EXACTLY as written** — school names, Sở/Phòng GD, loại đề, kỳ thi (`GK1`
   stays `GK1`), môn, lớp, năm học, mã/số đề, the original casing, wording and order.
3. **No beautifying**: do NOT expand abbreviations (GK1 ✗→ "giữa kỳ 1"), do NOT change casing, do NOT
   translate, do NOT reorder, do NOT invent info. The ONLY change is the deletion of provenance tokens
   plus tidying the gap they leave.
   - Exception: if the WHOLE title is a raw download slug (`de-gk1-toan-7-kntt_thuvienhoclieu.com`),
     de-slug the leftover to readable Vietnamese (`Đề GK1 Toán 7`) — a slug is itself the artefact.
4. If after stripping there is no meaningful exam info left (the title was *only* a source/brand),
   **SKIP** and list it for manual review.

### Worked examples

| Old title | New title | Stripped |
|---|---|---|
| `Đề GK1 Toán 7 Cánh Diều - thuvienhoclieu.com` | `Đề GK1 Toán 7` | Cánh Diều, thuvienhoclieu.com |
| `Đề thi thử TN THPT 2026 môn Toán – Sở GD Nghệ An - toanmath.com` | `Đề thi thử TN THPT 2026 môn Toán – Sở GD Nghệ An` | toanmath.com |
| `Đề thi thử TN THPT 2026 lần 1 môn Toán – Trường THPT Chuyên Trần Phú, Hải Phòng` | *(unchanged — no web/brand token, SKIP)* | — |
| `de-cuoi-ki-2-ngu-van-9-kntt_toanmath.com.pdf` | `Đề cuối kì 2 Ngữ văn 9` | KNTT, toanmath.com, .pdf, slug |
| `thuvienhoclieu.com` (no exam info) | — (SKIP) | — |

## Confidence / skip policy

- Rename ONLY when the title contains a web-source/brand token AND a clean title with real exam info
  remains after removal.
- No source/brand token → SKIP (out of scope). No exam info survives stripping → SKIP, list in report.
- The new title must contain NONE of the source/brand tokens — pass the blocklist as
  `forbidden_substrings` so the tool double-checks you.
- Renaming is idempotent at the tool level: an unchanged title is a no-op (reported as skipped).

## Report format

```markdown
## Practice title-sanitize report — subject={subject}, grade={grade}, limit={N}, target={local|prod}

| #  | P-id (short) | Old title (truncated)                      | New title             | Stripped            | Status  |
|----|--------------|--------------------------------------------|-----------------------|---------------------|---------|
| 1  | a1c0...7650  | Đề GK1 Toán 7 Cánh Diều - thuvienhoclie…   | Đề GK1 Toán 7         | Cánh Diều, thuvienho| renamed |
| 2  | a178...8ac3  | thuvienhoclieu.com                         | (skipped — no exam)   | —                   | skipped |

Summary:
- Renamed: N | Skipped: M | Unchanged (idempotent): K | total_remaining after run: …
- Audit: storage/logs/laravel.log channel=mcp ([mcp.practice.rename]) — keeps old_title for rollback
```

## Deployment note

`rename_practice` + the `title_contains` filter on `find_practices` are PHP MCP tools in
`app/Mcp/Tools/Practices/`. They work on `interedu-local` immediately, but `--target=prod` requires
them to be **deployed to the prod MCP server** first (same as `set_practice_type`). If a prod run
reports "tool not found", deploy then retry.
