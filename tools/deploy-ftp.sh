#!/usr/bin/env bash
# Deploy plugin and theme to 20i via FTP (lftp mirror).
# Requires: lftp, env vars FTP_HOST, FTP_USER, FTP_PASS (see .cursor/rules/secrets.mdc).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -z "${FTP_HOST:-}" || -z "${FTP_USER:-}" || -z "${FTP_PASS:-}" ]]; then
	echo "Set FTP_HOST, FTP_USER, FTP_PASS in the environment." >&2
	exit 1
fi

echo "Deploying plugin -> public_html/wp-content/plugins/lpnw-property-alerts/"
lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "
set ftp:ssl-allow true
set ssl:verify-certificate no
set net:max-retries 3
set net:reconnect-interval-base 4
mirror -R --verbose plugin/lpnw-property-alerts public_html/wp-content/plugins/lpnw-property-alerts
quit
"

echo "Deploying theme -> public_html/wp-content/themes/lpnw-theme/"
lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "
set ftp:ssl-allow true
set ssl:verify-certificate no
set net:max-retries 3
set net:reconnect-interval-base 4
mirror -R --verbose theme/lpnw-theme public_html/wp-content/themes/lpnw-theme
quit
"

echo "Done."
