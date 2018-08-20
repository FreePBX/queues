<?php
namespace FreePBX\modules\Queues;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
  public function runRestore($jobid){
    $configs = reset($this->getConfigs());
    $this->FreePBX->Queues->loadConfigs($configs['configs'])
        ->loadDetails($configs['details']);
  }
  

  public function processLegacy($pdo, $data, $tables, $unknownTables, $tmpfiledir){
    $tables = array_flip($tables+$unknownTables);
    if(!isset(tables['queues'])){
      return $this;
    }
    $bmo = $this->FreePBX->Queues;
    $bmo->setDatabase($pdo);
    $configs = [
      'configs' => $bmo->dumpConfigs(),
      'details' => $bmo->dumpDetails(),
    ];
    $bmo->resetDatabase();
    $configs = reset($configs);
    $bmo->loadConfigs($configs['configs'])
        ->loadDetails($configs['details']);
    return $this;
  } 
}