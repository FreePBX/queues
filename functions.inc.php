<?php /* $id:$ */

class queues_conf {

	var $_queues_general    = array();

	// return an array of filenames to write
	// files named like pinset_N
	function get_filename() {
		$files = array(
			'queues_additional.conf',
			'queues_general_additional.conf',
			);
		return $files;
	}
	
	// return the output that goes in each of the files
	function generateConf($file) {
		global $version;

		switch ($file) {
			case 'queues_additional.conf':
				return $this->generate_queues_additional($version);
				break;
			case 'queues_general_additional.conf':
				return $this->generate_queues_general_additional($version);
				break;
		}
	}

	function addQueuesGeneral($key, $value) {
		$this->_queues_general[] = array('key' => $key, 'value' => $value);
	}

	function generate_queues_additional($ast_version) {

		global $db;
		global $amp_conf;

		$additional = "";
		$output = "";
		// Asterisk 1.4 does not like blank assignments so just don't put them there
		//
		$ver12 = version_compare($ast_version, '1.4', 'lt');
		$ver16 = version_compare($ast_version, '1.6', 'ge');
    $ast_ge_14_25 = version_compare($ast_version,'1.4.25','ge');
    $ast_ge_18 = version_compare($ast_version,'1.8','ge');
		
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

    if ($ast_ge_14_25) {
		  $devices = array();
		  $device_results = core_devices_list('all','full',true);
		  if (is_array($device_results)) {
			  foreach ($device_results as $device) {
          if (!isset($devices[$device['user']]) && $device['devicetype'] == 'fixed') {
				    $devices[$device['user']] = $device['dial'];
          }
			  }
			  unset($device_results);
		  }
    }
    if ($amp_conf['USEQUEUESTATE'] || $ast_ge_14_25) {
		  $users = array();
		  $user_results = core_users_list();
		  if (is_array($user_results)) {
			  foreach ($user_results as $user) {
				  $users[$user[0]] = $user[1];
			  }
			  unset($user_results);
		  }
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
						case 'autofill': 
							break;
						case 'retry': 
							if ($data == 'none') {
								$data = 0;
							}
							// no break, fallthrough to default
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
						case 'announce-position':
							if ($ver16) {
								$output .= $keyword."=".$data."\n";
							}
							break;
						case 'retry': 
							if ($data == 'none') {
								$data = 0;
							}
							// no break, fallthrough to default
						default:
							$output .= $keyword."=".$data."\n";
							break;
					}
				}
			}

			// Now pull out all the memebers, one line for each
			//
      if ($ast_ge_18 || $amp_conf['USEQUEUESTATE']) {
			  foreach ($members as $member) {
				  preg_match("/^Local\/([\d]+)\@*/",$member,$matches);
				  if (isset($matches[1]) && isset($users[$matches[1]])) {
					  $name = $users[$matches[1]];
					  str_replace(',','\,',$name);
            
            $qnostate = queues_get_qnostate($matches[1]);
            if ($qnostate == 'ignorestate') {
              freepbx_log(FPBX_LOG_NOTICE,"Ignoring State information for Queue Member: ".$matches[1]);
					    $output .= "member=$member,$name\n";
            } else {
					    $output .= "member=$member,$name,hint:".$matches[1]."@ext-local\n";
            }
				  } else {
					  $output .= "member=".$member."\n";
				  }
			  }
      } else if ($ast_ge_14_25) {
			  foreach ($members as $member) {
				  preg_match("/^Local\/([\d]+)\@*/",$member,$matches);
				  if (isset($matches[1]) && isset($devices[$matches[1]])) {
					  $name = $users[$matches[1]];
					  str_replace(',','\,',$name);
            $qnostate = queues_get_qnostate($matches[1]);
            if ($qnostate == 'ignorestate') {
              freepbx_log(FPBX_LOG_NOTICE,"Ignoring State information for Queue Member: ".$matches[1]);
					    $output .= "member=$member,$name\n";
            } else {
					    $output .= "member=$member,$name,".$devices[$matches[1]]."\n";
            }
				  } else {
					  $output .= "member=".$member."\n";
				  }
			  }
      } else {
        foreach ($members as $member) {
          $output .= "member=".$member."\n";
        }
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

	function generate_queues_general_additional($ast_version) {
		$output = '';

		if (isset($this->_queues_general) && is_array($this->_queues_general)) {
			foreach ($this->_queues_general as $values) {
				$output .= $values['key']."=".$values['value']."\n";
			}
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
			return array('description' => sprintf(_("Queue %s : %s"),$exten,$thisexten['name']),
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
				'description' => sprintf(_("Queue: %s"),$result['descr']),
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
				'description' => sprintf(_("Queue: %s"),$result['descr']),
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
				queue_agent_del_toggle();
				queue_agent_add_toggle();
			}
			$qlist = queues_list(true);

			$from_queue_exten_only = 'from-queue-exten-only';
			$from_queue_exten_internal = 'from-queue-exten-internal';

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
					
					if (isset($q['qnoanswer']) && $q['qnoanswer'] == FALSE) {
						$ext->add('ext-queues', $exten, '', new ext_answer(''));
					} else {
            // TODO: should this only be set if noanswer + (!ringtones || joinannounce)???
					  $ext->add('ext-queues', $exten, '', new ext_progress());
          }

					// block voicemail until phone is answered at which point a macro should be called on the answering
					// line to clear this flag so that subsequent transfers can occur.
					//
					if ($q['queuewait']) {
						$ext->add('ext-queues', $exten, '', new ext_execif('$["${QUEUEWAIT}" = ""]', 'Set', '__QUEUEWAIT=${EPOCH}'));
					}
          // If extension_only don't do this and CFIGNORE
          if($q['use_queue_context'] != '2') {
					  $ext->add('ext-queues', $exten, '', new ext_macro('blkvm-set', 'reset'));
					  $ext->add('ext-queues', $exten, '', new ext_execif('$["${REGEX("(M[(]auto-blkvm[)])" ${DIAL_OPTIONS})}" != "1"]', 'Set', '_DIAL_OPTIONS=${DIAL_OPTIONS}M(auto-blkvm)'));
          }

					// Inform all the children NOT to send calls to destinations or voicemail
					//
					$ext->add('ext-queues', $exten, '', new ext_setvar('__NODEST', '${EXTEN}'));

					// deal with group CID prefix
					if ($grppre != '') {
						$ext->add('ext-queues', $exten, '', new ext_macro('prepend-cid', $grppre));
					}

					// Set Alert_Info
					if ($alertinfo != '') {
						$ext->add('ext-queues', $exten, '', new ext_setvar('__ALERT_INFO', str_replace(';', '\;', $alertinfo)));
					}

					$ext->add('ext-queues', $exten, '', new ext_setvar('MONITOR_FILENAME','/var/spool/asterisk/monitor/q${EXTEN}-${STRFTIME(${EPOCH},,%Y%m%d-%H%M%S)}-${UNIQUEID}'));
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
							$ext->add('ext-queues', $exten, '', new ext_setvar('MONITOR_OPTIONS', $monitor_options ));
						}
					}
					$joinannounce_id = (isset($q['joinannounce_id'])?$q['joinannounce_id']:'');
					if($joinannounce_id) {
						$joinannounce = recordings_get_file($joinannounce_id);
					
						if (isset($q['qnoanswer']) && $q['qnoanswer'] == TRUE) {
							$joinannounce = $joinannounce.', noanswer';
						}

						$ext->add('ext-queues', $exten, '', new ext_playback($joinannounce));
					}
					$options = 't';
					if ($q['rtone'] == 1) {
						$options .= 'r';
					}
					if ($q['retry'] == 'none'){
						$options .= 'n';
					}
					if (isset($q['music'])) {
 						$ext->add('ext-queues', $exten, '', new ext_setvar('__MOHCLASS', $q['music']));
					}
					// Set CWIGNORE  if enabled so that busy agents don't have another line key ringing and
					// stalling the ACD.
					if ($q['cwignore'] == 1 || $q['cwignore'] == 2 ) {
 						$ext->add('ext-queues', $exten, '', new ext_setvar('__CWIGNORE', 'TRUE'));
					}
					if ($q['use_queue_context']) {
 						$ext->add('ext-queues', $exten, '', new ext_setvar('__CFIGNORE', 'TRUE'));
 						$ext->add('ext-queues', $exten, '', new ext_setvar('__FORWARD_CONTEXT', 'block-cf'));
					}
					$agentannounce_id = (isset($q['agentannounce_id'])?$q['agentannounce_id']:'');
					if ($agentannounce_id) {
						$agentannounce = recordings_get_file($agentannounce_id);
					} else {
						$agentannounce = '';
					}
					
					if ($q['callconfirm'] == 1) {
						$ext->add('ext-queues', $exten, '', new ext_setvar('__FORCE_CONFIRM', '${CHANNEL}'));
						if ($amp_conf['AST_FUNC_SHARED']) {
        						$ext->add('ext-queues', $exten, '', new ext_setvar('SHARED(ANSWER_STATUS)','NOANSWER'));
      						}
						$ext->add('ext-queues', $exten, '', new ext_setvar('__CALLCONFIRMCID', '${CALLERID(number)}'));
						$callconfirm_id = (isset($q['callconfirm_id']))?$q['callconfirm_id']:'';
						if ($callconfirm_id) {	
							$callconfirm = recordings_get_file($callconfirm_id);
						} else {
							$callconfirm = '';
						}
						$ext->add('ext-queues', $exten, '', new ext_setvar('__ALT_CONFIRM_MSG', $callconfirm));					
					}

					$ext->add('ext-queues', $exten, '', new ext_queue($exten,$options,'',$agentannounce,$q['maxwait']));
 
          if($q['use_queue_context'] != '2') {
					  $ext->add('ext-queues', $exten, '', new ext_macro('blkvm-clr'));
          }
 					// If we are here, disable the NODEST as we want things to resume as normal
 					//
 					$ext->add('ext-queues', $exten, '', new ext_setvar('__NODEST', ''));
					
					if ($q['callconfirm'] == 1) {
						if ($amp_conf['AST_FUNC_SHARED']) {
							$ext->add('ext-queues', $exten, '', new ext_setvar('SHARED(ANSWER_STATUS)', ''));
						}
						$ext->add('ext-queues', $exten, '', new ext_setvar('__FORCE_CONFIRM', ''));
						$ext->add('ext-queues', $exten, '', new ext_setvar('__ALT_CONFIRM_MSG', ''));				
					}

					if($monitor_options != '') {
						$ext->add('ext-queues', $exten, '', new ext_setvar('MONITOR_OPTIONS', ''));
					}
					if ($q['cwignore'] == 1 || $q['cwignore'] == 2 ) {
						$ext->add('ext-queues', $exten, '', new ext_setvar('__CWIGNORE', '')); 
					}
					if ($q['use_queue_context']) {
 						$ext->add('ext-queues', $exten, '', new ext_setvar('__CFIGNORE', ''));
 						$ext->add('ext-queues', $exten, '', new ext_setvar('__FORWARD_CONTEXT', 'from-internal'));
					}
	
					// destination field in 'incoming' database is backwards from what ext_goto expects
					$goto_context = strtok($q['goto'],',');
					$goto_exten = strtok(',');
					$goto_pri = strtok(',');
					
					$ext->add('ext-queues', $exten, '', new ext_goto($goto_pri,$goto_exten,$goto_context));
					
					//dynamic agent login/logout
					if (trim($qregex) != '') {
 						$ext->add('ext-queues', $exten."*", '', new ext_setvar('QREGEX', $qregex));
					}
          if($q['use_queue_context'] == '2') {
					  $ext->add('ext-queues', $exten."*", '', new ext_macro('agent-add',$exten.",".$q['password'].",EXTEN"));
          } else {
					  $ext->add('ext-queues', $exten."*", '', new ext_macro('agent-add',$exten.",".$q['password']));
          }
					$ext->add('ext-queues', $exten."**", '', new ext_macro('agent-del',"$exten"));
					if ($que_code != '') {
            $ext->add('ext-queues', $que_code.$exten, '', new ext_setvar('QUEUENO',$exten));
            $ext->add('ext-queues', $que_code.$exten, '', new ext_goto('start','s','app-queue-toggle'));
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
              if ((!$dynmemberonly||$device['devicetype']=='adhoc'||isset($mem[$device['user']]))&&($device['tech']=='sip'||$device['tech']=='iax2')) {
							  $ext->add('ext-queues', $que_code.$device['id'].'*'.$exten, '', new ext_setvar('QUEUENO',$exten));
							  $ext->add('ext-queues', $que_code.$device['id'].'*'.$exten, '', new ext_goto('start','s','app-queue-toggle'));
							  $ext->addHint('ext-queues', $que_code.$device['id'].'*'.$exten, "Custom:QUEUE".$device['id'].'*'.$exten);
              }
						}
					}

					// Add routing vector to direct which context call should go
					//
					$agent_context = $q['use_queue_context'] ? $queue_context : 'from-internal';
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
			}
			// We need to have a hangup here, if call is ended by the caller during Playback it will end in the
			// h context and do a proper hangup and clean the blkvm if set, see #4671
			$ext->add('ext-queues', 'h', '', new ext_macro('hangupcall'));			
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
					$ext->add($from_queue_exten_only, $item[0], '', new ext_macro('record-enable',$item[0].",IN"));
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

			$context = 'macro-agent-add';
			$exten = 's';
			
			$ext->add($context, $exten, '', new ext_wait(1));
			$ext->add($context, $exten, '', new ext_macro('user-callerid', 'SKIPTTL'));
			$ext->add($context, $exten, 'a3', new ext_read('CALLBACKNUM', 'agent-login'));  // get callback number from user
			$ext->add($context, $exten, '', new ext_gotoif('$[${LEN(${CALLBACKNUM})}=0]','a5','a7'));  // if user just pressed # or timed out, use cidnum
			$ext->add($context, $exten, 'a5', new ext_set('CALLBACKNUM', '${IF($[${LEN(${AMPUSER})}=0]?${CALLERID(number)}:${AMPUSER})}'));

      if ($ast_ge_14_25) {
			  $ext->add($context, $exten, '', new ext_set('THISDEVICE', '${DB(DEVICE/${REALCALLERIDNUM}/dial)}'));
      }
			$ext->add($context, $exten, '', new ext_gotoif('$["${CALLBACKNUM}" = ""]', 'a3'));  // if still no number, start over
			$ext->add($context, $exten, 'a7', new ext_gotoif('$["${CALLBACKNUM}" = "${ARG1}"]', 'invalid'));  // Error, they put in the queue number

      // If this is an extension only queue then EXTEN is passed as ARG3 and we make sure this is a valid extension being entered
      //
			$ext->add($context, $exten, '', new ext_gotoif('$["${ARG3}" = "EXTEN" & ${DB_EXISTS(AMPUSER/${CALLBACKNUM}/cidname)} = 0]', 'invalid'));

      // If this is a restricted dynamic agent queue then check to make sure they are allowed
      //
      $ext->add($context, $exten, '', new ext_gotoif('$["${DB(QPENALTY/${ARG1}/dynmemberonly)}" = "yes" & ${DB_EXISTS(QPENALTY/${ARG1}/agents/${CALLBACKNUM})} != 1]', 'invalid'));

			$ext->add($context, $exten, '', new ext_execif('$["${QREGEX}" != ""]', 'GotoIf', '$["${REGEX("${QREGEX}" ${CALLBACKNUM})}" = "0"]?invalid'));
			$ext->add($context, $exten, '', new ext_execif('$["${ARG2}" != ""]', 'Authenticate', '${ARG2}'));


      if ($ast_ge_18 || $amp_conf['USEQUEUESTATE']) {
			  $ext->add($context, $exten, '', new ext_execif('$[${DB_EXISTS(AMPUSER/${CALLBACKNUM}/cidname)} = 1 & "${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" != "ignorestate"]', 'AddQueueMember', '${ARG1},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${ARG1}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)},hint:${CALLBACKNUM}@ext-local'));
			  $ext->add($context, $exten, '', new ext_execif('$[${DB_EXISTS(AMPUSER/${CALLBACKNUM}/cidname)} = 1 & "${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" = "ignorestate"]', 'AddQueueMember', '${ARG1},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${ARG1}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)}'));
			  $ext->add($context, $exten, '', new ext_execif('$[${DB_EXISTS(AMPUSER/${CALLBACKNUM}/cidname)} = 0]', 'AddQueueMember', '${ARG1},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${ARG1}/agents/${CALLBACKNUM})}'));
      } else if ($ast_ge_14_25) {
			  $ext->add($context, $exten, '', new ext_set('THISDEVICE', '${IF($[${LEN(${THISDEVICE})}=0]?${DB(DEVICE/${CUT(DB(AMPUSER/${CALLBACKNUM}/device),&,1)}/dial)}:${THISDEVICE})}'));
			  $ext->add($context, $exten, '', new ext_execif('$[${LEN(${THISDEVICE})}!=0 & "${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" != "ignorestate"]', 'AddQueueMember', '${ARG1},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${ARG1}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)},${THISDEVICE}'));
			  $ext->add($context, $exten, '', new ext_execif('$[${LEN(${THISDEVICE})}!=0 & "${DB(AMPUSER/${CALLBACKNUM}/queues/qnostate)}" = "ignorestate"]', 'AddQueueMember', '${ARG1},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${ARG1}/agents/${CALLBACKNUM})},,${DB(AMPUSER/${CALLBACKNUM}/cidname)}'));
			  $ext->add($context, $exten, '', new ext_execif('$[${LEN(${THISDEVICE})}=0]', 'AddQueueMember', '${ARG1},Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${ARG1}/agents/${CALLBACKNUM})}'));
      } else {
        $ext->add($context, $exten, 'a9', new ext_addqueuemember('${ARG1}', 'Local/${CALLBACKNUM}@from-queue/n,${DB(QPENALTY/${ARG1}/agents/${CALLBACKNUM})}'));
      }
		  $ext->add($context, $exten, '', new ext_userevent('Agentlogin', 'Agent: ${CALLBACKNUM}'));
		  $ext->add($context, $exten, '', new ext_wait(1));
		  $ext->add($context, $exten, '', new ext_playback('agent-loginok&with&extension'));
		  $ext->add($context, $exten, '', new ext_saydigits('${CALLBACKNUM}'));
		  $ext->add($context, $exten, '', new ext_hangup());
		  $ext->add($context, $exten, '', new ext_macroexit());
		  $ext->add($context, $exten, 'invalid', new ext_playback('pbx-invalid'));
		  $ext->add($context, $exten, '', new ext_goto('a3'));

			/*
			 * Removes a dynamic agent/member from a Queue
			 * Prompts for call-back number - in not entered, uses CIDNum 
			 */

			$context = 'macro-agent-del';
			
			$ext->add($context, $exten, '', new ext_wait(1));
			$ext->add($context, $exten, '', new ext_macro('user-callerid', 'SKIPTTL'));
			$ext->add($context, $exten, 'a3', new ext_read('CALLBACKNUM', 'agent-logoff'));  // get callback number from user
			$ext->add($context, $exten, '', new ext_gotoif('$[${LEN(${CALLBACKNUM})}=0]','a5','a7'));  // if user just pressed # or timed out, use cidnum
			$ext->add($context, $exten, 'a5', new ext_set('CALLBACKNUM', '${IF($[${LEN(${AMPUSER})}=0]?${CALLERID(number)}:${AMPUSER})}'));
			$ext->add($context, $exten, '', new ext_gotoif('$["${CALLBACKNUM}" = ""]', 'a3'));  // if still no number, start over

			// remove from both contexts in case left over dynamic agents after an upgrade
			$ext->add($context, $exten, 'a7', new ext_removequeuemember('${ARG1}', 'Local/${CALLBACKNUM}@from-queue/n'));
			$ext->add($context, $exten, '', new ext_removequeuemember('${ARG1}', 'Local/${CALLBACKNUM}@from-internal/n'));
			$ext->add($context, $exten, '', new ext_userevent('RefreshQueue'));
			$ext->add($context, $exten, '', new ext_wait(1));
			$ext->add($context, $exten, '', new ext_playback('agent-loggedoff'));
			$ext->add($context, $exten, '', new ext_hangup());
		break;
	}
}

function queues_timeString($seconds, $full = false) {
	if ($seconds == 0) {
		return "0 ".($full ? _("seconds") : "s");
	}

	$minutes = floor($seconds / 60);
	$seconds = $seconds % 60;

	$hours = floor($minutes / 60);
	$minutes = $minutes % 60;

	$days = floor($hours / 24);
	$hours = $hours % 24;

	if ($full) {
 		return substr(
		              ($days ? $days." "._("day").(($days == 1) ? "" : "s").", " : "").
		              ($hours ? $hours." ".(($hours == 1) ? _("hour") : _("hours")).", " : "").
		              ($minutes ? $minutes." ".(($minutes == 1) ? _("minute") : _("minutes")).", " : "").
		              ($seconds ? $seconds." ".(($seconds == 1) ? _("second") : _("seconds")).", " : ""),
		              0, -2);
	} else {
		return substr(($days ? $days."d, " : "").($hours ? $hours."h, " : "").($minutes ? $minutes."m, " : "").($seconds ? $seconds."s, " : ""), 0, -2);
	}
}

function queues_add($account,$name,$password,$prefix,$goto,$agentannounce_id,$members,$joinannounce_id,$maxwait,$alertinfo='',$cwignore='0',$qregex='',$queuewait='0', $use_queue_context='0', $dynmembers = '', $dynmemberonly = 'no', $togglehint = '0', $qnoanswer = '0', $callconfirm = '0', $callconfirm_id, $monitor_type = '', $monitor_heard = '0', $monitor_spoken = '0') {
  global $db,$astman,$amp_conf;

	$ast_ge_16 = version_compare($amp_conf['ASTVERSION'] , '1.6', 'ge');
	
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
	array($account,'announce-position',($_REQUEST['announceposition'])?$_REQUEST['announceposition']:'no',0),
	array($account,'queue-youarenext',($_REQUEST['announceposition']=='no')?'silence/1':'queue-youarenext',0),  //if no, play no sound
	array($account,'queue-thereare',($_REQUEST['announceposition']=='no')?'silence/1':'queue-thereare',0),  //if no, play no sound
	array($account,'queue-callswaiting',($_REQUEST['announceposition']=='no')?'silence/1':'queue-callswaiting',0),  //if no, play no sound
	array($account,'queue-thankyou',($_REQUEST['announceposition']=='no')?'':'queue-thankyou',0),  //if no, play no sound
	array($account,'periodic-announce-frequency',($_REQUEST['pannouncefreq'])?$_REQUEST['pannouncefreq']:'0',0),
	array($account,'monitor-format',($_REQUEST['monitor-format'])?$_REQUEST['monitor-format']:'',0),
	array($account,'monitor-join','yes',0),
	array($account,'eventwhencalled',($_REQUEST['eventwhencalled'])?$_REQUEST['eventwhencalled']:'no',0),
	array($account,'eventmemberstatus',($_REQUEST['eventmemberstatus'])?$_REQUEST['eventmemberstatus']:'no',0),
	array($account,'weight',(isset($_REQUEST['weight']))?$_REQUEST['weight']:'0',0),
	array($account,'autofill',(isset($_REQUEST['autofill']))?'yes':'no',0),
	array($account,'ringinuse',($cwignore == 2 || $cwignore == 3)?'no':'yes',0),
	array($account,'reportholdtime',(isset($_REQUEST['reportholdtime']))?$_REQUEST['reportholdtime']:'no',0),
	array($account,'servicelevel',(isset($_REQUEST['servicelevel']))?$_REQUEST['servicelevel']:60,0),
	array($account,'memberdelay',(isset($_REQUEST['memberdelay']))?$_REQUEST['memberdelay']:'0',0),
	array($account,'timeoutrestart',(isset($_REQUEST['timeoutrestart']))?$_REQUEST['timeoutrestart']:'no',0),
);

	if($ast_ge_16) {
		$fields[] = array($account, 'timeoutpriority',(isset($_REQUEST['timeoutpriority']))?$_REQUEST['timeoutpriority']:'app',0);
		$fields[] = array($account, 'penaltymemberslimit',(isset($_REQUEST['penaltymemberslimit']))?$_REQUEST['penaltymemberslimit']:'0',0);
	}

	if ($_REQUEST['music'] != 'inherit') {
		$fields[] = array($account,'music',($_REQUEST['music'])?$_REQUEST['music']:'default',0);
	}

	//there can be multiple members
	if (isset($members)) {
		$count = 0;
		$members = array_unique($members);
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
	$descr         = isset($name) ? $db->escapeSimple($name):'';
	$grppre        = isset($prefix) ? $db->escapeSimple($prefix):'';
	$alertinfo     = isset($alertinfo) ? $db->escapeSimple($alertinfo):'';
	//$joinannounce_id  = $joinannounce_id;
	$ringing       = isset($_REQUEST['rtone']) ? $_REQUEST['rtone']:'';
	//$agentannounce_id = $agentannounce_id;
	$maxwait       = isset($maxwait) ? $maxwait:'';
	$password      = isset($password) ? $password:'';
	$ivr_id        = isset($_REQUEST['announcemenu']) ? $_REQUEST['announcemenu']:'none';
	$dest          = isset($goto) ? $goto:'';
	$cwignore      = isset($cwignore) ? $cwignore:'0';
	$queuewait     = isset($queuewait) ? $queuewait:'0';
	$qregex        = isset($qregex) ? $db->escapeSimple($qregex):'';
	$use_queue_context = isset($use_queue_context) ? $use_queue_context:'0';
	$togglehint    = isset($togglehint) ? $togglehint:'0';
	$qnoanswer     = isset($qnoanswer) ? $qnoanswer:'0';
	$callconfirm   = isset($callconfirm) ? $callconfirm:'0';
	$monitor_type  = isset($monitor_type) ? $monitor_type:'';
	$monitor_heard = isset($monitor_heard) ? $monitor_heard:'0';
	$monitor_spoken = isset($monitor_spoken) ? $monitor_spoken:'0';

	// Assumes it has just been deleted
	$sql = "INSERT INTO queues_config (extension, descr, grppre, alertinfo, joinannounce_id, ringing, agentannounce_id, maxwait, password, ivr_id, dest, cwignore, qregex, queuewait, use_queue_context, togglehint, qnoanswer, callconfirm, callconfirm_id, monitor_type, monitor_heard, monitor_spoken)
         	VALUES ('$extension', '$descr', '$grppre', '$alertinfo', '$joinannounce_id', '$ringing', '$agentannounce_id', '$maxwait', '$password', '$ivr_id', '$dest', '$cwignore', '$qregex', '$queuewait', '$use_queue_context', '$togglehint', '$qnoanswer', '$callconfirm', '$callconfirm_id', '$monitor_type', '$monitor_heard', '$monitor_spoken')	";
	$results = sql($sql);

  // store dynamic member data in astDB
	if ($astman) {
    $dynmembers = array_unique($dynmembers);
	  foreach($dynmembers as $member){
  	  $mem=explode(',',$member);
      if (isset($mem[0]) && trim($mem[0]) != '') {
        $penalty = isset($mem[1]) && ctype_digit(trim($mem[1])) ? $mem[1] : 0;
  	    $astman->database_put('QPENALTY/'.$account.'/agents',trim($mem[0]),trim($penalty));
      }
    }
 	  $astman->database_put('QPENALTY/'.$account,'dynmemberonly',$dynmemberonly);
	} else {
		fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
	}

	return true;
}

function queues_del($account) {
	global $db,$astman,$amp_conf;
	
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
	
	//remove dynamic memebers from astDB
	if ($astman) {
	  $astman->database_deltree('QPENALTY/'.$account);
	} else {
		fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
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
		$extenlist[$thisexten]['description'] = sprintf(_("Queue: %s"),$result['descr']);
		$extenlist[$thisexten]['status'] = _('INUSE');
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
			'description' => sprintf(_("Queue: %s (%s)"),$result['descr'],$thisid),
			'edit_url' => 'config.php?display=queues&extdisplay='.urlencode($thisid),
		);
	}
	return $destlist;
}

function queue_change_destination($old_dest, $new_dest) {
	$sql = 'UPDATE queues_config SET dest = "' . $new_dest . '" WHERE dest = "' . $old_dest . '"';
	sql($sql, "query");
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
	global $db,$astman,$amp_conf;
	
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
	    $account=str_replace("'",'',$account);
	    //get dynamic members priority from astDB
	    $get=$astman->database_show('QPENALTY/'.$account.'/agents');
	    if($get){
		    foreach($get as $key => $value){
			    $key=explode('/',$key);
			    $mem[$key[4]]=$value;
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
			if( isset($arr['announcement_id']) && $arr['announcement_id'] != '') {
				$periodic = recordings_get_file($arr['announcement_id']);
				// We need to strip off all but the first sound file of any compound sound files
				//
				$periodic_arr = explode("&", $periodic);
				$results['periodic-announce'] = $periodic_arr[0];
			}
		}
	}
	return $results;
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
	global $ext;
	global $amp_conf;
	global $version;

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

/************************************************************************************************************
 * Hook Exentions/Users to allow an extension to indicate if the Queue should ignore it's state information
 * when it is acting as a Queue Member (Agent).
 */
function queues_get_qnostate($exten) {
	global $astman;

	// Retrieve the qnostate configuraiton from this user from ASTDB
	if ($astman) {
    $qnostate = $astman->database_get("AMPUSER",$exten."/queues/qnostate");
	} else {
		fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
	}
	// If it's blank, set it to 'usestate'
  //
	return ($qnostate == 'ignorestate' ? $qnostate : 'usestate');
}

function queues_set_qnostate($exten,$qnostate) {
	global $astman;
	
	// Update the settings in ASTDB
  if ($astman) {
	  $astman->database_put("AMPUSER",$exten."/queues/qnostate",$qnostate);
  } else {
	  fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
  }
}

function queues_applyhooks() {
	global $currentcomponent;

	$currentcomponent->addoptlistitem('qnostate', 'usestate', _('Use State'));
	$currentcomponent->addoptlistitem('qnostate', 'ignorestate',_('Ignore State'));
	$currentcomponent->setoptlistopts('qnostate', 'sort', false);

	// Add the 'process' function - this gets called when the page is loaded, to hook into 
	// displaying stuff on the page.
	$currentcomponent->addguifunc('queues_configpageload',9);

}

function queues_configpageinit($pagename) {
	global $currentcomponent;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

	// We only want to hook 'users' or 'extensions' pages.
	if ($pagename != 'users' && $pagename != 'extensions') 
		return true;
	// On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page.
	if ($tech_hardware != null || $pagename == 'users') {
		queues_applyhooks();
		$currentcomponent->addprocessfunc('queues_configprocess', 8);
	} elseif ($action=="add") {
		// We don't need to display anything on an 'add', but we do need to handle returned data.
		$currentcomponent->addprocessfunc('queues_configprocess', 8);
	} elseif ($extdisplay != '') {
		// We're now viewing an extension, so we need to display _and_ process.
		queues_applyhooks();
		$currentcomponent->addprocessfunc('queues_configprocess', 8);
	}
}

// This is called before the page is actually displayed, so we can use addguielem().
function queues_configpageload() {
	global $currentcomponent;

	// Init vars from $_REQUEST[]
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	
	// Don't display this stuff it it's on a 'This xtn has been deleted' page.
	if ($action != 'del') {

		$qnostate = queues_get_qnostate($extdisplay);

		$section = _('Extension Options');
		$currentcomponent->addguielem($section, new gui_selectbox('qnostate', $currentcomponent->getoptlist('qnostate'), $qnostate, _('Queue State Detection'), _("If this extension is part of a Queue then the Queue will attempt to use the user's extension state or device state information when determining if this queue member should be called. In some uncommon situations such as a Follow-Me with no physical device, or some virtual extension scenarios, the state information will indicate that this member is not available when they are. Setting this to 'Ignore State' will make the Queue ignore all state information thus always trying to contact this member. Certain side affects can occur when this route is taken due to the nature of how Queues handle Local channels, such as subsequent transfers will continue to show the member as busy until the original call is terminated. In most cases, this SHOULD BE set to 'Use State'."), false));
	}
}

function queues_configprocess() {
	//create vars from the request
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extn = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$qnostate = isset($_REQUEST['qnostate'])?$_REQUEST['qnostate']:null;

	if ($ext==='') { 
		$extdisplay = $extn; 
	} else {
		$extdisplay = $ext;
	} 
	if ($action == "add" || $action == "edit") {
		if (!isset($GLOBALS['abort']) || $GLOBALS['abort'] !== true) {
      queues_set_qnostate($extdisplay, $qnostate);
		}
	} // if 'del' then core will remove the entire tree
}
?>
