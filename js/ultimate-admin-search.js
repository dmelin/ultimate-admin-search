/**
 * Ultimate Admin Search - Main JavaScript
 *
 * Handles all interactive functionality for the Ultimate Admin Search plugin.
 */
(function ($) {
	"use strict";

	// Main controller object
	const UltimateAdminSearch = {
		// DOM elements
		elements: {
			modal: null,
			results: null,
			search: null,
			submit: null,
			resize: null,
			menuLink: null,
		},

		// Settings
		settings: {
			minWidth: 300,
			maxWidth: 700,
			defaultWidth: 400,
			typingDelay: 500,
			minSearchLength: 2,
		},

		// State
		state: {
			isResizing: false,
			lastX: 0,
			originalWidth: 0,
			searchTimeout: null,
			wpAdminUrl: window.location.origin + "/wp-admin/",
		},

		/**
		 * Initialize the plugin functionality
		 */
		init: function () {
			this.cacheElements();
			this.bindEvents();
			this.restoreModalState();
		},

		/**
		 * Cache DOM elements for reuse
		 */
		cacheElements: function () {
			this.elements.menuLink = $("#toplevel_page_ultimate-admin-search a");
			this.elements.modal = $("#ultimate-admin-search-modal");
			this.elements.results = $("#ultimate-admin-search-modal__results");
			this.elements.search = $("#ultimate-admin-search-input");
			this.elements.submit = $("#ultimate-admin-search-button");
			this.elements.resize = $("#ultimate-admin-search-modal__handle");

			// Settings form elements
			this.elements.settingsForm = $("#ultimate-admin-search-settings-form");
			this.elements.saveButton = $(".ultimate-admin-search-save-settings");
			this.elements.clearCacheButton = $(".ultimate-admin-search-clear-cache");
			this.elements.noticesArea = $(".ultimate-admin-search-notices");
		},

		/**
		 * Bind event listeners
		 */
		bindEvents: function () {
			// Modal opening and closing
			this.elements.menuLink.on("click", this.toggleModal.bind(this));
			$(document).on("click", this.handleOutsideClick.bind(this));
			$(document).on("keydown", this.handleKeyPress.bind(this));

			// Search functionality
			this.elements.search.on("input", this.handleSearchInput.bind(this));
			this.elements.submit.on("click", this.handleSearchSubmit.bind(this));

			// Modal resizing
			this.elements.resize.on("mousedown", this.startResizing.bind(this));
			$(document).on("mousemove", this.handleResize.bind(this));
			$(document).on("mouseup", this.stopResizing.bind(this));

			// Settings form
			if (this.elements.settingsForm.length) {
				this.elements.settingsForm.on("submit", this.saveSettings.bind(this));
				this.elements.saveButton.on(
					"click",
					function () {
						this.elements.settingsForm.submit();
					}.bind(this)
				);

				// Clear cache button if it exists
				if (this.elements.clearCacheButton.length) {
					this.elements.clearCacheButton.on("click", this.clearCache.bind(this));
				}
			}
		},

		/**
		 * Restore modal width from localStorage
		 */
		restoreModalState: function () {
			const savedWidth = localStorage.getItem("ultimate-admin-search-width");
			if (savedWidth) {
				this.elements.modal.css("width", savedWidth + "px");
			} else {
				this.elements.modal.css("width", this.settings.defaultWidth + "px");
			}
		},

		/**
		 * Toggle modal visibility
		 * @param {Event} event - The click event
		 */
		toggleModal: function (event) {
			event.preventDefault();
			this.elements.modal.toggleClass("open");

			if (this.elements.modal.hasClass("open")) {
				this.elements.search.focus();
			} else {
				this.resetSearch();
			}
		},

		/**
		 * Handle clicks outside the modal
		 * @param {Event} event - The click event
		 */
		handleOutsideClick: function (event) {
			if (this.state.isResizing) return;

			if (
				!$(event.target).closest("#ultimate-admin-search-modal").length &&
				!$(event.target).closest("#toplevel_page_ultimate-admin-search").length
			) {
				this.closeModal();
			}
		},

		/**
		 * Handle key presses
		 * @param {Event} event - The keydown event
		 */
		handleKeyPress: function (event) {
			// Close modal on Escape key
			if (event.key === "Escape") {
				this.closeModal();
			}
		},

		/**
		 * Close the modal and reset search
		 */
		closeModal: function () {
			this.elements.modal.removeClass("open");
			this.resetSearch();
		},

		/**
		 * Reset search input and results
		 */
		resetSearch: function () {
			this.elements.results.empty();
			this.elements.search.val("");
		},

		/**
		 * Handle search input
		 * @param {Event} event - The input event
		 */
		handleSearchInput: function () {
			clearTimeout(this.state.searchTimeout);

			const searchQuery = this.elements.search.val();
			if (searchQuery.length > this.settings.minSearchLength) {
				this.state.searchTimeout = setTimeout(() => {
					this.doSearch(searchQuery);
				}, this.settings.typingDelay);
			}
		},

		/**
		 * Handle search submit button click
		 * @param {Event} event - The click event
		 */
		handleSearchSubmit: function (event) {
			event.preventDefault();
			const searchQuery = this.elements.search.val();
			this.doSearch(searchQuery);
		},

		/**
		 * Initiate search process
		 * @param {string} searchQuery - The search query
		 */
		doSearch: function (searchQuery) {
			if (searchQuery.length <= this.settings.minSearchLength) {
				return;
			}

			this.elements.modal.addClass("searching");

			// Get available post types
			$.ajax({
				url: ultimateAdminSearch.ajax_url,
				type: "POST",
				data: {
					action: "ultimate_admin_search_get_types",
					nonce: ultimateAdminSearch.nonce,
					query: searchQuery,
				},
				success: (response) => {
					this.elements.results.empty();

					if (response?.success && response.data && response.data.length > 0) {
						response.data.forEach((postType) => {
							this.createPostTypeContainer(postType);
						});

						// Start searching each post type
						this.searchNextPostType();
					} else {
						this.elements.modal.removeClass("searching");
						this.showNoResults();
					}
				},
				error: () => {
					this.elements.modal.removeClass("searching");
					this.showSearchError();
				},
			});
		},

		/**
		 * Create a container for a post type
		 * @param {Object} postType - The post type data
		 */
		createPostTypeContainer: function (postType) {
			const postTypeItem = $("<div></div>");

			postTypeItem
				.addClass("post-type-item processing open")
				.attr("data-type", postType.id)
				.attr("data-icon", postType.icon);

			const icon = postType.icon
				? `<span class="dashicons ${postType.icon}"></span>`
				: '<span class="dashicons dashicons-admin-post"></span>';

			postTypeItem.html(
				`<h3>${icon}<span class="title">${postType.title}</span></h3>
                <div class="items"></div>`
			);

			postTypeItem.find("h3").on("click", function () {
				$(this).parent().toggleClass("open");
			});

			this.elements.results.append(postTypeItem);
		},

		/**
		 * Search for the next post type in queue
		 */
		searchNextPostType: function () {
			const target = this.elements.results.find(".post-type-item.processing:first");

			if (target.length === 0) {
				this.elements.modal.removeClass("searching");
				return; // No more post types to search
			}

			const postType = target.attr("data-type");
			target.removeClass("processing").addClass("searching");

			$.ajax({
				url: ultimateAdminSearch.ajax_url,
				type: "POST",
				data: {
					action: "ultimate_admin_search_get_posts",
					post_type: postType,
					query: this.elements.search.val(),
					nonce: ultimateAdminSearch.nonce,
				},
				success: (response) => {
					target.removeClass("searching").addClass("done");

					if (response?.success && response.data) {
						const posts = response.data;

						// Update the post type header with result count
						target.find("h3 .title").append(` (${posts.length})`);

						// If no posts found, add a message
						if (posts.length === 0) {
							target.find(".items").append('<span class="no-results">No items found</span>');
						} else {
							// Add post items
							posts.forEach((post) => {
								this.createPostItem(post, target);
							});
						}

						// Search the next post type after a short delay
						setTimeout(() => {
							this.searchNextPostType();
						}, 100);
					}
				},
				error: () => {
					target.removeClass("searching").addClass("error");
					target.find(".items").append('<span class="error">Error fetching results</span>');

					// Continue with next post type
					setTimeout(() => {
						this.searchNextPostType();
					}, 100);
				},
			});
		},

		/**
		 * Create a search result item for a post
		 * @param {Object} post - The post data
		 * @param {jQuery} container - The container element
		 */
		createPostItem: function (post, container) {
			const postItem = $(
				`<a href="${post.url || this.state.wpAdminUrl + "post.php?post=" + post.id + "&action=edit"}"></a>`
			);

			postItem.addClass("post-item").attr("data-id", post.id).attr("data-title", post.title);

			postItem.append(`<span class="title">${post.title}</span>`);

			if (post.matched && post.matched.length) {
				const matchesContainer = $('<div class="matches"></div>');

				post.matched.forEach((match) => {
					let matchLabel = match.type;
					if (match.type === "meta" && match.key) {
						matchLabel = `${match.type}: ${match.key}`;
					}

					const matchElem = $(
						`<span class="match" data-content="${this.escapeHtml(match.value)}">${matchLabel}</span>`
					);

					// Show match details on hover
					matchElem
						.on("mouseenter", function () {
							const tooltip = $('<div class="match-tooltip"></div>').html(match.value).appendTo("body");

							const pos = $(this).offset();
							tooltip.css({
								top: pos.top + $(this).outerHeight(),
								left: pos.left,
							});

							$(this).data("tooltip", tooltip);
						})
						.on("mouseleave", function () {
							const tooltip = $(this).data("tooltip");
							if (tooltip) {
								tooltip.remove();
							}
						});

					matchesContainer.append(matchElem);
				});

				postItem.append(matchesContainer);
			}

			container.find(".items").append(postItem);
		},

		/**
		 * Display no results message
		 */
		showNoResults: function () {
			this.elements.results.html('<div class="no-results-message">No matching content found</div>');
		},

		/**
		 * Display search error message
		 */
		showSearchError: function () {
			this.elements.results.html('<div class="error-message">An error occurred while searching</div>');
		},

		/**
		 * Start modal resizing
		 * @param {Event} event - The mousedown event
		 */
		startResizing: function (event) {
			this.state.isResizing = true;
			this.state.lastX = event.clientX;
			this.state.originalWidth = this.elements.modal.width();
			this.elements.modal.addClass("resizing");
			event.preventDefault();
		},

		/**
		 * Handle modal resizing
		 * @param {Event} event - The mousemove event
		 */
		handleResize: function (event) {
			if (!this.state.isResizing) return;

			const deltaX = event.clientX - this.state.lastX;
			const newWidth = this.state.originalWidth + deltaX;

			// Apply width constraints
			if (newWidth >= this.settings.minWidth && newWidth <= this.settings.maxWidth) {
				this.elements.modal.css("width", newWidth + "px");
			}

			event.preventDefault();
		},

		/**
		 * Stop modal resizing
		 */
		stopResizing: function () {
			if (this.state.isResizing) {
				this.state.isResizing = false;
				this.elements.modal.removeClass("resizing");

				// Save width preference
				localStorage.setItem("ultimate-admin-search-width", this.elements.modal.width());
			}
		},

		/**
		 * Save plugin settings
		 * @param {Event} event - The submit event
		 */
		saveSettings: function (event) {
			event.preventDefault();

			const formData = this.elements.settingsForm.serializeArray();

			$.ajax({
				url: ultimateAdminSearch.ajax_url,
				type: "POST",
				data: formData,
				beforeSend: () => {
					this.showNotice("Saving settings...", "info");
					this.elements.saveButton.prop("disabled", true);
				},
				success: (response) => {
					if (response?.success) {
						this.showNotice("Settings saved successfully!", "success");
					} else {
						this.showNotice("Error saving settings.", "error");
					}
				},
				error: (xhr, status, error) => {
					this.showNotice("Error: " + error, "error");
					console.error("AJAX error:", error);
				},
				complete: () => {
					this.elements.saveButton.prop("disabled", false);
				},
			});
		},

		/**
		 * Clear search cache
		 * @param {Event} event - The click event
		 */
		clearCache: function (event) {
			event.preventDefault();

			$.ajax({
				url: ultimateAdminSearch.ajax_url,
				type: "POST",
				data: {
					action: "ultimate_admin_search_clear_cache",
					nonce: ultimateAdminSearch.nonce,
				},
				beforeSend: () => {
					this.showNotice("Clearing cache...", "info");
					this.elements.clearCacheButton.prop("disabled", true);
				},
				success: (response) => {
					if (response?.success) {
						this.showNotice("Cache cleared successfully!", "success");
					} else {
						this.showNotice("Error clearing cache.", "error");
					}
				},
				error: () => {
					this.showNotice("Error clearing cache.", "error");
				},
				complete: () => {
					this.elements.clearCacheButton.prop("disabled", false);
				},
			});
		},

		/**
		 * Show a notification message
		 * @param {string} message - The message text
		 * @param {string} type - The message type (success, error, info)
		 */
		showNotice: function (message, type = "info") {
			if (!this.elements.noticesArea.length) return;

			const notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);

			this.elements.noticesArea.empty().append(notice).show();

			// Auto-dismiss after 3 seconds
			setTimeout(() => {
				notice.fadeOut(
					300,
					function () {
						$(this).remove();
						if (!this.elements.noticesArea.children().length) {
							this.elements.noticesArea.hide();
						}
					}.bind(this)
				);
			}, 3000);

			// Add dismiss button
			const dismissButton = $('<button type="button" class="notice-dismiss"></button>');
			dismissButton.on(
				"click",
				function () {
					notice.fadeOut(
						300,
						function () {
							$(this).remove();
							if (!this.elements.noticesArea.children().length) {
								this.elements.noticesArea.hide();
							}
						}.bind(this)
					);
				}.bind(this)
			);

			notice.append(dismissButton);
		},

		/**
		 * Escape HTML special characters
		 * @param {string} text - The text to escape
		 * @return {string} Escaped text
		 */
		escapeHtml: function (text) {
			if (!text) return "";

			return text
				.replace(/&/g, "&amp;")
				.replace(/</g, "&lt;")
				.replace(/>/g, "&gt;")
				.replace(/"/g, "&quot;")
				.replace(/'/g, "&#039;");
		},
	};

	// Initialize on document ready
	$(document).ready(function () {
		UltimateAdminSearch.init();
	});
})(jQuery);
