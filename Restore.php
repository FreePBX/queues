<?php
namespace FreePBX\modules\Queues;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
  public function runRestore($jobid){
    $configs = $this->getConfigs();
    $this->FreePBX->Queues->loadConfigs($configs['configs'])
        ->loadDetails($configs['details']);
  }
}