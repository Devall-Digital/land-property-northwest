# Project Status

Last updated: 2 April 2026

**Running task list and live-check notes:** see `docs/PROJECT-RUNBOOK.md` (updated as work proceeds). This file stays the high-level snapshot of the platform; the runbook holds prioritised open items and verification history.

## Platform State: Live, Multi-Source

The property alerts platform ingests listings from several active feeds, surfaces them in browse, map, and dashboard experiences, and runs the full subscriber preference and alert pipeline. **Live dashboard (wp-admin, 2 Apr 2026): ~3,465 properties tracked**, **1,696 added in the last 24 hours**, feeds running on schedule with **zero failed runs** on the Feed Status table. Rightmove and OnTheMarket drive bulk volume; auction feeds add specialist lots. **Zoopla** runs complete but **0 new properties** ingested (consistent with upstream blocking). **Allsop** auction feed shows **0** new properties so far.

## Data Snapshot

Figures below are **operational snapshots** from the live **LPNW Alerts** dashboard and **Feed Status** screen (2 Apr 2026), not a manual census of `lpnw_properties` by source.

| Source | Notes (live) |
|--------|----------------|
| Rightmove | Running (15-minute batch); strong volume |
| OnTheMarket | Running; high cumulative ingest in feed stats |
| Auction House NW | Running |
| SDL Auctions | Running |
| Pugh Auctions | Running |
| **Zoopla** | **0 new properties** (feed completes; no rows) |
| **Allsop** | **0 new properties** in feed stats (monitor) |
| Planning / EPC / Land Registry | Pipelines enabled; **EPC API email and key empty** in settings until configured |

Planning Portal runs; national platform still limits practical coverage. Land Registry uses the **monthly CSV** path as designed.

## Infrastructure

- **WordPress 6.9.4** on 20i shared hosting; **GeneratePress** parent with **LPNW child theme** (live child theme header **version 6.0.0**; may differ from repo until next theme deploy); **LPNW Property Alerts plugin 1.0.0** (matches repo header until next bump)
- **WP-Cron:** `DISABLE_WP_CRON` not set; **next scheduled jobs visible** in LPNW dashboard (portals, dispatch, planning, EPC, auctions, digest). **External** cron URLs may still be blocked by the WAF for third-party ping services (see Owner actions); traffic-driven cron is clearly firing.
- **Must-use plugins on live:** includes `lpnw-cron-endpoint.php`, operational helpers (`lpnw-backfill-otm.php`, `lpnw-postcode-stats.php`, `lpnw-tier-test.php`), **`lpnw-login-as.php`** (review security: should not rely on a static URL key long term), host `wp-stack-cache.php`
- **Mautic** base URL configured; **API check: connected (HTTP 200)**. **Repo plugin 1.0.13+** can **auto-fill VIP/Pro/Free email IDs** from Mautic template names; live needs deploy and one visit to **LPNW Alerts > Settings** (or wait for daily sync).
- **WooCommerce** with **Stripe** gateway; **three published products** in catalog; admin bar shows **Store coming soon** (confirm before public launch)
- **Tier detection** from WooCommerce orders (completed/processing) is **working** (alert log shows PRO tier)
- **Search indexing:** Rank Math admin notice reports **No Index** (WordPress Reading or Rank Math). **Reconcile** with intended launch state; do not assume the site is visible in Google until this is cleared

## Features Working

- **Subscriber dashboard:** coverage stats, alerts, action cards
- **Alert preferences:** granular filters (area, beds, baths, price, type, tenure, features, channel)
- **Property browse:** eight filter options with pagination
- **Leaflet map:** interactive view with **clustered markers**
- **Property cards:** images, prices, beds/baths, tenure, features, agent, listed date
- **Auction presentation:** guide prices, auction dates where applicable
- **Email alerts:** pipeline active (large **queued** volume observed); after **1.0.13** deploy, Mautic sends when templates exist and IDs sync; otherwise **wp_mail** fallback
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
- **EPC:** built; **configure EPC API key** for live pulls
- **Land Registry:** built; **monthly CSV** ingestion

## Owner Actions Still Needed

1. **SEO / launch:** Fix **No Index** if the site should rank; clear **WooCommerce “coming soon”** when the shop should sell in public.
2. **Alert delivery:** After deploy, open **LPNW Alerts > Settings** once (IDs sync from Mautic). If you rename templates in Mautic, adjust names or set IDs manually.
3. **External cron:** `cron-job.org` is blocked by 20i; try **EasyCron**, another provider, or ask **20i to whitelist** a single caller IP or URL so `wp-cron.php` (or your mu-plugin cron URL) can run on a fixed schedule without relying only on traffic.
4. **EPC:** register for an **EPC Open Data API key** (and email if required) and enter both in **LPNW Alerts > Settings**.
5. **Security / ops:** Audit **mu-plugins** (especially any login-bypass scripts); complete **Wordfence** onboarding; finish **Redirection** plugin setup (admin nag); consider **Wordfence Login Security** integration with WooCommerce (admin nag).
6. **Operations:** monitor **LPNW Alerts > Feed Status** and **Alert Log** (watch **queued vs sent**).
