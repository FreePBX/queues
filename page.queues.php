<?php /* $Id$ */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$request = $_REQUEST;
//used for switch on config.php
$dispnum = 'queues';

$heading = _("Queues");
//get unique queues
$queues = queues_list();
switch($request["view"]){
	case "form":
		if($request['extdisplay']){
			$heading .= _(" Edit: ");
			$heading .= $request['extdisplay'];
		}else{
			$heading .= _(" Add Queue");
		}
		$content = load_view(__DIR__.'/views/form.php', array('request' => $request, 'amp_conf' => $amp_conf));
	break;
	default:
		$content = load_view(__DIR__.'/views/qgrid.php');
	break;

}

?>
<div class="container-fluid">
	<h1><?php echo $heading ?></h1>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-9">
				<div class="fpbx-container">
					<div class="display full-border">
						<?php echo $content ?>
					</div>
				</div>
			</div>
			<div class="col-sm-3 hidden-xs bootnav <?php echo $fw_popover?'hidden':''?>">
				<div class="list-group">
					<?php echo load_view(__DIR__.'/views/bootnav.php', array('request' => $request));?>
				</div>
			</div>
		</div>
	</div>
</div>


<link type="text/css" src="config.php?display=queues&handler=file&module=queues&file=assets/css/queues.css"></link>
