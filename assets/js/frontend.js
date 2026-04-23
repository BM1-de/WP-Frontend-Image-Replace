/**
 * Frontend Image Replace — Frontend Script.
 *
 * Scans the page for WordPress media library images, adds hover overlays,
 * and handles the upload + replacement flow.
 */
(function () {
	'use strict';

	// Don't run inside iframes (page builders, visual editors like LiveCanvas).
	if (window !== window.top) return;

	// Don't run if a page builder editor is active.
	if (document.body && (
		document.body.classList.contains('lc-editing') ||
		document.body.classList.contains('elementor-editor-active') ||
		document.body.classList.contains('fl-builder-edit') ||
		document.body.classList.contains('ct-builder')
	)) return;

	// Don't run if URL contains editor parameters.
	var params = new URLSearchParams(window.location.search);
	if (params.has('lc_action_launch_editing') || params.has('elementor-preview') || params.has('ct_builder') || params.has('fl_builder') || params.has('brizy-edit')) return;

	var FIR = {
		images: new Map(),
		overlay: null,
		toolbar: null,
		progressBar: null,
		currentImage: null,
		isUploading: false,

		init: function () {
			this.createOverlay();
			this.createToolbar();
			this.scanImages();
			this.restoreScroll();
		},

		/**
		 * Create the single floating overlay element.
		 */
		createOverlay: function () {
			var overlay = document.createElement('div');
			overlay.className = 'bm1fir-overlay';
			overlay.innerHTML =
				'<div class="bm1fir-overlay__content">' +
					'<div class="bm1fir-overlay__icon">' +
						'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
							'<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>' +
							'<circle cx="8.5" cy="8.5" r="1.5"/>' +
							'<polyline points="21 15 16 10 5 21"/>' +
						'</svg>' +
					'</div>' +
					'<div class="bm1fir-overlay__label">' + bm1firData.i18n.replaceImage + '</div>' +
					'<div class="bm1fir-progress" style="display:none;">' +
						'<div class="bm1fir-progress__bar"></div>' +
					'</div>' +
					'<div class="bm1fir-overlay__status"></div>' +
				'</div>';

			var self = this;

			overlay.addEventListener('mouseleave', function () {
				if (!self.isUploading) {
					self.hideOverlay();
				}
			});

			overlay.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (!self.isUploading && self.currentImage) {
					var attachmentId = self.images.get(self.currentImage);
					if (attachmentId) {
						self.handleReplace(self.currentImage, attachmentId);
					}
				}
			});

			document.body.appendChild(overlay);
			this.overlay = overlay;
			this.progressBar = overlay.querySelector('.bm1fir-progress__bar');
		},

		/**
		 * Create the toolbar notification bar.
		 */
		createToolbar: function () {
			if (sessionStorage.getItem('bm1fir_toolbar_dismissed')) {
				return;
			}

			var toolbar = document.createElement('div');
			toolbar.className = 'bm1fir-toolbar';

			var textHtml =
				'<span class="bm1fir-toolbar__text">' +
					'<svg class="bm1fir-toolbar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>' +
					bm1firData.i18n.toolbarText +
				'</span>';

			toolbar.innerHTML = textHtml +
				'<button class="bm1fir-toolbar__close" type="button" aria-label="Close">&times;</button>';

			toolbar.querySelector('.bm1fir-toolbar__close').addEventListener('click', function () {
				toolbar.remove();
				sessionStorage.setItem('bm1fir_toolbar_dismissed', '1');
			});

			document.body.appendChild(toolbar);
			this.toolbar = toolbar;
		},

		/**
		 * Scan the page for images and identify their attachment IDs.
		 */
		scanImages: function () {
			var allImages = document.querySelectorAll('img');
			var unknownUrls = {};
			var self = this;

			allImages.forEach(function (img) {
				// Skip tiny images (icons, spacers, etc.).
				if (img.naturalWidth > 0 && img.naturalWidth < 50) return;
				if (img.naturalHeight > 0 && img.naturalHeight < 50) return;

				// Skip plugin UI elements, admin bar, and wp-admin elements.
				if (img.closest('.bm1fir-overlay') || img.closest('.bm1fir-toolbar')) return;
				if (img.closest('#wpadminbar') || img.closest('#adminmenuwrap') || img.closest('#wpbody') || img.closest('#adminmenu')) return;

				// Skip images excluded via fir-no-replace class.
				if (img.classList.contains('fir-no-replace') || img.closest('.fir-no-replace')) return;

				// Skip data URIs and SVGs.
				var src = img.currentSrc || img.src;
				if (!src || src.indexOf('data:') === 0 || src.indexOf('.svg') !== -1) return;

				// Skip the site logo.
				if (bm1firData.logoUrl && src.indexOf(bm1firData.logoUrl.replace(/^https?:\/\/[^/]+/, '')) !== -1) return;

				// Try to extract attachment ID from wp-image-{ID} class.
				var match = img.className && img.className.match(/wp-image-(\d+)/);
				if (match) {
					self.registerImage(img, parseInt(match[1], 10));
					return;
				}

				// Collect for batch resolution.
				if (!unknownUrls[src]) {
					unknownUrls[src] = [];
				}
				unknownUrls[src].push(img);
			});

			// Batch resolve unknown URLs.
			var urlList = Object.keys(unknownUrls);
			if (urlList.length > 0) {
				this.resolveImages(unknownUrls, urlList);
			}
		},

		/**
		 * Batch resolve image URLs to attachment IDs via AJAX.
		 */
		resolveImages: function (urlMap, urlList) {
			var formData = new FormData();
			formData.append('action', 'bm1fir_resolve_images');
			formData.append('nonce', bm1firData.nonce);
			formData.append('token', bm1firData.token);
			formData.append('urls', JSON.stringify(urlList));

			var self = this;

			fetch(bm1firData.ajaxUrl, { method: 'POST', body: formData })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.success && data.data) {
						Object.keys(data.data).forEach(function (url) {
							var id = data.data[url];
							if (id && urlMap[url]) {
								urlMap[url].forEach(function (img) {
									self.registerImage(img, id);
								});
							}
						});
					}
					self.updateToolbarCount();
				})
				.catch(function () {
					// Silently fail — images without IDs just won't show the overlay.
				});
		},

		/**
		 * Register an image element with its attachment ID.
		 */
		registerImage: function (img, attachmentId) {
			this.images.set(img, attachmentId);
			this.addHoverListener(img);
			this.updateToolbarCount();
		},

		/**
		 * Add hover listener to an image to show the overlay.
		 */
		addHoverListener: function (img) {
			var self = this;
			img.addEventListener('mouseenter', function () {
				if (!self.isUploading) {
					self.showOverlay(img);
				}
			});
			// Mark as replaceable with a subtle cursor change.
			img.style.cursor = 'pointer';
		},

		/**
		 * Show the overlay positioned over the given image.
		 */
		showOverlay: function (img) {
			var rect = img.getBoundingClientRect();

			// Don't show for very small rendered images.
			if (rect.width < 40 || rect.height < 40) return;

			this.overlay.style.top = rect.top + 'px';
			this.overlay.style.left = rect.left + 'px';
			this.overlay.style.width = rect.width + 'px';
			this.overlay.style.height = rect.height + 'px';
			this.overlay.classList.add('bm1fir-overlay--visible');
			this.overlay.classList.remove('bm1fir-overlay--uploading', 'bm1fir-overlay--success', 'bm1fir-overlay--error');
			this.overlay.querySelector('.bm1fir-progress').style.display = 'none';
			this.overlay.querySelector('.bm1fir-overlay__status').textContent = '';
			this.overlay.querySelector('.bm1fir-overlay__label').textContent = bm1firData.i18n.replaceImage;
			this.currentImage = img;
		},

		/**
		 * Hide the overlay.
		 */
		hideOverlay: function () {
			this.overlay.classList.remove('bm1fir-overlay--visible');
			this.currentImage = null;
		},

		/**
		 * Open file picker and start the replacement process.
		 */
		handleReplace: function (img, attachmentId) {
			var input = document.createElement('input');
			input.type = 'file';
			input.accept = 'image/*';

			var self = this;
			input.addEventListener('change', function () {
				if (input.files && input.files[0]) {
					self.uploadAndReplace(img, attachmentId, input.files[0]);
				}
			});

			input.click();
		},

		/**
		 * Upload the new image and trigger the replacement.
		 */
		uploadAndReplace: function (img, attachmentId, file) {
			this.isUploading = true;

			// Update overlay to show uploading state.
			var rect = img.getBoundingClientRect();
			this.overlay.style.top = rect.top + 'px';
			this.overlay.style.left = rect.left + 'px';
			this.overlay.style.width = rect.width + 'px';
			this.overlay.style.height = rect.height + 'px';
			this.overlay.classList.add('bm1fir-overlay--visible', 'bm1fir-overlay--uploading');
			this.overlay.querySelector('.bm1fir-overlay__label').textContent = bm1firData.i18n.uploading;
			this.overlay.querySelector('.bm1fir-progress').style.display = 'block';
			this.progressBar.style.width = '0%';

			// Get the specific src URL of the clicked image for targeted replacement.
			var imageSrc = img.getAttribute('src') || img.currentSrc || '';

			// Determine the occurrence index.
			var occurrenceIndex = 0;
			var self = this;
			this.images.forEach(function (aid, imgEl) {
				if (aid === attachmentId && imgEl !== img) {
					if (img.compareDocumentPosition(imgEl) & Node.DOCUMENT_POSITION_PRECEDING) {
						occurrenceIndex++;
					}
				}
			});

			var formData = new FormData();
			formData.append('action', 'bm1fir_replace_image');
			formData.append('nonce', bm1firData.nonce);
			formData.append('token', bm1firData.token);
			formData.append('attachment_id', attachmentId);
			formData.append('post_id', bm1firData.postId || 0);
			formData.append('image_src', imageSrc);
			formData.append('occurrence_index', occurrenceIndex);
			formData.append('file', file);

			var xhr = new XMLHttpRequest();

			xhr.upload.addEventListener('progress', function (e) {
				if (e.lengthComputable) {
					var percent = Math.round((e.loaded / e.total) * 100);
					self.progressBar.style.width = percent + '%';
				}
			});

			xhr.addEventListener('load', function () {
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.success) {
						self.onSuccess();
					} else {
						var msg = response.data;
						if (typeof msg === 'object' && msg !== null) {
							msg = msg.message || JSON.stringify(msg);
						}
						self.onError(msg || bm1firData.i18n.error);
					}
				} catch (e) {
					self.onError(bm1firData.i18n.error);
				}
			});

			xhr.addEventListener('error', function () {
				self.onError('Network error');
			});

			xhr.open('POST', bm1firData.ajaxUrl);
			xhr.send(formData);
		},

		/**
		 * Handle successful replacement.
		 */
		onSuccess: function () {
			this.overlay.classList.remove('bm1fir-overlay--uploading');
			this.overlay.classList.add('bm1fir-overlay--success');
			this.overlay.querySelector('.bm1fir-overlay__label').textContent = bm1firData.i18n.success;
			this.overlay.querySelector('.bm1fir-progress').style.display = 'none';

			// Save scroll position and reload.
			sessionStorage.setItem('fir_scroll_y', String(window.scrollY));

			// Preserve token in URL if present.
			var reloadUrl = window.location.href;
			setTimeout(function () {
				window.location.href = reloadUrl;
			}, 800);
		},

		/**
		 * Handle replacement error.
		 */
		onError: function (message) {
			this.isUploading = false;
			this.overlay.classList.remove('bm1fir-overlay--uploading');
			this.overlay.classList.add('bm1fir-overlay--error');
			this.overlay.querySelector('.bm1fir-overlay__label').textContent = bm1firData.i18n.error;
			this.overlay.querySelector('.bm1fir-progress').style.display = 'none';
			this.overlay.querySelector('.bm1fir-overlay__status').textContent = message;

			var self = this;
			setTimeout(function () {
				self.overlay.classList.remove('bm1fir-overlay--error');
				self.hideOverlay();
			}, 3000);
		},

		/**
		 * Restore scroll position after page reload.
		 */
		restoreScroll: function () {
			var scrollY = sessionStorage.getItem('fir_scroll_y');
			if (scrollY !== null) {
				window.scrollTo(0, parseInt(scrollY, 10));
				sessionStorage.removeItem('fir_scroll_y');
			}
		},

		/**
		 * Update the toolbar with the count of replaceable images.
		 */
		updateToolbarCount: function () {
			if (!this.toolbar) return;
			var count = this.images.size;
			var countEl = this.toolbar.querySelector('.bm1fir-toolbar__count');
			if (!countEl) {
				countEl = document.createElement('span');
				countEl.className = 'bm1fir-toolbar__count';
				this.toolbar.querySelector('.bm1fir-toolbar__text').appendChild(countEl);
			}
			countEl.textContent = ' (' + count + ')';
		}
	};

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { FIR.init(); });
	} else {
		FIR.init();
	}

	// Update overlay position on scroll.
	window.addEventListener('scroll', function () {
		if (FIR.overlay && FIR.overlay.classList.contains('bm1fir-overlay--visible') && !FIR.isUploading) {
			FIR.hideOverlay();
		}
	}, { passive: true });

	// Update overlay position on resize.
	window.addEventListener('resize', function () {
		if (FIR.overlay && FIR.overlay.classList.contains('bm1fir-overlay--visible') && !FIR.isUploading) {
			FIR.hideOverlay();
		}
	});
})();
