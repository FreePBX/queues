<?php
$queuelist = queues_list();
foreach($queuelist as $q){
	$qrows .= '<tr><td>'.$q[0].'</td><td>'.$q[1].'</td><td><a href="/admin/config.php?display=queues&view=form&extdisplay='.urlencode($q[0]).'"><i class="fa fa-edit"></i></a>&nbsp;<a href="/admin/config.php?display=queues&action=delete&account='.urlencode($q[0]).'"><i class="fa fa-trash"></i></a></td></tr>';
}
?>

<table class="table table-striped">
<thead>
	<tr>
		<th><?php echo _("Queue")?></th>
		<th><?php echo _("Description")?></th>
		<th><?php echo _("Actions")?></th>
	</tr>	
</thead>
<tbody>
	<?php echo $qrows ?>
</tbody>
</table>