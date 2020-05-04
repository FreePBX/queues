<?php
namespace FreePBX\modules\Queues;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$this->addDependency('recordings');
		$this->addDependency('core');
		$astdbqueue = $this->dumpAstDB('QPENALTY');
		$this->addConfigs(array_merge($this->dumpAll(),["astdb" => $astdbqueue]));
	}
}
