# Mautic email templates for LPNW alerts

Use **Mautic → Channels → Emails** (segment / template emails). Publish each email, then copy its **numeric ID** into **WordPress → LPNW Alert Settings** (`VIP Alert Email ID`, `Pro Alert Email ID`, `Free Digest Email ID`).

After plugin **1.0.9+**, the Settings screen lists **recent emails from the API** so you can copy IDs without hunting in Mautic.

## Recommended: browser setup (production)

The reliable approach is to **edit each template in the Mautic UI** (Theme → Code Mode if you use themes, then **Advanced → HTML Code**), **paste** the full HTML, and **Save**. You (or an agent using the browser) can confirm what is stored and avoid silent API or credential mismatches.

To **generate** the three alert bodies for pasting (no Mautic API required), run locally:

```bash
php tools/mautic-seed-alert-emails.php --dump-html
```

Copy the block under `---VIP---`, `---PRO---`, and `---FREE---` into the matching email. Marketing / prospect copy: `docs/mautic-prospect-intro-email.html`.

On each alert template, leave **Contact segment** empty unless every subscriber is in that segment (otherwise WordPress API sends can fail). Keep **Published** on.

## Optional: seed new emails via API

If the instance was **empty**, you can create three **template** emails via `tools/mautic-seed-alert-emails.php` using `MAUTIC_URL` / `MAUTIC_USER` / `MAUTIC_PASS`:


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

This is **not** wired from WordPress.

**On the live instance (as of setup pass):**

| Item | Typical ID / alias | Notes |
|------|--------------------|--------|
| Import pool segment | **1** · `lpnw-campaign-import-2026` | Tag `lpnw-campaign-2026` (raw import). **Do not** blast this alone without exclusions. |
| Mailable segment for intro | **2** · `lpnw-mailable-prospects-intro` | Same tag pool; **refine in UI** (exclude bounces, DNC, bad emails) before big sends. Rebuild segment after edits. |
| Segment email | **5** · `LPNW Prospect intro` | Published, uses `docs/mautic-prospect-intro-email.html` and `{unsubscribe_text}`. |

**Why two segments that sound similar?**

- **`lpnw-campaign-import-2026`** = the **big pool**: everyone who was imported with tag `lpnw-campaign-2026`. It is **not** meant to be emailed in one go without checks (wrong emails, bounces, unsubscribes, etc.).
- **`lpnw-mailable-prospects-intro`** = a **smaller, safer list** built from that same tag but **tightened in the Mautic UI** (filters/exclusions you add before a real send). Think: “import bucket” vs “who we actually mail.”

**Do segments “clean themselves” when people bounce or unsubscribe?**

**No, not just because you sent mail.** Sending does not shrink a segment by itself.

- A segment only changes when its **rules** match different contacts after **rebuilds** (and when bounces/unsubscribes are **recorded in Mautic**).
- If the rule is only “has tag X”, people can stay in the segment even after they unsubscribe, unless you **exclude** “do not contact” / bounces (wording in the UI varies by version) or remove the tag.
- **Bounce handling** needs the bounce mailbox + **`mautic:email:fetch`** cron; otherwise Mautic may never mark those contacts as bounced.
- So: the “mailable” segment is **not** inherently magic. It is safer only if you **add the right filters** and keep **segments updated** (`mautic:segments:update` on a schedule).

**Segment with only you (test send)**

1. **Segments** → **New** (or **+ New**).
2. Name it clearly, e.g. `LPNW test – admin only`.
3. On the **Filters** tab, add a condition: **Email** → **equals** → `admin@codevall.co.uk` (use your real address; fix typos like `.couk` → `.co.uk`).
4. **Save and close**, then **Rebuild** the segment if Mautic shows that option.
5. Open **Contacts** and confirm the segment contact count is **1**.

**See the email in the builder (preview)**

1. **Channels** → **Emails**.
2. Click the **name** of the email (e.g. `LPNW Prospect intro`). You are now on that email’s page.
3. Click **Edit** (or the pencil / builder entry point Mautic shows for that email).
4. Inside the builder, use **Preview** (often top or sidebar). That is only a **browser preview**; some tokens look wrong until a real send.
5. For a **real** test: send that email to the **one-person segment** above (not the whole import segment).

**“Duplicate” / copy an email (optional)**

On the **Emails** list, open the row menu for an email and choose **Clone** or **Duplicate** (wording varies). That makes a **second copy** you can change without breaking the original. Optional; skip if you only want one intro email.

**WordPress alert log says “WordPress mail” instead of Mautic**

The plugin sends via **Mautic** when: Mautic API credentials are saved **and** VIP / Pro / Free template IDs are set **and** the Mautic API accepts the send. Otherwise it falls back to **`wp_mail`**. The log line **“WordPress mail”** means that row was sent with the fallback (empty `mautic_email_id`).

Checklist:

1. **LPNW Alert Settings**: Mautic URL, API user, API password; VIP / Pro / Free email IDs filled (or open **Dashboard / Settings** once so template IDs can sync from Mautic by name).
2. Mautic templates must match the names the sync looks for (see table at top of this doc): **LPNW Alert — VIP**, **LPNW Alert — Pro**, **LPNW Weekly Digest — Free**.
3. If it still falls back, check the server **PHP error log** for lines starting with `LPNW Mautic:` (API errors or send refusals).

### “Send example” does nothing (no email arrives)

In **Mautic 5**, outbound mail is often **queued** first. If nothing is processing the queue, **Send example**, tests, and real sends can appear to do nothing.

**Fix (on the server that hosts `marketing.land-property-northwest.co.uk`):**

1. Confirm the **Mautic install path** (20i File Manager or support: where `bin/console` lives for that app).
2. Add a **cron job** every **5–15 minutes** (example; adjust PHP path and install path):

```bash
php /path/to/mautic/bin/console messenger:consume email --time-limit=160 --no-interaction --no-ansi
```

Official reference: [Mautic 5 cron jobs](https://docs.mautic.org/en/5.2/configuration/cron_jobs.html) (see **Process Email queue**). Also keep **`mautic:segments:update`**, **`mautic:email:fetch`** (bounces), and for big sends **`mautic:broadcasts:send`** on sensible staggered schedules.

3. In **Configuration → Email Settings**, use **Send test email** after cron is in place. If that works, **Send example** on an email should start working too.
4. Check spam/junk for `hello@land-property-northwest.co.uk` (or whatever **From** is set to).

Until the queue consumer runs, **do not assume** Mautic has “sent” anything just because the UI accepted a click.

**Recreate the email on another Mautic (or after delete):**

```bash
php tools/mautic-seed-prospect-intro-email.php
```

Uses `.env` `MAUTIC_*` and `curl`. Set `MAUTIC_PROSPECT_SEGMENT_ID` if your mailable segment ID is not discovered automatically.

**Manual steps (if you prefer the UI):**

1. **Subject:** `Northwest land and property alerts for your inbox`
2. **HTML:** paste the contents of `docs/mautic-prospect-intro-email.html` (Code view; omit the `<!-- ... -->` comment block if Mautic strips it).
3. **Import:** map CSV columns to **email**, **firstname**, **lastname**, **company** (aliases must match tokens in that file).
4. **Segment** contacts, then send the segment email. Test on yourself first; `{unsubscribe_text}` only works for real contacts, not preview.

### Bounces and unsubscribe

- **Unsubscribe:** `{unsubscribe_text}` is in the prospect template footer. Global wording lives under **Settings → Configuration → Email Settings** (unsubscribe token text).
- **Bounces (production):** use a dedicated mailbox (e.g. `bounces@land-property-northwest.co.uk`) on **Settings → Configuration → Email Settings → Monitored Inbox Settings**:
  - **Custom return path (bounce) address:** same bounce address so receiving MTAs can return bounces there (depends on your SMTP allowing custom envelope).
  - **Default mailbox:** IMAP host **`imap.stackmail.com`** (20i Stackmail TLS cert is `*.stackmail.com`; using `imap.land-property-northwest.co.uk` often fails with “hostname mismatch” in strict clients like Mautic). Port **993**, encryption **SSL/TLS**. **Username** = full mailbox address (e.g. `bounces@land-property-northwest.co.uk`), password = mailbox password.
  - **Bounces → Folder to check:** set explicitly to **`INBOX`** if bounces arrive in the main inbox. Leave **Contact replies** / **Unsubscribe requests** folder fields empty unless you use separate mailboxes or folders for those monitors.
  - **Cron:** Mautic must run **`bin/console mautic:email:fetch`** on a schedule (e.g. every 5–15 minutes) or bounces will not be processed. On 20i, add a cron job pointing at your Mautic install’s PHP/console path (see Mautic 5 cron docs).
- **Never** commit mailbox passwords to the repo. Rotate any password pasted into chat.

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