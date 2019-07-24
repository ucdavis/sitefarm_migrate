<?php

namespace Drupal\sitefarm_migrate_csv_people\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\Row;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Check if term exists and create new if doesn't.
 *
 * @MigrateProcessPlugin(
 *   id = "existing_term"
 * )
 */
class ExistingTerm extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param array $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($term_name, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $vocabulary = $this->configuration['vocabulary'];
    $mode = $this->configuration['mode'];
    if (empty($term_name)) {
      throw new MigrateSkipProcessException();
    }
    $return = $this->getTidByName($term_name, $vocabulary, $mode);
    return $return;
  }

  /**
   * @param null $name
   * @param null $vocabulary
   * @param string $mode
   * @return null
   */
  protected function getTidByName($name = NULL, $vocabulary = NULL, $mode = "name") {
    if ($mode != "tid") {
      $mode = "name";
    }
    $properties = [];

    if (!empty($name)) {
      // Trim the value, remove quotes, spaces, etc from the start and end of the string.
      $name = trim(trim($name,'"\''));
      $properties['name'] = $name;
    }
    if (!empty($vocabulary)) {
      $properties['vid'] = $vocabulary;
    }
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties($properties);

    $term = reset($terms);
    switch ($mode) {
      case "name":
        return !empty($term) ? NULL : $name;
        break;
      default:
        return !empty($term) ? $term->get("tid")->value : NULL;
        break;
    }

  }

}