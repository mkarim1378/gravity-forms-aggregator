(function () {
	'use strict';

	var form = document.getElementById('gfa-export-form');
	if (!form || typeof gfaAdmin === 'undefined') {
		return;
	}

	var checkAll = document.getElementById('gfa-check-all');
	var checkboxes = form.querySelectorAll('.gfa-form-checkbox');
	var searchInput = document.getElementById('gfa-form-search');
	var fromDate = document.getElementById('gfa-from-date');
	var toDate = document.getElementById('gfa-to-date');
	var exportMode = document.getElementById('gfa-export-mode');
	var formError = document.getElementById('gfa-form-error');
	var dateError = document.getElementById('gfa-date-error');
	var formatInput = document.getElementById('gfa-export-format');
	var exportButtons = form.querySelectorAll('.gfa-export-button');
	var previewButton = document.getElementById('gfa-preview-button');
	var previewPanel = document.getElementById('gfa-preview-panel');
	var previewLoading = document.getElementById('gfa-preview-loading');
	var previewError = document.getElementById('gfa-preview-error');
	var previewSummary = document.getElementById('gfa-preview-summary');
	var previewEmptyForms = document.getElementById('gfa-preview-empty-forms');
	var previewStaleForms = document.getElementById('gfa-preview-stale-forms');
	var previewNoEntries = document.getElementById('gfa-preview-no-entries');
	var selectAllBtn = form.querySelector('.gfa-select-all');
	var deselectAllBtn = form.querySelector('.gfa-deselect-all');
	var rows = form.querySelectorAll('.gfa-forms-table tbody tr');
	var previewRequest = null;

	function getVisibleCheckboxes() {
		return Array.prototype.filter.call(checkboxes, function (checkbox) {
			var row = checkbox.closest('tr');
			return row && !row.classList.contains('gfa-hidden-by-search');
		});
	}

	function getSelectedCount() {
		return Array.prototype.filter.call(checkboxes, function (checkbox) {
			return checkbox.checked;
		}).length;
	}

	function syncCheckAllState() {
		if (!checkAll) {
			return;
		}

		var visible = getVisibleCheckboxes();
		if (!visible.length) {
			checkAll.checked = false;
			checkAll.indeterminate = false;
			return;
		}

		var checkedVisible = visible.filter(function (checkbox) {
			return checkbox.checked;
		}).length;

		checkAll.checked = checkedVisible === visible.length;
		checkAll.indeterminate = checkedVisible > 0 && checkedVisible < visible.length;
	}

	function setButtonsDisabled(disabled) {
		exportButtons.forEach(function (button) {
			button.disabled = disabled;
		});

		if (previewButton) {
			previewButton.disabled = disabled;
		}
	}

	function hideError(element) {
		if (!element) {
			return;
		}
		element.hidden = true;
		element.textContent = '';
	}

	function showError(element, message) {
		if (!element) {
			return;
		}
		element.hidden = false;
		element.textContent = message;
	}

	function validateDates() {
		hideError(dateError);

		if (!fromDate || !toDate || !fromDate.value || !toDate.value) {
			return true;
		}

		if (fromDate.value > toDate.value) {
			showError(dateError, gfaAdmin.i18n.invalidDateRange);
			return false;
		}

		return true;
	}

	function validateForms() {
		hideError(formError);

		if (getSelectedCount() === 0) {
			showError(formError, gfaAdmin.i18n.noFormsSelected);
			return false;
		}

		return true;
	}

	function formatMessage(template, value) {
		return template.replace('%d', String(value)).replace('%s', String(value));
	}

	function resetPreviewPanel() {
		if (!previewPanel) {
			return;
		}

		if (previewRequest) {
			previewRequest.abort();
			previewRequest = null;
		}

		previewPanel.hidden = true;
		hideError(previewError);
		if (previewLoading) {
			previewLoading.hidden = true;
		}
		if (previewSummary) {
			previewSummary.hidden = true;
			previewSummary.innerHTML = '';
		}
		if (previewEmptyForms) {
			previewEmptyForms.hidden = true;
			previewEmptyForms.textContent = '';
		}
		if (previewStaleForms) {
			previewStaleForms.hidden = true;
			previewStaleForms.textContent = '';
		}
		if (previewNoEntries) {
			previewNoEntries.hidden = true;
			previewNoEntries.textContent = '';
		}
	}

	function renderPreview(data) {
		if (!previewPanel || !previewSummary) {
			return;
		}

		previewPanel.hidden = false;
		hideError(previewError);
		if (previewLoading) {
			previewLoading.hidden = true;
		}

		previewSummary.innerHTML = '';
		[
			formatMessage(gfaAdmin.i18n.formsSelected, data.form_count),
			formatMessage(gfaAdmin.i18n.entriesFound, data.entry_count),
			formatMessage(gfaAdmin.i18n.dateRange, data.date_label),
			formatMessage(gfaAdmin.i18n.exportMode, data.export_mode_label || data.export_mode || '')
		].forEach(function (text) {
			var item = document.createElement('li');
			item.textContent = text;
			previewSummary.appendChild(item);
		});
		previewSummary.hidden = false;

		if (previewEmptyForms && data.empty_form_ids && data.empty_form_ids.length) {
			previewEmptyForms.textContent = formatMessage(
				gfaAdmin.i18n.emptyFormsWarning,
				data.empty_form_ids.join(', ')
			);
			previewEmptyForms.hidden = false;
		} else if (previewEmptyForms) {
			previewEmptyForms.hidden = true;
		}

		if (previewStaleForms && data.stale_form_ids && data.stale_form_ids.length) {
			previewStaleForms.textContent = formatMessage(
				gfaAdmin.i18n.staleFormsWarning,
				data.stale_form_ids.join(', ')
			);
			previewStaleForms.hidden = false;
		} else if (previewStaleForms) {
			previewStaleForms.hidden = true;
		}

		if (previewNoEntries) {
			if (!data.has_entries) {
				previewNoEntries.textContent = gfaAdmin.i18n.noEntriesWarning;
				previewNoEntries.hidden = false;
			} else {
				previewNoEntries.hidden = true;
			}
		}
	}

	function buildFormPayload(action) {
		var payload = new FormData(form);
		payload.append('action', action);
		payload.append('nonce', gfaAdmin.nonce);
		return payload;
	}

	function runPreview() {
		if (!validateForms() || !validateDates()) {
			return;
		}

		if (!previewPanel || !previewLoading) {
			return;
		}

		if (previewRequest) {
			previewRequest.abort();
		}

		previewPanel.hidden = false;
		previewLoading.hidden = false;
		hideError(previewError);
		if (previewSummary) {
			previewSummary.hidden = true;
		}
		if (previewEmptyForms) {
			previewEmptyForms.hidden = true;
		}
		if (previewStaleForms) {
			previewStaleForms.hidden = true;
		}
		if (previewNoEntries) {
			previewNoEntries.hidden = true;
		}

		previewRequest = new XMLHttpRequest();
		previewRequest.open('POST', gfaAdmin.ajaxUrl, true);
		previewRequest.onreadystatechange = function () {
			var xhr = this;

			if (xhr.readyState !== 4 || xhr !== previewRequest) {
				return;
			}

			previewLoading.hidden = true;
			previewRequest = null;

			if (xhr.status === 0) {
				return;
			}

			var response;
			try {
				response = JSON.parse(xhr.responseText);
			} catch (error) {
				showError(previewError, gfaAdmin.i18n.previewFailed);
				return;
			}

			if (!response.success) {
				showError(
					previewError,
					response.data && response.data.message ? response.data.message : gfaAdmin.i18n.previewFailed
				);
				return;
			}

			renderPreview(response.data);
		};

		previewRequest.send(buildFormPayload('gfa_export_preview'));
	}

	function updateExportState() {
		setButtonsDisabled(getSelectedCount() === 0);
		syncCheckAllState();
	}

	function invalidatePreview() {
		resetPreviewPanel();
	}

	function filterRows() {
		var query = searchInput ? searchInput.value.trim().toLowerCase() : '';

		rows.forEach(function (row) {
			var title = row.getAttribute('data-form-title') || '';
			var id = row.getAttribute('data-form-id') || '';
			var matches = !query || title.indexOf(query) !== -1 || id.indexOf(query) !== -1;
			row.classList.toggle('gfa-hidden-by-search', !matches);
		});

		syncCheckAllState();
	}

	if (checkAll) {
		checkAll.addEventListener('change', function () {
			var checked = checkAll.checked;
			getVisibleCheckboxes().forEach(function (checkbox) {
				checkbox.checked = checked;
			});
			invalidatePreview();
			updateExportState();
		});
	}

	checkboxes.forEach(function (checkbox) {
		checkbox.addEventListener('change', function () {
			invalidatePreview();
			updateExportState();
		});
	});

	if (selectAllBtn) {
		selectAllBtn.addEventListener('click', function () {
			getVisibleCheckboxes().forEach(function (checkbox) {
				checkbox.checked = true;
			});
			invalidatePreview();
			updateExportState();
		});
	}

	if (deselectAllBtn) {
		deselectAllBtn.addEventListener('click', function () {
			getVisibleCheckboxes().forEach(function (checkbox) {
				checkbox.checked = false;
			});
			invalidatePreview();
			updateExportState();
		});
	}

	if (searchInput) {
		searchInput.addEventListener('input', filterRows);
	}

	if (fromDate) {
		fromDate.addEventListener('change', function () {
			validateDates();
			invalidatePreview();
		});
	}

	if (toDate) {
		toDate.addEventListener('change', function () {
			validateDates();
			invalidatePreview();
		});
	}

	if (exportMode) {
		exportMode.addEventListener('change', invalidatePreview);
	}

	if (previewButton) {
		previewButton.addEventListener('click', runPreview);
	}

	exportButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			if (formatInput) {
				formatInput.value = button.getAttribute('data-format') || 'csv';
			}
		});
	});

	form.addEventListener('submit', function (event) {
		var formsValid = validateForms();
		var datesValid = validateDates();

		if (!formsValid || !datesValid) {
			event.preventDefault();
			return;
		}

		exportButtons.forEach(function (button) {
			if (!button.disabled) {
				button.classList.add('gfa-is-loading');
				button.dataset.gfaOriginalText = button.textContent;
				button.textContent = gfaAdmin.i18n.exporting;
				button.disabled = true;
			}
		});

		if (previewButton) {
			previewButton.disabled = true;
		}
	});

	updateExportState();
	filterRows();
})();
