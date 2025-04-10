$ = jQuery;
$(document).ready(function () {
	const searchMenuLink = $("#toplevel_page_ultimate-admin-search a");
	const searchModal = $("#ultimate-admin-search-modal");
	searchModal.results = $("#ultimate-admin-search-modal__results");
	searchModal.search = $("#ultimate-admin-search-input");
	searchModal.submit = $("#ultimate-admin-search-button");
	searchModal.resize = $("#ultimate-admin-search-modal__handle");

	let isResizing = false;
	let lastX = 0;
	let originalWidth = 0;

	searchModal.resize.on("mousedown", function (e) {
		isResizing = true;
		lastX = e.clientX;
		originalWidth = searchModal.width();
		searchModal.addClass("resizing");
		e.preventDefault();
	});

	$(document).on("mousemove", function (e) {
		if (!isResizing) return;

		const deltaX = e.clientX - lastX;
		const newWidth = originalWidth + deltaX;

		// Set minimum and maximum width constraints
		if (newWidth >= 300 && newWidth <= 700) {
			searchModal.css("width", newWidth + "px");
		}

		e.preventDefault();
	});

	$(document).on("mouseup", function () {
		if (isResizing) {
			isResizing = false;
			searchModal.removeClass("resizing");

			// Save the new width in localStorage for persistence
			localStorage.setItem("ultimate-admin-search-width", searchModal.width());
		}
	});

	// Restore saved width on page load
	const savedWidth = localStorage.getItem("ultimate-admin-search-width");
	if (savedWidth) {
		searchModal.css("width", savedWidth + "px");
	}

	// get wp admin url
	const wpAdminUrl = window.location.origin + "/wp-admin/";

	let stoppedTyping;

	// Close the modal when clicking outside of it
	$(document).on("click", function (event) {
		if (searchModal.hasClass("resizing")) return;

		if (
			!$(event.target).closest("#ultimate-admin-search-modal").length &&
			!$(event.target).closest("#toplevel_page_ultimate-admin-search").length
		) {
			searchModal.removeClass("open");
			searchModal.results.empty();
			searchModal.search.val("");
		}
	});
	// Close modal when ESC is pressed
	$(document).on("keydown", function (event) {
		if (event.key === "Escape") {
			searchModal.removeClass("open");
			searchModal.results.empty();
			searchModal.search.val("");
		}
	});

	searchModal.search.on("input", function () {
		clearTimeout(stoppedTyping);
		const searchQuery = $(this).val();
		if (searchQuery.length > 2) {
			stoppedTyping = setTimeout(function () {
				doSearch(searchQuery);
			}, 500);
		} else {
		}
	});

	searchMenuLink.click(function (event) {
		event.preventDefault(); // Prevent the default action of the link
		searchModal.toggleClass("open");
		searchModal.search.focus();
	});

	searchModal.submit.click(function (event) {
		event.preventDefault(); // Prevent the default action of the button
		const searchQuery = searchModal.search.val();
		doSearch(searchQuery);
	});

	function doSearch(searchQuery) {
		// Get post types
		$.ajax({
			url: ultimateAdminSearch.ajax_url,
			type: "POST",
			data: {
				action: "ultimate_admin_search_get_types",
				nonce: ultimateAdminSearch.nonce,
				query: searchQuery,
			},
			success: function (response) {
				searchModal.results.empty(); // Clear previous results

				if (response && response.success && response.data) {
					const postTypes = response.data;
					// Populate the modal with post types
					Object.keys(postTypes).forEach((key) => {
						const postType = postTypes[key];
						const postTypeItem = $("<div></div>");

						postTypeItem.addClass("post-type-item processing open");
						postTypeItem
							.attr("data-type", postType.id)
							.attr("data-icon", postType.icon)
							.html(
								`<h3><span class="dashicons ${postType.icon}"></span><span class="title">${postType.title}</span></h3>`
							)
							.append('<div class="items"></div>');

						postTypeItem.find("h3").click(function () {
							$(this).parent().toggleClass("open");
						});
						searchModal.results.append(postTypeItem);
					});

					searchPostType(); // Trigger search for the first post type
				}
			},
		});
	}

	function searchPostType() {
		// Perform the search for the selected post type
		const target = searchModal.results.find(".post-type-item.processing:first");

		if (target.length === 0) {
			console.log("No more post types to search");
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
				query: searchModal.search.val(),
				nonce: ultimateAdminSearch.nonce,
			},
			success: function (response) {
				if (response && response.success && response.data) {
					target.removeClass("searching").addClass("done");
					const posts = response.data;

					target.find("h3 .title").append(" (" + posts.length + ")");

					if (posts.length === 0) {
					}

					// Populate the modal with posts
					posts.forEach((post) => {
						const postItem = $(`<a href="${wpAdminUrl}post.php?post=${post.id}&action=edit"></a>`);
						postItem.addClass("post-item");
						postItem.attr("data-id", post.id).attr("data-title", post.title);

						postItem.append(`<span class="title">${post.title}</span>`);

						post.matched.forEach(function (match) {
							console.log(match.type, match.value);
							postItem.append(
								`<span class="match" data-content="${match.value.replace(/"/g, "&quot;")}">
                                ${match.type}
                                </span>`
							);
						});

						target.find(".items").append(postItem);
					});

					setTimeout(() => {
						searchPostType();
					}, 100);
				}
			},
		});
	}

	const settingsButton = $(".ultimate-admin-search-save-settings");

	settingsButton.click(function (event) {
		console.log("Settings button clicked");
		const form = $(this).closest("form");
		const formData = form.serializeArray();
		console.log("Form data:", formData);
		event.preventDefault(); // Prevent the default action of the button

		$.ajax({
			url: ultimateAdminSearch.ajax_url,
			type: "POST",
			data: formData,
			success: function (response) {
				if (response && response.success) {
					// Handle success
					alert("Settings saved successfully!");
				} else {
					// Handle error
					alert("Error saving settings.");
				}
			},
			error: function (xhr, status, error) {
				console.error("AJAX error:", error);
				alert("Error saving settings: " + error);
			},
		});
	});
});
