# WordPress plugins — repository hygiene kit

Your nine plugins live in their own repositories. This folder contains what they all need but do not have yet.

## 1. Fix the plugin header comments

Findings from the current plugin files:

| Plugin | Problem |
|---|---|
| SEO Intelligence | `Plugin URI` is `https://example.com/…`; author is the plugin's own name |
| Phone Number Changer | Author is `Custom` |
| Content Cannibalization Scanner | Author is `—`; no author URI |
| AI Comment Seeder | Author is the plugin's own name |
| Backlink Pilot, QA Contact Form | Author is just `Mahyar` |
| Most plugins | No `Author URI`, `License` or `License URI` |

`fix_plugin_headers.py` repairs these. It edits **only the header comment**; plugin name, version,
text domain and all code are untouched, line endings are preserved, and a second run changes nothing.

```bash
# put the plugin folders side by side, then:
python fix_plugin_headers.py ../plugins            # dry run: shows what would change
python fix_plugin_headers.py ../plugins --apply    # writes the changes
```

Edit the constants at the top of the script first if your name, site or GitHub user differ.
It adds `License: GPL-2.0-or-later` where none is set — change `LICENSE` if you want something else
(note: plugins distributed on WordPress.org must be GPL-compatible).

## 2. Copy the templates into every plugin repo

`templates/` contains files to copy into each plugin repository:

| File | Purpose |
|---|---|
| `.gitignore` | ignores IDE files, `vendor/`, `node_modules/`, build zips |
| `.gitattributes` | LF endings, and `export-ignore` so release zips exclude dev files |
| `phpcs.xml.dist` | WordPress coding standards + PHP-compatibility check (7.4+; 8.1 for SEO Intelligence) |
| `php-ci.yml` → `.github/workflows/ci.yml` | runs `php -l` on PHP 7.4 / 8.1 / 8.3 and PHPCS on every push |

Also add, per plugin:

- a `LICENSE` file (use GitHub's *Add file → license → GPL-2.0* chooser)
- a WordPress-style `readme.txt` (Stable tag, Tested up to, Changelog)
- a Git tag + GitHub Release with a zip for every version bump
- screenshots in the README

## 3. Security review (worth doing before any release)

For each plugin check: `current_user_can()` on every admin action, `check_admin_referer()` /
nonces on every form and AJAX handler, `esc_html` / `esc_attr` / `esc_url` on output,
`$wpdb->prepare()` on every query with variables, and `sanitize_*` on all input. Large single-file plugins
(Backlink Pilot ≈ 438 KB, QA Contact Form ≈ 262 KB, Content Cannibalization Scanner ≈ 245 KB)
should be split into `includes/` classes at the same time.
