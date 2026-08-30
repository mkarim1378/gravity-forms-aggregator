(function () {
	'use strict';

	if (typeof gfaEntriesList === 'undefined') {
		return;
	}

	var fromDate = document.getElementById('gfa-entries-from-date');
	var toDate = document.getElementById('gfa-entries-to-date');
	var applyBtn = document.getElementById('gfa-entries-apply');
	var dateError = document.getElementById('gfa-entries-date-error');
	var tbody = document.getElementById('gfa-entries-tbody');
	var meta = document.getElementById('gfa-entries-meta');
	var loading = document.getElementById('gfa-entries-loading');
	var emptyNotice = document.getElementById('gfa-entries-empty');
	var pagination = document.getElementById('gfa-entries-pagination');
	var currentPage = 1;
	var listRequest = null;

	function hideError() {
		if (!dateError) {
			return;
		}
		dateError.hidden = true;
		dateError.textContent = '';
	}

	function showError(message) {
		if (!dateError) {
			return;
		}
		dateError.hidden = false;
		dateError.textContent = message;
	}

	function validateDates() {
		hideError();

		if (!fromDate || !toDate || !fromDate.value || !toDate.value) {
			return true;
		}

		if (fromDate.value > toDate.value) {
			showError(gfaEntriesList.i18n.invalidDateRange);
			return false;
		}

		return true;
	}

	function formatMessage(template) {
		var args = Array.prototype.slice.call(arguments, 1);
		return args.reduce(function (text, value, index) {
			return text.replace('%' + (index + 1) + '$d', String(value)).replace('%d', String(value));
		}, template);
	}

	function setLoading(isLoading) {
		if (loading) {
			loading.hidden = !isLoading;
		}
		if (applyBtn) {
			applyBtn.disabled = isLoading;
		}
	}

	function renderPagination(data) {
		if (!pagination) {
			return;
		}

		if (!data.total_pages || data.total_pages <= 1) {
			pagination.hidden = true;
			pagination.innerHTML = '';
			return;
		}

		var page = data.page || 1;
		var totalPages = data.total_pages || 1;
		var prevDisabled = page <= 1 ? ' disabled' : '';
		var nextDisabled = page >= totalPages ? ' disabled' : '';

		pagination.innerHTML =
			'<button type="button" class="button gfa-entries-page-btn" data-page="' + Math.max(1, page - 1) + '"' + prevDisabled + '>' + gfaEntriesList.i18n.previous + '</button>' +
			'<span class="gfa-entries-page-label">' + formatMessage(gfaEntriesList.i18n.pageOf, page, totalPages) + '</span>' +
			'<button type="button" class="button gfa-entries-page-btn" data-page="' + Math.min(totalPages, page + 1) + '"' + nextDisabled + '>' + gfaEntriesList.i18n.next + '</button>';

		pagination.hidden = false;
		bindPagination();
	}

	function bindPagination() {
		if (!pagination) {
			return;
		}

		pagination.querySelectorAll('.gfa-entries-page-btn').forEach(function (button) {
			button.addEventListener('click', function () {
				if (button.disabled) {
					return;
				}
				currentPage = parseInt(button.getAttribute('data-page'), 10) || 1;
				loadPage(currentPage);
			});
		});
	}

	function loadPage(page) {
		if (!validateDates()) {
			return;
		}

		if (listRequest) {
			listRequest.abort();
		}

		setLoading(true);

		var payload = new FormData();
		payload.append('action', 'gfa_entries_list');
		payload.append('nonce', gfaEntriesList.nonce);
		payload.append('gfa_page', String(page || 1));
		payload.append('gfa_per_page', '25');

		if (fromDate && fromDate.value) {
			payload.append('gfa_from_date', fromDate.value);
		}
		if (toDate && toDate.value) {
			payload.append('gfa_to_date', toDate.value);
		}

		listRequest = new XMLHttpRequest();
		listRequest.open('POST', gfaEntriesList.ajaxUrl, true);
		listRequest.onreadystatechange = function () {
			var xhr = this;

			if (xhr.readyState !== 4 || xhr !== listRequest) {
				return;
			}

			setLoading(false);
			listRequest = null;

			if (xhr.status === 0) {
				return;
			}

			var response;
			try {
				response = JSON.parse(xhr.responseText);
			} catch (error) {
				showError(gfaEntriesList.i18n.loadFailed);
				return;
			}

			if (!response.success) {
				showError(response.data && response.data.message ? response.data.message : gfaEntriesList.i18n.loadFailed);
				return;
			}

			hideError();
			currentPage = response.data.page || 1;

			if (tbody) {
				tbody.innerHTML = response.data.html || '';
			}

			if (meta) {
				meta.textContent = formatMessage(gfaEntriesList.i18n.entriesTotal, response.data.total || 0);
			}

			if (emptyNotice) {
				emptyNotice.hidden = (response.data.total || 0) > 0;
			}

			renderPagination(response.data);
		};

		listRequest.send(payload);
	}

	if (applyBtn) {
		applyBtn.addEventListener('click', function () {
			currentPage = 1;
			loadPage(1);
		});
	}

	bindPagination();
})();
