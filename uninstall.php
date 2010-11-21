<?php

global $db;

// Don't bother uninstalling feature codes, now module_uninstall does it

sql('DROP TABLE IF EXISTS queues_details');
sql('DROP TABLE IF EXISTS queues_config');

?>
