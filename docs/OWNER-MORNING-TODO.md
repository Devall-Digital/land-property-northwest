# Owner morning checklist (Land & Property Northwest)

Short list you can run through in order. The agent keeps working the technical backlog in the repo.

## Security and launch

1. **Cron URL:** In `wp-config.php`, add `define( 'LPNW_CRON_SECRET', 'long-random-string' );` and update any external cron job to  
   `https://land-property-northwest.co.uk/?lpnw_cron=tick&key=long-random-string`  
   (Until you do this, the tick URL stays open.)

2. **EPC:** Register at [epc.opendatacommunities.org](https://epc.opendatacommunities.org/), then paste **email + API key** in **LPNW Alerts → Settings**.

3. **Mautic:** Enter **email template IDs** (VIP / Pro / free digest) if you want sends through Mautic instead of plain `wp_mail`.

4. **SEO / WooCommerce:** When you are ready to sell and rank: clear **Rank Math “No Index”** (or Reading settings) and turn off **WooCommerce “Store coming soon”**.

5. **20i cron:** If you want feeds on the clock without relying on traffic, ask **20i to whitelist** one ping URL or use a cron provider that is not blocked by the WAF.

## Optional polish (after the above)

6. **Purge cache** once after theme deploys (Jetpack / Stack / host cache) so everyone gets fresh CSS.

7. **Hero image:** If you want a single “hero city” bitmap for even richer detail, export a wide PNG/WebP (~2400px), upload to **Media**, and we can wire it as a CSS background layer behind the existing SVG (no extra JS).

## Where the full backlog lives

- `docs/DISCOVERY-BACKLOG.md` — technical debt and product gaps  
- `docs/PROJECT-RUNBOOK.md` — ops notes and verification
