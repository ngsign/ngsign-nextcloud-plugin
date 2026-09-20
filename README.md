# NGSign for Nextcloud

This app adds the **Sign with NGSign** action to PDFs in the Files app. The user selects the action, enters one or more signers, and the Nextcloud server sends the document to NGSign and starts a `BY_MAIL` transaction.

## About NGSign

[NGSign](https://www.ng-sign.com) is an electronic-signature platform. This plugin connects Nextcloud to NGSign so users can initiate and track PDF-signing transactions directly from their Nextcloud workspace.

## Deliverables

| Deliverable | Purpose |
| --- | --- |
| `ngsign` app source | Install on an existing self-hosted Nextcloud instance. |
| `dist/ngsign-<version>.tar.gz` archive | Standalone customer delivery. |
| `nextcloud-ngsign` Docker Hub image | Deploy Nextcloud and NGSign together. |

## Installation on an existing Nextcloud instance

From the repository clone, generate the archive without development sources:

```sh
npm ci
npm run package-app
```

Extract `dist/ngsign-<version>.tar.gz` into the instance’s `custom_apps` directory; the final directory must be `<nextcloud>/custom_apps/ngsign`. Then enable the app:

```sh
sudo -u www-data php occ app:enable ngsign
```

The web-server user must be able to read the directory. Under **Administration → Additional settings → NGSign**, configure the NGSign URL, token, and expiration period. Enable the Nextcloud cron job: it is required to track completed signatures.

## Docker Hub deployment

Create a `.env` file from `.env.example`, defining at least the passwords, `NEXTCLOUD_TRUSTED_DOMAINS`, and the desired image:

```sh
IMAGE_NAME=<dockerhub-user>/nextcloud-ngsign:0.1.0
docker compose --env-file .env -f compose.production.yaml up -d
```

The `cron` service is included and automatically retrieves signed documents. Back up the `nextcloud` and `db` volumes before an upgrade.

## Local development

1. Copy this directory to `<nextcloud>/custom_apps/ngsign` (or archive it and install it from **Apps → Your apps**).
2. Enable **NGSign** in Apps.
3. Under **Administration → Additional settings → NGSign**, enter the NGSign server URL and your tenant’s bearer token.

By default, the URL targets the sandbox: `https://sandbox.ng-sign.com/server`.

The image is based on `nextcloud:34.0.4-apache`, and the app declares compatibility with Nextcloud **30 through 34**. Nextcloud 34 is the version used for the published image; the image is pinned so rebuilding does not implicitly change its version.

### Local startup

```sh
cp .env.example .env
# Edit .env: replace the three passwords and, if needed, NGSIGN_API_TOKEN.
docker compose up --build -d
```

Open `http://localhost:8080`. The app is automatically enabled at startup, and the `NGSIGN_BASE_URL` and `NGSIGN_API_TOKEN` variables are injected server-side. Never put the token in the Dockerfile or in a published image.

### Docker Hub publishing

The [docker-publish.yml](.github/workflows/docker-publish.yml) workflow publishes a multi-architecture image (`linux/amd64`, `linux/arm64`) when a `v*` Git tag is pushed. Create the following GitHub secrets:

- `DOCKERHUB_USERNAME` — your Docker Hub username;
- `DOCKERHUB_TOKEN` — a Docker Hub access token with write permission.

Then run:

```sh
git tag v0.1.0
git push origin v0.1.0
```

The image will be published as `<DOCKERHUB_USERNAME>/nextcloud-ngsign:0.1.0` and `:latest`.

## Implemented NGSign flow

1. `POST /protected/transaction/pdfs` with the PDF encoded as Base64.
2. `POST /protected/transaction/{transactionId}/launch` with one `sigConf` per signer: `CERTIFIED_TIMESTAMP` signature, `BY_MAIL` mode, and `NONE` OTP.
3. `GET /any/transaction/{transactionId}` to read the transaction status and the status of each signer (used by the **Check status** button and the cron job).
4. `GET /any/transaction/{transactionId}/pdfs/{documentId}` to retrieve the signed PDF once the transaction reaches `SIGNED` status.
5. `POST /protected/transaction/{transactionId}/cancel` to cancel a transaction that has not yet been signed.

The bearer token is never exposed to the browser. Before sending the file to NGSign, the controller reads it from the connected user’s own storage.

The expiration period is configurable in the NGSign settings (15 days by default). It is sent to NGSign as `expirationDate`; the local cron job also stops polling after that date and removes the expired transaction from local tracking.

## Transactions page

Available from the **NGSign** navigation entry (`/apps/ngsign/transactions`), this page lists the signature transactions started by the connected user:

- **Statistics**: total, in progress, signed.
- **Table**: document, status (with a colored Pending/Signed/Refused/Cancelled badge), signers with their individual statuses and the next expected signer, creation date, expiration date, and actions.
- **Pagination**: 10 transactions per page, newest first.
- **Row actions**:
  - **Check status** — queries NGSign live; disabled once the transaction is `SIGNED`.
  - **Cancel** — cancels the transaction in NGSign; available while the status is neither `SIGNED` nor `CANCELLED`, with confirmation before the request.
  - **Download** — enabled only after the signed PDF has actually been retrieved from NGSign (see the cron job below).

The `TransactionSyncService` (`lib/Service/TransactionSyncService.php`) centralizes the logic called by both the **Check status** button and the cron job: refresh the transaction and signer statuses, download and rename the signed PDF (`signed_<original name>`) as soon as the transaction reaches `SIGNED`, then notify its creator.

### Notifications

As soon as a transaction reaches `SIGNED` status, its creator receives a Nextcloud notification (bell and notification center) inviting them to view the signed document, with a direct link to the Transactions page. It is sent only once per transaction (through the internal `notifiedAt` marker), whether triggered by a manual check or the cron job.

### NGSign diagnostics

Under **Administration → Additional settings → NGSign**, enable **Debug mode** and save. After each launch, the signing window displays NGSign requests and responses. The token is masked, and the PDF’s Base64 contents are never displayed (only its size is shown). Disable this mode after diagnostics are complete.

## Tenant-specific adjustments

The initial signature position follows the supplied Postman collection: page 1, `xAxis: 81`, `yAxis: 44.28125`. If your tenant’s API returns a different format (upload, status, cancellation, and so on), adjust the requests and identifier extraction in `lib/Service/NGSignClient.php`.

## License

GNU General Public License v3.0 (see LICENSE).

This plugin integrates with Maarch Courrier, itself distributed under GPLv3: it extends its classes and runs inside its process (a derivative work).

Copyright © 2026 NG Technologies. This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License version 3 as published by the Free Software Foundation.
