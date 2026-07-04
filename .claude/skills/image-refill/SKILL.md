---
name: image-refill
description: Recover question images whose Mathpix CDN crops (cdn.mathpix.com/cropped/...) were deleted (404). Per affected question, web-search by the question's TEXT to find the original online, vision-verify the matching figure/option, re-host it to R2 (upload_question_image), and record old_url→new_url for the media:remap-urls command to apply. Unfound images go to misses.csv. Auto-loads for /image-refill or Mathpix image recovery work.
---

# Image Refill — recover deleted Mathpix crops via web search → R2

Mathpix deleted the images it hosted at `cdn.mathpix.com/cropped/<job>-<page>.jpg?...`.
They now 404. The pixels are unrecoverable from Mathpix. We recover them by finding the
SAME question online (Vietnamese exam sites) and re-hosting the matching image to R2.

## Inputs / outputs

- Work-list: `plans/260630-1127-mathpix-image-refill/data/targets.json` — produced by
  `php artisan media:refill-extract`. Each target = one owner (question/passage/option) with:
  `id, table, subject, grade, topic_slug, text_plain, images[{old_url, job, page, coords, slot}]`.
- Append-only ledgers (RESUMABLE — skip any `old_url` already present in EITHER):
  - `data/recovered.csv` — `old_url,new_url,source_url,owner_id,confidence`
  - `data/misses.csv`    — `old_url,owner_id,text_preview,reason`
- Apply (separate, deterministic): `php artisan media:remap-urls --map=data/recovered.csv [--apply]`.

## Per-target loop (work clustered by exam = `images[].job` to find a source ONCE)

1. **Skip if done**: `old_url` already in recovered.csv or misses.csv → next.
2. **Search**: `WebSearch` the most distinctive ~12–20 words of `text_plain` (drop `$…$`
   LaTeX; keep the Vietnamese prose). Prefer Vietnamese exam sites: tuyensinh247,
   vietjack, loigiaihay, hoc247, thuvienhoclieu, doctailieu, hoctap.dvtienich,
   tailieu, toanmath. Reuse a found source across sibling questions of the same `job`.
3. **Locate**: `WebFetch` the best candidate(s); confirm it is THE SAME question. Ask the
   fetch to list every image `src` URL in the question body.
4. **Match the slot**: download each candidate image with a real UA + Referer:
   `curl -sS -H "User-Agent: Mozilla/5.0" -H "Referer: <page>" -o /tmp/c.png "<imgurl>"`.
   `Read` it. Confirm it is the SAME content as the dead slot:
   - `slot=figure` → the stem diagram/chart/table.
   - `slot=option` → the correct A/B/C/D choice image; coords order (left→right, top→bottom)
     on the page disambiguates. A WRONG option image silently corrupts the answer — if you
     cannot be sure which option an image is, send it to misses, do NOT guess.
   - Optional crop: if the source image bundles several figures, crop with PIL /
     `.claude/skills/question-importer/scripts/crop_region.py`, then upload the local file.
5. **Re-host to R2** (only when vision-verified):
   `python .claude/skills/image-refill/scripts/refill_upload.py --src-url "<imgurl>"
     --question-uuid <owner_id> --topic-slug <topic_slug> --index <n> [--referer <page>]`
   (or `--file <cropped.png>`). It prints `OK <r2_url>`.  `owner_id` for a passage/option
   target is that row's own id; topic_slug `image-refill` is fine when unknown.
6. **Record**: append `old_url,<r2_url>,<source_url>,<owner_id>,high` to recovered.csv.
   On no-source / low-confidence / image-only-stem: append
   `old_url,<owner_id>,"<text_preview>",<reason>` to misses.csv. Reasons: `not-found`,
   `ambiguous-option`, `bare-storage`, `chart-data-specific`, `image-only-stem`.

## Quality bar

- Re-host ONLY a vision-verified image. Never upload a guess — a wrong image on a live
  question is worse than a miss. When unsure → misses.csv.
- `bare-storage` old_urls (`…/storage/`) are NOT substring-safe; the remap command refuses
  them. Record them in misses.csv (or fix that single owner precisely by hand).
- Throttle: ≤3 concurrent writers against the prod importer endpoint (R2 upload).
- CSV-safe: quote any field containing a comma (text previews).

## Source-site playbook (learned over the marathon)

- **Best sources (server-rendered, figures in HTML):** `khoahoc.vietjack.com/question/<id>`,
  `tuyensinh247.com/bai-tap-<id>.html`, `hoidap247.com`, `hoc247.net`, `sieugioi.com`.
- **Cluster-probe a whole exam:** when one question of an exam is found at `…/question/<id>`
  or `…/bai-tap-<id>`, the rest are usually nearby sequential IDs. Probe the cluster (follow
  each 302→canonical slug to map stem→ID), then pull each figure once. Unlocks the questions
  that aren't individually search-indexed.
- **JS-blind — never WebFetch for the image (returns nav/garbage/wrong cached body):**
  `loigiaihay.com`, `gauthmath.com`, `studyx.ai`, `fqa.vn` (but fqa's figure is in raw HTML
  `illustration_images`), `*.thi-online` pages. VnDoc renders full exams as page-images
  `st.vndoc.com/.../bgN.png` — readable.
- **Bundled images:** vietjack/tuyensinh247 often store a question's stem-figure + options, or
  all 4 option graphs as ONE image (1×4 strip or 2×2 grid). Download it, crop with PIL by the
  whitespace gaps, map crops to slots by the stored HTML `Hình N` order = ascending
  `top_left_x` (A<B<C<D), vision-verify each crop, then upload with `--file`.
- **Pick the STEM figure, not the solution figure:** a page may show both (e.g. coil alone vs
  coil + force vectors). Match the dead crop's aspect ratio + content to the stem.

## Miss reasons (taxonomy)

`not-found` (exact đề not online) · `chart-data-specific` (data-bearing graph unique to the
đề — p-V/F-I/decay/temperature curves; only different-parameter variants exist) ·
`ambiguous-option` (option images but no exact source to map A/B/C/D) ·
`decorative-no-figure-ref` (text-only stem with NO "hình/hình bên/đồ thị" reference — the
crop is a mis-attributed neighbor figure; recommend DETACHING the img, not refilling) ·
`image-only-stem` (stem is just the figure, nothing to search on).

## Verify a recovered URL resolves

`curl -sS -o /dev/null -w "%{http_code}\n" "<r2_url>"` → expect 200.
