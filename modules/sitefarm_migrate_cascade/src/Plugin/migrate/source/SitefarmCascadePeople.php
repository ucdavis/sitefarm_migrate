<?php

namespace Drupal\sitefarm_migrate_cascade\Plugin\migrate\source;

use Drupal;
use Drupal\migrate\Row;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowDeleteEvent;


/**
 * Source for importing the people
 *
 * @MigrateSource(
 *   id = "sitefarm_cascade_people"
 * )
 */
class SitefarmCascadePeople extends SitefarmCascadeBase {

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {

    $isTest = $row->getSourceProperty("is_test");

    if (!$isTest) {
      $this->cascade_connect();
    }

    # Grab the node from Cascade
    if (!$isTest) {
      $data = $this->cascadeMigrate->queryCascadeById($row->getSourceProperty("type"), $row->getSourceProperty("cascade_id"));
    }
    else {
      $data = $this->cascadeMigrate->getTestdata("people");
    }

    $data_export = \GuzzleHttp\json_encode($data);
    if (!isset($data->asset->page->contentTypePath) && isset($data->asset->xhtmlDataDefinitionBlock)) {
      $content_type = "person_block";
    }
    else {
      $content_type = $this->cascadeMigrate->getContentType($data->asset->page->contentTypePath);
    }

    # Check if we have the right content type. if not, skip and delete from queue
    if (!$isTest && !in_array($content_type, ['person', 'person_block'])) {
      $this->deleteFromQueue(implode(",", $row->getSourceIdValues()), $this->migration->getBaseId());
      return FALSE;
    }

    if ($content_type == "person_block") {
      $address1 = $this->getAddress1($data->asset->xhtmlDataDefinitionBlock->structuredData);
      $address2 = $this->getAddress2($data->asset->xhtmlDataDefinitionBlock->structuredData);
      $firstname = $this->getItemByIdentifier($data->asset->xhtmlDataDefinitionBlock->structuredData, "first-name");
      $lastname = $this->getItemByIdentifier($data->asset->xhtmlDataDefinitionBlock->structuredData, "last-name");
      $website = $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData, "website", "address");
      $email = $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData, "email", "address");
      $photo = $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData,
        "identity", "image-file", FALSE, "fileId");

      if (!$this->urlHelper->isValid($website)) {
        $website = NULL;
      }

      if ($this->emailValidator->isValid($email)) {
        $email = NULL;
      }

      # No lastname? Don't import.
      if (empty($lastname)) {
        $this->deleteFromQueue(implode(",", $row->getSourceIdValues()), $this->migration->getBaseId());
        return FALSE;
      }

      # Save the picture!
      if (!empty($photo)) {
        $photo = $this->getPhoto($data, $photo, $firstname . "_" . $lastname);
      }

      $row->setSourceProperty("created", $data->asset->xhtmlDataDefinitionBlock->createdDate);
      $row->setSourceProperty("modified", $data->asset->xhtmlDataDefinitionBlock->lastModifiedDate);
      $row->setSourceProperty("status", (string) $data->asset->xhtmlDataDefinitionBlock->shouldBePublished == "true" ? 1 : 0);

      $row->setSourceProperty("photo", empty($photo) ? NULL : $photo);
      $row->setSourceProperty("prefix", $this->getItemByIdentifier($data->asset->xhtmlDataDefinitionBlock->structuredData, "prefix"));
      $row->setSourceProperty("first_name", $firstname);
      $row->setSourceProperty("last_name", $lastname);
      $row->setSourceProperty("bio", $this->getBio($data->asset->xhtmlDataDefinitionBlock->structuredData));
      $row->setSourceProperty("credentials", $this->getItemByIdentifier($data->asset->xhtmlDataDefinitionBlock->structuredData, "credentials"));
      $row->setSourceProperty("unit", $this->getItemByIdentifier($data->asset->xhtmlDataDefinitionBlock->structuredData, "unit"));
      $row->setSourceProperty("position_title", $this->getItemByIdentifier($data->asset->xhtmlDataDefinitionBlock->structuredData, "worktitle"));
      $row->setSourceProperty("email", $email);
      $row->setSourceProperty("website", $website);
      $row->setSourceProperty("phone", $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData, "phone", "address"));
      $row->setSourceProperty("state", $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData, "mail", "state"));
      $row->setSourceProperty("city", $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData, "mail", "city"));
      $row->setSourceProperty("address1", $address1);
      $row->setSourceProperty("address2", $address2);
      $row->setSourceProperty("country", $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData, "mail", "country"));
      $row->setSourceProperty("zipcode", $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData, "mail", "zip"));;
    }
    else {

      $address1 = $this->getAddress1($data->asset->page->structuredData);
      $address2 = $this->getAddress2($data->asset->page->structuredData);
      $firstname = $this->getItemByIdentifier($data->asset->page->structuredData, "first-name");
      $lastname = $this->getItemByIdentifier($data->asset->page->structuredData, "last-name");
      $website = $this->getItemByIdentifiers($data->asset->page->structuredData, "website", "address");
      $email = $this->getItemByIdentifiers($data->asset->page->structuredData, "email", "address");
      $photo = $this->getItemByIdentifiers($data->asset->page->structuredData,
        "identity", "image-file", FALSE, "fileId");

      if (!$this->urlHelper->isValid($website)) {
        $website = NULL;
      }

      if ($this->emailValidator->isValid($email)) {
        $email = NULL;
      }

      # No lastname? Don't import.
      if (empty($lastname)) {
        $this->deleteFromQueue(implode(",", $row->getSourceIdValues()), $this->migration->getBaseId());
        return FALSE;
      }

      # Save the picture!
      if (!empty($photo)) {
        $photo = $this->getPhoto($data, $photo, $firstname . "_" . $lastname);
      }

      $row->setSourceProperty("created", $data->asset->page->createdDate);
      $row->setSourceProperty("modified", $data->asset->page->lastModifiedDate);
      $row->setSourceProperty("status", (string) $data->asset->page->shouldBePublished == "true" ? 1 : 0);

      $row->setSourceProperty("photo", empty($photo) ? NULL : $photo);
      $row->setSourceProperty("prefix", $this->getItemByIdentifier($data->asset->page->structuredData, "prefix"));
      $row->setSourceProperty("first_name", $firstname);
      $row->setSourceProperty("last_name", $lastname);
      $row->setSourceProperty("bio", $this->getBio($data->asset->page->structuredData));
      $row->setSourceProperty("credentials", $this->getItemByIdentifier($data->asset->page->structuredData, "credentials"));
      $row->setSourceProperty("unit", $this->getItemByIdentifier($data->asset->page->structuredData, "unit"));
      $row->setSourceProperty("position_title", $this->getItemByIdentifier($data->asset->page->structuredData, "worktitle"));
      $row->setSourceProperty("email", $email);
      $row->setSourceProperty("website", $website);
      $row->setSourceProperty("phone", $this->getItemByIdentifiers($data->asset->page->structuredData, "phone", "address"));
      $row->setSourceProperty("state", $this->getItemByIdentifiers($data->asset->page->structuredData, "mail", "state"));
      $row->setSourceProperty("city", $this->getItemByIdentifiers($data->asset->page->structuredData, "mail", "city"));
      $row->setSourceProperty("address1", $address1);
      $row->setSourceProperty("address2", $address2);
      $row->setSourceProperty("country", $this->getItemByIdentifiers($data->asset->page->structuredData, "mail", "country"));
      $row->setSourceProperty("zipcode", $this->getItemByIdentifiers($data->asset->page->structuredData, "mail", "zip"));
    }
    $return = parent::prepareRow($row);
    return $return;
  }

  # Runs after a row is saved
  public function onPostRowSave(MigratePostRowSaveEvent $event) {

  }

  # Runs after a row is deleted
  public function onPostRowDelete($event) {

  }

  # Runs after the migration has ended
  public function onPostImport($event) {

  }

  # Grabs the first address line
  protected function getAddress1($data) {
    $address = $this->getItemByIdentifiers($data, "mail", "address");
    $address = explode("\n", $address);
    return $address[0];
  }

  # Grabs the second address line
  protected function getAddress2($data) {
    $address = $this->getItemByIdentifiers($data, "mail", "address");
    $address = explode("\n", $address);
    return isset($address[1]) ? $address[1] : "";
  }

  # Grabs a bio
  protected function getBio($data) {
    $return = $this->getItemByIdentifier($data, "short-bio");
    $return .= $this->getItemByIdentifier($data, "description");
    $return .= $this->getItemByIdentifier($data, "content-box");
    return htmlspecialchars_decode($return);
  }

  # Creates a photo and saves it
  protected function getPhoto($data, $photo, $filename) {
    # Make sure the path exists
    if(!file_exists("public://images/")){
      mkdir("public://images/");
    }
    if(!file_exists("public://images/person")){
      mkdir("public://images/person");
    }

    # Grab the photopath from the data
    if (!isset($data->asset->page->structuredData)) {
      $photoPath = $this->getItemByIdentifiers($data->asset->xhtmlDataDefinitionBlock->structuredData,
        "identity", "image-file", FALSE, "filePath");
    }
    else {
      $photoPath = $this->getItemByIdentifiers($data->asset->page->structuredData,
        "identity", "image-file", FALSE, "filePath");
    }

    # Get the photo extension and set the filename
    $ext = array_pop(explode(".", $photoPath));
    $name = "public://images/person/" . trim(str_replace(" ", "_", $filename)) . "." . $ext;

    # Download the image from Cascade
    $file = $this->cascadeMigrate->queryCascadeById("file", $photo);
    if (empty($file->asset->file->data)) {
      return FALSE;
    }
    $data = $file->asset->file->data;

    # Save the file and return the Drupal File ID
    $dfile = file_save_data($data, $name, FILE_EXISTS_REPLACE);
    return $dfile ? $dfile->get("fid")->value : FALSE;
  }

  # Grab a specific item from the data
  protected function getItemByIdentifier($node, $identifier) {
    if (!isset($node->identifier) && !isset($node->structuredDataNodes)) {
      return "";
    }
    $return = "";

    if (!empty($node->structuredDataNodes->structuredDataNode)) {
      if (is_array($node->structuredDataNodes->structuredDataNode)) {
        foreach ($node->structuredDataNodes->structuredDataNode as $node2) {
          $return .= $this->getItemByIdentifier($node2, $identifier);
        }
      }
      else {
        $return .= $this->getItemByIdentifier($node->structuredDataNodes->structuredDataNode, $identifier);
      }
      return $return;
    }
    switch ($node->identifier) {
      case $identifier:
        if (!empty($node->text)) {
          $return .= $node->text;
        }
        break;
    }
    return $return;
  }

  # Grab a specific item from the data
  protected function getItemByIdentifiers($node, $identifier1, $identifier2, $found = FALSE, $variable = "text") {
    if (!isset($node->identifier) && !isset($node->structuredDataNodes)) {
      return "";
    }
    $return = "";

    switch ($node->identifier) {
      case $identifier1:
        if (!empty($node->structuredDataNodes->structuredDataNode)) {
          if (is_array($node->structuredDataNodes->structuredDataNode)) {
            foreach ($node->structuredDataNodes->structuredDataNode as $node2) {
              $return .= $this->getItemByIdentifiers($node2, $identifier1, $identifier2, TRUE, $variable);
            }
          }
          else {
            $return .= $this->getItemByIdentifiers($node->structuredDataNodes->structuredDataNode, $identifier1, $identifier2, TRUE, $variable);
          }
        }
        break;
      case $identifier2:
        if ($found === TRUE) {
          if (!empty($node->$variable)) {
            $return .= $node->$variable;
          }
        }
        break;

    }
    if (!empty($node->structuredDataNodes->structuredDataNode)) {
      if (is_array($node->structuredDataNodes->structuredDataNode)) {
        foreach ($node->structuredDataNodes->structuredDataNode as $node2) {
          $return .= $this->getItemByIdentifiers($node2, $identifier1, $identifier2, $found, $variable);
        }
      }
      else {
        $return .= $this->getItemByIdentifiers($node->structuredDataNodes->structuredDataNode, $identifier1, $identifier2, $found, $variable);
      }
      return $return;
    }

    return $return;
  }

}