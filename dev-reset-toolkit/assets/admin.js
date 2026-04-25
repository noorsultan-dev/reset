(function ($) {
	'use strict';

	function setActiveTab(tab) {
		$('.drt-tab').removeClass('active');
		$('.drt-tab[data-tab="' + tab + '"]').addClass('active');
		$('.drt-panel').removeClass('active');
		$('#drt-panel-' + tab).addClass('active');
	}

	$(document).on('click', '.drt-tab', function (e) {
		e.preventDefault();
		setActiveTab($(this).data('tab'));
	});

	function toggleResetButton() {
		$('#drt_run_reset').prop('disabled', ($('#drt_confirm').val() || '').trim() !== 'reset');
	}

	$(document).on('input', '#drt_confirm', toggleResetButton);
	$('#drt-reset-form').on('submit', function (e) {
		if (drtAdmin.showConfirmModals && !window.confirm(drtAdmin.resetConfirm)) {
			e.preventDefault();
		}
	});

	$('#drt-clear-local-data').on('click', function () {
		if (!window.confirm('Clear browser localStorage/sessionStorage for this site?')) {
			return;
		}
		try {
			window.localStorage.clear();
			window.sessionStorage.clear();
			alert('Browser local data cleared.');
		} catch (err) {
			alert('Could not clear browser local data.');
		}
	});

	toggleResetButton();
	setActiveTab('reset');
})(jQuery);
