#!/usr/bin/env python3
"""Normalise the WordPress plugin header block of every plugin in a folder.

Only the header comment (first 60 lines) is touched. Plugin Name, Version, Text Domain and all
code are left alone. Original line endings are preserved.

    python fix_plugin_headers.py PATH_TO_PLUGINS            # dry-run (prints what would change)
    python fix_plugin_headers.py PATH_TO_PLUGINS --apply    # write changes

Each plugin is expected at  PATH/<slug>/<slug>.php .
Edit the constants below if your details differ.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

AUTHOR = "Mahyar Momeni"
AUTHOR_URI = "https://mahyarhub.ir"
GITHUB_USER = "mahyarrmni"
LICENSE = "GPL-2.0-or-later"
LICENSE_URI = "https://www.gnu.org/licenses/gpl-2.0.html"
HEADER_LINES = 60
PLACEHOLDER_HOSTS = ("example.com",)


def desired_fields(slug: str, existing: dict[str, str]) -> dict[str, str]:
    fields = {}
    uri = existing.get("Plugin URI", "")
    if not uri or any(h in uri for h in PLACEHOLDER_HOSTS):
        fields["Plugin URI"] = f"https://github.com/{GITHUB_USER}/{slug}"
    fields["Author"] = AUTHOR
    fields["Author URI"] = AUTHOR_URI
    if not existing.get("License"):
        fields["License"] = LICENSE
    if not existing.get("License URI"):
        fields["License URI"] = LICENSE_URI
    return fields


FIELD_RE = re.compile(r"^(\s*\*\s*)([A-Za-z][A-Za-z ]+?)(\s*):(\s*)(.*?)\s*$")


def patch(text: str, slug: str) -> tuple[str, list[str]]:
    nl = "\r\n" if "\r\n" in text else "\n"
    lines = text.split(nl)
    head = lines[:HEADER_LINES]
    # The field block ends at the first empty comment line (" *") or at the closing "*/".
    end = next((i for i, ln in enumerate(head) if i > 1 and (ln.strip() == "*" or "*/" in ln)), None)
    if end is None or not any("Plugin Name:" in ln for ln in head[:end]):
        return text, []

    existing: dict[str, str] = {}
    positions: dict[str, int] = {}
    for i, line in enumerate(head[:end]):
        m = FIELD_RE.match(line)
        if m:
            existing[m.group(2)] = m.group(5)
            positions[m.group(2)] = i

    changes: list[str] = []
    additions: list[str] = []
    for key, value in desired_fields(slug, existing).items():
        if key in positions:
            i = positions[key]
            m = FIELD_RE.match(lines[i])
            if m and m.group(5) != value:
                if key == "Author" and m.group(5) not in ("", "—", "Custom", "Mahyar", "SEO Intelligence", "AI Comment Seeder", AUTHOR):
                    continue  # do not overwrite a real, different author
                lines[i] = f"{m.group(1)}{key}{m.group(3)}:{m.group(4)}{value}"
                changes.append(f"{key}: {m.group(5)!r} -> {value!r}")
        else:
            additions.append(f" * {key}: {value}")
            changes.append(f"{key}: (added) {value!r}")
    if additions:
        # keep the new fields together, right after Author (or Version / Plugin Name as a fallback)
        anchor = positions.get("Author", positions.get("Version", positions["Plugin Name"]))
        lines[anchor + 1:anchor + 1] = additions
    return nl.join(lines), changes


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("root", type=Path)
    ap.add_argument("--apply", action="store_true", help="write changes (default is a dry run)")
    args = ap.parse_args()

    touched = 0
    for plugin_dir in sorted(p for p in args.root.iterdir() if p.is_dir()):
        main_file = plugin_dir / f"{plugin_dir.name}.php"
        if not main_file.exists():
            continue
        raw = main_file.read_bytes().decode("utf-8")
        new, changes = patch(raw, plugin_dir.name)
        if not changes:
            print(f"= {plugin_dir.name}: header already fine")
            continue
        touched += 1
        print(f"* {plugin_dir.name}")
        for c in changes:
            print(f"    {c}")
        if args.apply:
            main_file.write_bytes(new.encode("utf-8"))
    print(f"\n{touched} plugin(s) {'updated' if args.apply else 'would change (dry run)'}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
