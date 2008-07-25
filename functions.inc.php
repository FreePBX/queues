<?php /* $id:$ */

class queues_conf {
	// return an array of filenames to write
	// files named like pinset_N
	function get_filename() {
		return "queues_additional.conf";
	}
	
	// return the output that goes in each of the files
	function generateConf() {

		global $db;
		global $version;

		$additional = "";
		$output = "";
		// Asterisk 1.4 does not like blank assignments so just don't put them there
		//
		$ver12 = version_compare($version, '1.4', 'lt');
		
		// legacy but in case someone was using this we will leave it
		//
		$sql = "SELECT keyword,data FROM queues_details WHERE id='-1' AND keyword <> 'account'";
		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if(DB::IsError($results)) {
   		die($results->getMessage());
		}
		foreach ($results as $result) {
			if (!$ver12 && trim($result['data']) == '') {
				continue;
			}
			$additional .= $result['keyword']."=".$result['data']."\n";
		}

		$results = queues_list(true);
		foreach ($results as $result) {
			$output .= "[".$result[0]."]\n";

			// passing 2nd param 'true' tells queues_get to send back only queue_conf required params
			// and nothing else
			//
			$results2 = queues_get($result[0], true);

			// memebers is an array of members so we set it asside and remove it
			// and then generate each later
			//
			$members = $results2['member'];
			unset($results2['member']);

			foreach ($results2 as $keyword => $data) {
				if ($ver12){
					switch($keyword){
						case 'ringinuse': 
							break;
						default:
							$output .= $keyword."=".$data."\n";
							break;
					}
				}else{
					switch($keyword){
						case (trim($data) == ''):
						case 'monitor-join': 
							break;
						case 'monitor-format':
							if (strtolower($data) != 'no'){
								$output .= "monitor-type=mixmonitor\n";
								$output .= $keyword."=".$data."\n";
							}
							break;
						default:
							$output .= $keyword."=".$data."\n";
							break;
					}
				}
			}

			// Now pull out all the memebers, one line for each
			//
			foreach ($members as $member) {
				$output .= "member=".$member."\n";
			}
			$output .= $additional."\n";
		}

		// Before returning the results, do an integrity check to see
		// if there are any truncated compound recrodings and if so
		// crate a noticication.
		//
		$nt = notifications::create($db);

		$compound_recordings = queues_check_compoundrecordings();
		if (empty($compound_recordings)) {
			$nt->delete('queues', 'COMPOUNDREC');
		} else {
			$str = _("Warning, there are compound recordings configured in one or more Queue configurations. Queues can not play these so they have been truncated to the first sound file. You should correct this problem.<br />Details:<br /><br />");
			foreach ($compound_recordings as $item) {
				$str .= sprintf(_("Queue - %s (%s): %s<br />"), $item['extension'], $item['descr'], $item['error']);
			}
			$nt->add_error('queues', 'COMPOUNDREC', _("Compound Recordings in Queues Detected"), $str);
		}
		return $output;
	}
}

// The destinations this module provides
// returns a associative arrays with keys 'destination' and 'description'
function queues_destinations() {
	//get the list of all exisiting
	$results = queues_list(true);
	
	//return an associative array with destination and description
	if (isset($results)) {
		foreach($results as $result){
				$extens[] = array('destination' => 'ext-queues,'.$result['0'].',1', 'description' => $result['1'].' <'.$result['0'].'>');
		}
	}
	
	if (isset($extens)) 
		return $extens;
	else
		return null;
}

function queues_getdest($exten) {
	return array('ext-queues,'.$exten.',1');
}

function queues_getdestinfo($dest) {
	global $active_modules;

	if (substr(trim($dest),0,11) == 'ext-queues,') {
		$exten = explode(',',$dest);
		$exten = $exten[1];
		$thisexten = queues_get($exten);
		if (empty($thisexten)) {
			return array();
		} else {
			//$type = isset($active_modules['announcement']['type'])?$active_modules['announcement']['type']:'setup';
			return array('description' => 'Queue '.$exten.' : '.$thisexten['name'],
			             'edit_url' => 'config.php?display=queues&extdisplay='.urlencode($exten),
								  );
		}
	} else {
		return false;
	}
}

function queues_recordings_usage($recording_id) {
	global $active_modules;

	$results = sql("SELECT `extension`, `descr` FROM `queues_config` WHERE `agentannounce_id` = '$recording_id' OR `joinannounce_id` = '$recording_id'","getAll",DB_FETCHMODE_ASSOC);
	if (empty($results)) {
		return array();
	} else {
		//$type = isset($active_modules['queues']['type'])?$active_modules['queues']['type']:'setup';
		foreach ($results as $result) {
			$usage_arr[] = array(
			  'url_query' => 'config.php?display=queues&extdisplay='.urlencode($result['extension']),
				'description' => "Queue: ".$result['descr'],
			);
		}
		return $usage_arr;
	}
}

function queues_ivr_usage($ivr_id) {
	global $active_modules;

	$results = sql("SELECT `extension`, `descr` FROM `queues_config` WHERE `ivr_id` = '$ivr_id'","getAll",DB_FETCHMODE_ASSOC);
	if (empty($results)) {
		return array();
	} else {
		foreach ($results as $result) {
			$usage_arr[] = array(
			  'url_query' => 'config.php?display=queues&extdisplay='.urlencode($result['extension']),
				'description' => "Queue: ".$result['descr'],
			);
		}
		return $usage_arr;
	}
}

/* 	Generates dialplan for "queues" components (extensions & inbound routing)
	We call this with retrieve_conf
*/
function queues_get_config($engine) {
	global $ext;  // is this the best way to pass this?
	switch($engine) {
		case "asterisk":
			/* queue extensions */
			$ext->addInclude('from-internal-additional','ext-queues');
			$qlist = queues_list(true);
			if (is_array($qlist)) {
				foreach($qlist as $item) {
					
					$exten = $item[0];
					$q = queues_get($exten);

					$grppre = (isset($q['prefix'])?$q['prefix']:'');
					$alertinfo = (isset($q['alertinfo'])?$q['alertinfo']:'');

					// Not sure why someone would ever have a ; in the regex, but since Asterisk has problems with them
					// it would need to be escaped
					//
					$qregex = (isset($q['qregex'])?$q['qregex']:'');
					str_replace(';','\;',$qregex);
					
					$ext->add('ext-queues', $exten, '', new ext_macro('user-callerid'));
					$ext->add('ext-queues', $exten, '', new ext_answer(''));

					// block voicemail until phone is answered at which point a macro should be called on the answering
					// line to clear this flag so that subsequent transfers can occur.
					//
					$ext->add('ext-queues', $exten, '', new ext_setvar('__BLKVM_OVERRIDE', 'BLKVM/${EXTEN}/${CHANNEL}'));
					$ext->add('ext-queues', $exten, '', new ext_setvar('__BLKVM_BASE', '${EXTEN}'));
					$ext->add('ext-queues', $exten, '', new ext_setvar('DB(${BLKVM_OVERRIDE})', 'TRUE'));
					$ext->add('ext-queues', $exten, '', new ext_execif('$["${REGEX("(M[(]auto-blkvm[)])" ${DIAL_OPTIONS})}" != "1"]', 'Set', '_DIAL_OPTIONS=${DIAL_OPTIONS}M(auto-blkvm)'));

					// Inform all the children NOT to send calls to destinations or voicemail
					//
					$ext->add('ext-queues', $exten, '', new ext_setvar('__NODEST', '${EXTEN}'));

					// deal with group CID prefix
					// Use the same variable as ringgroups/followme so that we can manage chaines of calls
					// but strip only if you plan on setting a new one
					//
					if ($grppre != '') {
						$ext->add('ext-queues', $exten, '', new ext_gotoif('$["foo${RGPREFIX}" = "foo"]', 'REPCID'));
						$ext->add('ext-queues', $exten, '', new ext_gotoif('$["${RGPREFIX}" != "${CALLERID(name):0:${LEN(${RGPREFIX})}}"]', 'REPCID'));
						$ext->add('ext-queues', $exten, '', new ext_noop('Current RGPREFIX is ${RGPREFIX}....stripping from Caller ID'));
						$ext->add('ext-queues', $exten, '', new ext_setvar('CALLERID(name)', '${CALLERID(name):${LEN(${RGPREFIX})}}'));
						$ext->add('ext-queues', $exten, '', new ext_setvar('_RGPREFIX', ''));
						$ext->add('ext-queues', $exten, 'REPCID', new ext_noop('CALLERID(name) is ${CALLERID(name)}'));
						$ext->add('ext-queues', $exten, '', new ext_setvar('_RGPREFIX', $grppre));
						$ext->add('ext-queues', $exten, '', new ext_setvar('CALLERID(name)','${RGPREFIX}${CALLERID(name)}'));
					}

					// Set Alert_Info
					if ($alertinfo != '') {
						$ext->add('ext-queues', $exten, '', new ext_setvar('__ALERT_INFO', str_replace(';', '\;', $alertinfo)));
					}

					$ext->add('ext-queues', $exten, '', new ext_setvar('MONITOR_FILENAME','/var/spool/asterisk/monitor/q${EXTEN}-${STRFTIME(${EPOCH},,%Y%m%d-%H%M%S)}-${UNIQUEID}'));
					$joinannounce_id = (isset($q['joinannounce_id'])?$q['joinannounce_id']:'');
					if($joinannounce_id) {
						$joinannounce = recordings_get_file($joinannounce_id);
						$ext->add('ext-queues', $exten, '', new ext_playback($joinannounce));
					}
					$options = 't';
					if ($q['rtone'] == 1)
						$options .= 'r';
					if (isset($q['music'])) {
 						$ext->add('ext-queues', $exten, '', new ext_setvar('__MOHCLASS', $q['music']));
					}
					// Set CWIGNORE  if enabled so that busy agents don't have another line key ringing and
					// stalling the ACD.
					if ($q['cwignore']) {
 						$ext->add('ext-queues', $exten, '', new ext_setvar('_CWIGNORE', 'TRUE'));
					}
					$agentannounce_id = (isset($q['agentannounce_id'])?$q['agentannounce_id']:'');
					if ($agentannounce_id) {
						$agentannounce = recordings_get_file($agentannounce_id);
					} else {
						$agentannounce = '';
					}
					$ext->add('ext-queues', $exten, '', new ext_queue($exten,$options,'',$agentannounce,$q['maxwait']));
 
					$ext->add('ext-queues', $exten, '', new ext_dbdel('${BLKVM_OVERRIDE}'));
 					// If we are here, disable the NODEST as we want things to resume as normal
 					//
 					$ext->add('ext-queues', $exten, '', new ext_setvar('__NODEST', ''));
	
					// destination field in 'incoming' database is backwards from what ext_goto expects
					$goto_context = strtok($q['goto'],',');
					$goto_exten = strtok(',');
					$goto_pri = strtok(',');
					
					$ext->add('ext-queues', $exten, '', new ext_goto($goto_pri,$goto_exten,$goto_context));
					
					//dynamic agent login/logout
					if (trim($qregex) != '') {
 						$ext->add('ext-queues', $exten."*", '', new ext_setvar('QREGEX', $qregex));
					}
					$ext->add('ext-queues', $exten."*", '', new ext_macro('agent-add',$exten.",".$q['password']));
					$ext->add('ext-queues', $exten."**", '', new ext_macro('agent-del',$exten.",".$exten));
				}
			}
		break;
	}
}

function queues_timeString($seconds, $full = false) {
	if ($seconds == 0) {
		return "0 ".($full ? "seconds" : "s");
	}

	$minutes = floor($seconds / 60);
	$seconds = $seconds % 60;

	$hours = floor($minutes / 60);
	$minutes = $minutes % 60;

	$days = floor($hours / 24);
	$hours = $hours % 24;

	if ($full) {
 		return substr(
		              ($days ? $days." day".(($days == 1) ? "" : "s").", " : "").
		              ($hours ? $hours." hour".(($hours == 1) ? "" : "s").", " : "").
		              ($minutes ? $minutes." minute".(($minutes == 1) ? "" : "s").", " : "").
		              ($seconds ? $seconds." second".(($seconds == 1) ? "" : "s").", " : ""),
		              0, -2);
	} else {
		return substr(($days ? $days."d, " : "").($hours ? $hours."h, " : "").($minutes ? $minutes."m, " : "").($seconds ? $seconds."s, " : ""), 0, -2);
	}
}

function queues_add($account,$name,$password,$prefix,$goto,$agentannounce_id,$members,$joinannounce_id,$maxwait,$alertinfo='',$cwignore='no',$qregex='') {
	global $db;

	if (trim($account) == '') {
		echo "<script>javascript:alert('"._("Bad Queue Number, can not be blank")."');</script>";
		return false;
	}

	//add to extensions table
	if (empty($agentannounce_id)) {
		$agentannounce_id="";
	}

$fields = array(
	array($account,'maxlen',($_REQUEST['maxlen'])?$_REQUEST['maxlen']:'0',0),
	array($account,'joinempty',($_REQUEST['joinempty'])?$_REQUEST['joinempty']:'yes',0),
	array($account,'leavewhenempty',($_REQUEST['leavewhenempty'])?$_REQUEST['leavewhenempty']:'no',0),
	array($account,'strategy',($_REQUEST['strategy'])?$_REQUEST['strategy']:'ringall',0),
	array($account,'timeout',(isset($_REQUEST['timeout']))?$_REQUEST['timeout']:'15',0),
	array($account,'retry',(isset($_REQUEST['retry']) && $_REQUEST['retry'] != '')?$_REQUEST['retry']:'5',0),
	array($account,'wrapuptime',($_REQUEST['wrapuptime'])?$_REQUEST['wrapuptime']:'0',0),
	array($account,'announce-frequency',($_REQUEST['announcefreq'])?$_REQUEST['announcefreq']:'0',0),
	array($account,'announce-holdtime',($_REQUEST['announceholdtime'])?$_REQUEST['announceholdtime']:'no',0),
	array($account,'queue-youarenext',($_REQUEST['announceposition']=='no')?'silence/1':'queue-youarenext',0),  //if no, play no sound
	array($account,'queue-thereare',($_REQUEST['announceposition']=='no')?'silence/1':'queue-thereare',0),  //if no, play no sound
	array($account,'queue-callswaiting',($_REQUEST['announceposition']=='no')?'silence/1':'queue-callswaiting',0),  //if no, play no sound
	array($account,'queue-thankyou',($_REQUEST['announceposition']=='no')?'':'queue-thankyou',0),  //if no, play no sound
	array($account,'periodic-announce-frequency',($_REQUEST['pannouncefreq'])?$_REQUEST['pannouncefreq']:'0',0),
	array($account,'monitor-format',($_REQUEST['monitor-format'])?$_REQUEST['monitor-format']:'',0),
	array($account,'monitor-join','yes',0),
	array($account,'eventwhencalled',($_REQUEST['eventwhencalled'])?$_REQUEST['eventwhencalled']:'no',0),
	array($account,'eventmemberstatus',($_REQUEST['eventmemberstatus'])?$_REQUEST['eventmemberstatus']:'no',0),
	array($account,'ringinuse',($cwignore)?'no':'yes',0),
);

	if ($_REQUEST['music'] != 'inherit') {
		$fields[] = array($account,'music',($_REQUEST['music'])?$_REQUEST['music']:'default',0);
	}

	//there can be multiple members
	if (isset($members)) {
		$count = 0;
		foreach ($members as $member) {
			$fields[] = array($account,'member',$member,$count);
			$count++;
		}
	}

	$compiled = $db->prepare('INSERT INTO queues_details (id, keyword, data, flags) values (?,?,?,?)');
	$result = $db->executeMultiple($compiled,$fields);
	if(DB::IsError($result)) {
		die_freepbx($result->getMessage()."<br><br>error adding to queues_details table");	
	}
	$extension   	 = $account;
	$descr         = isset($name) ? addslashes($name):'';
	$grppre        = isset($prefix) ? addslashes($prefix):'';
	$alertinfo     = isset($alertinfo) ? addslashes($alertinfo):'';
	//$joinannounce_id  = $joinannounce_id;
	$ringing       = isset($_REQUEST['rtone']) ? $_REQUEST['rtone']:'';
	//$agentannounce_id = $agentannounce_id;
	$maxwait       = isset($maxwait) ? $maxwait:'';
	$password      = isset($password) ? $password:'';
	$ivr_id        = isset($_REQUEST['announcemenu']) ? $_REQUEST['announcemenu']:'none';
	$dest          = isset($goto) ? $goto:'';
	$cwignore      = isset($cwignore) ? $cwignore:'0';
	$qregex        = isset($qregex) ? addslashes($qregex):'';

	// Assumes it has just been deleted
	$sql = "INSERT INTO queues_config (extension, descr, grppre, alertinfo, joinannounce_id, ringing, agentannounce_id, maxwait, password, ivr_id, dest, cwignore, qregex)
         	VALUES ('$extension', '$descr', '$grppre', '$alertinfo', '$joinannounce_id', '$ringing', '$agentannounce_id', '$maxwait', '$password', '$ivr_id', '$dest', '$cwignore', '$qregex')	";
	$results = sql($sql);
	return true;
}

function queues_del($account) {
	global $db;
	
	$sql = "DELETE FROM queues_details WHERE id = '$account'";
    $result = $db->query($sql);
    if(DB::IsError($result)) {
        die_freepbx($result->getMessage().$sql);
    }
	$sql = "DELETE FROM queues_config WHERE extension = '$account'";
    $result = $db->query($sql);
    if(DB::IsError($result)) {
        die_freepbx($result->getMessage().$sql);
    }

}

//get the existing queue extensions
//
function queues_list($listall=false) {
	global $db;
	$sql = "SELECT extension, descr FROM queues_config ORDER BY extension";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
		$results = array();
	}

	foreach($results as $result){
		if ($listall || checkRange($result[0])){
			$extens[] = array($result[0],$result[1]);
		}
	}
	if (isset($extens)) {
		return $extens;
	} else {
		return array();
	}
}

function queues_check_extensions($exten=true) {
	global $active_modules;

	$extenlist = array();
	if (is_array($exten) && empty($exten)) {
		return $extenlist;
	}
	$sql = "SELECT extension, descr FROM queues_config ";
	if (is_array($exten)) {
		$sql .= "WHERE extension in ('".implode("','",$exten)."')";
	}
	$sql .= " ORDER BY extension";
	$results = sql($sql,"getAll",DB_FETCHMODE_ASSOC);

	//$type = isset($active_modules['queues']['type'])?$active_modules['queues']['type']:'setup';
	foreach ($results as $result) {
		$thisexten = $result['extension'];
		$extenlist[$thisexten]['description'] = _("Queue: ").$result['descr'];
		$extenlist[$thisexten]['status'] = 'INUSE';
		$extenlist[$thisexten]['edit_url'] = 'config.php?display=queues&extdisplay='.urlencode($thisexten);
	}
	return $extenlist;
}

function queues_check_destinations($dest=true) {
	global $active_modules;

	$destlist = array();
	if (is_array($dest) && empty($dest)) {
		return $destlist;
	}
	$sql = "SELECT extension, descr, dest FROM queues_config";
	if ($dest !== true) {
		$sql .= " WHERE dest in ('".implode("','",$dest)."')";
	}
	$sql .= " ORDER BY extension";

	$results = sql($sql,"getAll",DB_FETCHMODE_ASSOC);

	//$type = isset($active_modules['announcement']['type'])?$active_modules['announcement']['type']:'setup';

	foreach ($results as $result) {
		$thisdest = $result['dest'];
		$thisid   = $result['extension'];
		$destlist[] = array(
			'dest' => $thisdest,
			'description' => 'Queue: '.$result['descr'].'('.$thisid.')',
			'edit_url' => 'config.php?display=queues&extdisplay='.urlencode($thisid),
		);
	}
	return $destlist;
}

function queues_check_compoundrecordings() {
	global $db;

	$compound_recordings = array();
	$sql = "SELECT extension, descr, agentannounce_id, ivr_id FROM queues_config WHERE (ivr_id != 'none' AND ivr_id != '') OR agentannounce_id != ''";
	$results = sql($sql, "getAll",DB_FETCHMODE_ASSOC);

	if (function_exists('ivr_list')) {
		$ivr_details = ivr_list();
		foreach ($ivr_details as $item) {
			$ivr_hash[$item['ivr_id']] = $item;
		}
		$check_ivr = true;
	} else {
		$check_ivr = false;
	}

	foreach ($results as $result) {
		$agentannounce = $result['agentannounce_id'] ? recordings_get_file($result['agentannounce_id']):'';
		if (strpos($agentannounce,"&") !== false) {
			$compound_recordings[] = array(
				                       	'extension' => $result['extension'],
															 	'descr' => $result['descr'],
															 	'error' => _("Agent Announce Msg"),
														 	);
		}
		if ($result['ivr_id'] != 'none' && $result['ivr_id'] != '' && $check_ivr) {
			$id = $ivr_hash[$result['ivr_id']]['announcement_id'];
			$announce = $id ? recordings_get_file($id) : '';
			if (strpos($announce,"&") !== false) {
				$compound_recordings[] = array(
				                       		'extension' => $result['extension'],
															 		'descr' => $result['descr'],
															 		'error' => sprintf(_("IVR Announce: %s"),$ivr_hash[$result['ivr_id']]['displayname']),
														 		);
			}
		}
	}
	return $compound_recordings;
}


function queues_get($account, $queues_conf_only=false) {
	global $db;
	
    if ($account == "")
    {
	    return array();
    }

	$account = q($account);
	//get all the variables for the queue
	$sql = "SELECT keyword,data FROM queues_details WHERE id = $account";
	$results = $db->getAssoc($sql);
	if (empty($results)) {
		return array();
	}

	//okay, but there can be multiple member variables ... do another select for them
	$sql = "SELECT data FROM queues_details WHERE id = $account AND keyword = 'member' order by flags";
	$results['member'] = $db->getCol($sql);
	
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
		if (strtolower($results['eventmemberstatusoff']) == 'yes') {
			$results['eventmemberstatus'] = 'no';
		} else {
			$results['eventmemberstatus'] = 'yes';
		}
	} elseif (!isset($results['eventmemberstatus'])){
		$results['eventmemberstatus'] = 'no';
	}

	if ($queues_conf_only) {
		$sql = "SELECT ivr_id FROM queues_config WHERE extension = $account";
		$config = sql($sql, "getRow",DB_FETCHMODE_ASSOC);

		// We need to strip off all but the first sound file of any compound sound files
		//
		$agentannounce_id_arr        = explode("&", $config['agentannounce_id']);
		$results['agentannounce_id'] = $agentannounce_id_arr[0];
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
		$results['rtone']         = $config['ringing'];
		$results['cwignore']      = $config['cwignore'];
		$results['qregex']        = $config['qregex'];
	}

	$results['context'] = '';
	$results['periodic-announce'] = '';

	if ($config['ivr_id'] != 'none' && $config['ivr_id'] != '') {
		if (function_exists('ivr_get_details')) {
			$results['context'] = "ivr-".$config['ivr_id'];
			$arr = ivr_get_details($config['ivr_id']);
			if( isset($arr['announcement']) && $arr['announcement'] != '') {

				// We need to strip off all but the first sound file of any compound sound files
				//
				$periodic_arr = explode("&", $arr['announcement']);
				$results['periodic-announce'] = $periodic_arr[0];
			}
		}
	}

	return $results;
}
?>
