<?php
namespace FreePBX\modules\Queues;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		global $astman;
		if($astman->connected()){
			$astman->database_deltree("QPENALTY");
		}
		$configs = $this->getConfigs();
		$this->importAll($configs);
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables){
		global $astman;
		$this->restoreLegacyAll($pdo);
		if(isset($data['astdb']['QPENALTY'])) {
			$queuePenalty = array();
			foreach($data['astdb']['QPENALTY'] as $key => $value) {
				$queuePenalty[] = [
					'QPENALTY' => [ $key => $value ]
				];
			}
		}
		if($astman->connected()){
			$astman->database_deltree("QPENALTY");
		}
		$this->importAstDB($queuePenalty);
	}
}
