# AGENTS.md

## Cursor Cloud specific instructions

### Overview

This is a WordPress plugin + theme repo (no standalone app). The core product is the `lpnw-property-alerts` plugin in `plugin/` and the GeneratePress child theme in `theme/`. See `BRIEF.md` and `STATUS.md` for product context and priorities.

### Live WordPress login (cloud agents, production site)

Cloud agents and browser automation should **not** guess wp-admin passwords. On **https://land-property-northwest.co.uk** use the **`lpnw-login-as`** must-use plugin (`mu-plugins/lpnw-login-as.php` in the repo; lives on the server under `wp-content/mu-plugins/`).

1. Include **`?nocache=1`** so 20i CDN does not return stale HTML.
2. Add **`&lpnw_login_as=test`** (subscriber / dashboard) or **`&lpnw_login_as=admin`** (wp-admin).
3. Add **`&key=`** — must match **`LPNW_LOGIN_AS_SECRET`** from `wp-config.php` on production. Cursor cloud agents: read the same value from **environment secrets** (e.g. `LPNW_LOGIN_AS_SECRET`). Local dev without that constant still accepts the fallback **`lpnw2026setup`** (see `docs/DEPLOYMENT.md`).

**Ready-to-use URL shape (replace `YOUR_LOGIN_SECRET`):**

- Subscriber (test user): `https://land-property-northwest.co.uk/?nocache=1&lpnw_login_as=test&key=YOUR_LOGIN_SECRET`
- Admin: `https://land-property-northwest.co.uk/?nocache=1&lpnw_login_as=admin&key=YOUR_LOGIN_SECRET`

**Alternative:** one-shot admin login via `tools/lpnw-autologin.php` copied to mu-plugins and `?lpnw_autologin=admin&key=...` — the file **removes itself** after one success. Full security notes: `docs/DEPLOYMENT.md`; batched checks and variants: `docs/VERIFICATION-BATCHES.md`, `docs/PROJECT-RUNBOOK.md`.

### Linting

```bash
composer lint        # PHPCS with WordPress coding standard
composer lint:fix    # Auto-fix violations
```

Linting exits with code 2 when it finds violations — this is normal PHPCS behaviour, not a broken tool.

### Local WordPress dev environment

The repo contains no WordPress core files. To test the plugin/theme locally, a WordPress + MariaDB stack must be set up:

1. Start MariaDB: `sudo mysqld_safe --skip-grant-tables &` then wait a few seconds and fix socket permissions: `sudo chmod 755 /var/run/mysqld/`
2. Download WordPress: `wp core download --path=/var/www/html/wordpress`
3. Create DB and configure: `mariadb -u root -e "CREATE DATABASE IF NOT EXISTS wordpress;"` then `wp config create --path=/var/www/html/wordpress --dbname=wordpress --dbuser=root --dbpass="" --dbhost=localhost` then `wp core install --path=/var/www/html/wordpress --url="http://localhost:8080" --title="LPNW Dev" --admin_user=admin --admin_password=admin --admin_email=admin@example.com`
4. Symlink plugin and theme into WordPress:
   - `ln -sf /workspace/plugin/lpnw-property-alerts /var/www/html/wordpress/wp-content/plugins/lpnw-property-alerts`
   - `ln -sf /workspace/theme/lpnw-theme /var/www/html/wordpress/wp-content/themes/lpnw-theme`
   - For mu-plugins: `mkdir -p /var/www/html/wordpress/wp-content/mu-plugins && for f in /workspace/mu-plugins/*.php; do ln -sf "$f" "/var/www/html/wordpress/wp-content/mu-plugins/$(basename $f)"; done`
5. Activate plugin: `wp plugin activate lpnw-property-alerts --path=/var/www/html/wordpress`
6. Create tables: `wp eval 'LPNW_Activator::activate();' --path=/var/www/html/wordpress`
7. Start dev server: `cd /var/www/html/wordpress && php -S 0.0.0.0:8080 -t .`

WP admin login: `admin` / `admin` at `http://localhost:8080/wp-login.php`.

### Following the agent’s browser (Cursor)

Agent browser automation does **not** open Chrome or Edge on your PC. Cursor runs a **built-in browser** for MCP browser tools.

**Rule for agents:** When the user wants to **follow along** (or they say they could not see the browser last time), use MCP **`browser_navigate`** with **`position`: `"side"`** so the browser opens **beside the editor** and stays visible. Do this on the **first** navigation of the session unless the user prefers full width.

- **Director / user:** Say “open the browser on the side” if the tab does not appear; the agent should use **`position`: `"side"`** on `browser_navigate`.
- **If you see nothing:** Check the **View** menu for browser / simple browser options for your Cursor version, or focus the chat’s browser preview if your layout hides it.
- **You can always mirror manually:** Open the same URL in your own browser (use the **Live WordPress login** URLs above, or `docs/DEPLOYMENT.md`).

### Gotchas

- The `mu-plugins/` directory may contain broken symlinks for files that were removed upstream (e.g. `lpnw-otm-price-backfill.php`, `lpnw-refresh-pages.php`). Only symlink files that actually exist in `/workspace/mu-plugins/`.
- The PHP built-in server is single-threaded; concurrent requests (e.g. during feed runs) may queue. This is fine for dev/testing.
- Feed crons fire on the first page load after plugin activation, which can make the initial request slow. This is expected.
- No automated test suite exists in the repo. Testing is manual via the WP admin and frontend.
- Deployment is via FTP to 20i hosting. See `docs/DEPLOYMENT.md`.
