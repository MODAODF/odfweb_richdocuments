((function(OCA) {
	OCA.FilesPdfEditor = OCA.FilesPdfEditor || {}

	/**
	 * @namespace OCA.FilesPdfEditor.PreviewPlugin
	 */
	OCA.FilesPdfEditor.PreviewPlugin = {
		attach(fileList) {
			const allowedLists = ['files', 'files.public']
			if (allowedLists.indexOf(fileList.id) < 0) return
			this._extendFileActions(fileList)
		},

		/**
		 * @param {Object} fileList the fileList info
		 * @private
		 */
		_extendFileActions(fileList) {
			const supportedMimes = [
				'application/vnd.oasis.opendocument.text',
				'application/vnd.oasis.opendocument.spreadsheet',
				'application/vnd.oasis.opendocument.presentation',
				'application/msword',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/vnd.ms-excel',
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'application/vnd.ms-powerpoint',
				'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			]
			for (const mime of supportedMimes) {
				fileList.fileActions.registerAction({
					name: '_toPDF',
					displayName: t('richdocuments', 'Save as PDF'),
					mime,
					permissions: OC.PERMISSION_READ,
					icon: OC.imagePath('richdocuments', 'topdf'),
					actionHandler: toPDF
				})
			}

			async function toPDF(fileName, context) {
				const isCreatable = (context.fileList.dirInfo.permissions & OC.PERMISSION_CREATE) !== 0 && context.fileList.$el.find('#free_space').val() !== '0'
				if (!isCreatable) {
					OC.dialogs.alert(
						t('files', 'You don’t have permission to upload or create files here'),
						t('richdocuments', 'Error')
					)
					return
				}

				try {
					const response = await fetch(OC.generateUrl('/apps/richdocuments/convert/check'))
					if (!response.ok) {
						const msg = await response.text()
						throw msg
					}
				} catch (error) {
					OC.dialogs.alert(
						t('The [convet-to] is not working or unavailable, please contact the system administrator'),
						t('richdocuments', 'Error'))
					return
				}

				const bar = fileList._operationProgressBar
				bar.showProgressBar(false)
				bar.setProgressBarValue(0)
				bar.setProgressBarText(t('richdocuments', 'Save as PDF'), null, null)
				bar.hideCancelButton()

				const fileid = (context.$file) ? context.$file.attr('data-id') : context.fileId
				const destination = (context.$file) ? context.$file.attr('data-path') : context.fileList.getCurrentDirectory()
				let url = OC.generateUrl('/apps/richdocuments/convert/pdf')
				url += '?fileid=' + fileid
				url += '&destination=' + destination
				if (context.fileList.id === 'files.public') {
					url += '&sharingToken=' + $('#sharingToken').val()
				}

				fetch(url).then(response => {
					if (!response.ok) {
						response.text().then((text) => {
							OC.dialogs.alert(t('richdocuments', text), t('richdocuments', 'Error'))
						})
					}
					bar.setProgressBarText(t('richdocuments', (response.ok) ? 'Finished file convert' : 'Error'), null, null)
					setTimeout(function() {
						bar.hideProgressBar()
						bar.setProgressBarText('')
					}, 1500)
					fileList.reload()
				})
			}
		}
	}
})(OCA))

OC.Plugins.register('OCA.Files.FileList', OCA.FilesPdfEditor.PreviewPlugin)
