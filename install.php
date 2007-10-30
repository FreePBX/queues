<?php

global $db;
global $amp_conf;

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

// Version 2.2.11 change (#1659)
//
$results = $db->query("ALTER TABLE `queues` CHANGE `id` `id` VARCHAR( 45 ) NOT NULL DEFAULT '-1'");
if(DB::IsError($results)) {
	echo $results->getMessage();
	return false;
}

// Version 2.2.13 change (#2277)
//
$results = $db->query("ALTER TABLE `queues` CHANGE `keyword` `keyword` VARCHAR( 30 ) NOT NULL");
if(DB::IsError($results)) {
	echo $results->getMessage();
	return false;
}

// Version 2.2.14 change - bump up priority on Goto because of inserted alert-info
//

$results = $db->query("UPDATE extensions SET priority = '7' WHERE context = 'ext-queues' AND priority = '6' AND application = 'Goto' AND descr = 'jump'");
if(DB::IsError($results)) {
	echo $results->getMessage();
	return false;
}

?>
