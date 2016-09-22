<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
global $db;
global $amp_conf;

// For translation only
if (false) {
	_("Queue Toggle");
	_("Queue Pause Toggle");
	_("Queue Callers");
}

// Add Feature Codes for Toggle Queues - Using *45
$fcc = new featurecode('queues', 'que_toggle');
$fcc->setDescription(_('Allow Dynamic Members of a Queue to login or logout. See the Queues Module for how to assign a Dynamic Member to a Queue.'));
$fcc->setDefault('*45');
$fcc->update();
unset($fcc);

// Add Feature Codes for Toggle Queue Pause- Using *46
$fcc = new featurecode('queues', 'que_pause_toggle');
$fcc->setDescription(_('Queue Pause Toggle'));
$fcc->setDefault('*46');
$fcc->update();
unset($fcc);

// Add Feature Codes for Queue Callers - Using *47
$fcc = new featurecode('queues', 'que_callers');
$fcc->setDescription(_('Playback Queue Caller Count'));
$fcc->setDefault('*47');
$fcc->update();
unset($fcc);

$results = array();
$sql = "SELECT args, extension, priority FROM extensions WHERE context = 'ext-queues' AND descr = 'jump'";
$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($results)) { // error - table must not be there
	foreach ($results as $result) {
		$old_dest  = $result['args'];
		$extension = $result['extension'];
		$priority  = $result['priority'];

		$new_dest = merge_ext_followme(trim($old_dest));
		if ($new_dest != $old_dest) {
			$sql = "UPDATE extensions SET args = '$new_dest' WHERE extension = '$extension' AND priority = '$priority' AND context = 'ext-queues' AND descr = 'jump' AND args = '$old_dest'";
			$results = $db->query($sql);
			if(DB::IsError($results)) {
				die_freepbx($results->getMessage());
			}
		}
	}
}


	// Create/Migrate the queues_details table, don't put IF NOT EXISTS so we
	// can get the status in the error
	//
	$sql = "
	CREATE TABLE IF NOT EXISTS `queues_details` (
		`id` varchar( 45 ) NOT NULL default '-1',
		`keyword` varchar( 30 ) NOT NULL default '',
		`data` varchar( 150 ) NOT NULL default '',
		`flags` int( 1 ) NOT NULL default '0',
		PRIMARY KEY ( `id` , `keyword` , `data` )
	)";

	outn(_("Creating queues_details.."));
	$results = $db->query($sql);
	if (DB::IsError($results)) {
		$migrate_queues_details = false;
		if ($results->getCode() == DB_ERROR_ALREADY_EXISTS) {
			out(_("already exists"));
		} else {
			out(_("ERROR: could not create table"));
			$return_code = false;
		}
	} else if ($migrate_queues_details) {
		out(_("OK"));
		// Successfully created table so migrate the data next
		//
		$sql = "
		INSERT INTO `queues_details`
		SELECT *
		FROM `queues`
		WHERE
		keyword NOT IN ('rtone', 'account', 'context')
		";

		outn(_("Migrating to queues_details.."));
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			out(_("ERROR: could not migrate to queues_details"));
			$return_code = false;
		} else {
			out(_("OK"));
		}
	} else {
		out(_("OK"));
	}
	// Finished migrating to queues_details
	outn(_("Creating/Updating queues_config.."));
$table = \FreePBX::Database()->migrate("queues_config");
$cols = array (
  'extension' =>  array (
    'type' => 'string',
    'length' => '20',
    'primaryKey' => true,
    'default' => '',
  ),
  'descr' =>  array (
    'type' => 'string',
    'length' => '35',
    'default' => '',
  ),
  'grppre' =>  array (
    'type' => 'string',
    'length' => '100',
    'default' => '',
  ),
  'alertinfo' =>  array (
    'type' => 'string',
    'length' => '254',
    'default' => '',
  ),
  'ringing' =>  array (
    'type' => 'boolean',
    'default' => '0',
  ),
  'maxwait' =>  array (
    'type' => 'string',
    'length' => '8',
    'default' => '',
  ),
  'password' =>  array (
    'type' => 'string',
    'length' => '20',
    'default' => '',
  ),
  'ivr_id' =>  array (
    'type' => 'string',
    'length' => '8',
    'default' => '0',
  ),
  'dest' =>  array (
    'type' => 'string',
    'length' => '50',
    'default' => '',
  ),
  'cwignore' =>  array (
    'type' => 'boolean',
    'default' => '0',
  ),
  'queuewait' =>  array (
    'type' => 'boolean',
    'notnull' => false,
    'default' => '0',
  ),
	'use_queue_context' =>  array (
    'type' => 'boolean',
    'notnull' => false,
    'default' => '0',
  ),
  'togglehint' =>  array (
    'type' => 'boolean',
    'notnull' => false,
    'default' => '0',
  ),
  'qnoanswer' =>  array (
    'type' => 'boolean',
    'notnull' => false,
    'default' => '0',
  ),
  'callconfirm' =>  array (
    'type' => 'boolean',
    'notnull' => false,
    'default' => '0',
  ),
  'callconfirm_id' =>  array (
    'type' => 'integer',
    'notnull' => false,
  ),
  'qregex' =>  array (
    'type' => 'string',
    'length' => '255',
    'notnull' => false,
  ),
  'agentannounce_id' =>  array (
    'type' => 'integer',
    'notnull' => false,
  ),
  'joinannounce_id' =>  array (
    'type' => 'integer',
    'notnull' => false,
  ),
  'monitor_type' =>  array (
    'type' => 'string',
    'length' => '5',
    'notnull' => false,
  ),
  'monitor_heard' =>  array (
    'type' => 'integer',
    'notnull' => false,
  ),
  'monitor_spoken' =>  array (
    'type' => 'integer',
    'notnull' => false,
  ),
  'callback_id' =>  array (
    'type' => 'string',
    'length' => '8',
    'default' => '',
  ),
);

$table->modify($cols);
unset($table);
out(_("OK"));


$freepbx_conf =& freepbx_conf::create();

// QUEUES_PESISTENTMEMBERS
//
$set['value'] = true;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 1;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'queues';
$set['category'] = 'Queues Module';
$set['emptyok'] = 0;
$set['sortorder'] = 10;
$set['name'] = 'Persistent Members';
$set['description'] = 'Queues: persistentmembers. Store each dynamic member in each queue in the astdb so that when asterisk is restarted, each member will be automatically read into their recorded queues.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('QUEUES_PESISTENTMEMBERS',$set);

// QUEUES_SHARED_LASTCALL
//
$set['value'] = true;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 1;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'queues';
$set['category'] = 'Queues Module';
$set['emptyok'] = 0;
$set['sortorder'] = 20;
$set['name'] = 'Honor Wrapup Time Across Queues';
$set['description'] = 'Queues: shared_lastcall, only valid with Asterisk 1.6+. This will make the lastcall and calls received be the same in members logged in more than one queue. This is useful to make the queue respect the wrapuptime of another queue for a shared member.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('QUEUES_SHARED_LASTCALL',$set);

$set['value'] = false;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'queues';
$set['category'] = 'Queues Module';
$set['emptyok'] = 0;
$set['sortorder'] = 30;
$set['name'] = 'Set Agent Name in CDR dstchannel';
$set['description'] = 'Queues: updatecdr, only valid with Asterisk 1.6+. This option is implemented to mimic chan_agents behavior of populating CDR dstchannel field of a call with an agent name, which is set if available at the login time with AddQueueMember membername parameter, or with static members.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('QUEUES_UPDATECDR',$set);

// QUEUES_MIX_MONITOR
//
$set['value'] = true;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'queues';
$set['category'] = 'Queues Module';
$set['emptyok'] = 0;
$set['sortorder'] = 40;
$set['name'] = 'Use MixMonitor for Recordings';
$set['description'] = "Queues: monitor-type = MixMonitor. Setting true will use the MixMonitor application instead of Monitor so the concept of 'joining/mixing' the in/out files now goes away when this is enabled.";
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('QUEUES_MIX_MONITOR',$set);

// QUEUES_HIDE_NOANSWER
//
$set['value'] = true;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'queues';
$set['category'] = 'Queues Module';
$set['emptyok'] = 0;
$set['sortorder'] = 50;
$set['name'] = 'Hide Queue No Answer Option';
$set['description'] = 'It is possible for a queue to NOT Answer a call and still enter callers to the queue. The normal behavior is that all  allers are answered before entering the queue. If the call is not answered, it is possible that some early media delivery would still allow callers to hear recordings, MoH, etc. but this can be inconsistent and vary. Because of the volatility of this option, it is not displayed by default. If a queue is set to not answer, the setting will be displayed for that queue regardless of this setting.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('QUEUES_HIDE_NOANSWER',$set);

// GENERATE_LEGACY_QUEUE_CODES
//
$set['value'] = true;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 3;
$set['module'] = 'queues';
$set['category'] = 'Queues Module';
$set['emptyok'] = 0;
$set['sortorder'] = 120;
$set['name'] = 'Generate queuenum*/** Login/off Codes';
$set['description'] = 'Queue login and out codes were historically queunum* and queunum**. These have been largely replaced by the *45 queue toggle codes. The legacy codes are required to login or out a third party user that is not the extension dialing. These can be removed from the system by setting this to false.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('GENERATE_LEGACY_QUEUE_CODES',$set,true);


// QUEUES_EVENTS_WHEN_CALLED_DEFAULT
$set['value'] = false;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 3;
$set['module'] = 'queues';
$set['category'] = 'Queues Module';
$set['emptyok'] = 0;
$set['sortorder'] = 120;
$set['name'] = 'Agent Called Events Default';
$set['description'] = 'Default state for AMI emit events related to an agent\'s call. This setting will only affect the default for NEW queues, it won\'t change existing queues or enfore the option on in new ones.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('QUEUES_EVENTS_WHEN_CALLED_DEFAULT', $set, true);


// QUEUES_EVENTS_MEMEBER_STATUS_DEFAULT
$set['value'] = false;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 3;
$set['module'] = 'queues';
$set['category'] = 'Queues Module';
$set['emptyok'] = 0;
$set['sortorder'] = 120;
$set['name'] = 'Member Status Event Default';
$set['description'] = 'Default state for AMI to emit the QueueMemberStatus event. This setting will only affect the default for NEW queues, it won\'t change existing queues or enfore the option on in new ones.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('QUEUES_EVENTS_MEMEBER_STATUS_DEFAULT', $set, true);
