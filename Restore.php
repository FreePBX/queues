<?php
namespace FreePBX\modules\Queues;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		global $astman;
		$astman->database_deltree("QPENALTY");
		$configs = $this->getConfigs();
		$this->importAll($configs);
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$this->restoreLegacyAll($pdo);
	}
}
