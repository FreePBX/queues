<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

function queues_add(
	$account,
	$name,
	$password,
	$prefix,
	$goto,
	$agentannounce_id,
	$members,
	$joinannounce_id,
	$maxwait,
	$alertinfo='',
	$cwignore='0',
	$qregex='',
	$queuewait='0',
	$use_queue_context='0',
	$dynmembers = '',
	$dynmemberonly = 'no',
	$togglehint = '0',
	$qnoanswer = '0',
	$callconfirm = '0',
	$callconfirm_id = '',
	$monitor_type = '',
	$monitor_heard = '0',
	$monitor_spoken = '0',
	$answered_elsewhere = '0',
	$recording = 'dontcare',
	$rvolume = '',
	$rvol_mode=''
) {
 	global $db,$astman,$amp_conf;

	if (trim((string) $account) == '') {
		echo "<script>javascript:alert('"._("Bad Queue Number, can not be blank")."');</script>";
		return false;
	}

	//add to extensions table
	if (empty($agentannounce_id)) {
		$agentannounce_id=NULL;
	}
	$fields = [
     [$account, 'maxlen', $_REQUEST['maxlen'] ?: '0', 0],
     [$account, 'joinempty', $_REQUEST['joinempty'] ?: 'yes', 0],
     [$account, 'leavewhenempty', $_REQUEST['leavewhenempty'] ?: 'no', 0],
     [$account, 'strategy', $_REQUEST['strategy'] ?: 'ringall', 0],
     [$account, 'timeout', $_REQUEST['timeout'] ?? '15', 0],
     [$account, 'retry', (isset($_REQUEST['retry']) && $_REQUEST['retry'] != '')?$_REQUEST['retry']:'5', 0],
     [$account, 'wrapuptime', $_REQUEST['wrapuptime'] ?: '0', 0],
     [$account, 'announce-frequency', $_REQUEST['announcefreq'] ?: '0', 0],
     [$account, 'min-announce-frequency', $_REQUEST['min-announce'] ?: '15', 0],
     [$account, 'announce-holdtime', $_REQUEST['announceholdtime'] ?: 'no', 0],
     [$account, 'announce-position', $_REQUEST['announceposition'] ?: 'no', 0],
     [$account, 'queue-youarenext', ($_REQUEST['announceposition']=='no')?'silence/1':'queue-youarenext', 0],
     //if no, play no sound
     [$account, 'queue-thereare', ($_REQUEST['announceposition']=='no')?'silence/1':'queue-thereare', 0],
     //if no, play no sound
     [$account, 'queue-callswaiting', ($_REQUEST['announceposition']=='no')?'silence/1':'queue-callswaiting', 0],
     //if no, play no sound
     [$account, 'queue-thankyou', ($_REQUEST['announceposition']=='no')?'':'queue-thankyou', 0],
     //if no, play no sound
     [$account, 'periodic-announce-frequency', $_REQUEST['pannouncefreq'] ?: '0', 0],
     [$account, 'recording', $_REQUEST['recording'] ?: 'dontcare', 0],
     [$account, 'monitor-join', 'yes', 0],
     [$account, 'weight', $_REQUEST['weight'] ?? '0', 0],
     [$account, 'autofill', (!empty($_REQUEST['autofill']))?'yes':'no', 0],
     [$account, 'ringinuse', ($cwignore == 2 || $cwignore == 3)?'no':'yes', 0],
     [$account, 'reportholdtime', $_REQUEST['reportholdtime'] ?? 'no', 0],
     [$account, 'autopause', $_REQUEST['autopause'] ?? 'no', 0],
     [$account, 'autopausedelay', $_REQUEST['autopausedelay'] ?? '0', 0],
     [$account, 'servicelevel', $_REQUEST['servicelevel'] ?? 60, 0],
     [$account, 'memberdelay', $_REQUEST['memberdelay'] ?? '0', 0],
     [$account, 'timeoutrestart', $_REQUEST['timeoutrestart'] ?? 'no', 0],
     [$account, 'skip_joinannounce', $_REQUEST['skip_joinannounce'] ?? '', 0],
     [$account, 'answered_elsewhere', $_REQUEST['answered_elsewhere'] ?? '0', 0],
     [$account, 'timeoutpriority', $_REQUEST['timeoutpriority'] ?? 'app', 0],
     [$account, 'penaltymemberslimit', $_REQUEST['penaltymemberslimit'] ?? '0', 0],
     [$account, 'rvolume', $_REQUEST['rvolume'] ?? '', 0],
     [$account, 'rvol_mode', $_REQUEST['rvol_mode'] ?? '', 0],
     [$account, 'eventwhencalled', ($_REQUEST['eventwhencalled']??'') ?: $amp_conf['QUEUES_EVENTS_MEMEBER_STATUS_DEFAULT'] ?? '', 0],
     [$account, 'eventmemberstatus', ($_REQUEST['eventmemberstatus']??'') ?: $amp_conf['QUEUES_EVENTS_MEMEBER_STATUS_DEFAULT'] ?? '', 0],
     [$account, 'autopausebusy', $_REQUEST['autopausebusy'] ?? 'no', 0],
     [$account, 'autopauseunavail', $_REQUEST['autopauseunavail'] ?? 'no', 0],
 ];
    
    foreach($_REQUEST as $key => $value) {
        switch($key) {
            case 'cron_minute':
            case 'cron_dom':
            case 'cron_dow':
            case 'cron_hour':
            case 'cron_month':
            case 'cron_random':
            case 'cron_schedule':
                if (is_array($value)) {
                    $request_value = implode(',',$value);
                } else {
                    $request_value = $value;
                }

                $fields[] = [$account, $key, $request_value, 0];
                break;
            default:
                break;
        }
    }
	
	if (isset($_REQUEST['music']) && $_REQUEST['music'] != 'inherit') {
		$fields[] = [$account, 'music', $_REQUEST['music'] ?: 'default', 0];
	}

	//there can be multiple members
	if (isset($members)) {
		$count = 0;
		$members = array_unique($members);
		foreach ($members as $member) {
			$fields[] = [$account, 'member', $member, $count];
			$count++;
		}
	}

	$compiled = $db->prepare('INSERT INTO queues_details (id, keyword, data, flags) values (?,?,?,?)');
	$result = $db->executeMultiple($compiled,$fields);

	if($db->IsError($result)) {
		die_freepbx($result->getMessage()."<br><br>error adding to queues_details table");
	}
	$extension		= $account;
	$descr			= $name ?? '';
	$grppre			= $prefix ?? '';
	$alertinfo ??= '';
	if (isset($joinannounce_id)) {
		if ($joinannounce_id == "None" || $joinannounce_id == "") {
			$joinannounce_id = NULL;
		}
	} else {
		$joinannounce_id = NULL;
	}
	$ringing		= $_REQUEST['rtone'] ?? 0;
	if (isset($agentannounce_id)) {
		if ($agentannounce_id == "None" || $agentannounce_id == "") {
			$agentannounce_id = NULL;
		}
	} else {
		$agentannounce_id = NULL;
	}
	if (isset($callconfirm_id)) {
		if ($callconfirm_id == "None" || $callconfirm_id == "") {
			$callconfirm_id = NULL;
		}
	} else {
		$joinannounce_id = NULL;
	}
	$maxwait ??= '';
	$password ??= '';
	$ivr_id			= $_REQUEST['announcemenu'] ?? 'none';
	if (isset($_REQUEST['breakouttype']) && $_REQUEST['breakouttype'] == 'callback') {
		$callback_id	= $_REQUEST['callback'] ?? 'none';
		$ivr_id		= 'none';
	} else {
		$callback_id    = 'none';
	}
	$dest			= $goto ?? '';
	$cwignore ??= '0';
	$queuewait ??= '0';
	$qregex ??= '';
	$use_queue_context ??= '0';
	if (isset($togglehint) && $togglehint == "") {
		$togglehint = 0;
	}
	if (isset($callconfirm) && $callconfirm == "") {
		$callconfirm = 0;
	}
	$qnoanswer ??= '0';
	$monitor_type ??= '';
	$monitor_heard ??= '0';
	$monitor_spoken ??= '0';
	// Assumes it has just been deleted
	$sql = "INSERT INTO queues_config (
			extension, descr, grppre, alertinfo, joinannounce_id,
			ringing, agentannounce_id, maxwait, password, ivr_id,
			callback_id, dest, cwignore, qregex, queuewait,
			use_queue_context, togglehint, qnoanswer, callconfirm,
			callconfirm_id, monitor_type, monitor_heard,
			monitor_spoken
		) VALUES (
			?, ?, ?, ?, ?,
			?, ?, ?, ?, ?,
			?, ?, ?, ?, ?,
			?, ?, ?, ?,
			?, ?, ?,
			?
		)";
	$sth = $db->prepare($sql);
	$values = [$extension, $descr, $grppre, $alertinfo, $joinannounce_id, $ringing, $agentannounce_id, $maxwait, $password, $ivr_id, $callback_id, $dest, $cwignore, $qregex, $queuewait, $use_queue_context, $togglehint, $qnoanswer, $callconfirm, $callconfirm_id, $monitor_type, $monitor_heard, $monitor_spoken];
	$results = $sth->execute($values);

  // store dynamic member data in astDB
	if ($astman) {
		$dynmembers = array_unique($dynmembers);
		foreach($dynmembers as $member){
			$mem = explode(',',(string) $member);
			if (isset($mem[0]) && trim($mem[0]) != '') {
				$penalty = isset($mem[1]) && ctype_digit(trim($mem[1])) ? $mem[1] : 0;
 				$astman->database_put('QPENALTY/'.$account.'/agents',trim($mem[0]),trim($penalty));
			}
		}
		$astman->database_put('QPENALTY/'.$account,'dynmemberonly',$dynmemberonly);
	} else {
		fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
	}

	$action = $_REQUEST['action'] ?? '';
	$res =  $astman->send_request("UserEvent", ["userEvent" => "update-queue-contacts", "reloadQueue" => $action == "add" ? '1' : '0', "queueId" => $account, "queueName" => $descr, "staticMembers" => json_encode($members, JSON_THROW_ON_ERROR), "dynamicMembers" => json_encode($dynmembers, JSON_THROW_ON_ERROR)]);
	return true;
}

function queues_del($account) {
	global $db, $astman, $amp_conf;

	$sql = "DELETE FROM queues_details WHERE id = '$account'";
	$result = $db->query($sql);
	if($db->IsError($result)) {
		die_freepbx($result->getMessage().$sql);
    }
	$sql = "DELETE FROM queues_config WHERE extension = '$account'";
	$result = $db->query($sql);
	if($db->IsError($result)) {
 		die_freepbx($result->getMessage().$sql);
    }

	//remove dynamic memebers from astDB
	if ($astman) {
	  $astman->database_deltree('QPENALTY/'.$account);
	} else {
		fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
	}
	$action = $_REQUEST['action'] ?? '';
	if ($action == 'delete') {
		$res =  $astman->send_request("UserEvent", ["userEvent" => "delete-queue", "queueId" => $account]);
	}
}

//get the existing queue extensions
//
function queues_list($listall=false) {
	global $db;
	$sql = "SELECT extension, descr,callback_id,use_queue_context FROM queues_config ORDER BY extension";
	$results = $db->getAll($sql);
	if($db->IsError($results)) {
		$results = [];
	}

	foreach($results as $result){
		if ($listall || checkRange($result[0])){
			$extens[] = [$result[0], $result[1], $result[2], $result[3]];
		}
	}
	if (isset($extens)) {
		return $extens;
	} else {
		return [];
	}
}

function queues_get_static_members($account = '') {
	global $db;
	if ($account != '') {
		$sql = "SELECT data FROM queues_details WHERE id = $account AND keyword = 'member' ORDER BY flags";
		return $db->getCol($sql);
	} else {
		$sql = "SELECT id, data FROM queues_details WHERE keyword = 'member' ORDER BY id, flags";
		$res = $db->getAll($sql,DB_FETCHMODE_ASSOC);
		$mem_hash = [];
		foreach ($res as $qmp) {
			$mem_hash[$qmp['id']][] = $qmp['data'];
		}
		return $mem_hash;
	}
}

function queues_get($account, $queues_conf_only=false) {
	global $db,$astman,$amp_conf;

    if ($account == "") {
	    return [];
    }

	$account = q($account);
	//get all the variables for the queue
	$sql = "SELECT keyword,data FROM queues_details WHERE id = $account";
	$results = $db->getAssoc($sql);
	if (empty($results)) {
		return [];
	}

	//okay, but there can be multiple member variables ... do another select for them
	$results['member'] = queues_get_static_members($account);

	//if 'queue-youarenext=queue-youarenext', then assume we want to announce position
	if (!$queues_conf_only) {
		if(isset($results['queue-youarenext']) && $results['queue-youarenext'] == 'queue-youarenext') {
			$results['announce-position'] = 'yes';
		} else {
			$results['announce-position'] = 'no';
		}
	}

	//if 'eventmemberstatusoff=Yes', then assume we want to 'eventmemberstatus=no'
	if(isset($results['eventmemberstatusoff'])) {
		if (strtolower((string) $results['eventmemberstatusoff']) == 'yes') {
			$results['eventmemberstatus'] = 'no';
		} else {
			$results['eventmemberstatus'] = 'yes';
		}
	} elseif (!isset($results['eventmemberstatus'])){
		$results['eventmemberstatus'] = 'no';
	}

	if ($queues_conf_only) {
		$sql = "SELECT ivr_id, callback_id FROM queues_config WHERE extension = $account";
		$config = sql($sql, "getRow",DB_FETCHMODE_ASSOC);
	} else {
		$sql = "SELECT * FROM queues_config WHERE extension = $account";
		$config = sql($sql, "getRow",DB_FETCHMODE_ASSOC);

		$results['prefix']        = $config['grppre'];
		$results['alertinfo']     = $config['alertinfo'];
		$results['agentannounce_id'] = $config['agentannounce_id'];
		$results['maxwait']       = $config['maxwait'];
		$results['name']          = $config['descr'];
		$results['joinannounce_id']  = $config['joinannounce_id'];
		$results['password']      = $config['password'];
		$results['goto']          = $config['dest'];
		$results['announcemenu']  = $config['ivr_id'];
		$results['callback']      = $config['callback_id'];
		$results['rtone']         = $config['ringing'];
		$results['cwignore']      = $config['cwignore'];
		$results['qregex']        = $config['qregex'];
		$results['queuewait']     = $config['queuewait'];
		$results['use_queue_context'] = $config['use_queue_context'];
		$results['togglehint']    = $config['togglehint'];
		$results['qnoanswer']     = $config['qnoanswer'];
		$results['callconfirm']    = $config['callconfirm'];
		$results['callconfirm_id'] = $config['callconfirm_id'];
		$results['monitor_type']   = $config['monitor_type'];
		$results['monitor_heard']   = $config['monitor_heard'];
		$results['monitor_spoken']   = $config['monitor_spoken'];

		// TODO: why the str_replace?
    		//
		if ($astman) {
			$account = str_replace("'",'',(string) $account);
			//get dynamic members priority from astDB
			$get = $astman->database_show('QPENALTY/'.$account.'/agents');
			if (empty($get)) {
				$astman->useCaching = false;
				$get = $astman->database_show('QPENALTY/'.$account.'/agents');
			}
			if($get){
				foreach($get as $key => $value){
					$key1=explode('/',(string) $key);
					$mem[$key1[4]]=$value;
				}
				foreach($mem as $mem => $pnlty){
					$dynmem[]=$mem.','.$pnlty;
				}
				$results['dynmembers']=implode("\n",$dynmem);
			} else {
				$results['dynmembers']='';
			}
			$results['dynmemberonly'] = $astman->database_get('QPENALTY/'.$account,'dynmemberonly');
		} else {
			fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
		}
	}

	$results['context'] = '';
	$results['periodic-announce'] = '';

	if ($config['ivr_id'] != 'none' && $config['ivr_id'] != '') {
		if (function_exists('ivr_get_details')) {
			$results['context'] = "ivr-".$config['ivr_id'];
			$arr = ivr_get_details($config['ivr_id']);
			if( isset($arr['announcement']) && $arr['announcement'] != '') {
				$periodic = recordings_get_file($arr['announcement']);
				// We need to strip off all but the first sound file of any compound sound files
				$periodic_arr = explode("&", (string) $periodic);
				$results['periodic-announce'] = $periodic_arr[0];
			}
		}
	} else if ($config['callback_id'] != 'none' && $config['callback_id'] != '') {
		if (function_exists('vqplus_callback_get')) {
			$results['context'] = "queuecallback-".$config['callback_id'];
			$arr = vqplus_callback_get($config['callback_id']);

			if (isset($arr[0]) && is_array($arr[0])) {
				/* Vqplus 13 moved to BMO, which made the return value format different. */
				$arr = $arr[0];
			}

			if (isset($arr['announcement']) && $arr['announcement'] != '') {
				$periodic = recordings_get_file($arr['announcement']);
				// We need to strip off all but the first sound file of any compound sound files
				$periodic_arr = explode("&", (string) $periodic);
				$results['periodic-announce'] = $periodic_arr[0];
			}
		}
	}
	return $results;
}
