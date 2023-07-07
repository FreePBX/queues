<?php

function queues_set_backup_cron() {
	global $amp_conf;

	//remove all stale backup's
	edit_crontab($amp_conf['AMPBIN'] . '/queue_reset_stats.php');

	//get our list of queues
	$queues_list = queues_list(true);
    $queues = [];
    foreach($queues_list as $key => $value) {
		//get queue details
		$queues[$value[0]] = queues_get($value[0],false);
	}

	foreach ($queues as $qid => $q) {


		$cron_vars = ['cron_minute', 'cron_hour', 'cron_dow', 'cron_month', 'cron_dom'];
        	foreach ($cron_vars as $value) {
        	        if (isset($q[$value])) {
				$q[$value] = [$q[$value]];
			}
        	}
		if (!isset($q['cron_schedule'])) {
                        $q['cron_schedule'] = 'never';
                }

		$cron = ['command' => $amp_conf['AMPBIN'] . '/queue_reset_stats.php --id=' . $qid];
		if (!isset($q['cron_random']) || $q['cron_random'] != 'true') {
			switch ($q['cron_schedule']) {
				case 'never':
					$cron = '';
					break;
				case 'hourly':
				case 'daily':
				case 'weekly':
				case 'monthly':
				case 'annually':
				case 'custom':
					$cron['minute']		= isset($q['cron_minute'])	? implode(',', $q['cron_minute'])	: '*';
					$cron['dom']		= isset($q['cron_dom'])		? implode(',', $q['cron_dom'])		: '*';
					$cron['dow']		= isset($q['cron_dow'])		? implode(',', $q['cron_dow'])		: '*';
					$cron['hour']		= isset($q['cron_hour'])	? implode(',', $q['cron_hour'])		: '*';
					$cron['month']		= isset($q['cron_month'])	? implode(',', $q['cron_month'])	: '*';
					break;
				default:
					$cron = '';
					break;
			}
		} else {
			switch ($q['cron_schedule']) {
				case 'annually':
					$cron['month']		= random_int(1, 12);
				case 'monthly':
					$cron['dom']		= random_int(1, 31);
				case 'weekly':
					if(!in_array($q['cron_schedule'], ['annually', 'monthly'])) {
						$cron['dow']	= random_int(0, 6);
					}
				case 'daily':
					$hour				= random_int(0, 7) + 21;
					$cron['hour']		= $hour > 23 ? $hour - 23 : $hour;
				case 'hourly':
					$cron['minute']		= random_int(0, 59);
					break;
				default:
					$cron = '';
					break;
			}
		}

		if ($cron) {
			//dbug('calling cron with ', $cron);
			edit_crontab('', $cron);
		}

	}
}



?>
