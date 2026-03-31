# Project Brief: Land & Property Northwest

This is the single source of truth for what we are building. Every contributor (human or AI agent) must read this before writing any code.

## The Product (one sentence)

A paid subscription service that instantly alerts users the moment a property or land opportunity appears on the market anywhere in Northwest England, giving them a speed advantage over everyone else.

## The USP

Speed. Subscribers get alerted instantly when new properties hit the market, new planning applications are submitted, auction lots are listed, or land becomes available. They see it before the general public catches up. That speed lets investors and developers act first, which is worth paying for.

Nothing gets missed. We aggregate data from every public source: planning portals, auction houses, EPC register, Land Registry. One subscription covers them all. No need to check ten different websites.

Relevant alerts only. Users set their criteria (area, price range, property type, alert type) and only get notified about properties that match. No noise, no spam, just opportunities they care about.

## Who Pays For This

Property investors, developers, and professionals in Northwest England. These are people who make money from finding properties quickly. A monthly subscription is a trivial cost compared to the deals they find through it.

## Pricing

- Free: weekly digest email (gets people hooked, builds the list)
- Pro at GBP 19.99/month: instant alerts, full filtering, all data sources
- Investor VIP at GBP 79.99/month: priority alerts (30 min before Pro), off-market deals, direct introductions

## Priority: Revenue First

The number one goal is to get paying subscribers as fast as possible. Every development decision should be judged by: "Does this get us closer to charging users?"

Build order:
1. Get the alert engine working (data feeds pulling, matching, emails sending)
2. Get the subscription/payment flow working (WooCommerce, Stripe)
3. Get the subscriber dashboard working (preferences, alert feed)
4. Make the site look professional enough to charge for
5. Content and SEO (drives organic traffic to free signups, which convert to paid)

Do NOT spend time on things that don't directly serve these five priorities.

## Tech Stack

- WordPress on 20i shared hosting (PHP 8.0+)
- Custom plugin: lpnw-property-alerts (the core product)
- GeneratePress theme + LPNW child theme
- WooCommerce + Subscriptions for billing
- Stripe for payment processing
- Mautic at marketing.land-property-northwest.co.uk for email delivery
- Leaflet.js for interactive maps
- Server cron for scheduled data pulls

## Data Sources (all free, public, legal)

1. Planning Portal (planning.data.gov.uk) - planning applications across all NW authorities
2. EPC Open Data (opendatacommunities.org) - indicates property coming to market
3. HM Land Registry Price Paid Data - monthly transaction data
4. Auction houses (Pugh, SDL, Allsop, AHNW) - upcoming lots

## NW Coverage

Postcode prefixes: M, L, PR, BB, LA, BL, OL, SK, WA, WN, CW, CH, CA, FY

Covering: Greater Manchester, Merseyside, Lancashire, Cheshire, Cumbria.

## Architecture Rules

- Component-based architecture everywhere. One class per concern. No god classes.
- Every data feed is its own class extending LPNW_Feed_Base
- Every admin page, public shortcode, and API endpoint is its own class
- Follow WordPress PHP Coding Standards (WPCS)
- Prefix everything with lpnw_ (functions, classes, DB tables, CSS classes, JS globals)
- Use WordPress APIs (wp_remote_get, $wpdb->prepare, wp_enqueue_scripts, etc.)
- Never modify WordPress core files
- Sanitize all input, escape all output, prepare all queries

## Deployment

Code lives in this repo. Deploy to the live server via FTP.
- Plugin: /public_html/wp-content/plugins/lpnw-property-alerts/
- Theme: /public_html/wp-content/themes/lpnw-theme/

For WordPress admin tasks that need server-side execution, create temporary PHP scripts, upload via FTP, run via browser, then delete.

## Content Rules

All user-facing copy must sound like a knowledgeable property professional, not like AI. No em dashes. No filler phrases. No "in today's fast-paced world" or "navigating the complex landscape." Direct, clear, confident English.

## What Not To Do

- Do not switch tech stacks or suggest rebuilding in React/Next.js/etc. We use WordPress.
- Do not install plugins or tools that are not in the approved list without asking first.
- Do not refactor working code for aesthetic reasons. Ship features.
- Do not write content that sounds like AI generated it.
- Do not create unnecessary abstractions. Keep it simple and working.
- Do not push directly to main without the work being reviewed or tested.
