<?php
script('richdocuments', 'richdocuments-personal');
$previewFileAllowedHosts = $_['previewFileAllowedHosts'];
$previewFileApi = $_['previewFileApi'];
?>
<div class="section" id="richdocuments">
	<h2>
		<?php p($l->t('Nextcloud Office')) ?>
	</h2>
	<span id="documents-admin-msg" class="msg"></span>
	<p>
		<label for="templateInputField"><?php p($l->t('Select a template directory')); ?></label>
		<br />
		<input type="text" name="templateInputField" id="templateInputField" value="<?php p($_['templateFolder']); ?>" disabled />
		<button id="templateSelectButton"  aria-label="<?php p($l->t('Select a personal template folder')); ?>">
			<span class="icon-folder" title="<?php p($l->t('Select a personal template folder')); ?>" data-toggle="tooltip">
			</span>
		</button>
		<button id="templateResetButton"  aria-label="<?php p($l->t('Remove personal template folder')); ?>">
			<span  class="icon-delete" title="<?php p($l->t('Remove personal template folder')); ?>" data-toggle="tooltip"></span>
		</button>
	</p>
	<p><em>
		<?php p($l->t('Templates inside of this directory will be added to the template selector of Nextcloud Office.')); ?>
	</em></p>
	<p id="personal-odftemplate">前往 <a href="https://odf.moda.gov.tw/QA/public/odftemplate" target="_blank">共用範本專區<span class="icon-external"></span></a></p>
	<hr>
	<?php if($previewFileAllowedHosts): ?>
		<div>
			<div><?php p($l->t('Generate file preview URL')) ?></div>

			<input type="text" value="" id="url-input">
			<button disabled id="copy-preview-url"><span><?php p($l->t('Generate and copy to clipboard')) ?></span></button>

			<div id="preview-url"></div>

			<ul>
				<?php p($l->t('Available hostnames:')) ?>
				<?php foreach ($previewFileAllowedHosts as $hostname): ?>
					<li><?php p($hostname) ?></li>
				<?php endforeach ?>
			</ul>

		</div>
		<hr>
		<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()) ?>">
			const fileUrlInput = document.querySelector('#url-input');
			const copyBtn = document.querySelector('#copy-preview-url');
			const previewFileApi = '<?php p($previewFileApi) ?>';
			const previewFileAllowedHosts = <?php echo json_encode($previewFileAllowedHosts) ?>;
			let fullPreviewUrl;

			fileUrlInput.addEventListener('input', (e) => {
				fullPreviewUrl = previewFileApi + encodeURIComponent(fileUrlInput.value);
				let isTrustedUrl = false;
				// Check if the URL is in the list.
				previewFileAllowedHosts.forEach((hostname) => {
					if (fileUrlInput.value.includes(hostname)) {
						isTrustedUrl = true;
						return;
					}
				})

				if (isTrustedUrl) {
					copyBtn.removeAttribute('disabled');
				} else {
					copyBtn.setAttribute('disabled', true);
				}
			})

			let timer
			copyBtn.addEventListener('click', (e) => {
				console.log(navigator);
				clearTimeout(timer);
				try {
					navigator.clipboard.writeText(fullPreviewUrl);
				} catch(e) {
					const el = document.createElement('textarea');
					el.value = fullPreviewUrl;
					document.body.appendChild(el);
					el.select();
					document.execCommand('copy');
					document.body.removeChild(el);
				}
				copyBtn.firstChild.textContent = '<?php p($l->t('Copied!')) ?>';
				copyBtn.firstChild.style.color = '#4a4';

				timer = setTimeout(() => {
					copyBtn.firstChild.textContent = '<?php p($l->t('Generate and copy to clipboard')) ?>';
					copyBtn.firstChild.style.color = '';
				}, 800);
			})
		</script>
	<?php endif; ?>
	<p><strong><?php p($l->t('Zotero')) ?></strong></p>
	<?php if ($_['hasZoteroSupport']) { ?>
		<div class="input-wrapper">
			<p><label for="zoteroAPIKeyField"><?php p($l->t('Enter Zotero API Key')); ?></label><br />
				<input type="text" name="zoteroAPIKeyField" id="zoteroAPIKeyField" value="<?php p($_['zoteroAPIKey']); ?>"/>
				<button id="zoteroAPIKeySave"><span title="<?php p($l->t('Save Zotero API key')); ?>" data-toggle="tooltip">Save</span></button>
				<button id="zoteroAPIKeyRemove"><span  class="icon-delete" title="<?php p($l->t('Remove Zotero API Key')); ?>" data-toggle="tooltip"></span></button>
			</p>
			<p><em><?php p($l->t('To use Zotero specify your API key here. You can create your API key in your')); ?> <a href="https://www.zotero.org/settings/keys" target="_blank"><?php p($l->t('Zotero account API settings.')); ?></a></em></p>
		</div>
	<?php } else { ?>
		<p><em><?php p($l->t('This instance does not support Zotero, because the feature is missing or disabled. Please contact the administration.')); ?></em></p>
	<?php } ?>
</div>
