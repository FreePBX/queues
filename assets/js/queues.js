$(document).ready(function(){
	//cron_custom
	cron_custom();
	$('#cron_schedule').change(cron_custom);

	//cron_schedule
	cron_random();
	$('#cron_schedule').change(cron_random);


	//style cron custom times
	$('#crondiv').find('input[type=checkbox]').button();

})
function cron_custom() {
	if ($('#cron_schedule').val() == 'custom') {
		$('#crondiv').show();
	} else {
		$('#crondiv').hide();
	}
}

function cron_random() {
	switch($('#cron_schedule').val()) {
		case 'never':
		case 'custom':
		case 'reboot':
			$('label[for=cron_random]').hide();
			$('#cron_random').removeAttr("checked").hide();
			break;
		default:
			$('label[for=cron_random]').show();
			$('#cron_random').show();
			break;
	}
}
