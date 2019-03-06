<?php
namespace FreePBX\modules\Queues;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$this->addDependency('recordings');
		$this->addDependency('core');
		$this->addConfigs($this->dumpAll());
	}
}