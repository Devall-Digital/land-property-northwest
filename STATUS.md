# Project Status

Last updated: 1 April 2026

## What Is This

Property intelligence and alert platform for Northwest England at `land-property-northwest.co.uk`. Aggregates data from public sources (planning, EPC, Land Registry, auctions, property portals) and sends alerts to subscribers by tier.

## Live Infrastructure

| Component | URL | Status |
|-----------|-----|--------|
| WordPress site | https://land-property-northwest.co.uk | Live, configured |
| Mautic (email) | https://marketing.land-property-northwest.co.uk | Installed; API not wired for dispatch yet |
| WordPress REST API | https://land-property-northwest.co.uk/wp-json/ | Accessible (200 OK) |
| FTP | ftp.gb.stackcp.com | Working |

## What Is Deployed and Working

### WordPress and theme

- **GeneratePress** parent theme installed.
- **LPNW Theme** child theme active (navy/amber branding).

### Plugins (active)

- **lpnw-property-alerts** (custom): alert engine, feeds, matcher, dispatcher, subscriber UI; custom DB tables in place.
- **WooCommerce**: checkout and Stripe; **simple virtual products** used for tiers (no WooCommerce Subscriptions extension required for first launch).
- **Wordfence**, **WPForms Lite**, **UpdraftPlus**, **Cookie Notice**, **Business Directory Plugin**, **Redirection** (and other standard stack as on live).

### Pages

Home (front page), Dashboard, Preferences, Property Map, Saved Properties, Pricing, About, Contact, Privacy Policy, Terms of Service.

### Content

Blog is available and posts publish as on the live site (ongoing SEO content is a separate backlog item).

### Site settings (typical)

Site name Land & Property Northwest, Europe/London, pretty permalinks, registration for subscribers as configured.

### Plugin load and cron (codebase)

Main plugin loads core includes (property, subscriber, matcher, dispatcher, Mautic helper, geocoder), all feed classes (planning, EPC, Land Registry, four auction feeds, **Rightmove and Zoopla portal feeds**), admin when in admin, and public/dashboard/map on the front.

Scheduled hooks on activation:

- `lpnw_cron_planning` every 6 hours  
- `lpnw_cron_epc` daily  
- `lpnw_cron_landregistry` daily  
- `lpnw_cron_auctions` daily  
- `lpnw_cron_portals` every 15 minutes  
- `lpnw_cron_dispatch_alerts` every 15 minutes  
- `lpnw_cron_free_digest` weekly (registered in code; see gaps below)

Production still needs **server-driven** triggering of `wp-cron.php` if WP-Cron is not reliable on 20i.

## What Is Not Working Yet

- **Rightmove feed:** Runs but returns **zero** ingestible results (needs investigation: URLs, parsing, or upstream behaviour).
- **Zoopla feed:** Likely **blocked or challenged by Cloudflare**; not a dependable source until access is solved or an alternative is agreed.
- **Planning Portal:** Feed runs against planning.data.gov.uk-style data; **authority or application IDs are not fully verified** end-to-end against live API behaviour.
- **Mautic:** Instance exists; **Mautic API is not enabled or connected** in the plugin for production sends (dispatcher may still use fallbacks such as `wp_mail` depending on settings).
- **Free weekly digest:** **Not effectively scheduled or not running** in production (do not treat as live until server cron and `lpnw_cron_free_digest` are verified).

## Active Bugs Being Fixed

- **Matcher:** Does not map **portal** property sources to subscriber `alert_types` correctly (portal listings not aligned with preference checks).
- **Geography:** **Non-Northwest filter gap** (edge cases where non-NW records can slip through or NW boundaries need tightening).
- **Queue integrity:** **`sent_at` set on failure** (or similar) so failed sends are not marked like successful ones; fix in dispatcher/queue handling.

## Outstanding Work (prioritised)

Work in small branches. Skip items already done on live (landing copy, menu, Rank Math) unless you confirm otherwise.

1. **Portal data:** Fix Rightmove to return real NW listings; resolve or replace Zoopla access.
2. **Matcher and filters:** Map portal sources to alert types; close non-NW leakage; fix `sent_at` on failed dispatch.
3. **Planning feed:** Verify IDs, fields, and authority coverage against current API docs.
4. **Mautic:** Enable API in Mautic, store credentials (see `.cursor/rules/secrets.mdc`), test contact sync and template sends from `LPNW_Dispatcher`.
5. **Free digest:** Ensure weekly digest actually runs (server cron + confirm `lpnw_cron_free_digest`).
6. **WooCommerce:** Confirm GBP, UK store, and tier products match `LPNW_Subscriber::get_tier()` expectations; add subscription extension later if auto-renewal is required.
7. **End-to-end test:** With real portal/planning data in `lpnw_properties`, prove match, queue, and email (Mautic or fallback) for Pro/VIP and then free digest.

### Soon after core path

8. Rank Math or equivalent SEO plugin if not already on live.  
9. Google Search Console and GA4.  
10. Area landing pages and additional auction sources (SDL, Allsop, AHNW) as in BRIEF.

## Known Issues / Workarounds

### 20i bot protection

Automated access to `wp-login.php` and wp-admin is blocked. Use temporary PHP scripts over FTP and a one-off URL; **REST** at `/wp-json/` works.

### Billing

WooCommerce Subscriptions is optional. Simple virtual products suffice for manual or periodic renewal until a subscription plugin is added.

## Deployment

- Plugin: `/public_html/wp-content/plugins/lpnw-property-alerts/`  
- Theme: `/public_html/wp-content/themes/lpnw-theme/`  

See `docs/DEPLOYMENT.md`.

## Secrets (for agents)

See `.cursor/rules/secrets.mdc` for FTP, WordPress, Stripe, Mautic, and EPC API variables.

## Revenue Model

- **Free:** weekly NW digest email (once digest is live).  
- **Pro:** instant alerts, full filtering, multiple alert types.  
- **VIP:** priority and higher-touch benefits as defined on Pricing.

Secondary: directory, advertising, hosting resale.
