<?php /* $id:$ */
// The destinations this module provides
// returns a associative arrays with keys 'destination' and 'description'
function queues_destinations() {
	//get the list of all exisiting
	$results = queues_list();
	
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

/* 	Generates dialplan for "queues" components (extensions & inbound routing)
	We call this with retrieve_conf
*/
function queues_get_config($engine) {
	global $ext;  // is this the best way to pass this?
	switch($engine) {
		case "asterisk":
			/* queue extensions */
			$ext->addInclude('from-internal-additional','ext-queues');
			$qlist = queues_list();
			if (is_array($qlist)) {
				foreach($qlist as $item) {
					
					$exten = $item[0];
					$q = queues_get($exten);

					$grppre = (isset($q['prefix'])?$q['prefix']:'');
					
					$ext->add('ext-queues', $exten, '', new ext_macro('user-callerid'));
					$ext->add('ext-queues', $exten, '', new ext_answer(''));

					// block voicemail until phone is answered at which point a macro should be called on the answering
					// line to clear this flag so that subsequent transfers can occur.
					//
					$ext->add('ext-queues', $exten, '', new ext_setvar('__BLKVM_OVERRIDE', 'BLKVM/${EXTEN}/${CHANNEL}'));
					$ext->add('ext-queues', $exten, '', new ext_setvar('__BLKVM_BASE', '${EXTEN}'));
					$ext->add('ext-queues', $exten, '', new ext_setvar('DB(${BLKVM_OVERRIDE})', 'TRUE'));
					$ext->add('ext-queues', $exten, '', new ext_setvar('_DIAL_OPTIONS', '${DIAL_OPTIONS}M(auto-blkvm)'));

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


					$ext->add('ext-queues', $exten, '', new ext_setvar('MONITOR_FILENAME','/var/spool/asterisk/monitor/q${EXTEN}-${STRFTIME(${EPOCH},,%Y%m%d-%H%M%S)}-${UNIQUEID}'));
					$joinannounce = (isset($q['joinannounce'])?$q['joinannounce']:'');
					if($joinannounce != "") {
						$ext->add('ext-queues', $exten, '', new ext_playback($joinannounce));
					}
					$options = 't';
					if ($q['rtone'] == 1)
						$options .= 'r';
					$agentannounce = (isset($q['agentannounce'])?$q['agentannounce']:'');
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

/*
This module needs to be updated to use it's own database table and not the extensions table
*/

function queues_add($account,$name,$password,$prefix,$goto,$agentannounce,$members,$joinannounce,$maxwait) {
	global $db;
	
	//add to extensions table
	if (!empty($agentannounce) && $agentannounce != 'None')
		$agentannounce="$agentannounce";
	else
		$agentannounce="";

	$addarray = array('ext-queues',$account,'1','Answer',''.'','','0');
	legacy_extensions_add($addarray);
	$addarray = array('ext-queues',$account,'2','SetCIDName',$prefix.'${CALLERID(name)}','','0');
	legacy_extensions_add($addarray);
	$addarray = array('ext-queues',$account,'3','SetVar','MONITOR_FILENAME=/var/spool/asterisk/monitor/q${EXTEN}-${STRFTIME(${EPOCH},,%Y%m%d-%H%M%S)}-${UNIQUEID}','','0');
	legacy_extensions_add($addarray);
	if ($joinannounce != 'None') {
		$addarray = array('ext-queues',$account,'4','Playback',$joinannounce,'','0');
		legacy_extensions_add($addarray);
	}
	$addarray = array('ext-queues',$account,'5','Queue',$account.'|t||'.$agentannounce.'|'.$maxwait,$name,'0');
	legacy_extensions_add($addarray);
	$addarray = array('ext-queues',$account.'*','1','Macro','agent-add,'.$account.','.$password,'','0');
	legacy_extensions_add($addarray);
	$addarray = array('ext-queues',$account.'**','1','Macro','agent-del,'.$account,'','0');
	legacy_extensions_add($addarray);
	
	//failover goto
	$addarray = array('ext-queues',$account,'6','Goto',$goto,'jump','0');
	legacy_extensions_add($addarray);
	//setGoto($account,'ext-queues','6',$goto,0);
	// Announce Menu?
	if ($_REQUEST['announcemenu']=='none') {
		$qthanku = 'queue-thankyou';
		$context = '';
	} else {
		$arr = (ivr_get_details($_REQUEST['announcemenu']));
		if( isset($arr['announcement']) && !empty($arr['announcement']) ) {
			$qthanku = $arr['announcement'];
		} else {
			$qthanku = '';
		}
		$context = "ivr-".$_REQUEST['announcemenu'];
	}
	
	
	// now add to queues table
	$fields = array(
		array($account,'account',$account,0),
		array($account,'maxlen',($_REQUEST['maxlen'])?$_REQUEST['maxlen']:'0',0),
		array($account,'joinempty',($_REQUEST['joinempty'])?$_REQUEST['joinempty']:'yes',0),
		array($account,'leavewhenempty',($_REQUEST['leavewhenempty'])?$_REQUEST['leavewhenempty']:'no',0),
		array($account,'strategy',($_REQUEST['strategy'])?$_REQUEST['strategy']:'ringall',0),
		array($account,'timeout',($_REQUEST['timeout'])?$_REQUEST['timeout']:'15',0),
		array($account,'retry',($_REQUEST['retry'])?$_REQUEST['retry']:'5',0),
		array($account,'wrapuptime',($_REQUEST['wrapuptime'])?$_REQUEST['wrapuptime']:'0',0),
		//array($account,'agentannounce',($_REQUEST['agentannounce'])?$_REQUEST['agentannounce']:'None'),
		array($account,'announce-frequency',($_REQUEST['announcefreq'])?$_REQUEST['announcefreq']:'0',0),
		array($account,'announce-holdtime',($_REQUEST['announceholdtime'])?$_REQUEST['announceholdtime']:'no',0),
		array($account,'queue-youarenext',($_REQUEST['announceposition']=='no')?'':'queue-youarenext',0),  //if no, play no sound
		array($account,'queue-thereare',($_REQUEST['announceposition']=='no')?'':'queue-thereare',0),  //if no, play no sound
		array($account,'queue-callswaiting',($_REQUEST['announceposition']=='no')?'':'queue-callswaiting',0),  //if no, play no sound
		array($account,'queue-thankyou',$qthanku,0),
		array($account,'context',$context,0), 
		array($account,'monitor-format',($_REQUEST['monitor-format'])?$_REQUEST['monitor-format']:'',0),
		array($account,'monitor-join','yes',0),
		array($account,'music',($_REQUEST['music'])?$_REQUEST['music']:'default',0),
		array($account,'rtone',($_REQUEST['rtone'])?$_REQUEST['rtone']:0,0),
		array($account,'eventwhencalled',($_REQUEST['eventwhencalled'])?$_REQUEST['eventwhencalled']:'no',0),
		array($account,'eventmemberstatus',($_REQUEST['eventmemberstatus'])?$_REQUEST['eventmemberstatus']:'no',0));


	//there can be multiple members
	if (isset($members)) {
		$count = 0;
		foreach ($members as $member) {
			$fields[] = array($account,'member',$member,$count);
			$count++;
		}
	}

    $compiled = $db->prepare('INSERT INTO queues (id, keyword, data, flags) values (?,?,?,?)');
	$result = $db->executeMultiple($compiled,$fields);
    if(DB::IsError($result)) {
        die($result->getMessage()."<br><br>error adding to queues table");	
    }
}

function queues_del($account) {
	global $db;
	//delete from extensions table
	legacy_extensions_del('ext-queues',$account);
	legacy_extensions_del('ext-queues',$account.'*');
	legacy_extensions_del('ext-queues',$account.'**');
	
	$sql = "DELETE FROM queues WHERE id = '$account'";
    $result = $db->query($sql);
    if(DB::IsError($result)) {
        die($result->getMessage().$sql);
    }

}

//get the existing queue extensions
function queues_list() {
	global $db;
	$sql = "SELECT extension,descr FROM extensions WHERE application = 'Queue' ORDER BY extension";
	$results = $db->getAll($sql);
	if(DB::IsError($results)) {
		$results = null;
	}
	foreach($results as $result){
		if (checkRange($result[0])){
			$extens[] = array($result[0],$result[1]);
		}
	}
	if (isset($extens)) {
		return $extens;
	} else {
		return null;
	}
}


function queues_get($account) {
	global $db;
	
    if ($account == "")
    {
	    return array();
    }
    
	//get all the variables for the queue
	$sql = "SELECT keyword,data FROM queues WHERE id = '$account'";
	$results = $db->getAssoc($sql);

	//okay, but there can be multiple member variables ... do another select for them
	$sql = "SELECT data FROM queues WHERE id = '$account' AND keyword = 'member' order by flags";
	$results['member'] = $db->getCol($sql);
	
	//queues.php looks for 'announcemenu', which is the same a context
	$results['announcemenu'] = 	$results['context'];
	
	//if 'queue-youarenext=queue-youarenext', then assume we want to announce position
	if($results['queue-youarenext'] == 'queue-youarenext') 
		$results['announce-position'] = 'yes';
	else
		$results['announce-position'] = 'no';
	
	//if 'eventmemberstatusoff=Yes', then assume we want to 'eventmemberstatus=no'
	if(isset($results['eventmemberstatusoff'])) {
		if (strtolower($results['eventmemberstatusoff']) == 'yes') {
			$results['eventmemberstatus'] = 'no';
		} else {
			$results['eventmemberstatus'] = 'yes';
		}
	} else {
		$results['eventmemberstatus'] = 'no';
	}

	//get CID Prefix
	$sql = "SELECT args FROM extensions WHERE extension = '$account' AND context = 'ext-queues' AND application = 'SetCIDName'";
	list($args) = $db->getRow($sql);
	$prefix = explode('$',$args); //in table like prefix${CALLERID(name)}
	$results['prefix'] = $prefix[0];	
	
	//get max wait time from Queue command
	$sql = "SELECT args,descr FROM extensions WHERE extension = '$account' AND context = 'ext-queues' AND application = 'Queue'";
	list($args, $descr) = $db->getRow($sql);
	$maxwait = explode('|',$args);  //in table like queuenum|t|||maxwait
	$results['agentannounce'] = $maxwait[3];
	$results['maxwait'] = $maxwait[4];
	$results['name'] = $descr;
	
	$sql = "SELECT args FROM extensions WHERE extension = '$account' AND context = 'ext-queues' and application = 'Playback'";
	list($args) = $db->getRow($sql);
	$results['joinannounce'] = $args; 
	
	//get password from AddQueueMember command
	$sql = "SELECT args FROM extensions WHERE extension = '$account*' AND context = 'ext-queues'";
	list($args) = $db->getRow($sql);
	$password = explode(',',$args); //in table like agent-add,account,password
	$results['password'] = $password[2];
	
	//get the failover destination (desc=jump)
	$sql = "SELECT args FROM extensions WHERE extension = '".$account."' AND descr = 'jump'";
	list($args) = $db->getRow($sql);
	$results['goto'] = $args; 

	return $results;
}
?>
