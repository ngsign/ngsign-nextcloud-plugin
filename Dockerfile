# Pinned Nextcloud release: do not use `latest` for a published production image.
FROM nextcloud:34.0.4-apache

LABEL org.opencontainers.image.title="Nextcloud with NGSign" \
	org.opencontainers.image.description="Nextcloud 34 with the NGSign PDF signature app" \
	org.opencontainers.image.source="https://github.com/REPLACE_WITH_YOUR_ORG/ngsign-nextcloud-plugin"

# /usr/src/nextcloud is copied to /var/www/html by the upstream entrypoint on first run.
COPY --chown=www-data:www-data appinfo/ /usr/src/nextcloud/custom_apps/ngsign/appinfo/
COPY --chown=www-data:www-data lib/ /usr/src/nextcloud/custom_apps/ngsign/lib/
COPY --chown=www-data:www-data js/ /usr/src/nextcloud/custom_apps/ngsign/js/
COPY --chown=www-data:www-data css/ /usr/src/nextcloud/custom_apps/ngsign/css/
COPY --chown=www-data:www-data img/ /usr/src/nextcloud/custom_apps/ngsign/img/
COPY --chown=www-data:www-data l10n/ /usr/src/nextcloud/custom_apps/ngsign/l10n/
COPY --chown=www-data:www-data templates/ /usr/src/nextcloud/custom_apps/ngsign/templates/
COPY docker/hooks/before-starting/10-ngsign.sh /docker-entrypoint-hooks.d/before-starting/10-ngsign.sh

RUN chmod 0755 /docker-entrypoint-hooks.d/before-starting/10-ngsign.sh
