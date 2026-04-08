# Mautic email templates for LPNW alerts

Use **Mautic → Channels → Emails** (segment / template emails). Publish each email, then copy its **numeric ID** into **WordPress → LPNW Alert Settings** (`VIP Alert Email ID`, `Pro Alert Email ID`, `Free Digest Email ID`).

After plugin **1.0.9+**, the Settings screen lists **recent emails from the API** so you can copy IDs without hunting in Mautic.

## Seeded templates (API)

If the instance was empty, three **template** emails were created via `tools/mautic-seed-alert-emails.php` using `MAUTIC_URL` / `MAUTIC_USER` / `MAUTIC_PASS`:


| Field in WordPress   | Typical Mautic ID | Email name                |
| -------------------- | ----------------- | ------------------------- |
| VIP Alert Email ID   | **2**             | LPNW Alert — VIP          |
| Pro Alert Email ID   | **3**             | LPNW Alert — Pro          |
| Free Digest Email ID | **4**             | LPNW Weekly Digest — Free |


Paste those IDs into **LPNW Alert Settings** and **Save**. If your Mautic already had other emails, IDs may differ; use the table on the Settings page or Mautic’s email list.

## Tokens (passed on send)

Use these in the email HTML body as Mautic tokens (same names):


| Token                          | Content                              |
| ------------------------------ | ------------------------------------ |
| `{lpnw_subscriber_first_name}` | Greeting name                        |
| `{lpnw_alert_count}`           | Number of listings in this send      |
| `{lpnw_tier}`                  | `vip`, `pro`, or `free`              |
| `{lpnw_properties_html}`       | Pre-built HTML summary of properties |


## Minimal HTML example

```html
<p>Hi {lpnw_subscriber_first_name},</p>
<p>You have <strong>{lpnw_alert_count}</strong> new match(es) on your <strong>{lpnw_tier}</strong> plan.</p>
{lpnw_properties_html}
<p><a href="https://land-property-northwest.co.uk/dashboard/">Open your dashboard</a></p>
```

Create **three** emails (or reuse one with shared layout): one for VIP, one for Pro, one for the weekly free digest. Point each tier field in WordPress to the correct Mautic email ID.

## Prospect intro (one-off segment blast)

This is **not** wired from WordPress. Build it only in Mautic.

1. **Subject:** `Northwest land and property alerts for your inbox`
2. **HTML:** paste the contents of `**docs/mautic-prospect-intro-email.html`** (use Code view; do not paste the `<!-- ... -->` comment block into the body if Mautic strips it anyway).
3. **Import:** map CSV columns to **email**, **firstname**, **lastname**, **company** (aliases must match tokens in that file).
4. **Segment** your imported contacts, then **send** the segment email. Test on yourself first; `{unsubscribe_text}` behaves correctly only for real contacts, not generic preview.

### Bulk import from `email_automation` SQLite (`contacts.db`)

From the repo root, with `**MAUTIC_URL`**, `**MAUTIC_USER**`, `**MAUTIC_PASS**` in `.env` (same as Cursor secrets):

```bash
python tools/import_sqlite_contacts_to_mautic.py --limit 5
python tools/import_sqlite_contacts_to_mautic.py
```

- Default DB path: `D:\Documents\Code\email_automation\data\contacts.db` (override with `LPNW_SQLITE_CONTACTS` or `--db`).
- Imports **all** rows (including legacy bounced/unsubscribed); data is **cleaned** (valid emails, trimmed names, deduped); tag `**lpnw-campaign-2026`** is applied for segmenting.
- **CSV fallback:** `python tools/import_sqlite_contacts_to_mautic.py --csv-only` then **Contacts → Import** in Mautic.
- **Stalled import:** if the API times out part-way, resume with `--offset N` (skip the first `N` contacts already imported), e.g. `--offset 900`.

## Note on logins

Mautic **API** credentials in Settings are often a **dedicated API user**, not the same as the WordPress admin password. If login fails, create an API user in Mautic and use those credentials in **LPNW Alert Settings**.