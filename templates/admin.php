<div id="ngsign-settings" class="section">
	<h2><?php p($l->t('NGSign')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Connect this Nextcloud instance to your NGSign tenant. The API token stays on the server.')); ?></p>
	<form id="ngsign-settings-form" action="<?php p($_['saveUrl']); ?>" method="post">
		<p><label for="ngsign-base-url"><?php p($l->t('NGSign server URL')); ?></label><input id="ngsign-base-url" name="baseUrl" type="url" required value="<?php p($_['baseUrl']); ?>" placeholder="https://sandbox.ng-sign.com/server"></p>
		<p><label for="ngsign-api-token"><?php p($l->t('API bearer token')); ?></label><input id="ngsign-api-token" name="apiToken" type="password" autocomplete="new-password" placeholder="<?php p($_['configured'] ? $l->t('Configured — leave blank to keep it') : $l->t('Paste the NGSign API token')); ?>"></p>
		<p><label for="ngsign-expiration-days"><?php p($l->t('Transaction expiration (days)')); ?></label><input id="ngsign-expiration-days" name="expirationDays" type="number" min="1" max="365" required value="<?php p($_['expirationDays']); ?>"><em><?php p($l->t('Default: 15 days. Expired transactions are no longer polled.')); ?></em></p>
		<p><label><input name="debug" type="checkbox" value="1"<?php p($_['debug'] ? ' checked' : ''); ?>> <?php p($l->t('Debug mode: show masked NGSign request and response details to the user launching a signature.')); ?></label></p>
		<p><button type="submit" class="primary"><?php p($l->t('Save')); ?></button><span id="ngsign-settings-message" aria-live="polite"></span></p>
	</form>
</div>
