# Deployment Guide

How to deploy changes from this repo to the live 20i hosting.

## FTP Deployment

### Connection Details

Get these from 20i panel > Manage Hosting > FTP Users:

- **Host:** (your 20i FTP host)
- **Port:** 21 (FTP) or 22 (SFTP). On 20i, SFTP may use the same hostname as FTP; if login fails, confirm in the panel which protocol and port apply to your user.
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

For **20i StackCP** hosts such as `ftp.gb.stackcp.com`, the script **defaults to SFTP** (same `FTP_HOST`, `FTP_USER`, `FTP_PASS` as in the panel). To **force plain FTP** instead:

```bash
FTP_USE_SFTP=0 ./tools/deploy-ftp.sh
```

To **force SFTP** on a non-StackCP host:

```bash
FTP_USE_SFTP=1 ./tools/deploy-ftp.sh
```

This mirrors `plugin/lpnw-property-alerts/`, `theme/lpnw-theme/`, and `mu-plugins/` into `public_html/wp-content/` on the 20i package (same layout as manual upload). The script sets `ssl:verify-certificate no` for FTP because some hosts present a chain that fails verification in CI.

**530 Login failed / FTP locking:** 20i can restrict FTP/SFTP to allowed IPs. Automated runs from cloud CI or Cursor agents may fail even when your home IP works. Fix: allowlist the runner IP in 20i, disable FTP locking for that user, or run `./tools/deploy-ftp.sh` from your own machine.

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
   (no `key` needed when your browser session is already an admin). If you are **not** logged in, you must pass **`&key=...`** matching **`LPNW_PAGE_SYNC_SECRET`** or **`LPNW_CRON_SECRET`** in `wp-config.php`. There is **no** default key: anonymous requests without a configured secret are denied.  
   **20i CDN note:** the **home URL** can be edge-cached as full HTML, so the sync may appear to “do nothing” (you still see the normal page). If that happens, run the same query string on another path, e.g. **`https://YOUR-DOMAIN/pricing/?nocache=1&lpnw_update=pages&key=...`** — the handler runs on any front request.
2. **Legacy:** avoid **`mu-plugins/lpnw-update-pages.php`** on production (fixed dev key). Remove it from the server if present; use the plugin handler above instead.

If you skip the sync, live pages keep **old HTML** (e.g. pricing table without recent markup).

### Cron URL secret (`LPNW_CRON_SECRET`)

Define `LPNW_CRON_SECRET` in `wp-config.php` as a long random string. The custom cron URL **`?lpnw_cron=tick`** only runs when **`&key=`** matches that value. If the constant is missing or empty, the endpoint returns **403** (fail closed). Update EasyCron / 20i jobs to include `&key=YOUR_SECRET`.

### Emergency admin login (`lpnw-login-as.php`)

**Production:** define **`LPNW_LOGIN_AS_SECRET`** in `wp-config.php` and use **`&key=`** with that value:

- `https://YOUR-DOMAIN/?nocache=1&lpnw_login_as=admin&key=YOUR_SECRET` → wp-admin  
- `https://YOUR-DOMAIN/?nocache=1&lpnw_login_as=test&key=YOUR_SECRET` → test subscriber (`admin@codevall.co.uk` in the file)

**Requirement:** `LPNW_LOGIN_AS_SECRET` must be **defined and non-empty** in `wp-config.php`. If it is missing, the login-as URL does nothing (no default key in the repo).

`tools/lpnw-autologin.php` (when placed in mu-plugins) uses the same rule.

**Remove** `lpnw-login-as.php` from the server when you no longer need it.

**Cursor / cloud agents:** add the same `LPNW_LOGIN_AS_SECRET` value to the agent **environment secrets** (or your runbook) so automated browsers can build the login URL with `&key=...`. The secret lives in **two places**: `wp-config.php` on the server (authorises the request) and the agent env (supplies the key on the URL). It is **not** “stored only in the cloud agent”; the server must define it too.

### One-shot `tools/*.php` URLs (`&key=`)

Scripts copied to `mu-plugins/` (or run from the site root) accept **`&key=`** only when it matches **`LPNW_CRON_SECRET`**, **`LPNW_PAGE_SYNC_SECRET`**, or **`LPNW_LOGIN_AS_SECRET`** from `wp-config.php` (at least one must be defined). There is **no** hard-coded fallback key. **`mu-plugins/lpnw-tool-auth-loader.php`** loads `lpnw_tool_query_key_ok()` for mu-plugins; keep it deployed alongside other mu-plugins.

**Note:** `lpnw-autologin.php` **deletes itself** after one successful admin login; `lpnw-login-as.php` does **not** (so agents can reuse the same URL with `&key=`).

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
