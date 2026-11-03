# Security policy

## Reporting a vulnerability

Please **do not open a public issue** for security problems in this code. Email
[mahyarmni@gmail.com](mailto:mahyarmni@gmail.com) with: affected file/tool, steps to reproduce, impact, and (optionally) a suggested fix.
You will get an acknowledgement as soon as possible; please allow reasonable time for a fix before disclosing publicly.

## Scope

In scope: code in this repository (tools, scripts, CI). Out of scope: vulnerabilities in third-party services the tools call, and results of scanning
systems you do not own.

## Handling secrets

- Never commit API keys, tokens or passwords. Use environment variables (`.env.example` lists them).
- Tool outputs (`*.csv`, `*.db`, reports) may contain business contact data or client information and are git-ignored by default.
- If you ever committed a secret, rotate it immediately — deleting the file does not remove it from git history.
