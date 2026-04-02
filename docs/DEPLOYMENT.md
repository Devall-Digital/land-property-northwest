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

## Automated mirror (from repo root)

With `lftp` installed and `FTP_HOST`, `FTP_USER`, `FTP_PASS` in the environment:

```bash
./tools/deploy-ftp.sh
```

This mirrors `plugin/lpnw-property-alerts/`, `theme/lpnw-theme/`, and `mu-plugins/` into `public_html/wp-content/` on the 20i package (same layout as manual upload). The script sets `ssl:verify-certificate no` in lftp because some FTP hosts present a chain that fails verification in CI; use SFTP or tighten SSL in your own environment if you prefer.

### Cron URL secret (`LPNW_CRON_SECRET`)

If you define `LPNW_CRON_SECRET` in `wp-config.php`, the custom cron endpoint requires `?lpnw_cron=tick&key=YOUR_SECRET`. Without the constant, behaviour stays open (legacy). Prefer defining the constant on production and updating EasyCron / 20i jobs to include `&key=...`.

## Quick Deploy Script

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
