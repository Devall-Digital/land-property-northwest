# Setup Guide

Complete setup instructions for the Land & Property Northwest platform.

## Prerequisites

- 20i hosting account with a package for `land-property-northwest.co.uk`
- WordPress installed via 20i one-click install
- Mautic installed via 20i one-click on `marketing.land-property-northwest.co.uk`
- Stripe account with API keys
- FTP/SFTP access to the hosting package

## Step 1: Upload Plugin and Theme

Connect to your hosting via FTP (credentials from 20i panel).

1. Upload `plugin/lpnw-property-alerts/` to `wp-content/plugins/lpnw-property-alerts/`
2. Upload `theme/lpnw-theme/` to `wp-content/themes/lpnw-theme/`

## Step 2: Install Required Plugins via WP Admin

Log into WordPress admin at `land-property-northwest.co.uk/wp-admin`.

Install and activate these plugins from the WordPress plugin directory:

1. **GeneratePress** (theme) - install from Appearance > Themes
2. **WooCommerce** - core ecommerce
3. **WooCommerce Subscriptions** - recurring billing (premium plugin, purchase required)
4. **WooCommerce Stripe Gateway** - payment processing
5. **RankMath SEO** - search engine optimisation
6. **Wordfence Security** - firewall and malware protection
7. **WPForms Lite** - contact and lead capture forms
8. **Business Directory Plugin** - professional directory
9. **UpdraftPlus** - automated backups
10. **Cookie Notice** - GDPR cookie consent

## Step 3: Activate Theme and Plugin

1. Go to Appearance > Themes, activate **GeneratePress** first, then activate **LPNW Theme** (child theme)
2. Go to Plugins, activate **LPNW Property Alerts**
3. The plugin will automatically create its database tables on activation

## Step 4: Configure WordPress Settings

- Settings > General: Set site title to "Land & Property Northwest", tagline to "NW Property Intelligence & Alerts"
- Settings > Permalinks: Select "Post name" structure
- Settings > Reading: Set homepage to a static page (create "Home" page first)
- Settings > Discussion: Disable comments on new posts (unless you want them)

## Step 5: Configure WooCommerce

1. Run the WooCommerce setup wizard
2. Set currency to GBP, store location to UK
3. Go to WooCommerce > Settings > Payments, enable Stripe
4. Enter your Stripe API keys (publishable + secret)
5. Start in test mode until ready to launch

## Step 6: Create Subscription Products

Create 3 products in WooCommerce > Products > Add New:

**Free Alert Subscription**
- Simple subscription, price GBP 0.00/month
- SKU: `lpnw-free`
- Slug: `lpnw-free`

**Pro Alert Subscription**
- Simple subscription, price GBP 19.99/month (or GBP 179.00/year)
- SKU: `lpnw-pro`
- Slug: `lpnw-pro`

**Investor VIP Alert Subscription**
- Simple subscription, price GBP 79.99/month (or GBP 749.00/year)
- SKU: `lpnw-vip`
- Slug: `lpnw-vip`

## Step 7: Configure LPNW Alert Settings

Go to WP Admin > LPNW Alerts > Settings.

### Data Feeds
- Enable Planning Portal feed: checked
- Enable EPC Open Data feed: checked
- EPC API Key: (register at https://epc.opendatacommunities.org/login and get your key)
- Enable Land Registry feed: checked
- Enable Auction House feeds: checked

### Mautic Integration
- Mautic URL: `https://marketing.land-property-northwest.co.uk`
- Mautic API Username: (your Mautic admin username)
- Mautic API Password: (your Mautic admin password)
- VIP/Pro/Free Email IDs: (create email templates in Mautic first, then enter their IDs here)

## Step 8: Create Pages

Create these WordPress pages and assign shortcodes:

| Page | Slug | Content/Shortcode |
|------|------|-------------------|
| Home | `home` | Hero section + `[lpnw_latest_properties limit="5"]` + `[lpnw_property_count]` |
| Dashboard | `dashboard` | `[lpnw_dashboard]` |
| Preferences | `preferences` | `[lpnw_preferences]` |
| Property Map | `map` | `[lpnw_property_map height="600px"]` |
| Saved Properties | `saved` | `[lpnw_saved_properties]` |
| Pricing | `pricing` | Manual pricing table (use theme CSS classes) |
| About | `about` | Manual content |
| Contact | `contact` | WPForms contact form |
| Privacy Policy | `privacy-policy` | Manual content |
| Terms of Service | `terms` | Manual content |

Set "Home" as the static front page in Settings > Reading.

## Step 9: Configure Cron

WordPress WP-Cron only fires on page visits, which is unreliable. Set up a real server cron in your 20i panel:

1. Go to 20i panel > Manage Hosting > Cron Jobs
2. Add: `*/15 * * * * wget -q -O /dev/null https://land-property-northwest.co.uk/wp-cron.php?doing_wp_cron`

This ensures feeds run on schedule even without visitor traffic.

## Step 10: Configure RankMath SEO

1. Run the RankMath setup wizard
2. Connect to your Google Search Console account
3. Set default meta title pattern: `%title% | Land & Property Northwest`
4. Enable sitemap generation
5. Submit sitemap URL to Google Search Console

## Step 11: Security

1. Configure Wordfence: enable firewall, set up login rate limiting
2. Ensure SSL is active (should be automatic with 20i)
3. Disable XML-RPC if not needed
4. Set strong admin password, consider two-factor auth
