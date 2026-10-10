# Security Policy

Thank you for helping keep CVolve Template Maker and its users safe. 🔒

## Supported versions

CVolve Template Maker is maintained by a single person, so security fixes are made only for the **latest version on the `main` branch**. Please update to the latest `main` before reporting.

| Version | Supported |
|---|---|
| Latest `main` | ✅ |
| Older commits and forks | ❌ |

## Reporting a vulnerability

**Please do not open a public issue, discussion or pull request for security problems.**

Report privately in one of these ways:

1. **Preferred:** GitHub's [private vulnerability reporting](https://github.com/future-site-ai/cvolve_template_maker/security/advisories/new) (the **Security** tab → **Report a vulnerability**).
2. Email **contact@futuresite.it** with the subject `[SECURITY] CVolve Template Maker`.

Please include:

- A description of the problem and its impact
- Steps to reproduce, or a proof of concept
- The affected version or commit, and your setup (Docker or local)
- Any ideas you have for a fix

## What to expect

The project is maintained in spare time by one person, so please be patient:

- **Acknowledgement:** within **7 days**.
- **First assessment:** within **14 days**, to confirm whether it is a vulnerability and how serious it is.
- **Fix:** as soon as reasonably possible, depending on severity and complexity. Critical issues, especially anything that exposes stored API keys, come first.

I will keep you updated, and once a fix is released I will publish a security advisory. **You will be credited** in it, unless you prefer to stay anonymous.

Please give a reasonable time to fix the issue before any public disclosure. If I do not reply within 14 days, feel free to send a reminder.

## Scope

**In scope:** the code in this repository, including:

- **Designers' stored AI API keys**: reading, leaking or decrypting them, or showing them in logs, pages or API responses
- Authentication, sessions and sign-up
- Access control between designers (one designer seeing or changing another designer's projects, assets or AI connections)
- File and font uploads, and template imports (zips, HTML, JSON Resume themes)
- Requests the server makes to AI endpoints and Gotenberg (for example, server-side request forgery through a custom base URL)

**Report to CVolve instead:** problems in the template sandbox, validator, renderer or importers live in CVolve's shared template kit. Report them through [CVolve's security policy](https://github.com/future-site-ai/cvolve/security/policy). If you are not sure, report here and I will move it.

**Out of scope:**

- Problems that need an attacker who already has admin access or access to the server
- The default designer account and example secrets (they are documented, and must be changed before going live)
- Self-hosted instances that are misconfigured or not updated
- Vulnerabilities in third-party dependencies with no demonstrated impact on the maker (please report them upstream)
- Behaviour of external AI providers, and AI output that is wrong but causes no security impact
- Denial of service through very high traffic, automated scanner output without a proof of concept, and missing security headers alone

## Running the maker safely

If you self-host CVolve Template Maker:

- Set a long, random `MAKER_ENCRYPTION_KEY` and **never change it** (stored keys become unreadable), and keep it secret.
- Change the default designer account (`MAKER_ADMIN_*`).
- Set `MAKER_OPEN_REGISTRATION=0` if you do not want strangers to sign up.
- Serve it over **HTTPS** only, and keep it updated to the latest `main`.
- Never commit `.env.local` or `.env.docker` with real keys.

Thank you for reporting responsibly! 💚
