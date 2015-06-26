<?php
$queuelist = queues_list();
$qrows = '';
foreach($queuelist as $q){
	$qrows .= '<tr><td>'.$q[0].'</td><td>'.$q[1].'</td><td><a href="/admin/config.php?display=queues&view=form&extdisplay='.urlencode($q[0]).'"><i class="fa fa-edit"></i></a>&nbsp;<a href="/admin/config.php?display=queues&action=delete&account='.urlencode($q[0]).'"><i class="fa fa-trash"></i></a></td></tr>';
}
?>
<div id="toolbar-all">
	<a href="config.php?display=queues&amp;view=form" class="btn btn-primary"><i class="fa fa-plus"></i> <?php echo _("Add Queue") ?></a>
</div>
<table id="qgrid" data-toolbar="#toolbar-all" data-maintain-selected="true" data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true" data-search="true"class="table table-striped">
<thead>
	<tr>
		<th data-sortable="true"><?php echo _("Queue")?></th>
		<th><?php echo _("Description")?></th>
		<th><?php echo _("Actions")?></th>
	</tr>
</thead>
<tbody>
	<?php echo $qrows ?>
</tbody>
</table>
