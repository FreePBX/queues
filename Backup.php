<?php
namespace FreePBX\modules\Queues;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
  public function runBackup($id,$transaction){
    $configs = [
        'configs' => $this->FreePBX->Queues->dumpConfigs(),
        'details' => $this->FreePBX->Queues->dumpDetails(),
    ];

    $this->addDependency('recordings');
    $this->addDependency('core');
    $this->addConfigs($configs);
  }
}