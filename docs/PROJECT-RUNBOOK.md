# Project runbook (living)

This is the **working checklist** for day-to-day delivery: what is done, what is next, and facts we must not forget. It complements **BRIEF.md** (product rules) and **STATUS.md** (canonical platform snapshot). When they disagree, **BRIEF wins on intent** and **STATUS wins on live numbers** until someone updates STATUS after a verified change.

**Roles:** The **director** owns business goals. The **project manager** is the lead agent (planning, execution, quality). **Sub-agents** are delegated parallel work (code audit, browser sessions, debugging) when that is faster or deeper than a single thread.

**How to use:** After meaningful work or a live check, update the **Last live verification** block and adjust **Recently completed** / **Open work** so the director and agents share one picture.

---

## North star (from the brief)

Ship and grow a **paid** property-and-land alert service for Northwest England where **speed and relevance** justify Pro and VIP. Every change should answer: *does this get us closer to reliable alerts and paying subscribers?*

**v1 launch focus:** **Portals + auctions + alert pipeline + subscriber UX + billing** are in scope first. **Planning / EPC / Land Registry** are positioned as a **later intelligence product** (Land Insight-style); **new plugin installs** default those feeds **off** in `lpnw_settings` until you turn them on in **LPNW Alerts → Settings**. Existing live sites are **not** auto-changed.

**Parallel work:** Keep **functionality QA** (see `docs/VERIFICATION-BATCHES.md`) separate from **visual / mobile-first** theme work so two agents do not fight the same files.

---

## Canonical references

| Document | Role |
|----------|------|
| `BRIEF.md` | Product, USP, build order, tech stack (do not change stack) |
| `STATUS.md` | Live data snapshot, infrastructure, owner actions |
| `docs/DEPLOYMENT.md` | FTP paths and release steps |
| `docs/DISCOVERY-BACKLOG.md` | Read-only audit: full backlog of gaps and improvements (synthesised from code review) |
| `docs/VISUAL-AUDIT.md` | Live **UI/UX and artistic direction** findings from browser review (test user); update after each visual pass |
| `docs/VERIFICATION-BATCHES.md` | **Batched live checks** (cron, feeds, prefs, alerts, off-market); login-as script URLs for VM/browser agents |
| `docs/SETUP.md` | Hosting and plugin setup (may lag live; cross-check STATUS) |
| `.cursor/rules/secrets.mdc` | Where credentials are named (not values) |

**Live site:** https://land-property-northwest.co.uk  
**Marketing / Mautic host:** https://marketing.land-property-northwest.co.uk  

**Transactional addresses (plugin, wp_mail):** Alerts **From** **`alerts@<your-domain>`**. Contact form: **delivered to** **`admin@<your-domain>`**, **From** **`hello@<your-domain>`** (visitor on **Reply-To**). Create those mailboxes or forwarders on the host and align **SPF/DKIM**. **WordPress admin email** can stay separate (password resets, etc.). Override with filters `lpnw_alert_mail_from_email`, `lpnw_contact_mail_from_email`, `lpnw_admin_notify_mail_to_email`, `lpnw_mail_from_name`, or local-part filters in `class-lpnw-email-branding.php`.

**20i CDN / full-page cache:** Deploys and theme/CSS changes may not show until the edge cache refreshes. For a **reliable live check**, either **log in to wp-admin** (often bypasses or varies cache) or load the site with **`/?nocache`** (and add paths as needed, e.g. `https://land-property-northwest.co.uk/?nocache`). Use the same after FTP deploy when verifying hero or stylesheet updates.

**Mautic alert templates:** On send, the plugin posts `tokens` including `{lpnw_subscriber_first_name}`, `{lpnw_alert_count}`, `{lpnw_tier}`, `{lpnw_properties_html}` (HTML summary). Use the same token names in Mautic email content. Filter `lpnw_mautic_alert_email_tokens` to extend.

---

## How we test (depth and parallelism)

**Shallow checks** (curl status codes, public REST) catch outages only. **Deep dives** need many probes in parallel: key URLs, forms, logged-in flows, WooCommerce paths, feed admin screens, mobile breakpoints, console errors, and cross-links. That is best done with **several agents or browser sessions at once**, each owning a slice (e.g. one on commerce, one on subscriber UX, one on plugin admin, one on SEO/schema). I will use that pattern whenever you want maximum coverage quickly.

**Visual review (VM browser):** Agents can drive a **real browser**, log in with **`/?nocache=1&lpnw_login_as=test&key=...`** where **`key`** equals **`LPNW_LOGIN_AS_SECRET`** from `wp-config.php` (see `docs/DEPLOYMENT.md` and `mu-plugins/lpnw-login-as.php`). The script **self-deletes after one use**; redeploy the file if you need it again. **Recordings** (`RecordScreen`) are optional; **delete the `.mp4` after** writing findings to **`docs/VISUAL-AUDIT.md`** to save disk space.

**GitHub vs live:** This repo is the **source of truth** for **plugin and theme code**. Pushing to GitHub means you can redeploy or rebuild the custom product after a host failure. A full site restore also needs **WordPress database, uploads, and wp-config** (use **UpdraftPlus** or 20i backups on a schedule). FTP deploy only replaces the two custom folders.

**Live deploy:** After commits are on GitHub, deploy plugin + theme with `./tools/deploy-ftp.sh` (or manual FTP per `docs/DEPLOYMENT.md`). **Verify on live** with **`/?nocache`** or while **logged in as admin** so 20i CDN does not serve a stale HTML/CSS snapshot.

**If portals block the server:** The product is built to use **many sources** (Rightmove, OnTheMarket, Zoopla, auctions, planning, EPC, Land Registry). If one portal blocks scraping, **others keep feeding** the index; watch **LPNW Alerts → Feed Status**. Longer term: **official feed or data licence**, **partner API**, or a **dedicated egress IP / proxy** agreed with the portal (legal and commercial, not a code flip). Keep **EPC API key** and **non-portal feeds** enabled to widen coverage.

**Cloudflare / bot blocks (e.g. Zoopla):** Workarounds that sometimes help: **residential or ISP-class proxy** with stable IP and strict rate limits; **data partnership** or licensed feed; **server IP allowlisting** if the vendor offers it. There is no guaranteed code-only bypass; rotating user-agents alone is fragile. Prefer **more sources** (OTM, auctions, planning, EPC) so one block does not empty the product.

**EPC Open Data:** Register at https://epc.opendatacommunities.org/ — you receive an **email + API key** used as HTTP Basic auth. Paste both into **LPNW Alerts → Settings** (EPC email + EPC API key). The agent cannot create accounts in your name; once credentials exist in WP settings or env, feeds can be verified from **Feed Status**.

---

## WordPress admin on 20i (login workaround)

20i can block **normal** `wp-login.php` access for automated or scripted use. The repo ships **temporary** PHP helpers that log a user in by setting auth cookies on `init`, then redirect (they are **not** a replacement for proper security).

| Script (in repo) | Purpose |
|------------------|---------|
| `tools/lpnw-autologin.php` | One-shot login as **first administrator** (by user ID), redirect to **wp-admin**, then **deletes itself**. |
| `mu-plugins/lpnw-login-as.php` | One-shot login as **admin** or **test** subscriber (`admin@codevall.co.uk` → dashboard). Requires **`LPNW_LOGIN_AS_SECRET`** in `wp-config.php`; **`key`** on the URL must match. **Deletes itself** after one successful login. |

**Typical use:** Define **`LPNW_LOGIN_AS_SECRET`** in `wp-config.php`, upload the file to `wp-content/mu-plugins/` if it is not already there, hit the URL once with `lpnw_login_as` / `lpnw_autologin` and matching **`key`**, then confirm the file removed itself (or delete it manually). **Remove the mu-plugin from the server when you do not need it**; the next full deploy may upload it again from the repo.

Authenticated API checks (e.g. custom endpoints) are an alternative once a session or application password exists; the script path matches what you described for admin visibility under 20i.

---

## Last live verification

### 4 April 2026 (repo parity: FTP + public smoke)

**Checked:** `tools/deploy-ftp.ps1` from repo **main** to 20i: **plugin** `lpnw-property-alerts` (**1.0.32** in repo header), **theme** `lpnw-theme` (**6.12.1**), **mu-plugins** (`lpnw-cron-endpoint.php`, `lpnw-login-as.php`, and other `mu-plugins/*.php` in repo). **Git:** `origin/main` only (stale `cursor/*` remote branches absent).

| Check | Result |
|--------|--------|
| Homepage, `/properties/`, `/pricing/`, `/dashboard/` with `?nocache=1` | HTTP **200** |
| `/wp-json/` | HTTP **200** |
| Live theme header | `wp-content/themes/lpnw-theme/style.css` → **Version: 6.12.1** (matches repo) |
| Live plugin header over HTTP | `lpnw-property-alerts.php` returns **200** with **empty body** (typical hardening); confirm **Plugins** screen on next wp-admin visit |
| `?nocache=1&lpnw_update=pages` (anonymous) | **403** on `/pricing/` — use **logged-in admin** or `key` per `docs/DEPLOYMENT.md` |

**Composer lint:** `composer lint` runs via **`@php vendor/bin/phpcs`** (see `composer.json`); PHPCS still reports many existing violations (exit code 2 is expected until cleaned up).

**Not re-checked this pass:** Feed Status row counts, Mautic row, or full wp-admin screens (use 2 Apr snapshot below until next deep login).

---

### 4 April 2026 (wp-admin pass, after browser login)

| Area | Finding |
|------|---------|
| **LPNW Overview** | **5,132** properties; **164** in last 24h; **2** active subscribers; **2** with preferences (**2** free / **0** pro / **0** vip); **0** sent today; **3,157** all-time sent; **141** queued |
| **LPNW Settings** | **Mautic** URL + app user set; **VIP / Pro / Free** email ID fields filled; **EPC** email + API key fields **filled** |
| **Admin nags** | **Redirection** setup; **Wordfence** prompts; **Cookie Notice** upsell; **Rank Math No Index**; Woo **coming soon** (as before) |
| **Security (repo same day)** | **`lpnw-login-as.php`**: no hard-coded key; requires **`LPNW_LOGIN_AS_SECRET`** in `wp-config.php`; **`hash_equals`** for key; **self-deletes** after one successful login (`tools/lpnw-autologin.php` aligned). See `docs/DEPLOYMENT.md`. |

---

### 2 April 2026 (public smoke + wp-admin read-only)

**Checked:** Public smoke tests plus **wp-admin read-only** session (one-shot `tools/lpnw-autologin.php` uploaded to mu-plugins, used once, **confirmed removed** from server).

#### Public

| Check | Result |
|--------|--------|
| Homepage, `/properties/`, `/pricing/`, `/dashboard/` | HTTP 200 |
| `/wp-json/` | OK; `Europe/London` |

#### wp-admin (verified that day; versions superseded on disk by 4 Apr deploy)

| Area | Finding |
|------|---------|
| **LPNW dashboard** | **3,465** properties tracked; **1,696** in last 24h; **1** active subscriber; **318** alerts sent today; **349** all-time sent; **668** queued |
| **Mautic** | Status row: **Connected (HTTP 200)** |
| **Cron** | Next runs listed (portals, planning, EPC, land registry, auctions, dispatch, free digest); `DISABLE_WP_CRON` not set |
| **Feed Status** | All listed feeds **0 failed runs**; **Zoopla** cumulative new **0**; **Allsop** **0**; Rightmove / OTM / others show healthy activity |
| **LPNW Settings** | EPC email + API key fields **empty**; Mautic URL set; **VIP/Pro/Free Mautic email IDs** auto-filled from API when templates named *LPNW Alert — VIP/Pro* and *LPNW Weekly Digest — Free* exist (plugin 1.0.13+) |
| **Alert log** | **1,017** total rows (sample); many recent rows **Queued** for PRO |
| **Plugins** | At time of check, Plugins list showed **LPNW 1.0.0** (WordPress reads the plugin header; **4 Apr 2026** deploy pushed repo **1.0.32** — re-check list) |
| **Themes** | At time of check: **LPNW Theme 6.0.0** — **4 Apr:** live `style.css` is **6.12.1** |
| **Products** | **3** published WooCommerce products |
| **Admin notices** | Rank Math: **No Index**; WooCommerce bar: **Store coming soon**; Redirection setup incomplete; Wordfence incomplete / Woo LS integration nag; Cookie Notice upsell |

**Still not exercised (either pass):** Checkout payment with a real card, subscriber-only pages as a non-admin test user, or reading raw server logs.

---

## Done (high level — see STATUS for numbers)

- Multi-source ingest (Rightmove, OnTheMarket, auction feeds, etc.) with browse, map, dashboard, preferences, tiering from WooCommerce orders, Stripe via WooCommerce, Mautic connected, SEO/content surface as described in STATUS.
- Repo dev tooling: PHP/Composer path documented in recent work; `composer.lock` present for PHPCS/WPCS.

---

## Open work (prioritised for the PM loop)

### Revenue and reliability first

1. **Scheduled tasks (20i / 401):** Direct hits to **`wp-cron.php`** from some external pingers get **401** from the host WAF. **Preferred:** ping **`https://YOURSITE/?lpnw_cron=tick&key=SECRET`** every **15 minutes** (same as portal interval) after defining **`LPNW_CRON_SECRET`** in `wp-config.php`. **Fallback:** traffic still triggers `spawn_cron()` at most **once per 900s** via `LPNW_Traffic_Cron` when `DISABLE_WP_CRON` is not true. **If you need clock reliability:** ask **20i support** to allowlist one caller IP or one URL path for cron.

**Theme 6.4+:** Hero uses **three full-width SVG layers** + scroll parallax (`--lpnw-parallax-p`); no canvas loops.
2. **Zoopla:** Code present; live ingestion blocked by Cloudflare from hosting—needs approved approach if that source must contribute.
3. **EPC:** Credentials present in **LPNW Alerts → Settings** (4 Apr 2026); keep monitoring **Feed Status** for successful runs and rotate the API key if it was ever exposed.

### Product / ops hygiene

4. **Operations:** Monitor **LPNW Alerts → Feed Status** and logs in wp-admin regularly.
5. **Alert queue vs send:** **~141 queued** observed 4 Apr 2026 — confirm dispatch cadence, Mautic deliverability, and whether **wp_mail** fallback is acceptable when Mautic is slow.
6. **Launch toggles:** Clear **No Index** and **WooCommerce coming soon** when you intend to sell and rank.
7. **Docs alignment:** Reconcile `docs/SETUP.md` (e.g. WPForms, wp-cron URL) with **STATUS.md** and live behaviour so operators are not misled.

### Repo quality (from codebase review — verify before closing)

8. **Plugin:** Ensure `uninstall.php` clears **all** scheduled hooks including portal cron if present (consistency with activator/deactivator).
9. **Theme:** Fix login inline CSS structure if broken. **Scroll:** JS toggles `.site-header.scrolled` (aligned with CSS).

### VIP / brief promises (confirm in code and copy)

10. **VIP tier:** Brief mentions priority timing, off-market, introductions—map each to implemented behaviour and close any gaps or soften marketing copy.

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
| 2026-04-04 | **Security + docs:** `lpnw-login-as.php` / `lpnw-autologin.php` require **`LPNW_LOGIN_AS_SECRET`**, timing-safe compare, self-delete; DEPLOYMENT + secrets.mdc + STATUS + runbook + verification docs updated; wp-admin audit (numbers + settings) |
| 2026-04-04 | **FTP deploy** from repo main (plugin 1.0.32, theme 6.12.1, mu-plugins); public `?nocache` smoke + live theme header check; `composer.json` lint script uses `vendor/bin/phpcs`; STATUS + runbook versions aligned |
| 2026-04-02 | **wp-admin read-only audit** (autologin script upload once, removed); STATUS + runbook updated with live numbers and notices |
| 2026-04-02 | Runbook created; live smoke check home/properties/pricing/dashboard + REST index |
| 2026-04-02 | `composer.lock` added on branch for reproducible PHPCS/WPCS installs (see git history) |

*(Append new rows upward or downward—pick one convention; current table is newest first.)*
