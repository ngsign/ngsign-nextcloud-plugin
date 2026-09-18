#!/bin/sh
set -eu

occ='php /var/www/html/occ'

# The official image executes this hook after its installation/upgrade work and
# before Apache starts.
if [ ! -f /var/www/html/config/config.php ]; then
	exit 0
fi

# `custom_apps` is a persistent Nextcloud volume. Synchronize the bundled app
# on every image update; otherwise an existing volume would retain old code.
rsync -rlD --delete /usr/src/nextcloud/custom_apps/ngsign/ /var/www/html/custom_apps/ngsign/

$occ app:enable ngsign --no-interaction >/dev/null

if [ -n "${NGSIGN_BASE_URL:-}" ]; then
	$occ config:app:set ngsign base_url --value="${NGSIGN_BASE_URL%/}" >/dev/null
fi

# Deliberately do not echo the token. An empty value preserves the existing
# configuration, allowing the setting to be managed from Nextcloud instead.
if [ -n "${NGSIGN_API_TOKEN:-}" ]; then
	$occ config:app:set ngsign api_token --value="$NGSIGN_API_TOKEN" >/dev/null
fi
