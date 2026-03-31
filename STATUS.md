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

### High Priority (next steps)
1. **Install RankMath SEO** - slug is `seo-by-rank-math` (was missed due to wrong slug)
2. **Configure WooCommerce** - set currency GBP, create 3 subscription products (Free/Pro/VIP)
3. **Install WooCommerce Subscriptions** - premium plugin, needs purchasing or alternative
4. **Connect Stripe** to WooCommerce (owner has Stripe account, keys needed)
5. **Configure Mautic** - set up API credentials, email templates, connect to WordPress plugin
6. **Write page content** - Home hero, About, Pricing table, How It Works (no AI signatures)
7. **Test data feeds** - run Planning Portal feed manually, verify data ingestion works
8. **Set up server cron** - in 20i panel, hit wp-cron.php every 15 minutes

### Medium Priority
9. **Build proper home page** - hero section, CTA, live property count, pricing teaser
10. **Create SEO area landing pages** - one per NW district (~35 pages)
11. **Write 10 blog posts** targeting NW property keywords
12. **Configure Google Search Console + Analytics**
13. **Register for EPC API key** at https://epc.opendatacommunities.org/login
14. **Test end-to-end alert flow** - data ingestion > matching > email dispatch
15. **Refine navigation menu** - remove WooCommerce default pages (Shop, Cart, etc.) from main nav

### Lower Priority
16. Add more auction house scrapers (SDL, Allsop, AHNW)
17. Add council-specific planning feeds
18. Build out directory with seed listings
19. Set up advertising slots
20. Create hosting resale packages on 20i

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
