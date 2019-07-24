<?php

namespace Drupal\sitefarm_migrate_wordpress;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\sitefarm_migrate\SiteFarmMigrate;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Functionality to construct WordPress migrations from broad configuration
 * settings.
 */
class WordPressMigrationGenerator implements ContainerInjectionInterface {

  /**
   * Configuration to guide our migration creation process.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * Process plugin configuration for uid references.
   *
   * @var array
   */
  protected $uidMapping = [];

  /**
   * ID of the configuration entity for tag migration.
   *
   * @var string
   */
  protected $tagsID = '';

  /**
   * ID of the configuration entity for category migration.
   *
   * @var string
   */
  protected $categoriesID = '';

  /**
   * Shared configuration
   *
   * @var array
   */
  protected $shared_config;

  /**
   * @var \Drupal\sitefarm_migrate\SiteFarmMigrate
   */
  protected $sitefarmMigrate;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;


  public function __construct(SiteFarmMigrate $siteFarmMigrate, EntityFieldManager $entityFieldManager) {
    $this->sitefarmMigrate = $siteFarmMigrate;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sitefarm_migrate.sitefarm_migrate'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * @param $config
   *
   * @throws \Exception
   */
  public function createMigrations($config) {

    $this->configuration = $config;

    $this->configuration['prefix'] = time() . "_";


    $this->shared_config = [
      // @todo: Dynamically populate from the source XML.
      // @link https://www.drupal.org/node/2742287
      'namespaces' => [
        'wp' => 'http://wordpress.org/export/1.2/',
        'excerpt' => 'http://wordpress.org/export/1.2/excerpt/',
        'content' => 'http://purl.org/rss/1.0/modules/content/',
        'wfw' => 'http://wellformedweb.org/CommentAPI/',
        'dc' => 'http://purl.org/dc/elements/1.1/',
      ],
      'urls' => [
        $this->configuration['file_uri'],
      ],
    ];

    // Determine the uid mapping
    if (isset($this->configuration['default_author'])) {
      $account = user_load_by_name($this->configuration['default_author']);
      if ($account) {
        $this->uidMapping = [
          'plugin' => 'default_value',
          'default_value' => $account->id(),
        ];
      }
      else {
        throw new Exception('Username @name does not exist.',
          ['@name' => $this->configuration['default_author']]);
      }
    }
    else {
      $this->uidMapping = [
        'plugin' => 'default_value',
        'default_value' => 1,
      ];
    }

    // Setup vocabulary migrations if requested.
    if ($this->configuration['tag_vocabulary']) {
      $this->tagsID = $this->configuration['prefix'] . 'sf_wordpress_tags';
      $migration = $this->sitefarmMigrate->createMigration('sf_wordpress_tags', $this->tagsID);
      $migration->set('migration_group', $this->configuration['group_id']);
      $process = $migration->get('process');
      $process['vid'] = [
        'plugin' => 'default_value',
        'default_value' => $this->configuration['tag_vocabulary'],
      ];
      $migration->set('process', $process);
      $source = $migration->get("source");
      $source = array_merge($source, $this->shared_config);
      $migration->set("source", $source);
      $migration->save();
    }
    if ($this->configuration['category_vocabulary']) {
      $this->categoriesID = $this->configuration['prefix'] . 'sf_wordpress_categories';
      $migration = $this->sitefarmMigrate->createMigration('sf_wordpress_categories', $this->categoriesID);
      $migration->set('migration_group', $this->configuration['group_id']);
      $process = $migration->get('process');
      $process['vid'] = [
        'plugin' => 'default_value',
        'default_value' => $this->configuration['category_vocabulary'],
      ];
      $migration->set('process', $process);
      $source = $migration->get("source");
      $source = array_merge($source, $this->shared_config);
      $migration->set("source", $source);
      $migration->save();
    }

    // Setup the content migrations.
    foreach (['post', 'page'] as $wordpress_type) {
      if (!empty($this->configuration[$wordpress_type]['type'])) {
        $this->createContentMigration($wordpress_type);
      }
    }
  }

  /**
   * Setup the migration for a given WordPress content type.
   *
   * @param $sf_wordpress_type
   *   WordPress content type - 'post' or 'page'.
   */
  protected function createContentMigration($sf_wordpress_type) {
    $dependencies = [];
    $content_id = $this->configuration['prefix'] . 'wordpress_content_' . $sf_wordpress_type;
    $migration = $this->sitefarmMigrate->createMigration('sf_wordpress_content', $content_id);
    $migration->set('migration_group', $this->configuration['group_id']);
    $migration->set("label", "Import content from Wordpress (" . $sf_wordpress_type . ")");
    $source = $migration->get('source');
    $source['item_selector'] .= '[wp:post_type="' . $sf_wordpress_type . '"]';
    $migration->set('source', $source);
    $process = $migration->get('process');
    $process['uid'] = $this->uidMapping;
    $process['body/format'] = [
      'plugin' => 'default_value',
      'default_value' => "basic_html",
    ];
    $process['type'] = [
      'plugin' => 'default_value',
      'default_value' => $this->configuration[$sf_wordpress_type]['type'],
    ];

    $process['type'] = [
      'plugin' => 'default_value',
      'default_value' => $this->configuration[$sf_wordpress_type]['type'],
    ];

    $process['field_sf_article_type'] = [
      'plugin' => 'default_value',
      'default_value' => $this->configuration[$sf_wordpress_type]['article_type'],
    ];

    $process['field_sf_article_category'] = [
      'plugin' => 'default_value',
      'default_value' => $this->configuration[$sf_wordpress_type]['article_category'],
    ];

    if ($this->configuration['tag_vocabulary']) {
      if ($term_field = $this->termField($this->configuration[$sf_wordpress_type]['type'], $this->configuration['tag_vocabulary'])) {
        $process[$term_field] = [
          'plugin' => 'migration',
          'migration' => $this->tagsID,
          'source' => 'post_tag',
        ];
        $dependencies[] = $this->tagsID;
      }
    }
    if ($this->configuration['category_vocabulary']) {
      if ($term_field = $this->termField($this->configuration[$sf_wordpress_type]['type'], $this->configuration['category_vocabulary'])) {
        $process[$term_field] = [
          'plugin' => 'migration',
          'migration' => $this->categoriesID,
          'source' => 'category',
        ];
        $dependencies[] = $this->categoriesID;
      }
    }
    $migration->set('process', $process);
    $migration->set('migration_dependencies', ['required' => $dependencies]);
    $source = $migration->get("source");
    $source = array_merge($source, $this->shared_config);
    $migration->set("source", $source);
    $migration->save();

    // Also create a comment migration, if the content type has a comment field.
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $all_fields */
    $all_fields = $this->entityFieldManager->getFieldDefinitions('node', $this->configuration[$sf_wordpress_type]['type']);
    foreach ($all_fields as $field_name => $field_definition) {
      if ($field_definition->getType() == 'comment') {
        $storage = $field_definition->getFieldStorageDefinition();
        $id = $this->configuration['prefix'] . 'sf_wordpress_comment_' . $sf_wordpress_type;
        $migration = $this->sitefarmMigrate->createMigration('sf_wordpress_comment', $id);
        $migration->set('migration_group', $this->configuration['group_id']);
        $source = $migration->get('source');
        $source['item_selector'] = str_replace(':content_type', $sf_wordpress_type, $source['item_selector']);
        $migration->set('source', $source);
        $process = $migration->get('process');
        $process['entity_id'][0]['migration'] = $content_id;
        $process['comment_type'][0]['default_value'] = $storage->getSetting('comment_type');
        $process['pid'][0]['migration'] = $id;
        $process['field_name'][0]['default_value'] = $field_name;
        $migration->set('process', $process);
        $migration->set('migration_dependencies', ['required' => [$content_id]]);
        $source = $migration->get("source");
        $source = array_merge($source, $this->shared_config);
        $migration->set("source", $source);
        $migration->save();
        break;
      }
    }
  }

  /**
   * Returns the first field referencing a given vocabulary.
   *
   * @param string $bundle
   * @param string $vocabulary
   *
   * @return string
   */
  protected function termField($bundle, $vocabulary) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $all_fields */
    $all_fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    foreach ($all_fields as $field_name => $field_definition) {
      if ($field_definition->getType() == 'entity_reference') {
        $storage = $field_definition->getFieldStorageDefinition();
        if ($storage->getSetting('target_type') == 'taxonomy_term') {
          $handler_settings = $field_definition->getSetting('handler_settings');
          if (isset($handler_settings['target_bundles'][$vocabulary])) {
            return $field_name;
          }
        }
      }
    }
    return '';
  }

}
