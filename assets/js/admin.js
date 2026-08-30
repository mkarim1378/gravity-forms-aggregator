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
	var formError = document.getElementById('gfa-form-error');
	var dateError = document.getElementById('gfa-date-error');
	var formatInput = document.getElementById('gfa-export-format');
	var exportButtons = form.querySelectorAll('.gfa-export-button');
	var selectAllBtn = form.querySelector('.gfa-select-all');
	var deselectAllBtn = form.querySelector('.gfa-deselect-all');
	var rows = form.querySelectorAll('.gfa-forms-table tbody tr');

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

	function setExportButtonsDisabled(disabled) {
		exportButtons.forEach(function (button) {
			button.disabled = disabled;
		});
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

	function updateExportState() {
		setExportButtonsDisabled(getSelectedCount() === 0);
		syncCheckAllState();
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
			updateExportState();
		});
	}

	checkboxes.forEach(function (checkbox) {
		checkbox.addEventListener('change', updateExportState);
	});

	if (selectAllBtn) {
		selectAllBtn.addEventListener('click', function () {
			getVisibleCheckboxes().forEach(function (checkbox) {
				checkbox.checked = true;
			});
			updateExportState();
		});
	}

	if (deselectAllBtn) {
		deselectAllBtn.addEventListener('click', function () {
			getVisibleCheckboxes().forEach(function (checkbox) {
				checkbox.checked = false;
			});
			updateExportState();
		});
	}

	if (searchInput) {
		searchInput.addEventListener('input', filterRows);
	}

	if (fromDate) {
		fromDate.addEventListener('change', validateDates);
	}

	if (toDate) {
		toDate.addEventListener('change', validateDates);
	}

	exportButtons.forEach(function (button) {
		button.addEventListener('click', function (event) {
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
		}
	});

	updateExportState();
	filterRows();
})();
