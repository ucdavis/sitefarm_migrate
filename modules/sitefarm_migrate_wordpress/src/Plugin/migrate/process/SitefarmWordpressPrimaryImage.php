<?php

namespace Drupal\sitefarm_migrate_wordpress\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * This plugin generates the primary image from wordpress featured image and
 * inject them as attached image
 *
 * @MigrateProcessPlugin(
 *   id = "sf_wordpress_primary_image"
 * )
 */
class SitefarmWordpressPrimaryImage extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_null($value) || empty($value)) {
      return $value;
    }

    // Get the directory for where to put the image
    $directory = 'public://' . date('Y-m') . '/';

    $selector = $row->getSourceProperty('item_selector');
    if (strpos($selector, 'post_type="post"')) {
      $directory = 'public://images/article/';
    }

    // Create the managed file
    $src = $row->getSourceProperty('featured_image_src');
    $fid = $this->saveFile($src, $directory);

    return $fid;
  }

  /**
   * @param $url
   * @param $directory
   *
   * @return int|\Drupal\file\FileInterface|false
   */
  function saveFile($url, $directory) {
    # Make sure the folders exist
    @mkdir($directory);

    if (!empty($url)) {
      # Make sure the url is usable
      if (FALSE === file_get_contents($url, 0, NULL, 0, 1)) {
        return FALSE;
      }

      $filename = $directory . array_pop(explode("/", $url));
      $data = file_get_contents($url);
      $file = file_save_data($data, $filename, FILE_EXISTS_REPLACE);

      # Return the file ID or false if the file didn't save
      return $file->id();
    }
    else {
      return FALSE;
    }
  }

}
