# FileSage

[فارسی](README.fa.md) · part of [Mahyar's Python Toolkit](../../README.md)

A smart file organiser and duplicate finder for messy download folders, photo libraries and drives:

- 🗂 **Organise** by type (Images, Videos, Documents …) and/or date (year/month)
- 🔁 **Exact duplicates** by content (SHA-256), not by file name
- 🖼 **Similar images** (resized, recompressed, re-saved) via perceptual hash
- 📊 **HTML report**: space by file type, duplicate groups, similar-image previews, 20 largest files ([sample](examples/sample-report.html))
- ↩️ **Full undo**: every move is journaled and reversible

**Safe by design:** the default is a **dry run** (preview only); you must pass `--apply` to change anything. FileSage **never deletes** files — it only moves them.
Works offline, no API.

| | |
|---|---|
| **Risk level** | 🟢 Low (moves files, never deletes; still back up important data) |
| **Needs** | Python 3.10+, `Pillow` (only for similar-image detection) |

## Install

```bash
cd utilities/filesage
pip install -r requirements.txt
```

## Usage

Replace the path with your folder (Windows example: `"C:\Users\Ali\Downloads"`).

**A. Report first (changes nothing)**
```bash
python filesage.py scan "~/Downloads" -o report.html
```

**B. Organise** — preview, then apply
```bash
python filesage.py organize "~/Downloads" --by type
python filesage.py organize "~/Downloads" --by type --apply
```
`--by type` (type folders) · `--by date` (year/month) · `--by both` (type/year/month).
Only the top level of the folder is organised unless you add `--recursive`.

**C. Move duplicates aside**
```bash
python filesage.py dupes "~/Pictures"            # preview
python filesage.py dupes "~/Pictures" --apply    # execute
```
In each duplicate group the **oldest copy stays**; the others are moved into `_duplicates/`. Review that folder and delete it yourself when sure.

**D. Undo**
```bash
python filesage.py undo "~/Downloads"
```
Reverts the most recent operation and removes folders it emptied. Run it repeatedly to step back through earlier operations.

## Categories

Images · Videos · Audio · Documents · Archives · Code · Apps · Fonts · Other (edit the `CATS` dict at the top of `filesage.py`).

## How it works

- **Exact duplicates:** group by size → hash first 64 KB → full SHA-256 only for likely matches (fast on big folders); hashing runs in threads.
- **Similar images:** 64-bit dHash + Hamming distance ≤ 5 + Union-Find clustering (max 4000 images per run). Similar images are only *shown*, never moved automatically.
- **Undo:** JSON journal in the target folder (`.filesage-journal-*.json`), written even if the run is interrupted. Name clashes never overwrite (`name (1).ext`).

## Troubleshooting

| Problem | Fix |
|---|---|
| `python is not recognized` | Reinstall Python with "Add to PATH", or use `py` / `python3` |
| Similar-images section empty / Pillow message | `pip install -r requirements.txt` |
| `folder not found` | Quote the path and check spelling |
| Permission error | File in use or no access — close the apps using it |
| Similar image missed | HEIC/RAW/SVG are unsupported (jpg/png/webp/bmp/gif/tiff only) |

## Recommendations

Do not run it on system folders (Windows, Program Files, `/usr`, …) — personal folders only. Back up important data before the first `--apply`.

## ⚠️ Disclaimer

This software is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

---
**Author:** [Mahyar Momeni](https://mahyarhub.ir) · [Telegram](https://t.me/mahyar_mmi) · [mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) · [MIT](../../LICENSE)
