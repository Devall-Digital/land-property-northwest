# Deployment Guide

How to deploy changes from this repo to the live 20i hosting.

## FTP Deployment

### Connection Details

Get these from 20i panel > Manage Hosting > FTP Users:

- **Host:** (your 20i FTP host)
- **Port:** 21 (FTP) or 22 (SFTP)
- **Username:** (your FTP username)
- **Password:** (your FTP password)
- **Root directory:** Usually `/` or `/public_html/`

### What Goes Where

| Repo Path | Server Path |
|-----------|-------------|
| `plugin/lpnw-property-alerts/` | `wp-content/plugins/lpnw-property-alerts/` |
| `theme/lpnw-theme/` | `wp-content/themes/lpnw-theme/` |
| `mu-plugins/` (e.g. `lpnw-cron-endpoint.php`) | `wp-content/mu-plugins/` |

### Deploying Plugin Updates

1. Connect via FTP
2. Navigate to `wp-content/plugins/`
3. Upload the entire `lpnw-property-alerts/` folder (overwrite existing)
4. If database schema has changed, deactivate and reactivate the plugin in WP admin to trigger the activator

### Deploying Theme Updates

1. Connect via FTP
2. Navigate to `wp-content/themes/`
3. Upload the entire `lpnw-theme/` folder (overwrite existing)
4. No further action needed; changes take effect immediately

## Automated mirror (from repo root, Linux / macOS / CI)

With `lftp` installed and `FTP_HOST`, `FTP_USER`, `FTP_PASS` in the environment:

```bash
./tools/deploy-ftp.sh
```

This mirrors `plugin/lpnw-property-alerts/`, `theme/lpnw-theme/`, and `mu-plugins/` into `public_html/wp-content/` on the 20i package (same layout as manual upload). The script sets `ssl:verify-certificate no` in lftp because some FTP hosts present a chain that fails verification in CI; use SFTP or tighten SSL in your own environment if you prefer.

## PowerShell deploy (Windows)

From the repo root, with `.env` filled in (copy from `.env.example`):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\deploy-ftp.ps1
```

This uploads `plugin/lpnw-property-alerts/`, `theme/lpnw-theme/`, and any `mu-plugins/*.php` to `public_html/wp-content/`. Never commit `.env`.

### 20i CDN cache (seeing your changes)

20i’s CDN can cache full pages and assets. After a deploy, if the site still looks old, open **`https://YOUR-DOMAIN/?nocache`** or verify while **logged into WordPress as admin** (both usually bypass or refresh reliably). Purge stack cache in the 20i panel if you use it alongside the CDN.

### Pricing / About / Home HTML (stored in the database)

Some marketing pages are **WordPress page post_content**, not read directly from PHP on every request. After changing **`class-lpnw-page-content.php`**, refresh the database:

1. **Recommended (plugin 1.0.17+):** while logged in as **administrator**, visit  
   `https://YOUR-DOMAIN/?nocache=1&lpnw_update=pages`  
   (no `key` needed). Or use the same URL with **`&key=...`** if you are not logged in: define **`LPNW_PAGE_SYNC_SECRET`** in `wp-config.php` and pass that value, or use the same value as **`LPNW_CRON_SECRET`**, or the legacy default **`lpnw2026setup`** if neither constant is set.  
   **20i CDN note:** the **home URL** can be edge-cached as full HTML, so the sync may appear to “do nothing” (you still see the normal page). If that happens, run the same query string on another path, e.g. **`https://YOUR-DOMAIN/pricing/?nocache=1&lpnw_update=pages&key=...`** — the handler runs on any front request.
2. **Legacy:** upload **`tools/lpnw-update-pages.php`** or **`mu-plugins/lpnw-update-pages.php`** and hit the URL once (that copy **self-deletes**).

If you skip the sync, live pages keep **old HTML** (e.g. pricing table without recent markup).

### Cron URL secret (`LPNW_CRON_SECRET`)

If you define `LPNW_CRON_SECRET` in `wp-config.php`, the custom cron endpoint requires `?lpnw_cron=tick&key=YOUR_SECRET`. Without the constant, behaviour stays open (legacy). Prefer defining the constant on production and updating EasyCron / 20i jobs to include `&key=...`.

### Emergency admin login (`lpnw-login-as.php`)

**During development:** the repo uses a **default key** in code (`lpnw2026setup`) so you can open:

- `https://YOUR-DOMAIN/?lpnw_login_as=admin&key=lpnw2026setup` → wp-admin  
- `https://YOUR-DOMAIN/?lpnw_login_as=test&key=lpnw2026setup` → test subscriber (`admin@codevall.co.uk` in the file)

`tools/lpnw-autologin.php` (when placed in mu-plugins) uses the same rule: default key, or override below.

**Before public launch:** define a long random secret in `wp-config.php` (it overrides the default):

```php
define( 'LPNW_LOGIN_AS_SECRET', 'paste-a-long-random-string-here' );
```

Then use `&key=` matching that value. **Remove** `lpnw-login-as.php` from the server when you no longer need it, or leave only the wp-config secret with no default (customise the mu-plugin for production-only builds).

**Note:** `lpnw-autologin.php` **deletes itself** after one successful admin login; `lpnw-login-as.php` does **not** (so agents and humans can reuse it during development).

## Quick Deploy Script (lftp)

If you have `lftp` or `ncftp` installed, you can script the deployment:

```bash
# Set your credentials
FTP_HOST="your-ftp-host"
FTP_USER="your-ftp-user"
FTP_PASS="your-ftp-pass"

# Deploy plugin
lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "
mirror -R --delete plugin/lpnw-property-alerts/ wp-content/plugins/lpnw-property-alerts/
quit
"

# Deploy theme
lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "
mirror -R --delete theme/lpnw-theme/ wp-content/themes/lpnw-theme/
quit
"
```

## Version Bumping

When making a release:

1. Update `LPNW_VERSION` in `plugin/lpnw-property-alerts/lpnw-property-alerts.php`
2. Update `Version:` in `plugin/lpnw-property-alerts/lpnw-property-alerts.php` header
3. Update `Version:` in `theme/lpnw-theme/style.css` header
4. Commit, tag, and push

## Environment Differences

- **Local/dev:** No WordPress environment; this repo contains only the plugin and theme source code. For local WordPress development, use Local by Flywheel or similar.
- **Production:** Full WordPress installation on 20i. The plugin and theme are uploaded into the existing WordPress `wp-content` directory.
