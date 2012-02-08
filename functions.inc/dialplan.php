<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
/* 	Generates dialplan for "queues" components (extensions & inbound routing)
	We call this with retrieve_conf
*/
function queues_get_config($engine) {
	global $ext;  // is this the best way to pass this?
	global $queues_conf;
	global $amp_conf;
	global $version;

	switch($engine) {
		case "asterisk":
			global $astman;

			$ast_ge_14 = version_compare($version,'1.4','ge');
			$ast_ge_16 = version_compare($version,'1.6','ge');
			$ast_ge_14_25 = version_compare($version,'1.4.25','ge');
			$ast_ge_18 = version_compare($version,'1.8','ge');

			$has_extension_state = $ast_ge_16;
			if ($ast_ge_14 && !$ast_ge_16) {
				$response = $astman->send_request('Command', array('Command' => 'module show like func_extstate'));
				if (preg_match('/1 modules loaded/', $response['data'])) {
					$has_extension_state = true;
					
				}
			}
			
			if (isset($queues_conf) && is_a($queues_conf, "queues_conf")) {
				$queues_conf->addQueuesGeneral('persistentmembers',$amp_conf['QUEUES_PESISTENTMEMBERS'] ? 'yes' : 'no');
					if ($ast_ge_16) {
					$queues_conf->addQueuesGeneral('shared_lastcall',$amp_conf['QUEUES_SHARED_LASTCALL'] ? 'yes' : 'no');
					$queues_conf->addQueuesGeneral('updatecdr',$amp_conf['QUEUES_UPDATECDR'] ? 'yes' : 'no');
				}
				if ($amp_conf['QUEUES_MIX_MONITOR']) {
					$queues_conf->addQueuesGeneral('monitor-type', 'MixMonitor');
				}
			}

			/* queue extensions */
			$ext->addInclude('from-internal-additional','ext-queues');
			/* Trial DEVSTATE */
			if ($amp_conf['USEDEVSTATE']) {
				$ext->addGlobal('QUEDEVSTATE','TRUE');
			}
			// $que_code = '*45';
			$fcc = new featurecode('queues', 'que_toggle');
			$que_code = $fcc->getCodeActive();
			unset($fcc);
			if ($que_code != '') {
				queue_app_toggle($que_code);
				queue_app_all_toggle();
				queue_agent_del_toggle();
				queue_agent_add_toggle();
				$ext->addGlobal('QUEUETOGGLE',$que_code);
			}
			$qlist = queues_list(true);
			
			if (empty($qlist)) {
				return; //nothing to do if we dont have any queues
			}
			
			$from_queue_exten_only = 'from-queue-exten-only';
			$from_queue_exten_internal = 'from-queue-exten-internal';

			foreach($qlist as $item) {
			
				$exten = $item[0];
				$q = queues_get($exten);
				$c = 'ext-queues';
				
				$grppre = (isset($q['prefix'])?$q['prefix']:'');
				$alertinfo = (isset($q['alertinfo'])?$q['alertinfo']:'');

				// Not sure why someone would ever have a ; in the regex, but since Asterisk has problems with them
				// it would need to be escaped
				$qregex = (isset($q['qregex'])?$q['qregex']:'');
				str_replace(';','\;',$qregex);
			
				$ext->add($c, $exten, '', new ext_macro('user-callerid'));
			
				if (isset($q['qnoanswer']) && $q['qnoanswer'] == FALSE) {
					$ext->add($c, $exten, '', new ext_answer(''));
				} else {
					// TODO: should this only be set if noanswer + (!ringtones || joinannounce)???
					$ext->add($c, $exten, '', new ext_progress());
				}

			// block voicemail until phone is answered at which point a macro should be called on the answering
			// line to clear this flag so that subsequent transfers can occur.
				if ($q['queuewait']) {
					$ext->add($c, $exten, '', new ext_execif('$["${QUEUEWAIT}" = ""]', 'Set', '__QUEUEWAIT=${EPOCH}'));
				}
				// If extension_only don't do this and CFIGNORE
				if($q['use_queue_context'] != '2') {
					$ext->add($c, $exten, '', new ext_macro('blkvm-set', 'reset'));
					$ext->add($c, $exten, '', new ext_execif('$["${REGEX("(M[(]auto-blkvm[)])" ${DIAL_OPTIONS})}" != "1"]', 'Set', '_DIAL_OPTIONS=${DIAL_OPTIONS}M(auto-blkvm)'));
				}

				// Inform all the children NOT to send calls to destinations or voicemail
				//
				$ext->add($c, $exten, '', new ext_setvar('__NODEST', '${EXTEN}'));

				// deal with group CID prefix
				if ($grppre != '') {
					$ext->add($c, $exten, '', new ext_macro('prepend-cid', $grppre));
				}

				// Set Alert_Info
				if ($alertinfo != '') {
					$ext->add($c, $exten, '', new ext_setvar('__ALERT_INFO', str_replace(';', '\;', $alertinfo)));
				}
				$record_mode = $q['monitor-format'] ? 'always' : 'dontcare';
						if ($q['monitor-format']) {
					$ext->add($c, $exten, '', new ext_set('__MIXMON_FORMAT', $q['monitor-format']));
				}
				$ext->add($c, $exten, '', new ext_gosub('1','s','sub-record-check',"q,$exten,$record_mode"));

				if ($amp_conf['QUEUES_MIX_MONITOR']) {
					$monitor_options = '';
				if (isset($q['monitor_type']) && $q['monitor_type'] != '') {
					$monitor_options .= 'b';
				}
				if (isset($q['monitor_spoken']) && $q['monitor_spoken'] != 0) {
					$monitor_options .= 'V('.$q['monitor_spoken'].')';
					}
				if (isset($q['monitor_heard']) && $q['monitor_heard'] != 0) {
					$monitor_options .= 'v('.$q['monitor_heard'].')';
				}
				if ($monitor_options != '') {
					$ext->add($c, $exten, '', new ext_setvar('MONITOR_OPTIONS', $monitor_options ));
				}
			}
			$joinannounce_id = (isset($q['joinannounce_id'])?$q['joinannounce_id']:'');
			if($joinannounce_id) {
				$joinannounce = recordings_get_file($joinannounce_id);
			
				if (isset($q['qnoanswer']) && $q['qnoanswer'] == TRUE) {
					$joinannounce = $joinannounce.', noanswer';
				}

				$ext->add($c, $exten, '', new ext_playback($joinannounce));
			}
			$options = 't';
			if ($ast_ge_18) {
				if (isset($q['answered_elsewhere']) && $q['answered_elsewhere'] == '1'){
					$options .= 'C';
				}
			}
			if ($q['rtone'] == 1) {
				$options .= 'r';
			}
			if ($q['retry'] == 'none'){
				$options .= 'n';
			}
			if (isset($q['music'])) {
					$ext->add($c, $exten, '', new ext_setvar('__MOHCLASS', $q['music']));
			}
			// Set CWIGNORE  if enabled so that busy agents don't have another line key ringing and
			// stalling the ACD.
			if ($q['cwignore'] == 1 || $q['cwignore'] == 2 ) {
					$ext->add($c, $exten, '', new ext_setvar('__CWIGNORE', 'TRUE'));
			}
			if ($q['use_queue_context']) {
					$ext->add($c, $exten, '', new ext_setvar('__CFIGNORE', 'TRUE'));
					$ext->add($c, $exten, '', new ext_setvar('__FORWARD_CONTEXT', 'block-cf'));
			}
			$agentannounce_id = (isset($q['agentannounce_id'])?$q['agentannounce_id']:'');
			if ($agentannounce_id) {
				$agentannounce = recordings_get_file($agentannounce_id);
			} else {
				$agentannounce = '';
			}
			
			if ($q['callconfirm'] == 1) {
				$ext->add($c, $exten, '', new ext_setvar('__FORCE_CONFIRM', '${CHANNEL}'));
				if ($amp_conf['AST_FUNC_SHARED']) {
					$ext->add($c, $exten, '', new ext_setvar('SHARED(ANSWER_STATUS)','NOANSWER'));
				}
				$ext->add($c, $exten, '', new ext_setvar('__CALLCONFIRMCID', '${CALLERID(number)}'));
				$callconfirm_id = (isset($q['callconfirm_id']))?$q['callconfirm_id']:'';
				if ($callconfirm_id) {	
					$callconfirm = recordings_get_file($callconfirm_id);
				} else {
					$callconfirm = '';
				}
				$ext->add($c, $exten, '', new ext_setvar('__ALT_CONFIRM_MSG', $callconfirm));					
			}
			$ext->add($c, $exten, '', new ext_queuelog($exten,'${UNIQUEID}','NONE','DID', '${FROM_DID}')); 
			$ext->add($c, $exten, '', new ext_queue($exten,$options,'',$agentannounce,$q['maxwait']));

			if($q['use_queue_context'] != '2') {
				$ext->add($c, $exten, '', new ext_macro('blkvm-clr'));
			}
			// cancel any recording previously requested

			$ext->add($c, $exten, '', new ext_gosub('1','s','sub-record-cancel'));
				// If we are here, disable the NODEST as we want things to resume as normal
				$ext->add($c, $exten, '', new ext_setvar('__NODEST', ''));
			
			if ($q['callconfirm'] == 1) {
				if ($amp_conf['AST_FUNC_SHARED']) {
					$ext->add($c, $exten, '', new ext_setvar('SHARED(ANSWER_STATUS)', ''));
				}
				$ext->add($c, $exten, '', new ext_setvar('__FORCE_CONFIRM', ''));
				$ext->add($c, $exten, '', new ext_setvar('__ALT_CONFIRM_MSG', ''));				
			}

			if($monitor_options != '') {
				$ext->add($c, $exten, '', new ext_setvar('MONITOR_OPTIONS', ''));
			}
			if ($q['cwignore'] == 1 || $q['cwignore'] == 2 ) {
				$ext->add($c, $exten, '', new ext_setvar('__CWIGNORE', '')); 
			}
			if ($q['use_queue_context']) {
					$ext->add($c, $exten, '', new ext_setvar('__CFIGNORE', ''));
					$ext->add($c, $exten, '', new ext_setvar('__FORWARD_CONTEXT', 'from-internal'));
			}

			// destination field in 'incoming' database is backwards from what ext_goto expects
			$goto_context = strtok($q['goto'],',');
			$goto_exten = strtok(',');
			$goto_pri = strtok(',');
			
			$ext->add($c, $exten, '', new ext_goto($goto_pri,$goto_exten,$goto_context));
			
			//dynamic agent login/logout
			if (trim($qregex) != '') {
					$ext->add($c, $exten."*", '', new ext_setvar('QREGEX', $qregex));
			}
			if($q['use_queue_context'] == '2') {
				$ext->add($c, $exten."*", '', new ext_macro('agent-add',$exten.",".$q['password'].",EXTEN"));
			} else {
				$ext->add($c, $exten."*", '', new ext_macro('agent-add',$exten.",".$q['password']));
			}
			$ext->add($c, $exten."**", '', new ext_macro('agent-del',"$exten"));
			if ($que_code != '') {
				$ext->add($c, $que_code.$exten, '', new ext_setvar('QUEUENO',$exten));
				$ext->add($c, $que_code.$exten, '', new ext_goto('start','s','app-queue-toggle'));
			}
			/* Trial Devstate */
			// Create Hints for Devices and Add Astentries for Users
			// Clean up the Members array
			if ($q['togglehint'] && $amp_conf['USEDEVSTATE'] && $que_code != '') {
				if (!isset($device_list)) {
				  $device_list = core_devices_list("all", 'full', true);
			}
			if ($astman) {
				if (($dynmemberonly = strtolower($astman->database_get('QPENALTY/'.$exten,'dynmemberonly')) == 'yes') == true) {
					$get=$astman->database_show('QPENALTY/'.$exten.'/agents');
					if($get){
						$mem = array();
						foreach($get as $key => $value){
							$key=explode('/',$key);
							$mem[$key[4]]=$value;
						}
					}
				}
			} else {
				fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
			}
			foreach ($device_list as $device) {
				if (
					(!$dynmemberonly || $device['devicetype'] == 'adhoc' || isset($mem[$device['user']]))
					&& ($device['tech'] == 'sip' || $device['tech'] == 'iax2')
					) {
						$ext->add($c, $que_code.$device['id'].'*'.$exten, '', new ext_setvar('QUEUENO',$exten));
						$ext->add($c, $que_code.$device['id'].'*'.$exten, '', new ext_goto('start','s','app-queue-toggle'));
						$ext->addHint($c, $que_code.$device['id'].'*'.$exten, "Custom:QUEUE".$device['id'].'*'.$exten);
					}
				}
			}

			// Add routing vector to direct which context call should go
			//
			$agent_context = isset($q['use_queue_context']) && $q['use_queue_context'] && isset($queue_context) ? $queue_context : 'from-internal';
				switch ($q['use_queue_context']) {
					case 1:
						$agent_context = $from_queue_exten_internal;
						break;
					case 2:
						$agent_context = $from_queue_exten_only;
						break;
					case 0:
					default:
						$agent_context = 'from-internal';
						break;
				}
				$ext->add('from-queue', $exten, '', new ext_goto('1','${QAGENT}',$agent_context));
			}
			// Create *45 all queue toggle
			//
			if ($que_code != '') {
				$ext->add($c, $que_code, '', new ext_goto('start','s','app-all-queue-toggle'));

				// create a generic one for any phones that don't get a specific one created since we only
				// create them for phones we know have queues but who knows what is provisioned on the phones
				//
				$ext->add($c, '_' . $que_code . '*X.', '', new ext_goto('start','s','app-all-queue-toggle'));

				// generate with #exec if we are using dynamic hints
				//
				if ($amp_conf['DYNAMICHINTS']) {
					$ext->addExec($c,$amp_conf['AMPBIN'].'/generate_queue_hints.php '.$que_code);
				} else {
					// Create hash of which queues each user is in
					//
					$qpenalty=$astman->database_show('QPENALTY');
					$qc = array();
					foreach(array_keys($qpenalty) as $key) {
						$key = explode('/', $key);
						if ($key[3] == 'agents') {
							$qc[$key[4]][] = $key[2];
						}
					}

					// Make sure we have all the devices
					//
					if (!isset($device_list)) {
						$device_list = core_devices_list("all", 'full', true);
					}

					foreach ($device_list as $device) {
						if ($device['tech'] == 'sip' || $device['tech'] == 'iax2') {
							$ext->add($c, $que_code . '*' . $device['id'], '', new ext_goto('start','s','app-all-queue-toggle'));
							if ($device['user'] != '' &&  isset($qc[$device['user']])) {
								$hlist = 'Custom:QUEUE' . $device['id'] . '*' . implode('&Custom:QUEUE' . $device['id'] . '*', $qc[$device['user']]);
								$ext->addHint($c, $que_code . '*' . $device['id'], $hlist);
							}
						}
					}
				}
			}

			// We need to have a hangup here, if call is ended by the caller during Playback it will end in the
			// h context and do a proper hangup and clean the blkvm if set, see #4671
			$ext->add($c, 'h', '', new ext_macro('hangupcall'));
			
			
			// NODEST will be the queue that this came from, so we will vector though an entry to determine the context the
			// agent should be delivered to. All queue calls come here, this decides if the should go direct to from-internal
			// or indirectly through from-queue-exten-only to trap extension calls and avoid their follow-me, etc.
			//
			$ext->add('from-queue', '_.', '', new ext_setvar('QAGENT','${EXTEN}'));
			$ext->add('from-queue', '_.', '', new ext_goto('1','${NODEST}'));

			$ext->addInclude($from_queue_exten_internal,$from_queue_exten_only);
			$ext->addInclude($from_queue_exten_internal,'from-internal');
			$ext->add($from_queue_exten_internal, 'foo', '', new ext_noop('bar'));

			/* create a context, from-queue-exten-only, that can be used for queues that want behavir similar to
			 * ringgroup where only the agent's phone will be rung, no follow-me will be pursued.
			 */
			$userlist = core_users_list();
			if (is_array($userlist)) {
				foreach($userlist as $item) {
					$ext->add($from_queue_exten_only, $item[0], '', new ext_setvar('RingGroupMethod', 'none'));
					$ext->add($from_queue_exten_only, $item[0], 'checkrecord', new ext_gosub('1','s','sub-record-check',"exten," . $item[0]));
					if ($has_extension_state) {
						$ext->add($from_queue_exten_only, $item[0], '', new ext_macro('dial-one',',${DIAL_OPTIONS},'.$item[0]));
					} else {
						$ext->add($from_queue_exten_only, $item[0], '', new ext_macro('dial',',${DIAL_OPTIONS},'.$item[0]));
					}
 					$ext->add($from_queue_exten_only, $item[0], '', new ext_hangup());
				}
 				$ext->add($from_queue_exten_only, 'h', '', new ext_macro('hangupcall'));
			}

			/*
			 * Adds a dynamic agent/member to a Queue
			 * Prompts for call-back number - in not entered, uses CIDNum
			 */

			$c = 'macro-agent-add';
			$exten = 's';
			
			$ext->add($c, $exten, '', new ext_wait(1));
			$ext->add($c, $exten, '', new ext_set('QUEUENO', '${ARG1}'));
			$ext->add($c, $exten, '', new ext_macro('user-callerid', 'SKIPTTL'));
			$ext->add($c, $exten, 'a3', new ext_read('CALLBACKNUM', 'agent-login'));  // get callback number from user
			$ext->add($c, $exten, '', new ext_gotoif('$[${LEN(${CALLBACKNUM})}=0]','a5','a7'));  // if user just pressed # or timed out, use cidnum
			$ext->add($c, $exten, 'a5', new ext_set('CALLBACKNUM', '${IF($[${LEN(${AMPUSER})}=0]?${CALLERID(number)}:${AMPUSER})}'));

			if ($ast_ge_14_25) {
				$ext->add($c, $exten, '', new ext_set('THISDEVICE', '${DB(DEVICE/${REALCALLERIDNUM}/dial)}'));
			}
			$ext->add($c, $exten, '', new ext_gotoif('$["${CALLBACKNUM}" = ""]', 'a3'));  // if still no number, start over
			$ext->add($c, $exten, 'a7', new ext_gotoif('$["${CALLBACKNUM}" = "${QUEUENO}"]', 'invalid'));  // Error, they put in the queue number

			// If this is an extension only queue then EXTEN is passed as ARG3 and we make sure this is a valid extension being entered
			$ext->add($c, $exten, '', new ext_gotoif('$["${ARG3}" = "EXTEN" & ${DB_EXISTS(AMPUSER/${CALLBACKNUM}/cidname)} = 0]', 'invalid'));

			// If this is a restricted dynamic agent queue then check to make sure they are allowed
			$ext->add($c, $exten, '', new ext_gotoif('$["${DB(QPENALTY/${QUEUENO}/dynmemberonly)}" = "yes" & ${DB_EXISTS(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})} != 1]', 'invalid'));

			$ext->add($c, $exten, '', new ext_execif('$["${QREGEX}" != ""]', 'GotoIf', '$["${REGEX("${QREGEX}" ${CALLBACKNUM})}" = "0"]?invalid'));
			$ext->add($c, $exten, '', new ext_execif('$["${ARG2}" != ""]', 'Authenticate', '${ARG2}'));

			if ($amp_conf['USEDEVSTATE']) {
				$ext->add($c, $exten, '', new ext_set('STATE', 'INUSE'));
				$ext->add($c, $exten, '', new ext_gosub('1', 'sstate', 'app-queue-toggle'));
			}

			if ($ast_ge_18 || $amp_conf['USEQUEUESTATE']) {
			  $ext->add($c, $exten, '', new ext_execif('$[${DB_EXISTS(AMPUSER/${CALLBACKNUM}/cidname)} = 1 & "${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" != "ignorestate"]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)},hint:${CALLBACKNUM}@ext-local'));
			  $ext->add($c, $exten, '', new ext_execif('$[${DB_EXISTS(AMPUSER/${CALLBACKNUM}/cidname)} = 1 & "${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" = "ignorestate"]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)}'));
			  $ext->add($c, $exten, '', new ext_execif('$[${DB_EXISTS(AMPUSER/${CALLBACKNUM}/cidname)} = 0]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})}'));
	      } else if ($ast_ge_14_25) {
				  $ext->add($c, $exten, '', new ext_set('THISDEVICE', '${IF($[${LEN(${THISDEVICE})}=0]?${DB(DEVICE/${CUT(DB(AMPUSER/${CALLBACKNUM}/device),&,1)}/dial)}:${THISDEVICE})}'));
				  $ext->add($c, $exten, '', new ext_execif('$[${LEN(${THISDEVICE})}!=0 & "${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" != "ignorestate"]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)},${THISDEVICE}'));
				  $ext->add($c, $exten, '', new ext_execif('$[${LEN(${THISDEVICE})}!=0 & "${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" = "ignorestate"]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)}'));
				  $ext->add($c, $exten, '', new ext_execif('$[${LEN(${THISDEVICE})}=0]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})}'));
			} else {
				$ext->add($c, $exten, 'a9', new ext_addqueuemember('${QUEUENO}', 'Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})}'));
			}
			$ext->add($c, $exten, '', new ext_userevent('Agentlogin', 'Agent: ${CALLBACKNUM}'));
			$ext->add($c, $exten, '', new ext_wait(1));
			$ext->add($c, $exten, '', new ext_playback('agent-loginok&with&extension'));
			$ext->add($c, $exten, '', new ext_saydigits('${CALLBACKNUM}'));
			$ext->add($c, $exten, '', new ext_hangup());
			$ext->add($c, $exten, '', new ext_macroexit());
			$ext->add($c, $exten, 'invalid', new ext_playback('pbx-invalid'));
			$ext->add($c, $exten, '', new ext_goto('a3'));

			/*
			 * Removes a dynamic agent/member from a Queue
			 * Prompts for call-back number - in not entered, uses CIDNum 
			 */

			$c = 'macro-agent-del';
			
			$ext->add($c, $exten, '', new ext_wait(1));
			$ext->add($c, $exten, '', new ext_set('QUEUENO', '${ARG1}'));
			$ext->add($c, $exten, '', new ext_macro('user-callerid', 'SKIPTTL'));
			$ext->add($c, $exten, 'a3', new ext_read('CALLBACKNUM', 'agent-logoff'));  // get callback number from user
			$ext->add($c, $exten, '', new ext_gotoif('$[${LEN(${CALLBACKNUM})}=0]','a5','a7'));  // if user just pressed # or timed out, use cidnum
			$ext->add($c, $exten, 'a5', new ext_set('CALLBACKNUM', '${IF($[${LEN(${AMPUSER})}=0]?${CALLERID(number)}:${AMPUSER})}'));
			$ext->add($c, $exten, '', new ext_gotoif('$["${CALLBACKNUM}" = ""]', 'a3'));  // if still no number, start over

			if ($amp_conf['USEDEVSTATE']) {
				$ext->add($c, $exten, '', new ext_set('STATE', 'NOT_INUSE'));
				$ext->add($c, $exten, '', new ext_gosub('1', 'sstate', 'app-queue-toggle'));
			}

			// remove from both contexts in case left over dynamic agents after an upgrade
			$ext->add($c, $exten, 'a7', new ext_removequeuemember('${QUEUENO}', 'Local/${CALLBACKNUM}@from-queue/n'));
			$ext->add($c, $exten, '', new ext_removequeuemember('${QUEUENO}', 'Local/${CALLBACKNUM}@from-internal/n'));
			$ext->add($c, $exten, '', new ext_userevent('RefreshQueue'));
			$ext->add($c, $exten, '', new ext_wait(1));
			$ext->add($c, $exten, '', new ext_playback('agent-loggedoff'));
			$ext->add($c, $exten, '', new ext_hangup());
		break;
	}
}


function queue_app_all_toggle() {
	global $ext;
	global $amp_conf;

	$c = "app-all-queue-toggle"; // The context to be included
	$e = 's';

	$ext->add($c, $e, 'start', new ext_answer(''));
	$ext->add($c, $e, '', new ext_wait('1'));
	$ext->add($c, $e, '', new ext_macro('user-callerid'));
	$ext->add($c, $e, '', new ext_agi('queue_devstate.agi,getall,${AMPUSER}'));
	$ext->add($c, $e, '', new ext_gotoif('$["${QUEUESTAT}" = "NOQUEUES"]', 'skip'));
	$ext->add($c, $e, '', new ext_set('TOGGLE_MACRO', '${IF($["${QUEUESTAT}"="LOGGEDOUT"]?toggle-add-agent:toggle-del-agent)}'));
	if ($amp_conf['USEDEVSTATE']) {
		$ext->add($c, $e, '', new ext_set('STATE', '${IF($["${QUEUESTAT}"="LOGGEDOUT"]?INUSE:NOT_INUSE)}'));
	}
	$ext->add($c, $e, '', new ext_set('LOOPCNTALL', '${FIELDQTY(USERQUEUES,-)}'));
	$ext->add($c, $e, '', new ext_set('ITERALL', '1'));
	$ext->add($c, $e, 'begin', new ext_set('QUEUENO', '${CUT(USERQUEUES,-,${ITERALL})}'));
	$ext->add($c, $e, '', new ext_set('ITERALL', '$[${ITERALL}+1]'));
	$ext->add($c, $e, '', new ext_macro('${TOGGLE_MACRO}'));
	if ($amp_conf['USEDEVSTATE']) {
		$ext->add($c, $e, '', new ext_gosub('1', 'sstate', 'app-queue-toggle'));
	}
	$ext->add($c, $e, '', new ext_gotoif('$[${ITERALL} <= ${LOOPCNTALL}]', 'begin'));
	$ext->add($c, $e, 'skip', new ext_execif('$["${QUEUESTAT}"="LOGGEDIN" | "${QUEUESTAT}"="NOQUEUES"]', 'Playback', 'agent-loggedoff'));
	$ext->add($c, $e, '', new ext_execif('$["${QUEUESTAT}"="LOGGEDOUT"]', 'Playback', 'agent-loginok'));
	$ext->add($c, $e, '', new ext_execif('$["${QUEUESTAT}"="LOGGEDOUT"]', 'SayDigits', '${AMPUSER}'));
	$ext->add($c, $e, '', new ext_macro('hangupcall'));
}


/* Trial DEVSTATE */
function queue_app_toggle($c) {
	global $ext;
	global $amp_conf;
	global $version;

	$id = "app-queue-toggle"; // The context to be included
	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal

	$c = 's';

	$ext->add($id, $c, 'start', new ext_answer(''));
	$ext->add($id, $c, '', new ext_wait('1'));
	$ext->add($id, $c, '', new ext_macro('user-callerid'));
	$ext->add($id, $c, '', new ext_setvar('QUEUESTAT', 'LOGGEDOUT'));
	$ext->add($id, $c, '', new ext_agi('queue_devstate.agi,getqueues,${AMPUSER}'));

	$ext->add($id, $c, '', new ext_gotoif('$["${QUEUESTAT}" = "LOGGEDOUT"]', 'activate'));
	$ext->add($id, $c, '', new ext_gotoif('$["${QUEUESTAT}" = "LOGGEDIN"]', 'deactivate'));
	$ext->add($id, $c, '', new ext_gotoif('$["${QUEUESTAT}" = "STATIC"]', 'static','end'));
	$ext->add($id, $c, 'deactivate', new ext_noop('Agent Logged out'));
	$ext->add($id, $c, '', new ext_macro('toggle-del-agent'));
	if ($amp_conf['USEDEVSTATE']) {
		$ext->add($id, $c, '', new ext_setvar('STATE', 'NOT_INUSE'));
		$ext->add($id, $c, '', new ext_gosub('1', 'sstate'));
	}
	$ext->add($id, $c, '', new ext_playback('agent-loggedoff'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));

	$ext->add($id, $c, 'activate', new ext_noop('Agent Logged In'));
	$ext->add($id, $c, '', new ext_macro('toggle-add-agent'));
	if ($amp_conf['USEDEVSTATE']) {
		$ext->add($id, $c, '', new ext_setvar('STATE', 'INUSE'));
		$ext->add($id, $c, '', new ext_gosub('1', 'sstate'));
	}
	$ext->add($id, $c, '', new ext_playback('agent-loginok'));
	$ext->add($id, $c, '', new ext_saydigits('${CALLBACKNUM}'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));

	$ext->add($id, $c, 'static', new ext_noop('User is a Static Agent'));
	if ($amp_conf['USEDEVSTATE']) {
		$ext->add($id, $c, '', new ext_setvar('STATE', 'INUSE'));
		$ext->add($id, $c, '', new ext_gosub('1', 'sstate'));
	}
	$ext->add($id, $c, '', new ext_playback('agent-loginok'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));

	if ($amp_conf['USEDEVSTATE']) {
		$c = 'sstate';
		$ext->add($id, $c, '', new ext_dbget('DEVICES','AMPUSER/${AMPUSER}/device'));
		$ext->add($id, $c, '', new ext_gotoif('$["${DEVICES}" = "" ]', 'return'));
		$ext->add($id, $c, '', new ext_setvar('LOOPCNT', '${FIELDQTY(DEVICES,&)}'));
		$ext->add($id, $c, '', new ext_setvar('ITER', '1'));
		$ext->add($id, $c, 'begin', new ext_setvar($amp_conf['AST_FUNC_DEVICE_STATE'].'(Custom:QUEUE${CUT(DEVICES,&,${ITER})}*${QUEUENO})','${STATE}'));
		$ext->add($id, $c, '', new ext_setvar('ITER', '$[${ITER} + 1]'));
		$ext->add($id, $c, '', new ext_gotoif('$[${ITER} <= ${LOOPCNT}]', 'begin'));
		$ext->add($id, $c, 'return', new ext_return());
		}
}

function queue_agent_add_toggle() {
	global $ext, $amp_conf, $version;

	$ast_ge_14_25 = version_compare($version,'1.4.25','ge');
	$ast_ge_18 = version_compare($version,'1.8','ge');
	$id = "macro-toggle-add-agent"; // The context to be included

	$c = 's';

	$ext->add($id, $c, '', new ext_wait('1'));
	$ext->add($id, $c, '', new ext_macro('user-callerid,SKIPTTL'));
	$ext->add($id, $c, '', new ext_setvar('CALLBACKNUM','${AMPUSER}'));
	//TODO: check if it's not a user for some reason and abort?
	$ext->add($id, $c, '', new ext_gotoif('$["${DB(QPENALTY/${QUEUENO}/dynmemberonly)}" = "yes" & ${DB_EXISTS(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})} != 1]', 'invalid'));
	if ($ast_ge_18 || $amp_conf['USEQUEUESTATE']) {
		$ext->add($id, $c, '', new ext_execif('$["${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" != "ignorestate"]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)},hint:${CALLBACKNUM}@ext-local'));
		$ext->add($id, $c, '', new ext_execif('$["${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" = "ignorestate"]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)}'));

	} else if ($ast_ge_14_25) {
		$ext->add($id, $c, '', new ext_execif('$["${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" != "ignorestate"]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)},${DB(DEVICE/${REALCALLERIDNUM}/dial)}'));
		$ext->add($id, $c, '', new ext_execif('$["${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" = "ignorestate"]', 'AddQueueMember', '${QUEUENO},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)}'));
	} else {
		$ext->add($id, $c, '', new ext_addqueuemember('${QUEUENO}','Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${QUEUENO}/agents/${CALLBACKNUM})}'));
	}

	$ext->add($id, $c, '', new ext_userevent('AgentLogin','Agent: ${CALLBACKNUM}'));
	$ext->add($id, $c, '', new ext_macroexit());
	$ext->add($id, $c, 'invalid', new ext_playback('pbx-invalid'));
	$ext->add($id, $c, '', new ext_macroexit());
}

function queue_agent_del_toggle() {
	global $ext;
	global $amp_conf;

	$id = "macro-toggle-del-agent"; // The context to be included

	$c = 's';

	$ext->add($id, $c, '', new ext_wait('1'));
	$ext->add($id, $c, '', new ext_macro('user-callerid,SKIPTTL'));
	$ext->add($id, $c, '', new ext_setvar('CALLBACKNUM','${AMPUSER}'));
	$ext->add($id, $c, '', new ext_removequeuemember('${QUEUENO}','Local/${CALLBACKNUM}@from-queue/n'));
	$ext->add($id, $c, '', new ext_removequeuemember('${QUEUENO}','Local/${CALLBACKNUM}@from-internal/n'));
	$ext->add($id, $c, '', new ext_userevent('RefreshQueue'));
	$ext->add($id, $c, '', new ext_macroexit());
}

?>
