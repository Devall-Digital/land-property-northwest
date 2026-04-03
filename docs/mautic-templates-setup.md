# Mautic email templates for LPNW alerts

Use **Mautic → Channels → Emails** (segment / template emails). Publish each email, then copy its **numeric ID** into **WordPress → LPNW Alert Settings** (`VIP Alert Email ID`, `Pro Alert Email ID`, `Free Digest Email ID`).

After plugin **1.0.9+**, the Settings screen lists **recent emails from the API** so you can copy IDs without hunting in Mautic.

## Tokens (passed on send)

Use these in the email HTML body as Mautic tokens (same names):

| Token | Content |
|-------|---------|
| `{lpnw_subscriber_first_name}` | Greeting name |
| `{lpnw_alert_count}` | Number of listings in this send |
| `{lpnw_tier}` | `vip`, `pro`, or `free` |
| `{lpnw_properties_html}` | Pre-built HTML summary of properties |

## Minimal HTML example

```html
<p>Hi {lpnw_subscriber_first_name},</p>
<p>You have <strong>{lpnw_alert_count}</strong> new match(es) on your <strong>{lpnw_tier}</strong> plan.</p>
{lpnw_properties_html}
<p><a href="https://land-property-northwest.co.uk/dashboard/">Open your dashboard</a></p>
```

Create **three** emails (or reuse one with shared layout): one for VIP, one for Pro, one for the weekly free digest. Point each tier field in WordPress to the correct Mautic email ID.

## Note on logins

Mautic **API** credentials in Settings are often a **dedicated API user**, not the same as the WordPress admin password. If login fails, create an API user in Mautic and use those credentials in **LPNW Alert Settings**.
