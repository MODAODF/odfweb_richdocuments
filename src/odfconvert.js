// 轉檔 doc -> odf 批次/單獨

((function(OCA) {

	OCA.RichDocuments = OCA.RichDocuments || {}
	OCA.RichDocuments.OdfConvert = OCA.RichDocuments.OdfConvert || {}

	/**
	 * @namespace OCA.RichDocuments.OdfConvert
	 */
	OCA.RichDocuments.OdfConvert = {
		fileList: null,
		progressLoaded: null,
		progressTotal: null,
		supportedMimetype: [
			'application/vnd.oasis.opendocument.text',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-word.document.macroEnabled.12',

			'application/vnd.oasis.opendocument.spreadsheet',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-excel.sheet.macroEnabled.12',

			'application/vnd.oasis.opendocument.presentation',
			'application/vnd.ms-powerpoint',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
		],

		attach(fileList) {
			if (fileList.id === 'trashbin' || fileList.id === 'files.public') {
				return
			}
			this.fileList = fileList
			this._extendFileActions(fileList)
		},

		/**
		 * @param {Object} fileList fileList
		 * @private
		 */
		_extendFileActions(fileList) {
			const self = this
			for (const mime of self.supportedMimetype) {
				fileList.fileActions.registerAction({
					name: 'convertodf',
					displayName: t('richdocuments', '轉存為 ODF'),
					mime,
					permissions: OC.PERMISSION_READ,
					iconClass: 'icon-projects',
					actionHandler: self._singleFile
				})
			}

			fileList.multiSelectMenuItems.push({
				name: 'convertodf',
				displayName: t('richdocuments', '批次轉存為 ODF'),
				iconClass: 'icon-projects',
				action: self._multiFiles
			})
			fileList.fileMultiSelectMenu = new OCA.Files.FileMultiSelectMenu(fileList.multiSelectMenuItems)
			fileList.fileMultiSelectMenu.render()
			fileList.$el.find('.selectedActions').append(fileList.fileMultiSelectMenu.$el)
		},

		_singleFile(fileName, context) {
			const self = OCA.RichDocuments.OdfConvert
			if (self._checkApi()) {

				self.progressTotal = 1
				self.progressLoaded = 0
				self.fileList._operationProgressBar.showProgressBar(false)
				self._updateProgress(0)

				const url = self._getUrl(fileName, context.dir, context.dir)
				fetch(url).then(response => {
					self.progressLoaded += 1
					self._updateProgress(self.progressLoaded)
					if (!response.ok) {
						response.text().then((text) => {
							OC.dialogs.alert(text, t('richdocuments', 'Error'))
						})
					}
					self.fileList.reload()
				})
			}
		},

		_multiFiles(Files) {
			const self = OCA.RichDocuments.OdfConvert
			const supportFiles = Files.filter(file =>
				file.mimetype && self.supportedMimetype.includes(file.mimetype)
			)
			if (supportFiles.length === 0) {
				OC.dialogs.alert(
					t('richdocuments', 'No files to convert'),
					t('richdocuments', 'Error')
				)
				return
			}

			const formatHTML = function() {
				const ListEl = supportFiles.map(file => `<li>${file.name}</li>`).join('')
				return `<p>下列 ${supportFiles.length} 個文件將轉存為 ODF 格式文件：</p>
					<ul style="margin-left:40px;list-style:disc;"><small><em>${ListEl}</em></small></ul><br>
					<p><b>確認進行轉檔？</b></p>`
			}

			const cb = async function(result) {
				const self = OCA.RichDocuments.OdfConvert
				if (!result || !self._checkApi()) return

				const destinationDir = 'ODF-' + Date.now()
				await self.fileList.createDirectory(destinationDir)
					.catch(() => {
						OC.dialogs.alert('無法建立轉檔資料夾', t('richdocuments', 'Error'))
						return false
					})
					.then(() => {
						const destinationPath = self.fileList._currentDirectory + '/' + destinationDir

						self.progressTotal = supportFiles.length
						self.progressLoaded = 0
						self.fileList._operationProgressBar.showProgressBar(false)
						self._updateProgress(0)

						const actions = supportFiles.map(file => {
							const url = self._getUrl(file.name, file.path, destinationPath)
							return fetch(url)
								.then(response => {
									self.progressLoaded += 1
									self._updateProgress(self.progressLoaded)
									return {
										filename: file.name,
										response
									}
								})
						})

						Promise.all(actions)
							.then(resultArr => {
								const failResult = resultArr.filter(result => (!result.response.ok))
								if (failResult.length !== 0) {

									for (const result of failResult) {
										const name = result.filename
										result.response.text()
											.then((text) => {
												console.debug(`ODF轉檔失敗(${name}): ${text}`)
											})
									}

									if (failResult.length === supportFiles.length) {
										try {
											self.fileList.do_delete(destinationDir)
										} catch (error) {}
									}

									OC.dialogs.alert(`${failResult.length}個檔案轉檔失敗`, t('richdocuments', 'Error'))
								}
								self.fileList.reload()
							})
					})
			}

			OC.dialogs.confirmHtml(
				formatHTML(),
				t('richdocuments', '確認'),
				cb,
				true
			)
		},

		/**
		 * Check Convert-to
		 * @returns {bool}
		 */
		async _checkApi() {
			try {
				const response = await fetch(OC.generateUrl('/apps/richdocuments/convert/check'))
				if (!response.ok) {
					const msg = await response.text()
					throw msg
				}
			} catch (error) {
				OC.dialogs.alert('轉檔功能沒有反應或尚未啟用，請聯繫系統管理人員。', t('richdocuments', 'Error'))
				return false
			}
			return true
		},

		/**
		 * Update progress bar
		 * @param {int} loaded loaded number
		 */
		_updateProgress(loaded) {
			const total = this.progressTotal
			const bar = this.fileList._operationProgressBar
			bar.setProgressBarValue((Math.round(loaded / total * 100)))
			if (loaded === total) {
				bar.setProgressBarText(t('richdocuments', `Finish (${loaded}/${total})`), null, null)
				setTimeout(function() {
					bar.hideProgressBar()
					bar.setProgressBarText('')
				}, 1500)
			} else {
				bar.setProgressBarText(t('richdocuments', `Converting...(${loaded}/${total})`), null, null)
			}
		},

		/**
		 * Format url
		 * @param {string} fileName file name
		 * @param {string} fileFolder file folder
		 * @param {string} destination The dir path to store new files
		 * @returns {string}
		 */
		_getUrl(fileName, fileFolder, destination = null) {
			const fileList = OCA.RichDocuments.OdfConvert.fileList
			const downloadUrl = fileList.getDownloadUrl(fileName, fileFolder)
			let url = OC.generateUrl('/apps/richdocuments/convert/odf') + '?file=' + downloadUrl
			if (destination !== null) {
				url += '&destination=' + destination
			}
			return url
		}
	}
})(OCA))

OC.Plugins.register('OCA.Files.FileList', OCA.RichDocuments.OdfConvert)
