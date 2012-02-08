#!/usr/bin/php -q
<?php
//include bootstrap
$restrict_mods = true;
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
	include_once('/etc/asterisk/freepbx.conf');
}

$queue_toggle_code = isset($argv[1]) ? $argv[1] : '*45';

$var = $astman->database_show('AMPUSER');
foreach ($var as $key => $value) {
	$myvar = explode('/',trim($key,'/'));
	$user_hash[$myvar[1]] = true;
}

foreach (array_keys($user_hash) as $user) {
	if ($user == '') {
		unset($user_hash[$user]);
		continue;
	}
	$user_hash[$user] = get_devices($user);
}

$qpenalty=$astman->database_show('QPENALTY');
$qc = array();
foreach(array_keys($qpenalty) as $key) {
	$key = explode('/', $key);
	if ($key[3] == 'agents') {
		$qc[$key[4]][] = $key[2];
	}
}

foreach ($user_hash as $user => $devices) {
	if (!isset($qc[$user])) {
		continue;
	}
	$device_list = explode('&',$devices);
	foreach ($device_list as $device) {
		set_hint($device, $qc[$user]);
	}
}

//---------------------------------------------------------------------

// Set the hint for a user based on the devices in their AMPUSER object
//
function set_hint($device, $queues) {
	global $queue_toggle_code;

	if (trim($device) == '') {
		return;
	}
	$hlist = 'Custom:QUEUE' . $device . '*' . implode('&Custom:QUEUE' . $device . '*', $queues);
	out("exten => $queue_toggle_code*$device,hint,$hlist");
}


// Get the list of current devices for this user
//
function get_devices($user) {
	global $astman;

	$devices = $astman->database_get('AMPUSER',$user.'/device');
	return trim($devices);
}
