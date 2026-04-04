# LPNW verification batches (live site)

Use this for periodic audits: **code defines intent**, these steps confirm **production behaviour**. Run after deploys or when marketing claims drift from reality.

## Browser / VM access (agents and humans)

Agents **can** exercise the live subscriber UI the same way you do:

1. **`mu-plugins/lpnw-login-as.php`** (upload to live `wp-content/mu-plugins/` if not already there).  
   - Visit once: `https://land-property-northwest.co.uk/?lpnw_login_as=test&key=lpnw2026setup`  
   - Logs in as the **test** subscriber (`admin@codevall.co.uk` per file) and redirects to `/dashboard/`.  
   - **`admin`** target sends first admin user to wp-admin.  
   - **Change the static `key` in the file** before uploading if it could have leaked; **remove the file after the session** (see `docs/PROJECT-RUNBOOK.md`).

2. **`tools/lpnw-autologin.php`** — one-shot **admin** login; file deletes itself after success. Upload to `mu-plugins`, hit URL with `key`, then confirm removal.

3. **`/?nocache`** — reduces stale HTML from 20i CDN while checking theme/plugin changes.

**Security:** These scripts are powerful. Treat keys like passwords. Do not leave `lpnw-login-as.php` on the server after review.

---

## Batch A — Schedules and ingest cadence

**Intent (code):**

| Job | Interval | Role |
|-----|----------|------|
| `lpnw_cron_portals` | 15 min | Rightmove, Zoopla, OnTheMarket (sequential, 2s gap) |
| `lpnw_cron_dispatch_alerts` | 15 min | Process alert queue (Mautic / wp_mail) |
| `lpnw_cron_planning` | 6 h | Planning feed (if enabled) |
| `lpnw_cron_epc` | daily | EPC (if enabled + API credentials) |
| `lpnw_cron_landregistry` | daily | Land Registry CSV path |
| `lpnw_cron_auctions` | **15 min** (plugin 1.0.29+) | Auction feeds (Pugh, SDL, AHNW, Allsop) if enabled |
| `lpnw_cron_free_digest` | weekly | Free tier digest |

**Should EPC / planning / Land Registry run every 15 minutes?** Usually **no**:

- **EPC Open Data** is not a live “new listing” firehose like portals; daily (or a few times per day) is normal and avoids API rate and noise.
- **Planning** is heavy and authority-dependent; 6 hours is already aggressive unless you measure value and cost.
- **Land Registry** monthly CSV is inherently **not** a 15-minute signal.

Keep **15 minutes** for what drives the **instant listing** USP: **portals + dispatch**. Tighten EPC/planning only if product research shows subscribers want that latency and the APIs tolerate it.

**Zoopla:** Still often **Cloudflare-blocked** from datacentre IPs. The feed rotates **www / mobile** hosts, **User-Agents**, and **browser-like headers**; a lasting fix may need **allowlisted egress**, **licensed data**, or **residential proxy** (legal/host-approved). Watch **Feed Status** and **PHP error_log** lines prefixed `[LPNW feed=zoopla]`.

**Checks:**

- [ ] **LPNW Alerts → Dashboard:** “Next scheduled cron runs” shows **Auction feeds** on the **15-minute** cadence alongside portals (after 1.0.29 deploy and one page load).
- [ ] **Feed Status:** Recent rows **completed**, `properties_new` plausible per source (expect **Zoopla 0** if upstream blocks).
- [ ] **Traffic / external cron:** If you rely on `?lpnw_cron=tick&key=…`, confirm the ping fires (runbook: 20i WAF may block bare `wp-cron.php`).

**USP note:** “Instant” means **as soon as the next portal run + dispatch run see a new row and a match**. Worst case roughly **up to ~15 minutes** plus queue lag—not millisecond real time. Tightening dispatch to **5 minutes** would need a new cron interval and a one-time reschedule (not shipped in this doc).

---

## Batch B — Listing dates vs “NEW” badges

**Intent (code after fix):** For **Rightmove / Zoopla / OnTheMarket**, **NEW / JUST LISTED** uses **`first_listed_date`** from the portal when present. If the portal did not give a date, the card shows **“First seen in LPNW: …”** and **does not** show NEW based only on ingest time.

**Checks:**

- [ ] Pick a **Rightmove** card with **Listed X days ago** in our UI; confirm **no NEW** badge when X > 2 days (portal date).
- [ ] Pick a genuinely new portal listing; confirm **NEW** or **JUST LISTED** matches portal timing.
- [ ] Compare one listing’s **our line** vs **portal page** (spot-check for parser drift).

---

## Batch C — Preferences save and reload

**Intent:** `public/js/lpnw-public.js` POSTs to `admin-ajax.php` → `lpnw_save_preferences` → `LPNW_Subscriber::save_preferences()` → `lpnw_subscriber_preferences` table.

**Checks (logged-in subscriber):**

- [ ] `/preferences/` — change areas, prices, alert types, frequency → **Save** → success notice.
- [ ] **Save again without changing anything** → should still show success (plugin 1.0.28+ fixed `wpdb->update` returning `0` for “no row changed”).
- [ ] Hard refresh; values **persist**.
- [ ] **Dashboard** coverage / email preview still load without PHP errors.
- [ ] **Free vs Pro vs VIP:** Free cannot pick `instant` if UI hides it; VIP sees **off_market** toggle when tier is VIP.
- [ ] If save still fails: browser **Network** tab → `admin-ajax.php` → response JSON; check for `403` (nonce/cache plugin), `-1` (logged out), or DB error in server logs.

---

## Batch D — Match → queue → send

**Checks:**

- [ ] **Alert Log:** rows move **queued → sent** for a test Pro user (or observe over 24h).
- [ ] **Mautic** (if used): template IDs and **Settings** sync (see runbook).
- [ ] **VIP priority:** Confirm documented delay (e.g. ~30 min before Pro) if still claimed in product copy—verify in `LPNW_Dispatcher` behaviour.

---

## Batch E — Off-market (VIP)

**Intent:** **No automated off-market scanner.** Staff add deals in wp-admin (**LPNW → Add off-market**). **VIP users** can submit via **`[lpnw_submit_off_market]`** (also embedded on **dashboard** for VIP tier): `admin-post.php` → `LPNW_Off_Market_Submit`, same `off_market` pipeline + rate limit + admin email. **Auto-removal when a portal lists the same property** is **not** implemented yet (needs matching rules + legal review).

**Checks:**

- [ ] Admin form creates row; VIP with **Off-market** enabled gets queue entries.
- [ ] **VIP dashboard:** submission section visible; submit test row; admin receives email; Feed / browse shows `off_market` source.
- [ ] **Non-VIP** sees upgrade message on shortcode; **logged-out** sees login prompt.
- [ ] Product copy does not promise **automated** off-market discovery unless you add a feed + legal review.

**Future product direction:** **Portal-dedup** (hide or flag user off-market when a portal match appears) is a separate build: normalise address/postcode, handle false positives, terms of use.

**Future:** Automated “off-market” would need a **defined lawful source** (partnership feed, opted-in network, etc.), not portal scraping.

---

## Batch F — Public surfaces

- [ ] `/properties/` filters and pagination; guest page-2 gate if applicable.
- [ ] `/map/` markers load; filters work.
- [ ] Contact form AJAX success.
- [ ] WooCommerce checkout → tier changes → dashboard reflects tier.

## Batch G — Content and SEO (smoke)

- [ ] Home, pricing, about, contact: **200**, no obvious PHP warnings (WP_DEBUG off on live).
- [ ] **Rank Math / Reading:** indexability matches launch intent (runbook: No Index).
- [ ] **OG / Twitter** preview for home + one post (Facebook debugger).

## Batch H — Data sources honesty

- [ ] **Feed Status** table: Zoopla row behaviour documented for stakeholders (0 new vs block).
- [ ] **EPC** settings: key present if you claim live EPC alerts.

---

## Recording results

Append a dated line to **`docs/PROJECT-RUNBOOK.md`** “Last live verification” when a full pass is done, or open a short internal note with pass/fail per batch.
