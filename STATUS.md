# Project Status

Last updated: 1 April 2026, 17:30 UTC

## Platform State: Functional MVP

The core product is operational. Property data is flowing from Rightmove (1,769+ NW listings ingested), the matching engine maps portal listings to subscriber preferences, and alert emails render correctly with real property data including images. The site has been through multiple audit and improvement cycles.

## Live Infrastructure

| Component | URL | Status |
|-----------|-----|--------|
| WordPress | https://land-property-northwest.co.uk | Live, themed, full content |
| Mautic | https://marketing.land-property-northwest.co.uk | Installed, API connected |
| REST API | https://land-property-northwest.co.uk/wp-json/ | Accessible |
| FTP | ftp.gb.stackcp.com | Working |

## Data Feeds

| Feed | Source | Status | Properties |
|------|--------|--------|------------|
| Rightmove | rightmove.co.uk | Working (HTML + JSON extraction, 19 NW regions) | 1,769 |
| Zoopla | zoopla.co.uk | Built, likely Cloudflare-blocked from server | 0 |
| OnTheMarket | onthemarket.com | Built, untested in production | 0 |
| Planning Portal | planning.data.gov.uk | Built, entity IDs verified, API may have data gaps | 0 |
| EPC | opendatacommunities.org | Built, needs API key configured | 0 |
| Land Registry | gov.uk CSV | Built, monthly data | 0 |
| Pugh Auctions | pugh-auctions.com | Built | 0 |
| SDL Auctions | sdlauctions.co.uk | Built | 0 |
| AHNW | auctionhouse.co.uk/northwest | Built | 0 |
| Allsop | allsop.co.uk | Built | 0 |

## Deployed Plugins (all active)

lpnw-property-alerts (custom), WooCommerce, RankMath SEO, Wordfence, WPForms Lite, UpdraftPlus, Cookie Notice, Business Directory Plugin, Redirection

## Pages and Content

- Home: hero, trust bar, stats, how it works, 6 property cards with images, pricing teaser, CTA
- Pricing: comparison table, 3 tier cards, FAQ
- About: authority-building content
- Contact: native AJAX contact form (no WPForms dependency)
- Privacy Policy, Terms of Service: complete
- 10 SEO blog posts published
- Dashboard, Preferences, Property Map, Saved Properties pages created

## WooCommerce

- Currency: GBP, country: GB
- Products: Free (GBP 0), Pro (GBP 19.99), VIP (GBP 79.99)
- Stripe: not yet connected (owner needs to add keys)
- Tier detection: via completed/processing WooCommerce orders

## Cron

- DISABLE_WP_CRON set in wp-config.php
- All 7 events registered: planning (6h), EPC/LandReg/Auctions (daily), portals/dispatch (15min), free digest (weekly)
- NEEDS: external cron service (cron-job.org) or 20i scheduled task hitting wp-cron.php every 15 minutes

## Alert System

- Matcher: maps rightmove, zoopla, onthemarket, planning, epc, landregistry, auction sources to alert types (listing, planning, epc, price, auction)
- Type matching: normalizes portal property types to canonical preference values
- Frequency: honours subscriber preference capped by tier (free=weekly, pro=daily/instant, vip=instant)
- Dispatcher: wp_mail with HTML templates (instant, daily digest, weekly digest), Mautic as optional enhancement
- Email templates: verified rendering with real data (property cards, prices, source badges, View Details buttons)
- Security: property API requires login, frequency save enforces tier caps

## Owner Actions Needed

1. Set up cron: go to cron-job.org, create free account, add job for https://land-property-northwest.co.uk/wp-cron.php?doing_wp_cron every 15 minutes
2. Add Stripe API keys to WooCommerce (for taking payments)
3. Register as a test user and set alert preferences to verify the full flow
4. Enable Mautic API: config page > API Settings > toggle both switches to Yes

## Known Limitations

- Zoopla and OnTheMarket feeds may be Cloudflare-blocked from shared hosting (Rightmove is the reliable primary source)
- Planning Portal data coverage is limited by the national platform (not all LPAs publish there yet)
- WooCommerce uses simple products, not the premium Subscriptions plugin (no auto-renewal until that is added)
- Page cache on 20i requires cache-busted URLs or manual purge to see fresh content immediately
