# Project Status

Last updated: 4 April 2026

**Running task list and live-check notes:** see `docs/PROJECT-RUNBOOK.md` (updated as work proceeds). This file stays the high-level snapshot of the platform; the runbook holds prioritised open items and verification history.

**Roles:** You are the **director** (goals and priorities). The lead agent is **project manager** for delivery; **sub-agents** are used for parallel deep work when useful. Same wording is in **BRIEF.md** and `.cursor/rules/project.mdc`.

## Platform State: Live, Multi-Source

The property alerts platform ingests listings from several active feeds, surfaces them in browse, map, and dashboard experiences, and runs the full subscriber preference and alert pipeline. **Live dashboard (wp-admin, verified 4 Apr 2026): ~5,132 properties tracked**, **164 added in the last 24 hours** (figures move daily). Rightmove and OnTheMarket drive bulk volume; auction feeds add specialist lots. **Zoopla** typically completes with **0 new rows** (upstream blocking). **Allsop** often shows **0** in feed stats (monitor in **Feed Status**).

## Data Snapshot

Figures below are **operational snapshots** from the live **LPNW Alerts** dashboard (4 Apr 2026), not a manual census of `lpnw_properties` by source. Re-check **LPNW Alerts → Overview** and **Feed Status** after major deploys.

| Source | Notes (live) |
|--------|----------------|
| Rightmove | Running (15-minute batch); strong volume |
| OnTheMarket | Running; high cumulative ingest in feed stats |
| Auction House NW | Running |
| SDL Auctions | Running |
| Pugh Auctions | Running |
| **Zoopla** | **0 new properties** typical (feed completes; no rows) |
| **Allsop** | **0 new properties** in feed stats (monitor) |
| Planning / EPC / Land Registry | Pipelines available; **EPC email + API key are set** in **LPNW Alerts → Settings** (4 Apr 2026); confirm rows in **Feed Status** for EPC |

Planning Portal runs; national platform still limits practical coverage. Land Registry uses the **monthly CSV** path as designed.

## Infrastructure

- **WordPress 6.9.4** on 20i shared hosting; **GeneratePress** parent with **LPNW child theme** (live `style.css` **6.12.1**); **LPNW Property Alerts plugin** **1.0.35** in repo (deploy via FTP after pull).
- **WP-Cron:** `DISABLE_WP_CRON` not set; **next scheduled jobs visible** in LPNW dashboard. Custom HTTP cron **`?lpnw_cron=tick&key=...`** requires non-empty **`LPNW_CRON_SECRET`** in `wp-config.php` (missing secret → 403). Traffic-driven WP-Cron still applies on normal page loads.
- **Must-use plugins on live:** includes `lpnw-cron-endpoint.php`, **`lpnw-tool-auth-loader.php`** (shared key helper for one-shot scripts), and other helpers; **`lpnw-login-as.php`** uses **`LPNW_LOGIN_AS_SECRET`** when set in `wp-config.php`, else dev fallback **`lpnw2026setup`** (public sites should always set the constant; see `docs/DEPLOYMENT.md`). **`?lpnw_update=pages`:** admin session or **`LPNW_PAGE_SYNC_SECRET` / `LPNW_CRON_SECRET`** on the URL (no anonymous default).
- **Mautic** base URL configured; **API check: connected (HTTP 200)**. **VIP / Pro / Free** Mautic email **IDs** were populated in settings (4 Apr 2026); reopen **Settings** after template changes.
- **WooCommerce** with **Stripe** gateway; **three published products** in catalog; admin bar may show **Store coming soon** until you launch the shop publicly.
- **Tier detection** from WooCommerce orders (completed/processing) is implemented; live tier mix varies (4 Apr: **2** active subscribers with preferences, **2 free / 0 pro / 0 vip** in the dashboard breakdown).
- **Search indexing:** Rank Math may report **No Index** (WordPress Reading or Rank Math). **Reconcile** with intended launch state.

## Features Working

- **Subscriber dashboard:** coverage stats, alerts, action cards
- **Alert preferences:** granular filters (area, beds, baths, price, type, tenure, features, channel)
- **Property browse:** eight filter options with pagination
- **Leaflet map:** interactive view with **clustered markers**
- **Property cards:** images, prices, beds/baths, tenure, features, agent, listed date
- **Auction presentation:** guide prices, auction dates where applicable
- **Email alerts:** pipeline active; **~141 alerts queued** observed 4 Apr 2026 (monitor **Alert Log** and Mautic / dispatch). Mautic sends when templates and IDs align; otherwise **wp_mail** fallback.
- **Contact form:** native AJAX (no third-party form plugin required)
- **14 Northwest area landing pages**
- **10 SEO blog posts**
- **JSON-LD** schema markup
- **Mobile-optimised CSS**
- **Login/logout** in navigation
- **WhatsApp and email** sharing on listings

## Feeds (Built vs Active)

- **Rightmove, OnTheMarket, Auction House NW, SDL Auctions, Pugh Auctions:** active and contributing rows as above
- **Zoopla:** built; **0 properties** until Cloudflare allows the server or an approved workaround exists
- **Planning Portal:** built; national platform limits practical coverage
- **EPC:** built; **credentials in plugin settings** (4 Apr); verify ingest in **Feed Status**
- **Land Registry:** built; **monthly CSV** ingestion

## Owner Actions Still Needed

1. **SEO / launch:** Fix **No Index** if the site should rank; clear **WooCommerce “coming soon”** when the shop should sell in public.
2. **Alert delivery:** Watch **queued vs sent**; confirm Mautic templates and IDs match production sends.
3. **External cron:** Prefer **`https://YOURSITE/?lpnw_cron=tick&key=SECRET`** on a fixed schedule with **`LPNW_CRON_SECRET`**; ask **20i** to allowlist a caller if the WAF blocks remote cron.
4. **EPC:** Confirm **Feed Status** shows successful EPC runs now that settings are filled; rotate API key if it was ever exposed.
5. **Security / ops:** Before public launch, rotate **`lpnw-login-as`** to **`LPNW_LOGIN_AS_SECRET`** or remove the mu-plugin; complete **Wordfence** onboarding; finish **Redirection** plugin setup (admin nag); consider **Wordfence Login Security** with WooCommerce (admin nag).
6. **Operations:** monitor **LPNW Alerts → Feed Status** and **Alert Log**.
