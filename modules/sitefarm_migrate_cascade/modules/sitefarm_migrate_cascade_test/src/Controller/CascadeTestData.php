<?php

namespace Drupal\sitefarm_migrate_cascade_test\Controller;

use Drupal;
use Drupal\Core\Extension\ModuleHandlerInterface;


class CascadeTestData {

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $module_handler;

  /**
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  function __construct(ModuleHandlerInterface $module_handler){
    $this->module_handler = $module_handler;
  }

  /**
   * @param $type
   * @return mixed
   */
  public function getTestData($type){
    $data = FALSE;
    $path = $this->module_handler->getModule("sitefarm_migrate_cascade_test")->getPath();

    switch($type){
      case "people":
        $data = file_get_contents($path . "/files/peopledata.json");
        break;
      case "page":
        $data = file_get_contents($path . "/files/testpage.json");
        break;
      case "image":
        $data = file_get_contents($path . "/files/testimage.json");
        $data = json_decode($data);
        $data->asset->file->data = base64_decode($data->asset->file->data);
        return $data;
        break;
    }
    $data = json_decode($data);
    return $data;
  }
}