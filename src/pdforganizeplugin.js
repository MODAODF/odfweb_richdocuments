((function(OCA) {
	OCA.FilesPdfEditor = OCA.FilesPdfEditor || {}

	/**
	 * @namespace OCA.FilesPdfEditor.PreviewPlugin
	 */
	OCA.FilesPdfEditor.PreviewPlugin = {
		supportMimetype: ['pdf', 'odt', 'ods', 'odp', 'doc', 'ppt', 'xls', 'docx', 'pptx', 'xlsx'],
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
			/*
			Extension MIME Type
			.doc      application/msword
			.dot      application/msword

			.docx     application/vnd.openxmlformats-officedocument.wordprocessingml.document
			.dotx     application/vnd.openxmlformats-officedocument.wordprocessingml.template
			.docm     application/vnd.ms-word.document.macroEnabled.12
			.dotm     application/vnd.ms-word.template.macroEnabled.12

			.xls      application/vnd.ms-excel
			.xlt      application/vnd.ms-excel
			.xla      application/vnd.ms-excel

			.xlsx     application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
			.xltx     application/vnd.openxmlformats-officedocument.spreadsheetml.template
			.xlsm     application/vnd.ms-excel.sheet.macroEnabled.12
			.xltm     application/vnd.ms-excel.template.macroEnabled.12
			.xlam     application/vnd.ms-excel.addin.macroEnabled.12
			.xlsb     application/vnd.ms-excel.sheet.binary.macroEnabled.12

			.ppt      application/vnd.ms-powerpoint
			.pot      application/vnd.ms-powerpoint
			.pps      application/vnd.ms-powerpoint
			.ppa      application/vnd.ms-powerpoint

			.pptx     application/vnd.openxmlformats-officedocument.presentationml.presentation
			.potx     application/vnd.openxmlformats-officedocument.presentationml.template
			.ppsx     application/vnd.openxmlformats-officedocument.presentationml.slideshow
			.ppam     application/vnd.ms-powerpoint.addin.macroEnabled.12
			.pptm     application/vnd.ms-powerpoint.presentation.macroEnabled.12
			.potm     application/vnd.ms-powerpoint.template.macroEnabled.12
			.ppsm     application/vnd.ms-powerpoint.slideshow.macroEnabled.12

			.mdb      application/vnd.ms-access
			*/
			const supportedMimes = [
				'application/vnd.oasis.opendocument.text',
				'application/vnd.oasis.opendocument.spreadsheet',
				'application/vnd.oasis.opendocument.graphics',
				'application/vnd.oasis.opendocument.presentation',
				// 'application/vnd.lotus-wordpro',
				// 'application/vnd.visio',
				// 'application/vnd.ms-visio.drawing',
				// 'application/vnd.wordperfect',
				// 'application/msonenote',
				'application/msword',
				// 'application/rtf',
				// 'text/rtf',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				// 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
				// 'application/vnd.ms-word.document.macroEnabled.12',
				// 'application/vnd.ms-word.template.macroEnabled.12',
				'application/vnd.ms-excel',
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				// 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
				// 'application/vnd.ms-excel.sheet.macroEnabled.12',
				// 'application/vnd.ms-excel.template.macroEnabled.12',
				// 'application/vnd.ms-excel.addin.macroEnabled.12',
				// 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
				'application/vnd.ms-powerpoint',
				'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				// 'application/vnd.openxmlformats-officedocument.presentationml.template',
				// 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
				// 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
				// 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
				// 'application/vnd.ms-powerpoint.template.macroEnabled.12',
				// 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
				// 'text/csv',
				// 'application/pdf'
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
					const response = await fetch(OC.generateUrl('/apps/richdocuments/pdf/check'))
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
				const url = OC.generateUrl('/apps/richdocuments/pdf/topdf') + '?file=' + downloadUrl
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
						const filename = fileName.split('.')[0]
						fileList._operationProgressBar.setProgressBarText(t('richdocuments', 'Upload PDF'), null, null)
						const url = fileList.getUploadUrl() + '/' + filename + '.pdf'
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
