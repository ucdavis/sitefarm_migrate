<?php

namespace Drupal\sitefarm_migrate_csv_people\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Migrate images from local and remote locations.
 *
 * @MigrateProcessPlugin(
 *   id = "sf_file_import"
 * )
 */
class FileImport extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Component\Utility\UrlHelper
   */
  protected $urlHelper;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param array $plugin_definition
   * @param $migration
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, $migration, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->initializeUrlHelper();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager')
    );
  }

  /**
   * Initialize the URL Helper
   */
  function initializeUrlHelper() {
    $this->urlHelper = new UrlHelper();
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty(trim($value))) {
      return [];
    }

    if (empty($this->configuration['delimiter'])) {
      $this->configuration['delimiter'] = "|";
    }

    if (empty($this->configuration['destination'])) {
      $this->configuration['destination'] = "public://";
    }

    $source = explode($this->configuration['delimiter'], $value);
    $files = [];

    if(!file_exists($this->configuration['destination'])){
      mkdir($this->configuration['destination'], 0775, TRUE);
    }

    foreach ($source as $file) {
      $file = trim($file);
      $destination = $this->configuration['destination'] . "/" . array_pop(explode("/", $file));
      if ($this->urlHelper->isExternal($file)) {
        if (!$uri = system_retrieve_file($file, $destination)) {
          return [];
        }
      }
      else {
        // See if the file exists, then try to copy
        if (file_exists($file)) {
          if (!$uri = file_unmanaged_copy($file, $destination)) {
            return [];
          }
          // No, lets try to get a relative path and try that
        }
        else {
          $localFile = DRUPAL_ROOT . $this->getRelativePathToRoot($file);
          if (!$uri = file_unmanaged_copy($localFile, $destination)) {
            return [];
          }
        }

      }
      $file = $this->entityTypeManager
        ->getStorage('file')
        ->create(['uri' => $uri]);
      $file->save();
      $files[] = $file->id();
    }

    return $files;
  }

  /**
   * Returns a full path. Specify a file relative to the docroot.
   * @param $path
   * @return string
   */
  private function getRelativePathToRoot($path) {
    $root = explode("/", DRUPAL_ROOT);
    $path = explode("../", $path);
    $down = count($path) - 1;
    $root = array_splice($root, 0, -$down);
    return implode("/", $root) . "/" . array_pop($path);
  }
}