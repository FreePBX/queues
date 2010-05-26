<?php
global $db;
global $amp_conf;

// Add Feature Codes for Toggle Queues - Using *45
$fcc = new featurecode('queues', 'que_toggle');
$fcc->setDescription('Queue Toggle');
$fcc->setDefault('*45');
$fcc->update();
unset($fcc);

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

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

// Version 2.2.14 change - bump up priority on Goto because of inserted alert-info
//

$results = $db->query("UPDATE extensions SET priority = '7' WHERE context = 'ext-queues' AND priority = '6' AND application = 'Goto' AND descr = 'jump'");
if(DB::IsError($results)) {
	echo $results->getMessage();
	return false;
}

	/** 2.4.0 Migrate away from legacy extensions table and queues table to queues_config and queues_details
	*/

	$return_code = true;

	outn(_("Checking for legacy queues table.."));
	$sql = "SELECT * FROM `queues`";
	$results = $db->query($sql);
	if (DB::IsError($results)) {
		out(_("NO table found, no migration to do just create tables"));
		// Must not be a table so don't try to migrate
		$migrate_queues_config = false;
		$migrate_queues_details = false;
	} else {
		out(_("OK"));
		$migrate_queues_config = true;
		$migrate_queues_details = true;
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

	// Create the queues_config table, don't put IF NOT EXISTS so we
	// can get the status in the error
	//
	// for sqlite3, create the final table template since sqlite3 
	// support officially begins at 2.5 release.
	if($amp_conf["AMPDBENGINE"] == "sqlite3")  {

		$sql = "
		CREATE TABLE IF NOT EXISTS queues_config (
		  extension varchar(20) NOT NULL default '',
		  descr varchar(35) NOT NULL default '',
		  grppre varchar(100) NOT NULL default '',
		  alertinfo varchar(254) NOT NULL default '',
		  joinannounce_id int,
		  ringing tinyint(1) NOT NULL default '0',
		  agentannounce_id int,
		  maxwait varchar(8) NOT NULL default '',
		  `password` varchar(20) NOT NULL default '',
		  ivr_id varchar(8) NOT NULL default '0',
		  dest varchar(50) NOT NULL default '',
		  cwignore tinyint(1) NOT NULL default '0',
		  `qregex` VARCHAR( 255 ) NULL,
			`queuewait` TINYINT( 1 ) DEFAULT 0,
			`use_queue_context` TINYINT( 1 ) DEFAULT 0,
			`togglehint` TINYINT( 1 ) DEFAULT 0,
		PRIMARY KEY  (extension)
		)
		";
	}
	else  {
		$sql = "
		CREATE TABLE IF NOT EXISTS queues_config (
		  extension varchar(20) NOT NULL default '',
		  descr varchar(35) NOT NULL default '',
		  grppre varchar(100) NOT NULL default '',
		  alertinfo varchar(254) NOT NULL default '',
		  joinannounce varchar(254) NOT NULL default '',
		  ringing tinyint(1) NOT NULL default '0',
		  agentannounce varchar(254) NOT NULL default '',
		  maxwait varchar(8) NOT NULL default '',
		  `password` varchar(20) NOT NULL default '',
		  ivr_id varchar(8) NOT NULL default '0',
		  dest varchar(50) NOT NULL default '',
		  cwignore tinyint(1) NOT NULL default '0',
			`queuewait` TINYINT( 1 ) DEFAULT 0,
			`use_queue_context` TINYINT( 1 ) DEFAULT 0,
			`togglehint` TINYINT( 1 ) DEFAULT 0,
		PRIMARY KEY  (extension)
		)
		";

	}

	outn(_("Creating queues_config.."));
	$results = $db->query($sql);
	if (DB::IsError($results)) {
		$migrate_queues_config = false;
		if ($results->getCode() == DB_ERROR_ALREADY_EXISTS) {
			out(_("already exists"));
		} else {
			out(_("ERROR: could not create table"));
			$return_code = false;
		}
	} else if ($migrate_queues_config) {
		out(_("OK"));
		// Successfully created table so migrate the data next
		//
		$got_items = true;
		outn(_("Migrating data to queues_config.."));
		$sql = "SELECT id, data context FROM queues WHERE keyword = 'context'";
		$context_results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if(DB::IsError($context_results)) {
			out(_("ERROR: accessing queues table obtaining context info, aborting"));
			$return_code = false;
			$got_items = false;
		} 
		$sql = "SELECT id, data rtone FROM queues WHERE keyword = 'rtone'";
		$rtone_results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if(DB::IsError($context_results)) {
			out(_("ERROR: accessing queues table obtaining rtone info, aborting"));
			$return_code = false;
			$got_items = false;
		} 
		if ($got_items) {
			// Got context & rtone, make a hash and then  get the list of IDs
			//
			$context_hash = array();
			foreach ($context_results as $item) {
				$context_hash[$item['id']] = $item['context'];
			}
			$rtone_hash = array();
			foreach ($rtone_results as $item) {
				$rtone_hash[$item['id']] = $item['rtone'];
			}
			$sql = "SELECT DISTINCT id FROM `queues`";
			$queue_ids = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if(DB::IsError($queue_ids)) {
				out(_("ERROR: accessing queues table obtaining id list, aborting"));
				$return_code = false;
			} else {
				// Got ids, now we need to go through and get info from legacy table
				// but first lets create a hash with the context info
				foreach ($queue_ids as $item) {
					if (isset($context_hash[$item['id']])) {
						$queue_ids_hash[$item['id']]['ivr_id'] = ltrim('ivr-',$context_hash[$item['id']]);
					} else {
						$queue_ids_hash[$item['id']]['ivr_id'] = '';
					}
					if (isset($rtone_hash[$item['id']])) {
						$queue_ids_hash[$item['id']]['rtone'] = $rtone_hash[$item['id']];
					} else {
						$queue_ids_hash[$item['id']]['rtone'] = '0';
					}
				}
				// Now we have a queue_ids_hash with each unique id and the state of ivr_id. The
				// remaining information comes from the legacy extensions table
				//
				foreach ($queue_ids as $item) {
					$account = $item['id']; // don't run through addslashes() here

					$ivr_id = isset($queue_ids_hash['account']['ivr_id']) ? $queue_ids_hash['account']['ivr_id'] : '';
					$ivr_id = addslashes($ivr_id);
					$rtone  = isset($queue_ids_hash['account']['rtone']) ? $queue_ids_hash['account']['rtone'] : '';
					$rtone  = addslashes($rtone);

					// get CID Prefix
					//
					$sql = "SELECT args FROM extensions WHERE extension = '$account' AND context = 'ext-queues' AND application = 'SetCIDName'";
					list($args) = $db->getRow($sql);
					$prefix = explode('$',$args); //in table like prefix${CALLERID(name)}
					$grppre = isset($prefix[0]) ?	$prefix[0] : '';	
					$grppre = addslashes($grppre);

					// get ALERT_INFO
					//
					$sql = "SELECT args FROM extensions WHERE extension = '$account' AND context = 'ext-queues' AND application = 'SetVar' AND args LIKE '__ALERT_INFO=%'";
					list($args) = $db->getRow($sql);
					$alertinfo = substr($args,strlen("__ALERT_INFO="));
					$alertinfo = isset($alertinfo) ? addslashes($alertinfo) : '';

					// get maxwait time, agentannounce and name from Queue command
					//
					$sql = "SELECT args,descr FROM extensions WHERE extension = '$account' AND context = 'ext-queues' AND application = 'Queue'";
					list($args, $descr) = $db->getRow($sql);
					$maxwait = explode(',',$args);  //in table like queuenum,t,,,maxwait

					$agentannounce = isset($maxwait[3]) ? $maxwait[3] : '';
					$agentannounce = addslashes($agentannounce);
					$maxwait       = isset($maxwait[4]) ? $maxwait[4] : '';
					$maxwait       = addslashes($maxwait);
					$descr         = isset($descr) ? addslashes($descr) : '';

					// get joinannounce from Playback command
					//
					$sql = "SELECT args FROM extensions WHERE extension = '$account' AND context = 'ext-queues' and application = 'Playback'";
					list($args) = $db->getRow($sql);
					$joinannounce = isset($args) && $args !== NULL ? addslashes($args) : ''; 
					

					// get password from AddQueueMember command
					//
					$sql = "SELECT args FROM extensions WHERE extension = '$account*' AND context = 'ext-queues'";
					list($args) = $db->getRow($sql);
					$password_arr = explode(',',$args); //in table like agent-add,account,password
					$password = isset($password_arr[2]) ? $password_arr[2] : '';
					$password = addslashes($password);

					// get the failover destination (desc=jump)
					//
					$sql = "SELECT args FROM extensions WHERE extension = '".$account."' AND descr = 'jump' AND context = 'ext-queues'";
					list($args) = $db->getRow($sql);
					$dest = isset($args) ? addslashes($args) : '';

					// insert cwignore new to 2.4
					//
					$cwignore = '0';
					$account = addslashes($account);

					// Got everything we need for this id (account) so insert it into the queues_config table
					$sql = 
					"INSERT INTO queues_config (extension, descr, grppre, alertinfo, joinannounce, ringing, agentannounce, maxwait, password, ivr_id, dest, cwignore)
         	VALUES 
					('$account', '$descr', '$grppre', '$alertinfo', '$joinannounce', '$rtone', '$agentannounce', '$maxwait', '$password', '$ivr_id', '$dest', '$cwignore')";
					$results = $db->query($sql);
					if (DB::IsError($results)) {
						outn(sprintf(_("ERROR: inserting data for row %s: %s.."),$account,$results->getMessage()));
						$return_code = false;
					}
				}
			}
			if ($return_code) {
				out(_("OK"));
			} else {
				out(_("ERROR were encountered"));
			}
		}
	} else {
		out(_("OK"));
	}
	// Finished migrating to queues_config

	// Now if all went well, we will remove the old queues table and entries in the extensions table
	//
	if ($return_code) {
		outn(_("Dropping old queues table.."));
		$sql = "DROP TABLE IF EXISTS queues";
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			out(sprintf(_("WARNING FAILED %s"),$results->getMessage()));
		} else {
			out(_("OK"));
		}

		outn(_("removing queues data extensions table.."));
		$sql = "DELETE FROM extensions WHERE context = 'ext-queues'";
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			out(sprintf(_("WARNING FAILED %s"),$results->getMessage()));
		} else {
			out(_("OK"));
		}
	} else {
		return $return_code;
	}

	// Version 2.5 upgrade
	outn(_("checking for qregex field.."));
	$sql = "SELECT `qregex` FROM queues_config";
	$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($check)) {
		// add new field
		$sql = "ALTER TABLE queues_config ADD `qregex` VARCHAR( 255 ) NULL ;";
		$result = $db->query($sql);
		if(DB::IsError($result)) {
			die_freepbx($result->getDebugInfo());
		}
		out(_("OK"));
	} else {
		out(_("already exists"));
	}

// Version 2.5 migrate to recording ids
// Note: we purposely did not chnage the inital creation of the
//       recording ids as it is safer with all the complex
//       migration code to simply stick with what works and
//       then convert it here even if new.
//
outn(_("Checking if recordings need migration.."));
$sql = "SELECT agentannounce_id FROM queues_config";
$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($check)) {
	//  Add recording_id field
	//
	out(_("migrating"));
	outn(_("adding agentannounce_id field.."));
  $sql = "ALTER TABLE queues_config ADD agentannounce_id INTEGER";
  $result = $db->query($sql);
  if(DB::IsError($result)) {
		out(_("fatal error"));
		die_freepbx($result->getDebugInfo()); 
	} else {
		out(_("ok"));
	}
	outn(_("adding joinannounce_id field.."));
  $sql = "ALTER TABLE queues_config ADD joinannounce_id INTEGER";
  $result = $db->query($sql);
  if(DB::IsError($result)) {
		out(_("fatal error"));
		die_freepbx($result->getDebugInfo()); 
	} else {
		out(_("ok"));
	}

	// Get all the valudes and replace them with recording_id
	//
	outn(_("migrate agentannounce to ids.."));
  $sql = "SELECT `extension`, `agentannounce` FROM `queues_config`";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($results)) {
		out(_("fatal error"));
		die_freepbx($results->getDebugInfo());	
	}
	$migrate_arr = array();
	$count = 0;
	foreach ($results as $row) {
		if (trim($row['agentannounce']) != '') {
			$rec_id = recordings_get_or_create_id($row['agentannounce'], 'queues');
			$migrate_arr[] = array($rec_id, $row['extension']);
			$count++;
		}
	}
	if ($count) {
		$compiled = $db->prepare('UPDATE `queues_config` SET `agentannounce_id` = ? WHERE `extension` = ?');
		$result = $db->executeMultiple($compiled,$migrate_arr);
		if(DB::IsError($result)) {
			out(_("fatal error"));
			die_freepbx($result->getDebugInfo());	
		}
	}
	out(sprintf(_("migrated %s entries"),$count));

	outn(_("migrate joinannounce to ids.."));
  $sql = "SELECT `extension`, `joinannounce` FROM `queues_config`";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($results)) {
		out(_("fatal error"));
		die_freepbx($results->getDebugInfo());	
	}
	$migrate_arr = array();
	$count = 0;
	foreach ($results as $row) {
		if (trim($row['joinannounce']) != '') {
			$rec_id = recordings_get_or_create_id($row['joinannounce'], 'queues');
			$migrate_arr[] = array($rec_id, $row['extension']);
			$count++;
		}
	}
	if ($count) {
		$compiled = $db->prepare('UPDATE `queues_config` SET `joinannounce_id` = ? WHERE `extension` = ?');
		$result = $db->executeMultiple($compiled,$migrate_arr);
		if(DB::IsError($result)) {
			out(_("fatal error"));
			die_freepbx($result->getDebugInfo());	
		}
	}
	out(sprintf(_("migrated %s entries"),$count));

	// Now remove the old recording field replaced by new id field
	//
	outn(_("dropping agentannounce field.."));
  // sqlite doesn't support drop syntax, but since we already CREATE'd the table properly, these don't need to be executed anyway
  if($amp_conf["AMPDBENGINE"] != "sqlite3")  {
	  $sql = "ALTER TABLE `queues_config` DROP `agentannounce`";
 	 $result = $db->query($sql);
	  if(DB::IsError($result)) { 
			out(_("no agentannounce field???"));
		} else {
			out(_("ok"));
		}
		outn(_("dropping joinannounce field.."));
	  $sql = "ALTER TABLE `queues_config` DROP `joinannounce`";
	  $result = $db->query($sql);
	  if(DB::IsError($result)) { 
			out(_("no joinannounce field???"));
		} else {
			out(_("ok"));
		}
	} else {
		out(_("already migrated"));
	}
  }

outn(_("checking for queuewait field.."));
$sql = "SELECT `queuewait` FROM queues_config";
$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($check)) {
	// add new field
	$sql = "ALTER TABLE queues_config ADD `queuewait` TINYINT( 1 ) DEFAULT 0";
	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die_freepbx($result->getDebugInfo());
	}
	out(_("OK"));
} else {
	out(_("already exists"));
}

outn(_("checking for use_queue_context field.."));
$sql = "SELECT `use_queue_context` FROM queues_config";
$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($check)) {
	// add new field
	$sql = "ALTER TABLE queues_config ADD `use_queue_context` TINYINT( 1 ) DEFAULT 0";
	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die_freepbx($result->getDebugInfo());
	}
	out(_("OK"));
} else {
	out(_("already exists"));
}

outn(_("checking for togglehint field.."));
$sql = "SELECT `togglehint` FROM queues_config";
$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if(DB::IsError($check)) {
	// add new field
	$sql = "ALTER TABLE queues_config ADD `togglehint` TINYINT( 1 ) DEFAULT 0";
	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die_freepbx($result->getDebugInfo());
	}
	out(_("OK"));
} else {
	out(_("already exists"));
}

?>
