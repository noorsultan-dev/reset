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
		if (!window.confirm(drtAdmin.resetConfirm)) {
			e.preventDefault();
		}
	});

	toggleResetButton();
	setActiveTab('reset');
})(jQuery);
