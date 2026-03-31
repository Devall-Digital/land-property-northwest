# Land & Property Northwest

Property intelligence and alert platform for Northwest England.

**Live site:** [land-property-northwest.co.uk](https://land-property-northwest.co.uk)

## What This Does

Automatically pulls property data from free public sources and sends instant alerts to paid subscribers. Built for investors, developers, and property professionals in the Northwest.

### Data Sources

- **Planning Portal** (planning.data.gov.uk) -- new planning applications across all NW local authorities
- **EPC Open Data** (opendatacommunities.org) -- Energy Performance Certificates indicating property activity
- **HM Land Registry** -- price paid data for all NW transactions
- **Auction Houses** -- lot details from Pugh, SDL, Allsop, Auction House North West

### Revenue Model

- **Subscription alerts**: Free (weekly digest) / Pro at GBP 19.99/mo (instant) / VIP at GBP 79.99/mo (priority + off-market)
- **Professional directory**: paid listings for property professionals
- **Advertising**: sponsored slots in alert emails and on-site banners
- **Hosting resale**: WordPress sites for listed professionals via 20i reseller

## Repo Structure

```
plugin/
  lpnw-property-alerts/       Custom WordPress plugin (core product)
    lpnw-property-alerts.php   Main plugin file
    includes/                  Core classes
    feeds/                     Data feed integrations (one per source)
    admin/                     WP admin settings and dashboards
    public/                    Frontend shortcodes, dashboard, map
    templates/                 Email templates for Mautic

theme/
  lpnw-theme/                  GeneratePress child theme
    style.css                  Theme metadata + custom styles
    functions.php              Enqueues, hooks, customisations
    assets/                    CSS, JS, images
    template-parts/            Reusable template partials

docs/
  SETUP.md                     Full installation and deployment guide
  DATA-SOURCES.md              API documentation for each data source
  DEPLOYMENT.md                FTP deployment instructions
```

## Tech Stack

- **WordPress** on 20i shared hosting (PHP 8.0+)
- **WooCommerce + Subscriptions** for subscription billing via Stripe
- **Mautic** (self-hosted) for email delivery and automation
- **Server cron** for scheduled data ingestion
- **Leaflet.js** for interactive property maps
- **GeneratePress** parent theme

## Local Development

### Requirements

- PHP 8.0+
- Composer (for linting tools)

### Setup

```bash
git clone https://github.com/Devall-Digital/land-property-northwest.git
cd land-property-northwest
composer install
```

### Linting

```bash
composer lint        # Check WordPress coding standards
composer lint:fix    # Auto-fix where possible
```

### Deployment

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for FTP deployment instructions.

## NW Coverage Area

Greater Manchester, Merseyside, Lancashire, Cheshire, Cumbria.

Postcode prefixes: M, L, PR, BB, LA, BL, OL, SK, WA, WN, CW, CH, CA, FY

## Architecture

The custom plugin (`lpnw-property-alerts`) handles:

1. **Data ingestion** -- cron-scheduled pulls from each public API/source
2. **Normalisation** -- all data mapped to a common property schema
3. **Matching** -- new properties matched against subscriber preferences
4. **Dispatch** -- alerts queued by tier (VIP first, Pro, then Free digest) and sent via Mautic
5. **Dashboard** -- frontend pages for subscribers to view alerts, set preferences, save properties

The theme provides branding, page templates, and layout customisations on top of GeneratePress.

WooCommerce handles all billing. Mautic handles all email delivery.
