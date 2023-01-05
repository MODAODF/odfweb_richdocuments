((function(OCA) {
	OCA.FilesPdfEditor = OCA.FilesPdfEditor || {}

	/**
	 * @namespace OCA.FilesPdfEditor.PreviewPlugin
	 */
	OCA.FilesPdfEditor.PreviewPlugin = {
		attach(fileList) {
			if (fileList.id === 'trashbin') {
				return
			}
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
				try {
					const response = await fetch(OC.generateUrl('/apps/richdocuments/convert/check'))
					if (!response.ok) {
						const msg = await response.text()
						throw msg
					}
				} catch (error) {
					OC.dialogs.alert('PDF 功能沒有反應，請聯繫系統管理人員。', t('richdocuments', 'Error'))
					return
				}

				fileList._operationProgressBar.showProgressBar(false)
				fileList._operationProgressBar.setProgressBarValue(0)
				fileList._operationProgressBar.setProgressBarText(t('richdocuments', 'Save as PDF'), null, null)
				fileList._operationProgressBar.hideCancelButton()
				const downloadUrl = context.fileList.getDownloadUrl(fileName, context.dir)
				const url = OC.generateUrl('/apps/richdocuments/convert/pdf') + '?file=' + downloadUrl
				fetch(url).then(response => {
					if (!response.ok) {
						fileList._operationProgressBar.hideProgressBar()
						fileList._operationProgressBar.setProgressBarText('')
						response.text().then((text) => {
							OC.dialogs.alert(text, t('richdocuments', 'Error'))
						})
						throw Error(response.status + ' ' + response.statusText)
					}

					if (!response.body) {
						fileList._operationProgressBar.hideProgressBar()
						fileList._operationProgressBar.setProgressBarText('')
						OC.dialogs.alert('ReadableStream not yet supported in this browser.', t('richdocuments', 'Error'))
						throw Error('ReadableStream not yet supported in this browser.')
					}
					// to access headers, server must send CORS header 'Access-Control-Expose-Headers: content-encoding, content-length x-file-size'
					// server must send custom x-file-size header if gzip or other content-encoding is used
					const contentEncoding = response.headers.get('content-encoding')
					const contentLength = response.headers.get(contentEncoding ? 'x-file-size' : 'content-length')
					if (contentLength === null) {
						fileList._operationProgressBar.hideProgressBar()
						fileList._operationProgressBar.setProgressBarText('')
						throw Error('Response size header unavailable')
					}
					const total = parseInt(contentLength, 10)
					let loaded = 0
					fileList._operationProgressBar.setProgressBarText(t('richdocuments', 'Get PDF'), null, null)
					fileList._operationProgressBar.hideCancelButton()

					return new Response(
						new ReadableStream({
							start(controller) {
								const reader = response.body.getReader()

								read()
								function read() {
									reader.read().then(({ done, value }) => {
										if (done) {
											controller.close()
											return
										}
										loaded += value.byteLength
										fileList._operationProgressBar.setProgressBarValue(Math.round(loaded / total * 50))
										controller.enqueue(value)
										read()
									}).catch(error => {
										console.error(error)
										controller.error(error)
										fileList._operationProgressBar.hideProgressBar()
										fileList._operationProgressBar.setProgressBarText('')
									})
								}
							}
						})
					)
				}).then(response => response.blob())
					.then((blob) => {
						const newBlob = new Blob([blob], { type: 'application/pdf' })
						const nameParts = fileName.split('.')
						const supportExtension = ['pdf', 'odt', 'ods', 'odp', 'doc', 'xls', 'ppt', 'docx', 'xlsx', 'pptx']
						if (supportExtension.includes(nameParts[nameParts.length - 1])) {
							nameParts.pop() // remove extension
						}
						nameParts.push('pdf')
						let filename = nameParts.join('.')
						filename = FileList.getUniqueName(filename)
						fileList._operationProgressBar.setProgressBarText(t('richdocuments', 'Upload PDF'), null, null)
						const url = fileList.getUploadUrl() + '/' + filename
						$.ajax({
							xhr() {
								const xhr = new window.XMLHttpRequest()
								xhr.upload.addEventListener('progress', function(evt) {
									if (evt.lengthComputable) {
										fileList._operationProgressBar.setProgressBarValue(Math.round(evt.loaded / evt.total * 50) + 50)
									}
								}, false)
								return xhr
							},
							type: 'PUT',
							url,
							data: newBlob,
							processData: false,
							contentType: 'application/pdf',
							cache: false,
							success: (res) => {
								fileList._operationProgressBar.setProgressBarText(t('richdocuments', 'Finish PDF'), null, null)
								fileList._operationProgressBar.hideProgressBar()
								fileList._operationProgressBar.setProgressBarText('')
								fileList.reload()

							},
							error: (res) => {
								fileList._operationProgressBar.hideProgressBar()
								fileList._operationProgressBar.setProgressBarText('')
								OC.dialogs.alert(res, t('richdocuments', 'Error'))
							}
						})

					})
			}
		}
	}

})(OCA))

OC.Plugins.register('OCA.Files.FileList', OCA.FilesPdfEditor.PreviewPlugin)
