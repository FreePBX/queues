<?php

global $db;

$fcc = new featurecode('queues', 'que_toggle');
$fcc->delete();
unset($fcc);

sql('DROP TABLE IF EXISTS queues_details');
sql('DROP TABLE IF EXISTS queues_config');

?>
