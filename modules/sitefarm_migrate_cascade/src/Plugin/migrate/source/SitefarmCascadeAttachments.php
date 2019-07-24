<?php

namespace Drupal\sitefarm_migrate_cascade\Plugin\migrate\source;

use Drupal;
use Drupal\migrate\Row;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowDeleteEvent;
use DOMDocument;
use Drupal\redirect\Entity\Redirect;

/**
 * Source for importing the attachments
 *
 * @MigrateSource(
 *   id = "sitefarm_cascade_attachments"
 * )
 */
class SitefarmCascadeAttachments extends SitefarmCascadeBase {


  public function initializeIterator() {
    return parent::initializeIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {

    $this->isTest = $row->getSourceProperty("is_test");

    if (!$this->isTest) {
      $this->cascade_connect();
    }

    $file = $row->getSourceProperty('url');

    $new_file = $this->saveFile($file);

    if (!$new_file) {
      return FALSE;
    }

    $row->setSourceProperty("fid", md5($new_file[1]));
    $row->setSourceProperty("filename", "public://" . $new_file[1]);
    $row->setSourceProperty("uri", implode("", $new_file));

    $return = parent::prepareRow($row);
    return $return;
  }

  /**
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {

    # Grab the row
    $row = $event->getRow();
    # Grab the migration object, id and idmap
    $migration = $event->getMigration();
    $migration_id = $migration->id();
    $file_id = $event->getDestinationIdValues()[0];

    # Load the node that needs to be updated
    $node_migrate_id = $row->getSourceProperty('article_tid');
    $node_id = $this->getPageNid($node_migrate_id, str_replace("_att", "", $migration_id));

    $node = $this->entitytypemanager->getStorage("node")->load($node_id);
    $file = $this->entitytypemanager->getStorage("file")->load($file_id);

    # Load the body field
    $body = $node->get("body")->getValue();

    # Inject the new files into the body of the node
    $newbody = $this->injectbody($body[0]['value'], $file, $row->getSourceProperty("url"));

    # Update the value and save it
    $body[0]['value'] = $newbody;
    $node->body->value = $newbody;
    $node->set("body", $body);
    $node->setNewRevision(FALSE);
    $node->save();
  }

  /**
   * Returns the destid1 from the migrate map
   * @param $sourceid
   * @param $migrate_id
   * @return mixed
   */
  function getPageNid($sourceid, $migrate_id) {
    return $this->database->select("migrate_map_" . $migrate_id, "m")
      ->fields("m", ["destid1"])
      ->condition("sourceid1", $sourceid)
      ->execute()
      ->fetchField();
  }

  /**
   * Saves the file to the filesystem
   * @param $url
   * @param bool|TRUE $image
   * @return array|bool
   */
  function saveFile($url, $image = TRUE) {

    $path = "public://";

    if (!empty($url)) {
      # Make sure the url is usable
      $file = $this->query_file($url);
      if ($file === FALSE) {
        return FALSE;
      }

      # Use the original filename
      $filename = $this->create_filename($path, $file["path"]);
      $this->create_filename("public://", $file["path"]);
      file_save_data($file['data'], implode("", $filename), FILE_EXISTS_REPLACE);

      # Return the file ID or false if the file didn't save
      return $filename;
    }
    return FALSE;
  }

  /**
   * Queries the file and data from cascade
   * @param $url
   * @return array|bool
   */
  function query_file($url) {
    # Make sure we have a connection to cascade
    if (!$this->isTest) {
      $this->open_cascade_handler();


      $file = FALSE;

      # Try to use the internal path if that's all we've got
      if (substr($url, 0, 1) == "/") {
        # Grab the file from cascade
        $file = $this->cascadeMigrate->queryCascadeByPath("file", $url, $this->cascadeMigrate->cascade_site_name, TRUE);
      }

      # Use the internal URL if supplied
      if (substr($url, 0, 7) == "site://") {
        # Split the url into sitename and path
        $url2 = str_replace("site://", "", $url);
        $url2 = explode("/", $url2, 2);

        # Grab the file from cascade
        $file = $this->cascadeMigrate->queryCascadeByPath("file", $url2[1], $url2[0], TRUE);
      }

      # Starting with the same url as we are importing? Great!
      if (substr($url, 0, strlen($this->cascadeMigrate->site_url)) == $this->cascadeMigrate->site_url) {
        $url = str_replace($this->cascadeMigrate->site_url, "", $url);
        $file = $this->cascadeMigrate->queryCascadeByPath("file", $url, $this->cascadeMigrate->cascade_site_name, TRUE);
      }
    }
    else {
      $file = $this->cascadeMigrate->getTestData("image");
    }

    # Return the file and data
    if ($file && $file->success == "true") {
      # Return the name, data and path
      return [
        "name" => $file->asset->file->name,
        "data" => $file->asset->file->data,
        "path" => $file->asset->file->path
      ];
    }
    else {
      drupal_set_message("Unable to download file: " . $url);
    }

    return FALSE;
  }

  /**
   * Opens the cascade handler
   */
  function open_cascade_handler() {
    $this->cascadeMigrate->setSavedCredentials();
  }

  /**
   * Create a valid filename and makes sure folders exsist
   * @param $path
   * @param $file
   * @return array
   */
  function create_filename($path, $file) {
    # Make sure the folders exist
    $dirs = explode("/", $file);
    unset($dirs[count($dirs) - 1]);
    if(!file_exists($path)){
      mkdir($path);
    }
    # Loop through the folders to create them all (mkdir in recursive mode doesn't work for some reason)
    $create = substr($path, 0, -1);
    foreach ($dirs as $dir) {
      $create = $create . "/" . $dir;
      if(!file_exists($create)) {
        mkdir($create);
      }
    }

    # Return internal path with filename
    return [$path, $file];
  }

  /**
   * Replaces the image and href variables to the wysiwyg drupal style attributes
   * @param $value
   * @param $file
   * @param $url
   * @return mixed|string
   */
  public function injectbody($value, $file, $url) {
    if (is_null($value) || empty($value)) {
      return $value;
    }

    # Load a domdocument of the HTML
    $doc = new DOMDocument();
    @$doc->loadHTML($value);

    # Grab all images and loop though them
    $tags = $doc->getElementsByTagName('img');

    foreach ($tags as $id => $tag) {
      $image = $tag->getAttribute('src');

      # Only replace the image we are working on now
      if ($image != $url) {
        continue;
      }

      # Ugly way to replace the image with the local image we just created
      if ($file) {
        # Create a redirect from the old url to the new one
        $this->create_redirect($image, $file->getFileUri());

        $value = str_replace('src="' . $image . '"',
          ' data-entity-uuid="' . $file->get("uuid")->value . '"' .
          ' src="' . file_url_transform_relative(file_create_url($file->getFileUri())) . '"' .
          ' data-entity-type="file" ',
          $value);

        # Also replace the links to this image
        $value = str_replace('href="' . $image . '"',
          'href="' . file_url_transform_relative(file_create_url($file->getFileUri())) . '"',
          $value);
      }
    }

    # Grab all links, check if it is a local file and download where needed
    $tags = $doc->getElementsByTagName('a');

    foreach ($tags as $id => $tag) {
      $link = $tag->getAttribute('href');

      # ONly replace attachment we are working on
      if ($link != $url) {
        continue;
      }

      if ((stristr($link, ".jpg") ||
        stristr($link, ".gif") ||
        stristr($link, ".pdf") ||
        stristr($link, ".doc") ||
        stristr($link, ".png"))
      ) {

        # Ugly way to replace the link with the local link we just created
        if ($file) {
          $this->create_redirect($link, $file->getFileUri());
          $value = str_replace('href="' . $link . '"',
            ' data-entity-uuid="' . $file->get("uuid")->value . '"' .
            ' href="' . file_url_transform_relative(file_create_url($file->getFileUri())) . '"' .
            ' data-entity-type="file" ',
            $value);
        }
      }
    }
    # Decode html entities
    $value = htmlspecialchars_decode($value);

    return ($value);
  }

  /**
   * Creates a redirect from one place to another
   * @param $from
   * @param $to
   * @return bool
   */
  function create_redirect($from, $to) {
    $request = $this->requestStack->getCurrentRequest();

    if (strlen($from) < 3) {
      return FALSE;
    }

    if (substr($from, 0, 1) == "/") {
      $from = substr($from, 1);
    }
    $to = str_replace($request->getSchemeAndHttpHost(), "internal:", file_create_url($to));
    # Delete possible double
    $this->database->delete("redirect")
      ->condition('redirect_source__path', $from, "LIKE")
      ->execute();

    $redirect = new Redirect([], "node");
    # Create the redirect using the redirect module
    $redirect = $redirect->create([
      'redirect_source' => $from,
      'redirect_redirect' => $to,
      'language' => 'und',
      'status_code' => '301',
    ]);
    $redirect->save();
  }

}