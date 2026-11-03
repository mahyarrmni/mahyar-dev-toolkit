# Responsible use & disclaimer

[فارسی](RESPONSIBLE_USE.fa.md)

The tools in this repository send HTTP requests, open sockets, collect contact data, call paid APIs and move files on your behalf.
**You alone are responsible for how you use them.**

## Rules

1. **Authorization first.** Run the scanners in `security/` only against systems you own or have *written* permission to test.
   Unauthorized scanning, probing or payload submission can be a criminal offence in your jurisdiction. The authorization checkbox in the apps is a
   reminder, not a legal shield.
2. **Stay in scope.** Permission for one hostname is not permission for its neighbours. The port scanner connects to the resolved IP,
   which may be shared hosting.
3. **Be gentle.** Respect `robots.txt`, rate limits and the target's capacity. Keep `--workers`/`--delay` conservative.
4. **Third-party terms.** Follow the terms of every service you call (Google, CoinGecko, NewsAPI, Replicate, Unsplash, DuckDuckGo …)
   and the license of every image or data set you download.
5. **Personal data.** Lead tools (`leads/`) produce contact data. Collect only business contact details organisations publish themselves,
   have a lawful basis (consent / legitimate interest), honour opt-outs, store data securely, delete it when no longer needed.
   Bulk unsolicited e-mail/SMS/calls is illegal or restricted in many countries (GDPR/ePrivacy, CAN-SPAM, local e-commerce and telecom law).
6. **No manipulation.** Do not use this code to inflate traffic, fake engagement or reviews, simulate search clicks, or spam sites with
   automated comments, pings or links. Search engines treat that as spam, it harms real site owners and users, and it is outside what this project supports.
7. **Handle findings responsibly.** If you discover a vulnerability on a system that is not yours, report it privately to the owner
   (look for `/.well-known/security.txt`); do not exploit or publish it.
8. **Back up first.** Tools that move or modify files (`utilities/filesage`) are designed to be reversible, but backups are your job.

## Risk matrix

| Tool | Level | Main risks | Mitigation |
|---|:-:|---|---|
| SEO Analyzer · SEO Audit Bot | 🟢 | Load on the audited site; Audit Bot has no robots.txt check or delay | Audit your own sites, low `--workers` |
| Keyword Miner | 🟡 | Unofficial suggestion endpoints; possible ToS conflict, blocking | Low request rate, research use only |
| Maps Leads Bot | 🟡 | API cost; Google Maps Platform terms; marketing/privacy law for the output | API key restriction, budget alert, lawful outreach |
| B2B Lead Intelligence | 🟡 | Scraping terms; personal-data law; `--ignore-robots` | Keep defaults, document lawful basis |
| Auto Image Downloader | 🟡 | Scrapes Unsplash HTML; photo licenses | Respect licenses/attribution; prefer official API |
| Decor AI – Image | 🟡 | Paid per run; uploaded photos go to Replicate | Mind privacy of photos |
| FileSage · Image Optimizer · Crypto Analyzer · Decor chat | 🟢 | Moves/overwrites files (FileSage); financial misuse (Crypto) | Dry-run & backups; not financial advice |
| AI Web Vulnerability Scanner · Deep Scanner | 🔴 | Unauthorized scanning is illegal; false positives/negatives | Written authorization only; staging systems |

The WordPress plugins (separate repositories) carry their own risk notes; the highest-risk ones are the automated link-publishing and
synthetic-comment plugins, which can trigger search-engine penalties, violate platform terms and, in the case of fabricated comments or reviews,
consumer-protection law.

## Tools deliberately not published here

Scripts that simulate search-engine clicks (CTR bots), blast URLs to ping/shortener services, run bulk searches through proxies, or post comments
automatically on third-party sites were left out on purpose. A disclaimer cannot make fake traffic or spam acceptable, and they can get
the sites they target — and the people using them — penalised or sued. If they remain in an old repository or in git history, keep that
repository private or archive it, and treat any password committed there as public.

## Reporting problems

Bugs: open a GitHub issue. Security-sensitive reports: see [SECURITY.md](../SECURITY.md).

## ⚠️ Disclaimer

This software is provided **"as is"**, without warranty of any kind. **You alone are responsible for how you use it.**
That includes compliance with the laws of your country, the terms of service of every third-party platform or API
involved, and privacy/anti-spam regulations (GDPR, CAN-SPAM, local telecom and e-commerce laws, etc.).
The author (Mahyar Momeni) and contributors accept **no liability** for any damage, data loss, account suspension,
search-engine penalty, legal claim or fine arising from its use or misuse.
Use it only on systems, sites and data you own or are explicitly authorised to work with.

Scan results can contain false positives and false negatives and are **not** a substitute for a professional security assessment.
