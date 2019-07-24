<?php

namespace Drupal\sitefarm_migrate_cascade\Plugin\migrate\process;

use DOMDocument;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\user\PrivateTempStoreFactory;

/**
 * This plugin generates the body for a bodyfield from cascade
 * We also grab images and attachements from the body field and
 * inject them as attached image
 *
 * @MigrateProcessPlugin(
 *   id = "cascade_body"
 * )
 */
class Cascade_body extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate
   */
  protected $cascadeMigrate;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * A request stack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var $migration
   */
  protected $migration;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param array $plugin_definition
   * @param $migration
   * @param \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate $cascade_migrate
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   * @param \Drupal\sitefarm_migrate_cascade\Plugin\migrate\process\PrivateTempStoreFactory $privateTempStoreFactory
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, $migration, CascadeMigrate $cascade_migrate, Connection $database, FileSystemInterface $file_system, RequestStack $request_stack, PrivateTempStoreFactory $privateTempStoreFactory) {
    $this->cascadeMigrate = $cascade_migrate;
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->requestStack = $request_stack;
    $this->migration = $migration;
    $this->privateTempStorage = $privateTempStoreFactory->get("sitefarm_cascase_body." . $migration->id());
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
      $container->get('sitefarm_migrate_cascade.cascade_migrate'),
      $container->get('database'),
      $container->get('file_system'),
      $container->get('request_stack'),
      $container->get('user.private_tempstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
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
      $this->queue_file($image, $row->getSourceProperty("id"));
    }

    # Grab all links, check if it is a local file and download where needed
    $tags = $doc->getElementsByTagName('a');

    foreach ($tags as $id => $tag) {
      $link = $tag->getAttribute('href');

      if ((stristr($link, ".jpg") ||
        stristr($link, ".gif") ||
        stristr($link, ".pdf") ||
        stristr($link, ".doc") ||
        stristr($link, ".png"))
      ) {
        $this->queue_file($link, $row->getSourceProperty("id"), FALSE);
      }
    }
    # Decode html entities
    $value = htmlspecialchars_decode($value);

    return ($value);
  }

  /**
   * @param $url
   * @param $source_id
   * @param bool $image
   *
   * @throws \Exception
   */
  private function queue_file($url, $source_id, $image = TRUE) {
    $path = $image ? 'public://inline-images/' : 'public://inline-files/';
    if (!empty($url)) {

      if (substr($url, 0, 1) == "/" || substr($url, 0, 7) == "site://" || substr($url, 0, strlen($this->cascadeMigrate->site_url)) == $this->cascadeMigrate->site_url) {

        # Delete double items
        $this->database->delete("sitefarm_migrate_cascade_queue")
          ->condition("migration_id", $this->migration->id() . "_att")
          ->condition("url", $url)
          ->execute();

        # Insert this in the queue
        $this->database->insert("sitefarm_migrate_cascade_queue")
          ->fields([
            'path',
            'cascade_id',
            'url',
            'type',
            'migration_id',
            'article_tid',
            'category_tid'
          ])
          ->values([
            $path,
            '',
            $url,
            'attachment',
            $this->migration->id() . "_att",
            $source_id,
            0
          ])
          ->execute();
      }
    }
  }
}
