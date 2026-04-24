(function ($) {
	'use strict';

	function toggleResetButton() {
		var confirmation = ($('#drt_confirm_word').val() || '').trim();
		$('#drt-run-reset').prop('disabled', confirmation !== 'reset');
	}

	$(document).on('input', '#drt_confirm_word', toggleResetButton);

	$('#drt-reset-form').on('submit', function (e) {
		var resetType = $('#drt_reset_type').val();
		if (!resetType) {
			e.preventDefault();
			window.alert('Please select a reset type.');
			return;
		}
		var confirmation = ($('#drt_confirm_word').val() || '').trim();
		if (confirmation !== 'reset') {
			e.preventDefault();
			window.alert('Type exactly "reset" to continue.');
			return;
		}
		var msg = drtAdmin.confirmMessages[resetType] || 'Are you sure you want to run this reset?';
		if (!window.confirm(msg)) {
			e.preventDefault();
		}
	});

	toggleResetButton();
})(jQuery);
