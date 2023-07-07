<?php /* $id:$ */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
function queues_ivr_delete_event($id = '') {
	global $db;
	
	if (!$id) {
		sql('UPDATE queues_config SET ivr_id = ""');
	} else {
		$sql = 'UPDATE queues_config SET ivr_id = "" WHERE ivr_id = ?';
		$ret = $db->query($sql, [$id]);
	}
}

function queues_configprocess_ivr() {
	$action = $_REQUEST['action'] ?? null;
	$display = $_REQUEST['display'] ?? null;
	$id = $_REQUEST['id'] ?? null;

	
	if($display == 'ivr' && $action == 'delete') {
		queues_ivr_delete_event($id);
	}
	
}
?>