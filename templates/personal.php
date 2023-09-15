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
		<button id="templateSelectButton">
			<span class="icon-folder" title="<?php p($l->t('Select a personal template folder')); ?>" data-toggle="tooltip">
			</span>
		</button>
		<button id="templateResetButton">
			<span  class="icon-delete" title="<?php p($l->t('Remove personal template folder')); ?>" data-toggle="tooltip"></span>
		</button>
	</p>
	<p><em>
		<?php p($l->t('Templates inside of this directory will be added to the template selector of Collabora Online.')); ?>
	</em></p>
	<p id="personal-odftemplate">前往 
		<a href="https://odf.nat.gov.tw/QA/web/odftemplate.html" target="_blank">
			共用範本專區
			<span class="icon-external"></span>
		</a>
	</p>
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
</div>
