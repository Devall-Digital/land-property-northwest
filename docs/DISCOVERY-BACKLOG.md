# Discovery backlog (read-only audit)

**Purpose:** Single inventory of outstanding work, suspected bugs, and improvement opportunities from a multi-agent code and documentation review. **No fixes applied in this pass.**

**Sources:** Parallel exploration of `plugin/`, `theme/`, `docs/`, `tools/`, `mu-plugins/`, cross-checked with `BRIEF.md`, `STATUS.md`, `PROJECT-RUNBOOK.md`, and prior live wp-admin verification (2 Apr 2026).

**How to use:** Treat items as a candidate backlog; validate each before implementation (especially security and product promises).

---

## Executive summary

| Theme | Headline |
|-------|----------|
| **Product truth** | VIP **30-minute head start** and **direct introductions** are marketed but **not implemented** in the alert pipeline; Mautic sends **do not pass listing content** the way wp_mail does. |
| **Reliability** | Feeds are fragile by nature (scraping, Cloudflare, timeouts); several **admin manual-run** gaps and a likely **broken Settings “Run feed” form** (nonce/field mismatch). |
| **Scale** | Matcher runs on **every upsert** (updates too), **O(properties × subscribers)** with repeated tier lookups; queue dedup is **not enforced in DB**. |
| **Theme / UX** | Known bugs: **scroll class vs CSS**, **broken wp-login inline CSS**, **dead animation / parallax** paths; possible **JSON-LD duplication** with Rank Math. |
| **Docs / ops** | `SETUP.md`, `DATA-SOURCES.md`, and `README.md` **lag** portals, auctions, cron URL, and contact form story; cron URL supports optional secret via **`LPNW_CRON_SECRET`** (define in wp-config on live). |
| **Security (defer per director)** | Shared static keys across tools; **`?lpnw_cron=tick`** is open until `LPNW_CRON_SECRET` is set; `lpnw-login-as.php` **does not self-delete** despite comments/runbook. |

---

## P0 — Revenue, correctness, or operator-blocking

1. **Settings manual feed form likely broken** — `admin/views/settings.php` uses nonce action `lpnw_manual_feed` and field `feed_name`; `handle_manual_feed_run()` expects `check_admin_referer( 'lpnw_run_feed' )` and `$_POST['feed']`. Dashboard manual run uses the correct nonce. **Impact:** “Run Feed Now” from Settings may always fail or fall through incorrectly.
2. **Settings dropdown vs feed map mismatch** — Form offers aggregate `auctions`; `$manual_feed_class_map` has per-source keys (`rightmove`, `zoopla`, `onthemarket`, `planning`, `epc`, `landregistry`) not `auctions`. **Impact:** wrong or default feed when fixed.
3. **Mautic path omits property payload** — `LPNW_Mautic::send_alert()` triggers Mautic email send **without** passing `$properties` (unused). **Impact:** tiered emails via Mautic may not contain listing details unless templates pull from elsewhere.
4. **VIP “30 minutes before Pro” not implemented** — Dispatch runs `process_tier('vip')` then `process_tier('pro')` in the **same** cron tick; no `scheduled_at` / delay. **Impact:** marketing and `BRIEF.md` overstate behaviour.
5. **Cron trigger URL** — **Partial fix (1.0.6):** if `LPNW_CRON_SECRET` is defined in `wp-config.php`, `?lpnw_cron=tick` requires matching `&key=`. Without the constant, endpoint remains open (legacy). **Impact until secret set:** abuse / load risk.
6. **Large alert queue vs template IDs** — Live observation: **many queued** alerts while Mautic email IDs were **empty**; dispatcher favours Mautic when configured. **Impact:** risk of **stuck or wp_mail-only** behaviour until templates and send path are verified end-to-end.

---

## P1 — High priority improvements

### Tiering and subscriptions

7. **`get_tier()` from last 10 orders** — Slug substring `vip` / `pro`; no refunds/cancelled handling; **expired buyers may still read as paid**. No WooCommerce Subscriptions integration despite optional future in BRIEF.
8. **Tier frozen at enqueue** — `lpnw_alert_queue` stores tier at match time; upgrades/downgrades before send use **stale** tier for frequency and priority.
9. **VIP frequency vs preferences** — `get_effective_alert_frequency()` **forces instant for VIP**; preferences may allow daily/weekly for VIP → **inconsistent UX**.

### Matching and queue

10. **Match on every upsert** — `LPNW_Feed_Base::run()` passes **all** successful upsert IDs to `match_and_queue()`, including updates → extra DB/WC work; dedup prevents duplicate queue rows but not the work.
11. **No unique DB constraint** on `(subscriber_id, property_id)` for queue — race could duplicate rows under concurrency.
12. **`mautic_email_id` column** unused in dispatcher — audit trail incomplete.
13. **Rent vs price filters** — `matches_price()` may **skip min/max for rent** (`application_type === 'rent'`); users may get unexpected matches or noise.

### Feeds (reliability)

14. **Zoopla / Cloudflare** — Cursor can advance on empty fetch → **skipped listings** until a later cycle.
15. **Rightmove / OTM** — 403/429 handling, time budgets, JSON drift; partial batches under timeout.
16. **Planning** — Many LPAs + delays → **host timeout** risk on shared hosting.
17. **EPC** — No credentials → empty runs; 429 truncates prefix for the run.
18. **Land Registry** — Long downloads; HEAD/GET fragility.
19. **Auction scrapes** — DOM/URL drift; Allsop **0 new** on live stats (monitor).
20. **`sleep(2)` between portal feeds** — Extends wall time on cron.

### Admin / product surface

21. **No UI to manually run Zoopla / OnTheMarket** — Map includes them; forms do not expose all keys.
22. **`portals_enabled` not in registered settings** — Cron reads it; Settings UI does not define/sanitize it → **no supported toggle**.

### Public / abuse

23. **`lpnw_map_properties` is nopriv** — Up to **500 markers** per request (with nonce). Confirm acceptable data exposure.
24. **`ajax_load_properties` (logged-in)** — Broad filters without same allowlist discipline as search GET handler.

### Theme

25. **Scroll class bug** — JS sets `lpnw-scrolled` on `body`; CSS targets `.site-header.scrolled`.
26. **Login CSS broken** — Orphan rules after `#login h1 a` block in `functions.php`.
27. **Version drift** — Repo `style.css` **2.0.0** vs live **6.0.0** until deploy aligned.

### Documentation

28. **`docs/DATA-SOURCES.md`** — Auction URLs still “TBD / Phase 3” while feeds exist.
29. **`docs/SETUP.md`** — WPForms vs native contact; `wp-cron.php` vs `?lpnw_cron=tick`; Subscriptions vs BRIEF “simple products”.
30. **`README.md`** — Omits portals as primary data sources.

### Tools / mu-plugins truth

31. **`lpnw-login-as.php`** — Header/runbook say **self-delete**; **no `unlink`** in file.
32. **Single shared key** (`lpnw2026setup`) across many scripts — one leak affects all.
33. **Destructive GET tools** — e.g. cache purge / tier-test scripts; ensure never left on production without rotation.

---

## P2 — Medium (quality, SEO, maintainability)

34. **BRIEF vs `off_market` alert type** — Product adds `off_market`; BRIEF lists five types only; clarify spec or update BRIEF.
35. **Direct introductions** — Not implemented (copy only).
36. **“Monthly report” for VIP** — Appears in page content; not in BRIEF line item; no automated feature found.
37. **Data retention** — Orphan queue rows if `property_id` invalid; **feed log never pruned** by retention job.
38. **`get_new_since()`** — Appears unused in feed pipeline (possible dead code or missed optimisation).
39. **JSON-LD overlap** — Theme outputs Organization (+ front-page extras); Rank Math also outputs schema → possible duplicates.
40. **Animation / parallax dead code** — `.lpnw-reveal` / `.lpnw-animate` without CSS; hero parallax targets missing markup; duplicate IO logic in inline JS vs `theme.js`.
41. **`LPNW_Mautic` `$segment_map` computed but unused**.
42. **Dispatcher batching** — LIMIT 50 **rows**, then group by user → variable email count per cron run.
43. **Verbose `error_log` in feeds** — Noise, disk, possible sensitive URLs in logs.
44. **`@` silencing** on DOM/file ops — hides parse failures.

### Live / launch hygiene (from wp-admin pass)

45. **Rank Math: No Index** — Conflicts with older “indexing enabled” wording until Reading/Rank Math updated.
46. **WooCommerce “Store coming soon”** — Confirm when going live.
47. **Redirection plugin** — Setup incomplete (admin nag).
48. **Wordfence** — Onboarding incomplete; WooCommerce Login Security integration nag.
49. **Cookie Notice** — Upsell / compliance messaging clutter.

---

## P3 — Lower priority / polish

50. **Uninstall vs `lpnw_cron_portals`** — Repo note: `uninstall.php` may omit clearing portal cron hook (verify against activator).
51. **Preferences coverage estimator** — Synthetic JSON shape may drift from real DB rows.
52. **Google Fonts** — Four weights; consider trim or self-host.
53. **Large monolithic `style.css`** — Harder maintenance; optional split later.
54. **Smooth scroll without `prefers-reduced-motion`** in `theme.js`.
55. **Sticky CTA vs cookie bar / safe-area** — Device testing.
56. **No PHPUnit / CI** — `composer lint` only plugin+theme; no `.github/workflows`.
57. **`DEPLOYMENT.md`** — Does not document `tools/` or `mu-plugins` upload patterns.
58. **Jetpack present on live** — Inactive connection; optional remove if unused (noise only).

---

## Brief vs implementation (explicit gaps)

| BRIEF / marketing | Implementation |
|-------------------|----------------|
| VIP **30 min before Pro** | Same cron batch; seconds apart at best |
| VIP **direct introductions** | **Not in code** |
| Mautic email delivery with rich alerts | Mautic send **does not attach property data** in reviewed path |
| WooCommerce “simple products now” | Tier from **order history**; subscription lifecycle optional later |
| Five `alert_types` | Plus **`off_market`** for VIP |

---

## Suggested workstreams (for later execution)

1. **Truth in marketing** — Update copy or implement 30-minute delay + introductions workflow (CRM/email).
2. **Send pipeline** — Unify Mautic vs wp_mail; pass listing data; clear queue backlog strategy.
3. **Admin fixes** — Settings manual run + portal toggle + full manual feed list.
4. **Tier model** — Subscriptions plugin or stricter “active entitlement” query; refresh tier at dispatch.
5. **Theme pass** — Scroll/login CSS/animation cleanup; schema dedupe; version bump on deploy.
6. **Docs pass** — SETUP, DATA-SOURCES, README, runbook security notes vs `lpnw-login-as` behaviour.
7. **Security hardening (end phase)** — Secret on cron endpoint; rotate keys; remove or gate mu-plugins; fix login-as self-delete or docs.

---

## Changelog

| Date | Note |
|------|------|
| 2026-04-02 | **Shipped (repo):** Match alerts only on **new** property inserts (not every upsert); feed log `properties_new` / `properties_updated` semantics fixed. VIP **daily** preference honoured (weekly coerced to daily). Mautic send passes **tokens** + contact first/last name on create. Settings: **portal feeds** checkbox; sanitize merges with existing options. Uninstall clears **lpnw_cron_portals**. Property card price: tabular nums. |
| 2026-04-02 | **Backlog discipline:** Open items remain in this file until shipped or explicitly cancelled; each agent pass should update the changelog rather than dropping findings. |
| 2026-04-02 | **Shipped:** Post-checkout alert timing tip; `deploy-ftp.sh`; tier detection skips **refunded** / **fully refunded** orders. **Theme 6.1.0:** hero cityscape layers, clouds, parallax JS, content wrapper. |
| 2026-04-02 | **Shipped (Theme 6.1.1):** Live front-page hero uses **SVG + canvas** in DB; theme now styles `.lpnw-hero__scene` / `__canvas` / `__title` / scan line, adds **CSS cloud layer**, **canvas particle loop** in `theme.js`, scroll + mouse parallax on illustration. Removed broken `the_content` hero replacement filter. |
| 2026-04-02 | **Shipped (Theme 6.2.0):** Cinematic hero FX canvas (aurora, stars, heat shimmer, rare lightning), 3D tilt on `.lpnw-hero__scene`, title line stagger + scroll-parallax for `.lpnw-hero__title` / subtitle. |
| 2026-04-02 | **Shipped (Plugin 1.0.6):** `LPNW_Traffic_Cron` rate-limits `spawn_cron()` to **once per 900s** on front-end requests; optional `LPNW_CRON_SECRET` on `?lpnw_cron=tick`. `deploy-ftp.sh` mirrors **mu-plugins**. |
| 2026-04-02 | **Shipped (Theme 6.3.0):** Removed **dual canvas RAF loops** (likely tab crashes). Hero: **CSS-only** clouds on `.lpnw-hero__scene`, gentler tilt, hide empty `#lpnw-hero-particles` when SVG hero present. **body.lpnw-site** overrides for plugin cards/forms on dark background (contrast). Owner list: `docs/OWNER-MORNING-TODO.md`. |
| 2026-04-02 | Initial discovery synthesis from multi-agent audit; no code changes. |
