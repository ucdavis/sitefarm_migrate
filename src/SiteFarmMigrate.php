<?php

namespace Drupal\sitefarm_migrate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\migrate_plus\Entity\Migration as Migration;
use Symfony\Component\Config\Definition\Exception\Exception;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Plugin\MigrationConfigEntityPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

class SiteFarmMigrate implements ContainerInjectionInterface{

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $plugin_manager;

  /**
   * A migration entity.
   *
   * @var \Drupal\migrate_plus\Entity\Migration
   */
  public $migrationConfigEntity;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;


  /**
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $plugin_manager
   * @param \Drupal\Core\Database\Connection $database
   */
  public function __construct(MigrationPluginManagerInterface $plugin_manager, Connection $database) {
    $this->plugin_manager = $plugin_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('database')
    );
  }

  protected function initializeMigrationEntity() {
    $this->migrationConfigEntity = new Migration([], "migration");
  }

  /**
   * Create a new migration
   *
   * @param string $pluginId
   * @param string $newPluginId
   * @return \Drupal\migrate_plus\Entity\Migration
   */
  public function createMigration($pluginId, $newPluginId) {
    try {
      # Create migration instances
      $migration_plugin = $this->plugin_manager->createInstance($pluginId);
      $this->initializeMigrationEntity();

      if (!$migration_plugin) {
        # Maybe it doesn't exist in the second manager, lets try to initialize it and try again
        $migration_plugin = $this->plugin_manager->createInstance($pluginId);

        if (!$migration_plugin) {
          # Migration creation didn't work, lets try resetting the definition cache and try again
          # This resets the migrate definitions
          $this->plugin_manager->clearCachedDefinitions();
          $migration_plugin = $this->plugin_manager->createInstance($pluginId);
        }

        # Even that didn't work? Lets flush ALL caches.
        if (!$migration_plugin) {
          $this->plugin_manager->clearCachedDefinitions();
          drupal_flush_all_caches();
          $migration_plugin = $this->plugin_manager->createInstance($pluginId);
        }

        # Still broken? Sorry, I give up. Might be that the plugin used doesn't exist or
        # the migration yml file just isn't there
        if (!$migration_plugin) {
          return FALSE;
        }
      }

      $entity_array['id'] = $newPluginId;
      $entity_array['created'] = time();
      $entity_array['last_imported'] = 0;
      $entity_array['migration_group'] = $migration_plugin->get('migration_group');
      $entity_array['migration_tags'] = $migration_plugin->get('migration_tags');
      $entity_array['label'] = $migration_plugin->label();
      $entity_array['source'] = $migration_plugin->getSourceConfiguration();
      $entity_array['destination'] = $migration_plugin->getDestinationConfiguration();
      $entity_array['process'] = $migration_plugin->getProcess();
      $entity_array['migration_dependencies'] = $migration_plugin->getMigrationDependencies();
      $migration_entity = $this->migrationConfigEntity->create($entity_array);

      # Set the created and last_imported variable
      $source = $migration_entity->get("source");
      $source['created'] = time();
      $source['last_imported'] = 0;
      $migration_entity->set("source", $source);

    } catch (Exception $e) {
      # It should totally work, if it doesn't we have this just in case
      return FALSE;
    }
    return $migration_entity;
  }

  /**
   * Save a migration permanently
   *
   * @param \Drupal\migrate_plus\Entity\Migration $migration
   * @return \Drupal\migrate_plus\Entity\Migration | bool
   */
  public function saveMigration(Migration $migration) {

    $migration->save();

    # Start a new service to save the config in the second manager
    $id = $migration->id();
    $instance = $this->plugin_manager->createInstance($id);

    if (!$instance) {
      return FALSE;
    }
    return $migration;
  }

  /**
   * Get a migration instance
   *
   * @param string $id
   * @return \Drupal\migrate\Plugin\Migration
   */
  public function getMigrationInstance($id) {

    # Grab the instance from the migration manager
    $instance = $this->plugin_manager->createInstance($id);

    # Ugh, nope. Lets play with some caches and try again
    if (!$instance) {
      $this->plugin_manager->clearCachedDefinitions();
      drupal_flush_all_caches();
      //$this->migrationConfigEntityPluginManager->createInstance($id);
      $instance = $this->plugin_manager->createInstance($id);
    }

    return $instance;
  }

  /**
   * Sets the time the migration was imported to now
   * @param string $id
   * @param int|bool $time
   */
  public function setMigrationLastImportedTime($id, $time = FALSE){
    # Use now if no time was defined
    $time = $time === FALSE ? time() : $time;

    # Initialize a Migration entity without using the static methods
    $this->initializeMigrationEntity();

    # Load the entity we want to edit
    $migration_entity = $this->migrationConfigEntity->load($id);

    # Load the source
    $source = $migration_entity->get('source');

    # Set the time in the source and inject the source back
    $source['last_imported'] = $time;
    $migration_entity->set("source", $source);

    # Save the migration entity
    $migration_entity->save();
  }

  /**
   * @param $id
   */
  public function deleteMigration($id){

    # Delete the migration
    $this->database->delete('config')
      ->condition('name', 'migrate_plus.migration.' . $id)
      ->execute();

    $this->database->delete('cache_config')
      ->condition('cid', 'migrate_plus.migration.' . $id)
      ->execute();

    $this->database->schema()->dropTable("migrate_map_" . $id);
    $this->database->schema()->dropTable("migrate_message_" . $id);

    # Clear the plugin cache
    $this->plugin_manager->clearCachedDefinitions();
  }

}