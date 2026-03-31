# Project Status

Last updated: 31 March 2026

## What Is This

Property intelligence and alert platform for Northwest England at `land-property-northwest.co.uk`. Aggregates data from free public sources (planning applications, EPCs, Land Registry, auction houses) and sends instant alerts to paid subscribers.

## Live Infrastructure

| Component | URL | Status |
|-----------|-----|--------|
| WordPress site | https://land-property-northwest.co.uk | Live, configured |
| Mautic (email) | https://marketing.land-property-northwest.co.uk | Installed |
| WordPress REST API | https://land-property-northwest.co.uk/wp-json/ | Accessible (200 OK) |
| FTP | ftp.gb.stackcp.com | Working |

## What's Deployed and Active

### WordPress Plugins (all active)
- **lpnw-property-alerts** - Our custom alert engine (5 DB tables created)
- **WooCommerce** - Payments and subscriptions
- **Wordfence** - Security
- **WPForms Lite** - Contact/lead forms
- **UpdraftPlus** - Backups
- **Cookie Notice** - GDPR consent
- **Business Directory Plugin** - Professional directory
- **Redirection** - 301 redirects

### Theme
- **GeneratePress** parent theme installed
- **LPNW Theme** child theme active (navy/amber branding)

### Pages Created
Home (static front page), Dashboard, Preferences, Property Map, Saved Properties, Pricing, About, Contact, Privacy Policy, Terms of Service

### WordPress Settings Configured
- Site name: Land & Property Northwest
- Tagline: NW Property Intelligence & Alerts
- Timezone: Europe/London
- Permalinks: /%postname%/
- User registration: enabled (subscriber role)
- Search engines: discouraged (turn on at launch)

## What Still Needs Doing

Tasks are numbered by priority. Do them in order. Each task should be done in its own branch or as an isolated change. Do not bundle unrelated work.

### CRITICAL PATH (must be done to start charging users)

**Task 1: Test and fix the Planning Portal data feed**
The Planning Portal feed class exists at `plugin/lpnw-property-alerts/feeds/class-lpnw-feed-planning.php`. It needs to be tested against the real API. Create a temporary PHP script that calls `$feed = new LPNW_Feed_Planning(); $feed->run();` and verify properties are stored in the DB. Fix any issues with the API response format, field mapping, or error handling. The planning.data.gov.uk API may have changed or may use different endpoints than assumed. Research the current API docs and update the feed class accordingly.

**Task 2: Test and fix remaining data feeds (EPC, Land Registry, Auctions)**
Same as Task 1 but for the other three feeds. Each is a separate class in `plugin/lpnw-property-alerts/feeds/`. Test each one individually. The EPC feed needs an API key (stored in plugin settings). The Land Registry feed downloads a CSV. The Pugh auction feed scrapes HTML. Fix any issues found.

**Task 3: Test the matching engine end-to-end**
With real data in the DB from Tasks 1-2, test that `LPNW_Matcher::match_and_queue()` correctly matches properties to subscriber preferences and creates entries in the alert queue table. Create a test subscriber preference and verify matching works.

**Task 4: Test alert dispatch (email sending)**
Test that `LPNW_Dispatcher::process_queue()` picks up queued alerts and sends emails. Initially test with wp_mail (Mautic integration can come later). Verify the email templates render correctly. The fallback plain email builder in the dispatcher should work without Mautic configured.

**Task 5: Install RankMath SEO**
The WordPress.org slug is `seo-by-rank-math`. Create a setup script to install and activate it. Configure basic SEO settings (site title, meta description template, sitemap).

**Task 6: Configure WooCommerce for UK subscriptions**
Set WooCommerce currency to GBP, store location to UK. Create 3 subscription products: Free (GBP 0), Pro (GBP 19.99/month), VIP (GBP 79.99/month). Use WooCommerce's built-in simple products initially if WooCommerce Subscriptions plugin is not available. The subscription tier detection in `LPNW_Subscriber::get_tier()` checks product slugs containing 'pro' or 'vip'.

**Task 7: Build the home page properly**
Replace the current shortcode-only home page with a proper landing page. Must include: hero section with headline "Get NW Property Alerts Before Anyone Else", subheadline about the speed USP, CTA button to sign up, live property count via `[lpnw_property_count]`, latest properties teaser `[lpnw_latest_properties limit="5"]`, 3-step "How it works" section, pricing summary, trust signals. Use the theme CSS classes in `theme/lpnw-theme/style.css`. No page builder plugins. Either use WordPress block editor content or a custom page template.

**Task 8: Build the pricing page**
Proper 3-tier comparison table using the CSS in the theme (`.lpnw-pricing`, `.lpnw-pricing-card`). Feature comparison list for each tier. CTA buttons linking to WooCommerce checkout for each product. FAQ section below.

**Task 9: Write page content (no AI signatures)**
Write content for: About, Contact, Privacy Policy, Terms of Service. All copy must sound like a knowledgeable NW property professional. No em dashes. No filler. Direct, confident English. The About page should establish authority. Privacy policy needs GDPR basics for a sole trader collecting email and preferences.

**Task 10: Clean up navigation menu**
Remove WooCommerce default pages (Shop, Cart, Checkout, My Account) from the primary navigation. Keep: Home, Pricing, About, Contact. Add logged-in-only menu items: Dashboard, Preferences, Property Map, Saved Properties. Use WordPress menu system.

### IMPORTANT (should be done soon after critical path)

**Task 11: Connect Mautic to the WordPress plugin**
Configure the LPNW plugin settings with Mautic API credentials (URL: https://marketing.land-property-northwest.co.uk, same login as WP admin). Test contact sync and email sending via Mautic API. Update email templates in Mautic.

**Task 12: Set up server cron**
Create a script or documentation for setting up real cron in 20i panel (not WP-Cron). Should hit wp-cron.php every 15 minutes to trigger data feed pulls and alert dispatch.

**Task 13: Write 10 SEO blog posts**
Topics are listed in the plan. NW property focused. No AI signatures. Target long-tail keywords like "planning applications manchester", "property auction northwest", "land for sale lancashire".

**Task 14: Create area landing pages**
Template-driven pages for each NW district. Auto-populated with latest properties from that area. Target "[property type] in [area]" keywords.

**Task 15: Configure Google Search Console + Analytics**
Set up GA4 and GSC for the domain. Add tracking code. Submit sitemap.

### LATER (growth phase)

16. Add SDL, Allsop, AHNW auction scrapers
17. Add council-specific planning feeds beyond the central API
18. Build out professional directory with seed listings
19. Set up advertising slots
20. Create hosting resale packages on 20i
21. Add SMS alerts for VIP tier (Twilio)
22. Consider PWA / push notifications

## Known Issues / Workarounds

### 20i Bot Protection
20i's StackCP blocks automated access to wp-login.php and wp-admin. Workaround: use PHP setup scripts uploaded via FTP and accessed at custom URLs (not /wp-admin/). The WordPress REST API at /wp-json/ IS accessible and returns 200.

### WooCommerce Subscriptions
WooCommerce Subscriptions is a premium plugin (not free on WordPress.org). Options:
- Purchase from WooCommerce.com (~$199/year)
- Use the free alternative "SUMO Subscriptions" or "Subscriptions for WooCommerce" by WebToffee
- Build a simple subscription system using WooCommerce + our custom plugin

## Deployment

Plugin and theme are deployed via FTP:
- Plugin: FTP upload to `/public_html/wp-content/plugins/lpnw-property-alerts/`
- Theme: FTP upload to `/public_html/wp-content/themes/lpnw-theme/`
- Setup scripts: upload to `/public_html/`, access via browser, then delete

See `docs/DEPLOYMENT.md` for full instructions.

## Secrets Required

These should be set as Cursor Secrets for cloud agents:
- FTP_HOST, FTP_USER, FTP_PASS
- WP_URL, WP_ADMIN_USER, WP_ADMIN_PASS
- MAUTIC_URL, MAUTIC_USER, MAUTIC_PASS
- STRIPE_PUBLISHABLE_KEY, STRIPE_SECRET_KEY
- EPC_API_KEY

## Revenue Model

- **Free tier**: Weekly NW property digest email
- **Pro** (GBP 19.99/mo): Instant alerts, full filtering, planning + auction alerts
- **VIP** (GBP 79.99/mo): Priority alerts, off-market deals, direct introductions

Secondary: professional directory listings, advertising, hosting resale.
