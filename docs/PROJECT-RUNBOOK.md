# Project runbook (living)

This is the **working checklist** for day-to-day delivery: what is done, what is next, and facts we must not forget. It complements **BRIEF.md** (product rules) and **STATUS.md** (canonical platform snapshot). When they disagree, **BRIEF wins on intent** and **STATUS wins on live numbers** until someone updates STATUS after a verified change.

**How to use:** After meaningful work or a live check, update the **Last live verification** block and adjust **Recently completed** / **Open work** so the director and agents share one picture.

---

## North star (from the brief)

Ship and grow a **paid** property-and-land alert service for Northwest England where **speed and relevance** justify Pro and VIP. Every change should answer: *does this get us closer to reliable alerts and paying subscribers?*

---

## Canonical references

| Document | Role |
|----------|------|
| `BRIEF.md` | Product, USP, build order, tech stack (do not change stack) |
| `STATUS.md` | Live data snapshot, infrastructure, owner actions |
| `docs/DEPLOYMENT.md` | FTP paths and release steps |
| `docs/SETUP.md` | Hosting and plugin setup (may lag live; cross-check STATUS) |
| `.cursor/rules/secrets.mdc` | Where credentials are named (not values) |

**Live site:** https://land-property-northwest.co.uk  
**Marketing / Mautic host:** https://marketing.land-property-northwest.co.uk  

---

## Last live verification

**Checked:** 2 April 2026 (automated smoke checks from agent environment)

| Check | Result |
|--------|--------|
| Homepage | HTTP 200 |
| `/properties/` | HTTP 200 |
| `/pricing/` | HTTP 200 |
| `/dashboard/` | HTTP 200 (may be login-gated for anonymous users; still a valid response) |
| WordPress REST root `/wp-json/` | Available; site title and timezone match expectations (`Europe/London`) |

**Observed on REST index (live):** Namespaces include **WooCommerce** (`wc/v3`, store, analytics), **Wordfence**, **Redirection**, **Jetpack**, **GeneratePress**. The repo brief lists a core set of plugins; production may include additional plugins—treat this as *live truth* for integrations, not only what SETUP.md lists.

**Not verified in this pass:** Logged-in dashboard behaviour, checkout, actual email send, feed row counts, or deployed plugin/theme version strings (would need wp-admin, authenticated API, or server files).

---

## Done (high level — see STATUS for numbers)

- Multi-source ingest (Rightmove, OnTheMarket, auction feeds, etc.) with browse, map, dashboard, preferences, tiering from WooCommerce orders, Stripe via WooCommerce, Mautic connected, SEO/content surface as described in STATUS.
- Repo dev tooling: PHP/Composer path documented in recent work; `composer.lock` present for PHPCS/WPCS.

---

## Open work (prioritised for the PM loop)

### Revenue and reliability first

1. **Scheduled tasks:** External HTTP cron reportedly blocked by 20i WAF; feeds and dispatch depend on cron firing. Resolve with an allowed provider, whitelist, or host-approved method so schedules run without relying only on traffic.
2. **Zoopla:** Code present; live ingestion blocked by Cloudflare from hosting—needs approved approach if that source must contribute.
3. **EPC:** Pipeline exists; needs **EPC Open Data API key** in plugin settings for live pulls.

### Product / ops hygiene

4. **Operations:** Monitor **LPNW Alerts → Feed Status** and logs in wp-admin regularly.
5. **Docs alignment:** Reconcile `docs/SETUP.md` (e.g. WPForms, wp-cron URL) with **STATUS.md** and live behaviour so operators are not misled.

### Repo quality (from codebase review — verify before closing)

6. **Plugin:** Ensure `uninstall.php` clears **all** scheduled hooks including portal cron if present (consistency with activator/deactivator).
7. **Theme:** Fix login inline CSS structure if broken; align scroll class (`lpnw-scrolled` vs `.site-header.scrolled`) and animation classes with actual CSS so intended motion works.

### VIP / brief promises (confirm in code and copy)

8. **VIP tier:** Brief mentions priority timing, off-market, introductions—map each to implemented behaviour and close any gaps or soften marketing copy.

---

## How agents should update this file

1. After **deploying** or confirming a change on live, update **STATUS.md** if facts (counts, infra, owner actions) changed.
2. Move items from **Open work** to **Done** only when verified (code + live or strong evidence).
3. Append one line under **Last live verification** when re-checking the site (date + what was checked).
4. Keep bullets **plain English**; link paths, not secrets.

---

## Completed log (chronological, lightweight)

| Date | Item |
|------|------|
| 2026-04-02 | Runbook created; live smoke check home/properties/pricing/dashboard + REST index |
| 2026-04-02 | `composer.lock` added on branch for reproducible PHPCS/WPCS installs (see git history) |

*(Append new rows upward or downward—pick one convention; current table is newest first.)*
