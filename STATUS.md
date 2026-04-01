# Project Status

Last updated: 1 April 2026, 19:00 UTC

## Platform State: Launch-Ready MVP

10 improvement cycles completed. The platform pulls real property listings from Rightmove across 19 Northwest England regions, displays them with images and prices, and has the full alert pipeline built (matching, dispatching, email templates). The site looks professional with proper branding, responsive design, and conversion-focused CTAs.

## Health Check (all passing)

Hero section, trust bar, stats bar, property cards with images, pricing cards, CTA banner, sticky CTA bar, footer links, JSON-LD schema, Rightmove source badges, View on Rightmove links: all rendering correctly.

## What's Live

### Data
- 1,769+ Rightmove property listings from 19 NW regions (Manchester, Liverpool, Bolton, Bury, Oldham, Rochdale, Salford, Stockport, Tameside, Trafford, Wigan, Preston, Blackpool, Blackburn, Burnley, Chester, Warrington, Lancaster, Carlisle)
- Both sales and rentals, correctly labelled with "For sale" or "pcm" pricing
- Geographic diversity on homepage (shows properties from different areas, not just the last-processed region)
- Batched feed processing (6 region+channel pairs per cron run, 25s time budget for reliability on shared hosting)

### Pages
- Home: full-bleed hero, trust bar, stats, how-it-works, 6 diverse property cards with Rightmove images, pricing teaser, CTA
- Pricing: comparison table, 3 tier cards (Free/Pro/VIP), FAQ
- About: authority content with area stats and source count
- Contact: native AJAX contact form (no WPForms dependency)
- 14 NW area landing pages (property-alerts-manchester, etc.) with unique content and area-filtered properties
- 10 SEO blog posts
- Privacy Policy, Terms of Service
- Dashboard, Preferences, Property Map, Saved Properties, Email Preview (subscriber pages)

### Technical
- All 7 cron events registered (portals every 15min, planning every 6h, EPC/LandReg/Auctions daily, free digest weekly, dispatch every 15min)
- DISABLE_WP_CRON set in wp-config.php
- Mautic API connected (HTTP 200 confirmed)
- 3 WooCommerce products (Free GBP 0, Pro GBP 19.99, VIP GBP 79.99)
- Tier detection via WooCommerce orders (completed + processing)
- Frequency enforcement per tier (free=weekly, pro=daily/instant, vip=instant)
- Cross-portal deduplication with sale/rent channel awareness
- Property type normalization for matching (portal types mapped to canonical preferences)
- Property data API requires login (security fix)
- JSON-LD schema markup on all pages (Organization, WebSite, Product, Article)
- Comprehensive mobile CSS for 480px screens (44px touch targets, stacked layouts)
- Sticky CTA bar for non-logged-in visitors
- Blog post CTAs with area-aware messaging
- Property grid signup prompt for visitors

### Feeds (10 sources built)
- Rightmove: working, batched, 19 regions, sale/rent channel storage
- Zoopla: built with batching, Cloudflare may block from shared hosting
- OnTheMarket: built with batching
- Planning Portal: verified entity IDs (626xxx series), data availability varies by LPA
- EPC: built, needs API key in settings
- Land Registry: built, monthly CSV download
- Pugh, SDL, AHNW, Allsop auction scrapers: built

## Owner Actions Still Needed

1. Set up external cron at cron-job.org (URL: https://land-property-northwest.co.uk/wp-cron.php?doing_wp_cron, every 15 minutes)
2. Add Stripe API keys for payment processing
3. Enable Mautic API toggles (config page, API Settings, both switches to Yes)
4. Register as test user, set preferences, verify email alerts work
5. Review site and flag any changes wanted
