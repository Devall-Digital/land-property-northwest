# Project Status

Last updated: 2 April 2026

## Platform State: Live, Multi-Source

The property alerts platform ingests listings from several active feeds, surfaces them in browse, map, and dashboard experiences, and runs the full subscriber preference and alert pipeline. **2,938+ properties** are in the index from **5 active data sources**. Rightmove and OnTheMarket supply the bulk of volume; auction feeds add specialist lots. Zoopla remains integrated in code but returns no data because **Cloudflare blocks** requests from the hosting environment.

## Data Snapshot

| Source | Approx. count | Notes |
|--------|---------------|--------|
| Rightmove | 1,834 | Primary source; batched every 15 minutes; working well |
| OnTheMarket | 989 | Working; ~120 properties per batch |
| Auction House NW | 81 | Auction lots |
| SDL Auctions | 24 | Auction lots |
| Pugh Auctions | 10 | Auction lots |
| **Zoopla** | **0** | **Blocked by Cloudflare** (feed built; no rows until unblocked or proxied) |

Planning Portal, EPC, and Land Registry pipelines are **built** but are secondary to on-market volume: Planning Portal yields **limited data** from the national platform; EPC needs an **API key** in settings; Land Registry uses the **monthly CSV** path as designed.

## Infrastructure

- **WordPress** on 20i shared hosting; **GeneratePress** parent with **LPNW child theme**
- **WP-Cron** is triggered by site traffic; **external HTTP cron is blocked by the 20i WAF** (see Owner actions)
- **Mautic** at `marketing.land-property-northwest.co.uk` with **API connected**
- **Stripe** connected via **WooCommerce**
- **Three products:** Free (GBP 0), Pro (GBP 19.99), VIP (GBP 79.99)
- **Tier detection** from WooCommerce orders (completed/processing) is **working**
- **Search engine indexing** is **enabled**

## Features Working

- **Subscriber dashboard:** coverage stats, alerts, action cards
- **Alert preferences:** granular filters (area, beds, baths, price, type, tenure, features, channel)
- **Property browse:** eight filter options with pagination
- **Leaflet map:** interactive view with **clustered markers**
- **Property cards:** images, prices, beds/baths, tenure, features, agent, listed date
- **Auction presentation:** guide prices, auction dates where applicable
- **Email alerts:** instant, daily, and weekly schedules via **wp_mail**
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

1. **External cron:** `cron-job.org` is blocked by 20i; try **EasyCron**, another provider, or ask **20i to whitelist** a single caller IP or URL so `wp-cron.php` can run on a fixed schedule without relying only on traffic.
2. **EPC:** register for an **EPC Open Data API key** and enter it in plugin settings.
3. **Operations:** monitor **LPNW Alerts > Feed Status** in wp-admin and review feed logs for errors, stalls, or source-specific failures.
