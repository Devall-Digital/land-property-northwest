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

## Subscriber alerts: why Mautic can show “0 sent”

WordPress sends each alert with `POST …/api/emails/{templateId}/contact/{contactId}/send`. The **Emails** list “Sent” counter only increases when Mautic actually queues/sends through that email asset. If it stays at **0** while **WordPress → LPNW Alerts → Alert Log** shows traffic, usual causes are:

1. **Wrong template IDs** in **LPNW Alert Settings** (must match the numeric IDs of the three published LPNW emails).
2. **Templates not published** (toggle on; no pending-only state).
3. **API send rejected** for the contact: check the hosting **PHP error log** for lines `LPNW Mautic: send failed` or `sync_contact failed` (Do Not Contact, missing email, validation).
4. **Fallback path:** if Mautic is not configured or the tier template ID is missing, the plugin uses **wp_mail** instead, so Mautic’s sent count does not move.
5. **Cron:** `lpnw_cron_dispatch_alerts` should run every 15 minutes (or hit `?lpnw_cron=tick&key=…` if you use server cron). Stuck queue with no cron means nothing calls Mautic.

**Quick check:** pick one subscriber contact in Mautic, confirm they are not **Do Not Contact** for email, then use **Send example** on a template (sanity check). Trigger a test match on staging or watch the next real dispatch while tailing the PHP log.

## Prospect / blast segments (exclude unsubscribes and bounces)

Mautic enforces **Do Not Contact** when you send through normal **Channels → Emails** to a **segment** (and tracks bounces). Your import segment must not be “everyone with a tag” only, or you risk including people who should be excluded.

**Recommended pattern (two segments):**

1. **Base import segment** (what you have today): e.g. tag `lpnw-campaign-2026` or `lpnw-campaign-import-2026` / alias `lpnw-campaign-import-2026`.
2. **Send segment** (use this for the blast): clone or create **LPNW campaign — mailable** with filters:
   - **Membership:** contact is in segment **LPNW campaign import 2026** (or matching tag filter).
   - **Email** is not empty.
   - **Do Not Contact** → **Email** is false / not opted out (wording varies by Mautic version; look for DNC or “Contactable by email”).
   - **Bounced** / **Email bounced** → exclude bounced contacts if your build exposes this as a filter (often under **Email** or **Behaviour**).

Then create a **segment email** (or **Campaign** with an Email action), choose the **send** segment, **send to segment** or schedule. Always **send a small batch or test segment** first.

**Campaign vs one-shot segment email:** A **Campaign** lets you add waits, conditions, and second steps; a **segment email** is simpler for a single blast. Both respect DNC when configured correctly.

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
- Imports **all** rows by default (including legacy bounced/unsubscribed in the source DB); data is **cleaned** (valid emails, trimmed names, deduped); tag `**lpnw-campaign-2026`** is applied for segmenting. Use **`--skip-legacy-risky`** to omit rows whose `status` / `contact_stage` look like bounced, unsubscribed, spam, or invalid (string match on SQLite fields only; Mautic is still the source of truth after import).
- Use **`--skip-if-bounces`** to skip rows with `total_bounces > 0` when that column is present.
- **CSV fallback:** `python tools/import_sqlite_contacts_to_mautic.py --csv-only` then **Contacts → Import** in Mautic.
- **Stalled import:** if the API times out part-way, resume with `--offset N` (skip the first `N` contacts already imported), e.g. `--offset 900`.

## Note on logins

Mautic **API** credentials in Settings are often a **dedicated API user**, not the same as the WordPress admin password. If login fails, create an API user in Mautic and use those credentials in **LPNW Alert Settings**.