<?php

namespace Drupal\sitefarm_migrate_cascade\Plugin\migrate\source;

use Drupal;
use Drupal\migrate\Row;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowDeleteEvent;

/**
 * Source for importing the pages
 *
 * @MigrateSource(
 *   id = "sitefarm_cascade_blocks"
 * )
 */
class SitefarmCascadeBlocks extends SitefarmCascadeBase {


  public function initializeIterator() {
    return parent::initializeIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {

    $this->cascade_connect();

    $idmap = $row->getIdMap();
    $updating = isset($idmap['destid1']) && (int) $idmap['destid1'] > 0;


    # Grab the node from Cascade
    $data = $this->cascadeMigrate->queryCascadeById($row->getSourceProperty("type"), $row->getSourceProperty("cascade_id"));

    # Check if we have the right content type. if not, skip and delete from queue
    # Also check if the article type is set
    $content_type = $this->cascadeMigrate->getContentType($data->asset->page->contentTypePath);
    if (!in_array($content_type, [
        'standard_page',
        'flex_page'
      ])
    ) {
      $this->deletedTypes[$content_type] = $content_type;
    //  $this->deleteFromQueue(implode(",", $row->getSourceIdValues()), $this->migration->getBaseId());
    //  return FALSE;
    }

    # Set the Body and Alias
    $body = $this->getBlockBody($data->asset);
    $info = $this->getBlockInfo($data->asset);

    # Skip this page if there is no title, body or page alias
    if (empty($body) || empty($info)) {
      //   $this->deleteFromQueue(implode(",", $row->getSourceIdValues()), $this->migration->getBaseId());
      return FALSE;
    }

    $row->setSourceProperty("info", $info);
    $row->setSourceProperty("body", $body);

    $return = parent::prepareRow($row);
    return $return;
  }

  private function getBlockType($asset){
    $type = false;
    # We only define the blocktypes here that we need and can use
    !empty($asset->xhtmlDataDefinitionBlock) ? $type = "xhtmlDataDefinitionBlock" : "";
    !empty($asset->xmlBlock) ? $type = "xmlBlock" : "";
    return $type;
  }

  /**
   * @param $asset
   * @return string
   */
  private function getBlockBody($asset){
    $type = $this->getBlockType($asset);

    if($type == "xhtmlDataDefinitionBlock" || $type == "xmlBlock"){
      return $this->get_body($asset->$type->structuredData);
    }
  }

  /**
   * @param $asset
   * @return mixed
   */
  private function getBlockInfo($asset){
    $type = $this->getBlockType($asset);

    switch($type) {
      case "xhtmlDataDefinitionBlock":
      case "xmlBlock":
        return $asset->$type->name;
        break;
    }
  }

}