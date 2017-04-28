<?php /* $Id$ */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$request = $_REQUEST;
//used for switch on config.php
$dispnum = 'queues';

$heading = _("Queues");
//get unique queues
$queues = queues_list();
$view = isset($request['view'])?$request["view"]:'';
$usagehtml = '';
switch($view){
	case "form":
		if($request['extdisplay']){
			$heading .= _(" Edit: ");
			$heading .= $request['extdisplay'];
			$usage_list = framework_display_destination_usage(queues_getdest($extdisplay));
			if(!empty($usage_list)){
				$usagehtml = <<< HTML
<div class="panel panel-default fpbx-usageinfo">
	<div class="panel-heading">
		$usage_list[text]
	</div>
	<div class="panel-body">
		$usage_list[tooltip]
	</div>
</div>

HTML;
			}
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
	<?php echo $usagehtml?>
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display no-border">
					<?php echo $content ?>
				</div>
			</div>
		</div>
	</div>
</div>
