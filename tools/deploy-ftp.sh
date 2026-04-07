#!/usr/bin/env bash
# Deploy plugin and theme to 20i via lftp mirror (FTP or SFTP).
# Requires: lftp, env vars FTP_HOST, FTP_USER, FTP_PASS (see .cursor/rules/secrets.mdc).
#
# 20i StackCP: SFTP on port 22 is the usual choice; the script defaults to SFTP when
# FTP_HOST contains stackcp.com. Plain FTP can 530 from some networks; use FTP_USE_SFTP=0 to force FTP.
#
# SFTP:   FTP_USE_SFTP=1 ./tools/deploy-ftp.sh
# Plain FTP: FTP_USE_SFTP=0 ./tools/deploy-ftp.sh
# Default: SFTP for StackCP / 20i hosts (*.stackcp.com); FTP otherwise.
# Optional: FTP_PORT=21 (FTP explicit port)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -z "${FTP_HOST:-}" || -z "${FTP_USER:-}" || -z "${FTP_PASS:-}" ]]; then
	echo "Set FTP_HOST, FTP_USER, FTP_PASS in the environment." >&2
	exit 1
fi

# Auto: 20i / StackCP SFTP uses the same hostname as FTP (see .env.example).
use_sftp=false
if [[ "${FTP_USE_SFTP:-}" == "1" || "${FTP_USE_SFTP:-}" == "yes" ]]; then
	use_sftp=true
elif [[ "${FTP_USE_SFTP:-}" == "0" || "${FTP_USE_SFTP:-}" == "no" ]]; then
	use_sftp=false
elif [[ "${FTP_HOST}" == *stackcp.com* ]]; then
	use_sftp=true
fi

if [[ "$use_sftp" == true ]]; then
	REMOTE_OPEN=( -u "$FTP_USER,$FTP_PASS" "sftp://${FTP_HOST}" )
	LFTP_EXTRA="
set sftp:auto-confirm yes
"
else
	FTP_PORT="${FTP_PORT:-21}"
	REMOTE_OPEN=( -u "$FTP_USER,$FTP_PASS" -p "${FTP_PORT}" "$FTP_HOST" )
	LFTP_EXTRA="
set ftp:ssl-allow true
set ssl:verify-certificate no
"
fi

run_mirror() {
	local src="$1"
	local dest="$2"
	local label="$3"
	echo "Deploying ${label} -> ${dest}"
	# shellcheck disable=SC2086
	lftp "${REMOTE_OPEN[@]}" -e "
${LFTP_EXTRA}
set net:max-retries 3
set net:reconnect-interval-base 4
mirror -R --verbose ${src} ${dest}
quit
"
}

run_mirror "plugin/lpnw-property-alerts" "public_html/wp-content/plugins/lpnw-property-alerts" "plugin"
run_mirror "theme/lpnw-theme" "public_html/wp-content/themes/lpnw-theme" "theme"
run_mirror "mu-plugins" "public_html/wp-content/mu-plugins" "mu-plugins"

echo "Done."
