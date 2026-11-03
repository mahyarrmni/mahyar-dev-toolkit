# Migration from the old `python-bots/` folder

| Old file | New location |
|---|---|
| `seo_analyzor.py` | `seo/seo_analyzer/` (package; run `seo/seo_analyzer/app.py`) |
| `ai_web_vulnerability_scanner.py` | `security/ai_web_vulnerability_scanner.py` |
| `deep_scanner.py` | `security/deep_scanner.py` |
| `crypto_analyzer.py` | `ai-apps/crypto_analyzer.py` |
| `decor_ai_image.py` | `ai-apps/decor_ai_image.py` |
| `decor_ai_chat.py` | `ai-apps/decor_ai_chat.py` |
| `decor_ai_llm.py` | `ai-apps/decor_ai_llm.py` |
| `image_optimizer.py` | `utilities/image_optimizer.py` |
| `auto_image_downloader.py` | `utilities/auto_image_downloader.py` |

## New in 1.1.0

`seo_audit_bot`, `keyword_miner` → `seo/` · `maps_leads_bot`, `b2b_lead_intel` → `leads/` · `filesage` → `utilities/`.

## Not carried over

These scripts automate search-engine interaction or posting on third-party sites (simulated search clicks,
ping/link-submission bursts, bulk searches through proxies, automated comment posting). They were left out of the
public repository on purpose — see [RESPONSIBLE_USE.md](RESPONSIBLE_USE.md) — and should be archived
or kept private:

`google_scroller.py`, `streamlity_ctr.py`, `backlinks_bomber.py`, `google_proxy_search.py`,
`wp_comment_bot.py`, `comments_all.py`

If any of them is still in your git history, it also contains a hard-coded UI password
(`backlinks_bomber.py`). Treat that password as public and do not reuse it.

## Environment variables

Keys that used to be edited inside the source are now environment variables — see `.env.example`.

## Repository names

Rename the old repositories to fix typos before linking to them from your profile:
`backlinckbomber`, `commenswp`, `seoanalyzor`, `streamlity` (the first, second and fourth are
tied to the tools that were not carried over).
