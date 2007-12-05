<?php /* $Id$ */
//Copyright (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.

//used for switch on config.php
$dispnum = 'queues';

isset($_REQUEST['action'])?$action = $_REQUEST['action']:$action='';
//the extension we are currently displaying
isset($_REQUEST['extdisplay'])?$extdisplay=$_REQUEST['extdisplay']:$extdisplay='';
isset($_REQUEST['account'])?$account = $_REQUEST['account']:$account='';
isset($_REQUEST['name'])?$name = $_REQUEST['name']:$name='';
isset($_REQUEST['password'])?$password = $_REQUEST['password']:$password='';
isset($_REQUEST['agentannounce'])?$agentannounce = $_REQUEST['agentannounce']:$agentannounce='';
isset($_REQUEST['prefix'])?$prefix = $_REQUEST['prefix']:$prefix='';
isset($_REQUEST['alertinfo'])?$alertinfo = $_REQUEST['alertinfo']:$alertinfo='';
isset($_REQUEST['joinannounce'])?$joinannounce = $_REQUEST['joinannounce']:$joinannounce='';
$maxwait = isset($_REQUEST['maxwait'])?$_REQUEST['maxwait']:'';
$cwignore = isset($_REQUEST['cwignore'])?$_REQUEST['cwignore']:'0';

if (isset($_REQUEST['goto0']) && isset($_REQUEST[$_REQUEST['goto0']."0"])) {
	$goto = $_REQUEST[$_REQUEST['goto0']."0"];
} else {
	$goto = '';
}
if (isset($_REQUEST["members"])) {
	$members = explode("\n",$_REQUEST["members"]);

	if (!$members) {
		$members = null;
	}
	
	foreach (array_keys($members) as $key) {
		//trim it
		$members[$key] = trim($members[$key]);

		// check if an agent (starts with a or A)

		if (strtoupper(substr($members[$key],0,1)) == "A") {
			// remove invalid chars
			$members[$key] = "A".preg_replace("/[^0-9#\,*]/", "", $members[$key]);
			$agent = 1;
		} else {
			// remove invalid chars
			$members[$key] = preg_replace("/[^0-9#\,*]/", "", $members[$key]);
			$agent = 0;
		}

		$penalty_pos = strrpos($members[$key], ",");
		if ( $penalty_pos === false ) {
				$penalty_val = 0;
		} else {
				$penalty_val = substr($members[$key], $penalty_pos+1); // get penalty
				$members[$key] = substr($members[$key],0,$penalty_pos); // clean up ext
				$members[$key] = preg_replace("/[^0-9#*]/", "", $members[$key]); //clean out other ,'s
				$penalty_val = preg_replace("/[^0-9*]/", "", $penalty_val); // get rid of #'s if there
				$penalty_val = ($penalty_val == "") ? 0 : $penalty_val;
		}

		// remove blanks // prefix with the channel
		if (empty($members[$key]))  
			unset($members[$key]);
		elseif ($agent) {
			$members[$key] = "Agent/".ltrim($members[$key],"aA").",".$penalty_val;
		} else {
			$members[$key] = "Local/".$members[$key]."@from-internal/n,".$penalty_val;
		}
	}
	
	// check for duplicates, and re-sequence
	// $members = array_values(array_unique($members));
}

// do if we are submitting a form
if(isset($_POST['action'])){
	//check if the extension is within range for this user
	if (isset($account) && !checkRange($account)){
		echo "<script>javascript:alert('"._("Warning! Extension")." $account "._("is not allowed for your account.")."');</script>";
	} else {
		
		//if submitting form, update database
		switch ($action) {
			case "add":
				$conflict_url = array();
				$usage_arr = framework_check_extension_usage($account);
				if (!empty($usage_arr)) {
					$conflict_url = framework_display_extension_usage_alert($usage_arr);
				} else {
					queues_add($account,$name,$password,$prefix,$goto,$agentannounce,$members,$joinannounce,$maxwait,$alertinfo,$cwignore);
					needreload();
					redirect_standard();
				}
			break;
			case "delete":
				queues_del($account);
				needreload();
				redirect_standard();
			break;
			case "edit":  //just delete and re-add
				queues_del($account);
				queues_add($account,$name,$password,$prefix,$goto,$agentannounce,$members,$joinannounce,$maxwait,$alertinfo,$cwignore);
				needreload();
				redirect_standard('extdisplay');
			break;
		}
	}
}

//get unique queues
$queues = queues_list();
	
?>
</div>

<div class="rnav"><ul>
    <li><a id="<?php echo ($extdisplay=='' ? 'current':'') ?>" href="config.php?display=<?php echo urlencode($dispnum)?>"><?php echo _("Add Queue")?></a></li>
<?php
if (isset($queues)) {
	foreach ($queues as $queue) {
		echo "<li><a id=\"".($extdisplay==$queue[0] ? 'current':'')."\" href=\"config.php?display=".urlencode($dispnum)."&extdisplay=".urlencode($queue[0])."\">{$queue[0]}:{$queue[1]}</a></li>";
	}
}
?>
</ul>
</div>

<div class="content">
<?php
if ($action == 'delete') {
	echo '<br><h3>'._("Queue").' '.$account.' '._("deleted").'!</h3><br><br><br><br><br><br><br><br>';
} else {
	$member = array();
	//get members in this queue
	$thisQ = queues_get($extdisplay);
	//create variables
	extract($thisQ);
	
	$delButton = "
				<form name=delete action=\"{$_SERVER['PHP_SELF']}\" method=POST>
					<input type=\"hidden\" name=\"display\" value=\"{$dispnum}\">
					<input type=\"hidden\" name=\"account\" value=\"{$extdisplay}\">
					<input type=\"hidden\" name=\"action\" value=\"delete\">
					<input type=submit value=\""._("Delete Queue")."\">
				</form>";
?>

<?php if (!empty($conflict_url)) {
      	echo "<h5>"._("Conflicting Extensions")."</h5>";
      	echo implode('<br .>',$conflict_url);
      }
?>
<?php if ($extdisplay != '') { ?>
	<h2><?php echo _("Queue:")." ". $extdisplay; ?></h2>
<?php } else { ?>
	<h2><?php echo _("Add Queue"); ?></h2>
<?php } ?>

<?php		if ($extdisplay != '') { 
					echo $delButton;
					$usage_list = framework_display_destination_usage(queues_getdest($extdisplay));
					if (!empty($usage_list)) {
?>
						<a href="#" class="info"><?php echo $usage_list['text']?>:<span><?php echo $usage_list['tooltip']?></span></a>
<?php
					}
				} 
?>
	<form autocomplete="off" name="editQ" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
	<input type="hidden" name="display" value="<?php echo $dispnum?>">
	<input type="hidden" name="action" value="<?php echo (($extdisplay != '') ? 'edit' : 'add') ?>">
	<table>
	<tr><td colspan="2"><h5><?php echo ($extdisplay ? _("Edit Queue") : _("Add Queue")) ?><hr></h5></td></tr>
	<tr>
<?php		if ($extdisplay != ''){ ?>
		<input type="hidden" name="account" value="<?php echo $extdisplay; ?>">
<?php		} else { ?>
		<td><a href="#" class="info"><?php echo _("Queue Number:")?><span><?php echo _("Use this number to dial into the queue, or transfer callers to this number to put them into the queue.<br><br>Agents will dial this queue number plus * to log onto the queue, and this queue number plus ** to log out of the queue.<br><br>For example, if the queue number is 123:<br><br><b>123* = log in<br>123** = log out</b>")?></span></a></td>
		<td><input type="text" name="account" value=""></td>
<?php		} ?>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Queue Name:")?><span><?php echo _("Give this queue a brief name to help you identify it.")?></span></a></td>
		<td><input type="text" name="name" value="<?php echo (isset($name) ? $name : ''); ?>"></td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Queue Password:")?><span><?php echo _("You can require agents to enter a password before they can log in to this queue.<br><br>This setting is optional.")?></span></a></td>
		<td><input type="text" name="password" value="<?php echo (isset($password) ? $password : ''); ?>"></td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("CID Name Prefix:")?><span><?php echo _("You can optionally prefix the Caller ID name of callers to the queue. ie: If you prefix with \"Sales:\", a call from John Doe would display as \"Sales:John Doe\" on the extensions that ring.")?></span></a></td>
		<td><input size="4" type="text" name="prefix" value="<?php echo (isset($prefix) ? $prefix : ''); ?>"></td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Alert Info")?><span><?php echo _('ALERT_INFO can be used for distinctive ring with SIP devices.')?></span></a>:</td>
		<td><input type="text" name="alertinfo" size="30" value="<?php echo (isset($alertinfo)?$alertinfo:'') ?>"></td>
	</tr>

	<tr>
		<td valign="top"><a href="#" class="info"><?php echo _("Static Agents") ?>:<span><br><?php echo _("Static agents are extensions that are assumed to always be on the queue.  Static agents do not need to 'log in' to the queue, and cannot 'log out' of the queue.<br><br>List extensions to ring, one per line.<br><br>You can include an extension on a remote system, or an external number (Outbound Routing must contain a valid route for external numbers).<br><br>You can list agents defined in agents.conf by preceding the agent number with A, so agent 4002 would be listed as A4002. This is experimental and not supported. There are known issues, such as the inability for an agents.conf agent to do subsequent transfers to voicemail<br><br>In all cases, you can put a \",\" after the agent followed by a penalty value. Use penalties at your own risk, they are very broken in asterisk.") ?><br><br></span></a></td>
		<td valign="top">
			<textarea id="members" cols="15" rows="<?php  $rows = count($member)+1; echo (($rows < 5) ? 5 : (($rows > 20) ? 20 : $rows) ); ?>" name="members"><?php foreach ($member as $mem) { $premem = ""; if (substr($mem,0,5) == "Agent") {$premem = "A";}; $mem = $premem.rtrim(ltrim(strstr($mem,"/"),"/"),"@from-internal");echo substr($mem,0,(strpos($mem,"@")!==false?strpos($mem,"@"):strpos($mem,","))).substr($mem,strrpos($mem, ","))."\n"; }?></textarea>
		</td>
	</tr>

	<tr>
		<td>
		<a href=# class="info"><?php echo _("Extension Quick Pick")?>
			<span>
				<?php echo _("Choose an extension to append to the end of the static agents list above.")?>
			</span>
		</a>
		</td>
		<td>
			<select onChange="insertExten();" id="insexten">
				<option value=""><?php echo _("(pick extension)")?></option>
	<?php
				$results = core_users_list();
				foreach ($results as $result) {
					echo "<option value='".$result[0]."'>".$result[0]." (".$result[1].")</option>\n";
				}
	?>
			</select>
		</td>
	</tr>

	<tr><td colspan="2"><br><h5><?php echo _("Queue Options")?><hr></h5></td></tr>
<?php if(function_exists('recordings_list')) { //only include if recordings is enabled?>
	<tr>
		<td><a href="#" class="info"><?php echo _("Agent Announcement:")?><span><?php echo _("Announcement played to the Agent prior to bridging in the caller <br><br> Example: \"the Following call is from the Sales Queue\" or \"This call is from the Technical Support Queue\".<br><br>To add additional recordings please use the \"System Recordings\" MENU to the left")?></span></a></td>
		<td>
			<select name="agentannounce"/>
			<?php
				$tresults = recordings_list();
				$default = (isset($agentannounce) ? $agentannounce : 'None');

				$compound_recordings = false;
				$is_error = false;

				echo '<option value="None">'._("None").'</option>';
				if (isset($tresults[0])) {
					foreach ($tresults as $tresult) {
						if (strpos($tresult[2],"&") === false) {
							echo '<option value="'.$tresult[2].'"'.($tresult[2] == $default ? ' SELECTED' : '').'>'.$tresult[1]."</option>\n";
						} else {
							$compound_recordings = true;
							echo '<option style="color:red" value="'.$tresult[2].'"'.($tresult[2] == $default ? ' SELECTED' : '').'>'.$tresult[1]." (**)</option>\n";
							if ($tresult[2] == $default) {
								$is_error = true;
							}
						}
					}
				}
			?>		
			</select>		
			<?php
			if ($compound_recordings) {
			?>
				<small><a style="color:red"  href="#" class="info"><?php echo ($is_error ? _("(**) ERRORS") : _("(**) Warning Potential Errors"))?>
					<span> 
						<?php 
							if ($is_error) {
								echo _("ERROR: You have configured a compound annoucement in your selection. These are annoucements composed of multiple concatenated sound files. The Queue system is not able to play such sound files. Your announcement file will be concatentated and only the first sound file will be played.");
							} else {
								echo _("There are compound annoucements in your selection. These are annoucements composed of multiple concatenated sound files. The Queue system is not able to play such sound files. If you choose one of these annoucements your announcement will be concatentated and only the first sound file will be played.");
							}
						?>
					</span></small>
				</a>
			<?php
			}
			?>
		</td>
	</tr>

<?php } else { ?>
	<tr>
		<td><a href="#" class="info"><?php echo _("Agent Announcement:")?><span><?php echo _("Announcement played to the Agent prior to bridging in the caller <br><br> Example: \"the Following call is from the Sales Queue\" or \"This call is from the Technical Support Queue\".<br><br>You must install and enable the \"Systems Recordings\" Module to edit this option")?></span></a></td>
		<td>
			<?php
				$default = (isset($agentannounce) ? $agentannounce : '');
			?>
			<input type="hidden" name="agentannounce" value="<?php echo $default; ?>"><?php echo ($default != '' ? $default : 'None'); ?>
		</td>
	</tr>
<?php } ?>

<?php if(function_exists('music_list')) { //only include if music module is enabled?>
	<tr>
		<td><a href="#" class="info"><?php echo _("Music on Hold Class:")?><span><?php echo _("Music (or Commercial) played to the caller while they wait in line for an available agent. Choose \"inherit\" if you want the MoH class to be what is currently selected, such as by the inbound route.<br><br>  This music is defined in the \"Music on Hold\" Menu to the left.")?></span></a></td>
		<td>
			<select name="music"/>
			<?php
				$tresults = music_list($amp_conf['ASTVARLIBDIR']."/mohmp3");
				array_unshift($tresults,'inherit');
				$default = (isset($music) ? $music : 'inherit');
				if (isset($tresults)) {
					foreach ($tresults as $tresult) {
						$searchvalue="$tresult";	
						echo '<option value="'.$tresult.'" '.($searchvalue == $default ? 'SELECTED' : '').'>'.$tresult;
					}
				}
			?>		
			</select>		
		</td>
	</tr>
<?php } ?>

	<tr>
		<td><a href="#" class="info"><?php echo _("Ringing Instead of MoH:")?><span><?php echo _("Enabling this option make callers hear a ringing tone instead of Music on Hold.<br/>If this option is enabled, settings of the previous drop down are ignored.")?></span></a></td>
		<td>
			<input name="rtone" type="checkbox" value="1" <?php echo (isset($rtone) && $rtone == 1 ? 'checked' : ''); ?> />
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Max Wait Time:")?><span><?php echo _("The maximum number of seconds a caller can wait in a queue before being pulled out.  (0 for unlimited).")?></span></a></td>
		<td>
			<select name="maxwait"/>
			<?php
				$default = (isset($maxwait) ? $maxwait : 0);
				for ($i=0; $i < 60; $i+=10) {
					if ($i == 0)
						echo '<option value="">'._("Unlimited").'</option>';
					else
						echo '<option value="'.$i.'"'.($i == $maxwait ? ' SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
				for ($i=60; $i < 300; $i+=30) {
					echo '<option value="'.$i.'"'.($i == $maxwait ? ' SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
				for ($i=300; $i < 1200; $i+=60) {
					echo '<option value="'.$i.'"'.($i == $maxwait ? ' SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
				for ($i=1200; $i <= 3600; $i+=300) {
					echo '<option value="'.$i.'"'.($i == $maxwait ? ' SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Max Callers:")?><span><?php echo _("Maximum number of people waiting in the queue (0 for unlimited)")?></span></a></td>
		<td>
			<select name="maxlen"/>
			<?php 
				$default = (isset($maxlen) ? $maxlen : 0);
				for ($i=0; $i <= 50; $i++) {
					echo '<option value="'.$i.'" '.($i == $default ? 'SELECTED' : '').'>'.$i.'</option>';
				}
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Join Empty:")?><span><?php echo _("If you wish to allow callers to join queues that currently have no agents, set this to yes")?></span></a></td>
		<td>
			<select name="joinempty"/>
			<?php
				$default = (isset($joinempty) ? $joinempty : 'yes');
				$items = array('yes'=>_("Yes"),'no'=>_("No"));
				foreach ($items as $item=>$val) {
					echo '<option value="'.$item.'" '. ($default == $item ? 'SELECTED' : '').'>'.$val;
				}
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Leave When Empty:")?><span><?php echo _("If you wish to remove callers from the queue if there are no agents present, set this to yes")?></span></a></td>
		<td>
			<select name="leavewhenempty"/>
			<?php
				$default = (isset($leavewhenempty) ? $leavewhenempty : 'no');
				$items = array('yes'=>_("Yes"),'no'=>_("No"));
				foreach ($items as $item=>$val) {
					echo '<option value="'.$item.'" '. ($default == $item ? 'SELECTED' : '').'>'.$val;
				}
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td>
			<a href="#" class="info"><?php echo _("Ring Strategy:")?>
				<span>
					<b><?php echo _("ringall")?></b>:  <?php echo _("ring all available agents until one answers (default)")?><br>
					<b><?php echo _("roundrobin")?></b>: <?php echo _("take turns ringing each available agent")?><br>
					<b><?php echo _("leastrecent")?></b>: <?php echo _("ring agent which was least recently called by this queue")?><br>
					<b><?php echo _("fewestcalls")?></b>: <?php echo _("ring the agent with fewest completed calls from this queue")?><br>
					<b><?php echo _("random")?></b>: <?php echo _("ring random agent")?><br>
					<b><?php echo _("rrmemory")?></b>: <?php echo _("round robin with memory, remember where we left off last ring pass")?><br>
				</span>
			</a>
		</td>
		<td>
			<select name="strategy"/>
			<?php
				$default = (isset($strategy) ? $strategy : 'ringall');
				$items = array('ringall','roundrobin','leastrecent','fewestcalls','random','rrmemory');
				foreach ($items as $item) {
					echo '<option value="'.$item.'" '.($default == $item ? 'SELECTED' : '').'>'._($item);
				}
			?>		
			</select>
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Agent Timeout:")?><span><?php echo _("The number of seconds an agent's phone can ring before we consider it a timeout. Unlimited or other timeout values may still be limited by system ringtime or individual extension defaults.")?></span></a></td>
		<td>
			<select name="timeout"/>
			<?php
				$default = (isset($timeout) ? $timeout : 15);
				echo '<option value="0" '.(0 == $default ? 'SELECTED' : '').'>'."Unlimited".'</option>';
				for ($i=1; $i <= 60; $i++) {
					echo '<option value="'.$i.'" '.($i == $default ? 'SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("retry:")?><span><?php echo _("The number of seconds we wait before trying all the phones again")?></span></a></td>
		<td>
			<select name="retry"/>
			<?php
				$default = (isset($retry) ? $retry : 5);
				for ($i=0; $i <= 20; $i++) {
					echo '<option value="'.$i.'" '.($i == $default ? 'SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Wrap-Up-Time:")?><span><?php echo _("After a successful call, how many seconds to wait before sending a potentially free agent another call (default is 0, or no delay)")?></span></a></td>
		<td>
			<select name="wrapuptime"/>
			<?php
				$default = (isset($wrapuptime) ? $wrapuptime : 0);
				for ($i=0; $i <= 60; $i++) {
					echo '<option value="'.$i.'" '.($i == $default ? 'SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Call Recording:")?><span><?php echo _("Incoming calls to agents can be recorded. (saved to /var/spool/asterisk/monitor)")?></span></a></td>
		<td>
			<select name="monitor-format"/>
			<?php
				$default = (empty($thisQ['monitor-format']) ? "no" : $thisQ['monitor-format']);  
				echo '<option value="wav49" '.($default == "wav49" ? 'SELECTED' : '').'>'._("wav49").'</option>';
				echo '<option value="wav" '.($default == "wav" ? 'SELECTED' : '').'>'._("wav").'</option>';
				echo '<option value="gsm" '.($default == "gsm" ? 'SELECTED' : '').'>'._("gsm").'</option>';
				echo '<option value="" '.($default == "no" ? 'SELECTED' : '').'>'._("No").'</option>';
			?>	
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Event When Called:")?><span><?php echo _("When this option is set to YES, the following manager events will be generated: AgentCalled, AgentDump, AgentConnect and AgentComplete.")?></span></a></td>
		<td>
			<select name="eventwhencalled"/>
			<?php
				$default = (isset($eventwhencalled) ? $eventwhencalled : 'no');
				$items = array('yes'=>_("Yes"),'no'=>_("No"));
				foreach ($items as $item=>$val) {
					echo '<option value="'.$item.'" '. ($default == $item ? 'SELECTED' : '').'>'.$val;
				}
			?>
			</select>
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Member Status:")?><span><?php echo _("When if this is option is set to YES, the following manager event will be generated: QueueMemberStatus")?></span></a></td>
		<td>
			<select name="eventmemberstatus"/>
			<?php
				$default = (isset($eventmemberstatus) ? $eventmemberstatus : 'no');
				$items = array('yes'=>_("Yes"),'no'=>_("No"));
				foreach ($items as $item=>$val) {
					echo '<option value="'.$item.'" '. ($default == $item ? 'SELECTED' : '').'>'.$val;
				}
			?>
			</select>
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Skip Busy Agents:")?><span><?php echo _("When set to Yes, agents who are on an occupied phone will be skipped as if the line were returning busy. This means that Call Waiting or multi-line phones will not be presented with the call and in the various hunt style ring strategies, the next agent will be attempted.")?></span></a></td>
		<td>
			<select name="cwignore"/>
			<?php
				$default = (isset($cwignore) ? $cwignore : 'no');
				$items = array('1'=>_("Yes"),'0'=>_("No"));
				foreach ($items as $item=>$val) {
					echo '<option value="'.$item.'" '. ($default == $item ? 'SELECTED' : '').'>'.$val;
				}
			?>
			</select>
		</td>
	</tr>

	<tr><td colspan="2"><br><h5><?php echo _("Caller Position Announcements")?><hr></h5></td></tr>
	<tr>
		<td><a href="#" class="info"><?php echo _("Frequency:")?><span><?php echo _("How often to announce queue position and estimated holdtime (0 to Disable Announcements).")?></span></a></td>
		<td>
			<select name="announcefreq"/>
			<?php
				$default = (isset($thisQ['announce-frequency']) ? $thisQ['announce-frequency'] : 0);
				for ($i=0; $i <= 1200; $i+=15) {
					echo '<option value="'.$i.'" '.($i == $default ? 'SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Announce Position:")?><span><?php echo _("Announce position of caller in the queue?")?></span></a></td>
		<td>
			<select name="announceposition"/>
			<?php //setting to "no" will override sounds queue-youarenext, queue-thereare, queue-callswaitingÊ 
				$default = (isset($thisQ['announce-position']) ? $thisQ['announce-position'] : "no");  
					echo '<option value=yes '.($default == "yes" ? 'SELECTED' : '').'>'._("Yes").'</option>';
					echo '<option value=no '.($default == "no" ? 'SELECTED' : '').'>'._("No").'</option>';
			?>		
			</select>		
		</td>
	</tr>

	<tr>
		<td><a href="#" class="info"><?php echo _("Announce Hold Time:")?><span><?php echo _("Should we include estimated hold time in position announcements?  Either yes, no, or only once; hold time will not be announced if <1 minute")?> </span></a></td>
		<td>
			<select name="announceholdtime">
			<?php
				$default = (isset($thisQ['announce-holdtime']) ? $thisQ['announce-holdtime'] : "no");
				echo '<option value=yes '.($default == "yes" ? 'SELECTED' : '').'>'._("Yes").'</option>';
				echo '<option value=no '.($default == "no" ? 'SELECTED' : '').'>'._("No").'</option>';
				echo '<option value=once '.($default == "once" ? 'SELECTED' : '').'>'._("Once").'</option>';
			?>		
			</select>		
		</td>
	</tr>

	<tr><td colspan="2"><br><h5><?php echo _("Periodic Announcements")?><hr></h5></td></tr>

	
<?php if(function_exists('ivr_list')) { //only include if IVR module is enabled ?>
	<tr>
		<td><a href="#" class="info"><?php echo _("IVR Break Out Menu:")?><span> <?php echo _("You can optionally present an existing IVR as a 'break out' menu.<br><br>This IVR must only contain single-digit 'dialed options'. The Recording set for the IVR will be played at intervals specified in 'Repeat Frequency', below.")?></span></a></td>
		<td>
			<select name="announcemenu">
			<?php // setting this will set the context= option
			$default = (isset($announcemenu) ? $announcemenu : "none");
			
			echo '<option value=none '.($default == "none" ? 'SELECTED' : '').'>'._("None").'</option>';
			
			//query for exisiting aa_N contexts
			$unique_aas = ivr_list();		
			
			$compound_recordings = false;
			$is_error = false;
			if (isset($unique_aas)) {
				foreach ($unique_aas as $unique_aa) {
					$menu_id = $unique_aa['ivr_id'];
					$menu_name = $unique_aa['displayname'];
					if (strpos($unique_aa['announcement'],"&") === false) {
						echo '<option value="'.$menu_id.'" '.($default == $menu_id ? 'SELECTED' : '').'>'.($menu_name ? $menu_name : _("Menu ID ").$menu_id)."</option>\n";
					} else {
						$compound_recordings = true;
						echo '<option style="color:red" value="'.$menu_id.'" '.($default == $menu_id ? 'SELECTED' : '').'>'.($menu_name ? $menu_name : _("Menu ID ").$menu_id)." (**)</option>\n";
						if ($menu_id == $default) {
							$is_error = true;
						}
					}
				}
			}
			?>
			</select>
			<?php
			if ($compound_recordings) {
			?>
				<small><a style="color:red"  href="#" class="info"><?php echo ($is_error ? _("(**) ERRORS") : _("(**) Warning Potential Errors"))?>
					<span> 
						<?php 
							if ($is_error) {
								echo _("ERROR: You have selected an IVR's that use Announcements created from compound sound files. The Queue is not able to play these announcements. This IVR's recording will be truncated to use only the first sound file. You can correct the problem by selecting a different annoucement for this IVR that is not from a compound sound file. The IVR itself can play such files, but the Queue subsystem can not");
							} else {
								echo _("You have IVR's that use Announcements created from compound sound files. The Queue is not able to play these announcements. If you choose one of these the recording used with be truncated to use only the first sound file. You can choose this IVR now and then correct the problem by selecting a different annoucement for your IVR that is not from a compound sound file. The IVR itself can play such files, but the Queue subsystem can not");
							}
						?>
					</span></small>
				</a>
			<?php
			}
			?>

		</td>
	</tr>
	
	<tr>
		<td><a href="#" class="info"><?php echo _("Repeat Frequency:")?><span><?php echo _("How often to announce a voice menu to the caller (0 to Disable Announcements).")?></span></a></td>
		<td>
			<select name="pannouncefreq"/>
			<?php
				$default = (isset($thisQ['periodic-announce-frequency']) ? $thisQ['periodic-announce-frequency'] : 0);
				for ($i=0; $i <= 1200; $i+=15) {
					echo '<option value="'.$i.'" '.($i == $default ? 'SELECTED' : '').'>'.queues_timeString($i,true).'</option>';
				}
			?>		
			</select>		
		</td>
	</tr>

<?php } else {
	echo "<input type=\"hidden\" name=\"announcemenu\" value=\"none\">";
}

if(function_exists('recordings_list')) { //only include if recordings is enabled ?>
	<tr>
		<td><a href="#" class="info"><?php echo _("Join Announcement:")?><span><?php echo _("Announcement played to callers once prior to joining the queue.<br><br>To add additional recordings please use the \"System Recordings\" MENU to the left")?></span></a></td>
		<td>
			<select name="joinannounce"/>
			<?php
				$tresults = recordings_list();
				$default = (isset($joinannounce) ? $joinannounce : 'None');
				echo '<option value="None">'._("None");
				if (isset($tresults[0])) {
					foreach ($tresults as $tresult) {
						echo '<option value="'.$tresult[2].'" '.($tresult[2] == $default ? 'SELECTED' : '').'>'.$tresult[1]."</option>\n";;
					}
				}
			?>		
			</select>		
		</td>
	</tr>
<?php } else { ?>
	<tr>
		<td><a href="#" class="info"><?php echo _("Join Announcement:")?><span><?php echo _("Announcement played to callers once prior to joining the queue.<br><br>You must install and enable the \"Systems Recordings\" Module to edit this option")?></span></a></td>
		<td>
			<?php
				$default = (isset($joinannounce) ? $joinannounce : '');
			?>
			<input type="hidden" name="joinannounce" value="<?php echo $default; ?>"><?php echo ($default != '' ? $default : 'None'); ?>
		</td>
	</tr>
<?php } ?>

	<tr><td colspan="2"><br><h5><?php echo _("Fail Over Destination")?><hr></h5></td></tr>
	<?php 
	echo drawselects($goto,0);
	?>
	
	<tr>
		<td colspan="2"><br><h6><input name="Submit" type="button" value="<?php echo _("Submit Changes")?>" onclick="checkQ(editQ);"></h6></td>		
	</tr>
	</table>

<script language="javascript">
<!--

function insertExten() {
	exten = document.getElementById('insexten').value;

	grpList=document.getElementById('members');
	if (grpList.value[ grpList.value.length - 1 ] == "\n") {
		grpList.value = grpList.value + exten + ',0';
	} else {
		grpList.value = grpList.value + '\n' + exten + ',0';
	}

	// reset element
	document.getElementById('insexten').value = '';
}

function checkQ(theForm) {
        $queuename = theForm.name.value;
        var bad = "false";

        var whichitem = 0;
        while (whichitem < theForm.goto0.length) {
                if (theForm.goto0[whichitem].checked) {
                        theForm.goto0.value=theForm.goto0[whichitem].value;
                }
                whichitem++;
        }

				if (!isInteger(theForm.account.value)) {
                <?php echo "alert('"._("Queue Number must not be blank")."')"?>;
                bad="true";
        }

        if ($queuename == "") {
                <?php echo "alert('"._("Queue name must not be blank")."')"?>;
                bad="true";
        } else if (!$queuename.match('^[a-zA-Z][a-zA-Z0-9]+$')) {
                <?php echo "alert('"._("Queue name cannot start with a number, and can only contain letters and numbers")."')"?>;
                bad="true";
        }

        if (bad == "false") {
                theForm.submit();
        }
}

//-->
</script>

	</form>
<?php		
} //end if action == delGRP
?>
