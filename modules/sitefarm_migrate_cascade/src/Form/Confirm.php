<?php

namespace Drupal\sitefarm_migrate_cascade\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate;
use Drupal\sitefarm_migrate_cascade\Controller\QueueGenerator;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\user\PrivateTempStoreFactory;

class Confirm extends ConfirmFormBase implements ContainerInjectionInterface {

  /**
   * @var \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate
   */
  protected $cascadeMigrate;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;


  public function __construct(CascadeMigrate $cascade_migrate, PrivateTempStoreFactory $privateTempStorage) {
    $this->cascadeMigrate = $cascade_migrate;
    $this->privateTempStorage = $privateTempStorage->get("sitefarm_migrate_cascade");
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sitefarm_migrate_cascade.cascade_migrate'),
      $container->get('user.private_tempstore')
    );
  }

  function getFormId() {
    return "sitefarm.cascade_migrate.confirm";
  }

  function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);

    if ($this->cascadeMigrate->setupComplete()) {

      $form['overview'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Cascade Migration - Step 4/4 - Confirm'),
      ];

      $form['description'] = [
        '#markup' => "We collected all information nessesary to import your website. Click on Generate Migration(s) to
            generate the migration(s). This process generally takes about a minute, but can take up to 10 minutes on larger websites.",
        '#weight' => 99
      ];

      return $form;
    }
    else {
      $form["description"] = ["#markup" => "There is currently no migration active"];
    }


    return $form;
  }

  function validateForm(array &$form, FormStateInterface $form_state) {

  }

  function submitForm(array &$form, FormStateInterface $form_state) {

    # Create an instance of the CascadeMigrate class and set the variables
    $this->cascadeMigrate->setSavedCredentials();

    # Get the types we want to import
    $importTypes = $this->cascadeMigrate->getImportTypes();

    $batch = [
      'title' => t('Processing'),
      'init_message' => t('Creating migrations...'),
      'progress_message' => t('Completed @current of @total.'),
      'error_message' => t('An error has occurred.'),
      'operations' => [
        [
          [$this, 'run'],
          [array_keys($importTypes), 'import'],
        ],
      ],
      'finished' => [
        $this,
        'finished',
      ],
    ];
    batch_set($batch);

    $form_state->setRedirect("entity.migration_group.list");
  }

  /**
   * Runs a single migrate batch import.
   *
   * @param int[] $initial_ids
   *   The full set of migration IDs to import.
   * @param string $action
   *   An array of additional configuration from the form.
   * @param array $context
   *   The batch context.
   */
  public function run($initial_ids, $action, &$context) {

    # Create an instance of the CascadeMigrate class and set the variables
    $this->cascadeMigrate->setSavedCredentials();

    # Get the types we want to import
    $importTypes = $this->cascadeMigrate->getImportTypes();
    $content_types = $this->privateTempStorage->get("folder_config");
    $article_types = $this->privateTempStorage->get("folder_config_atypes");
    $categories = $this->privateTempStorage->get("folder_config_categories");
    $tags = $this->privateTempStorage->get("folder_config_tags");

    $migrations = [];

    # Loop through the types and generate the migrations
    foreach ($importTypes as $type) {
      switch ($type) {
        case "people":
          $migrationId = $this->cascadeMigrate->generateQueue(FALSE, "sf_people", ['page', 'block_XHTML_DATADEFINITION']);
          $this->cascadeMigrate->generatePeopleMigration($migrationId);
          $migrations[] = $migrationId;
          break;
        case "pages":
          foreach ($content_types as $content_type => $folders) {
            # Skip no_import
            if($content_type == "no_import"){
              continue;
            }
            $migrationId = $this->cascadeMigrate->generateQueue($folders, $content_type, ['page'], $article_types, $categories, $tags);
            $this->cascadeMigrate->generatePagesMigration($migrationId, $content_type);
            $this->cascadeMigrate->generateAttachmentsMigration($migrationId . "_att");
            $migrations[] = $migrationId;
          }
          break;
        case "feature_blocks":
          $migrationId = $this->cascadeMigrate->generateQueue(FALSE, "sf_blocks", ['block_XHTML_DATADEFINITION']);
          $this->cascadeMigrate->generateBlocksMigration($migrationId);
          $migrations[] = $migrationId;
          break;
      }
    }
  }

  /**
   * Callback executed when the Migrate batch process completes.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   An array of methods run in the batch.
   * @param string $elapsed
   *   The time to run the batch.
   */
  public function finished($success, $results, $operations, $elapsed) {
    drupal_set_message("Migrations created successfully");
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to generate the migration script(s)?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.migration_group.list');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Generate Migration(s)');
  }

  /**
   * {@inheritdoc}.
   */
  protected function getEditableConfigNames() {
    return [];
  }
}
