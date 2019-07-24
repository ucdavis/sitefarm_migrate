<?php

namespace Drupal\sitefarm_migrate_csv_people\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sitefarm_migrate\SiteFarmMigrate;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\user\PrivateTempStoreFactory;

class Confirm extends ConfirmFormBase implements ContainerInjectionInterface {

  /**
   * Sitefarm Migrate
   *
   * @var \Drupal\sitefarm_migrate\SiteFarmMigrate
   */
  protected $sitefarmMigrate;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;

  /**
   * @param \Drupal\sitefarm_migrate\SiteFarmMigrate $siteFarmMigrate
   * @param \Drupal\user\PrivateTempStoreFactory $privateTempStorage
   */
  public function __construct(SiteFarmMigrate $siteFarmMigrate, PrivateTempStoreFactory $privateTempStorage) {
    $this->sitefarmMigrate = $siteFarmMigrate;
    $this->privateTempStorage = $privateTempStorage->get("sitefarm_migrate_csv_people");
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sitefarm_migrate.sitefarm_migrate'),
      $container->get('user.private_tempstore')
    );
  }

  function getFormId() {
    return "sitefarm.migrate.csv.people.confirm";
  }

  function buildForm(array $form, FormStateInterface $form_state) {
    # Grab the file we uploaded in the previous step, and see if it is still there
    $file = $this->privateTempStorage->get("file");

    if ($file === FALSE || !file_exists($file)) {
      $form['error'] = [
        '#markup' => $this->t('Import file not found, please try again'),
      ];
      return $form;
    }

    $form = parent::buildForm($form, $form_state);

    $form['overview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('People CSV Import Confirmation'),
      '#weight' => -1
    ];

    $form['description'] = [
      '#markup' => "Please confirm the data is correctly lined up with all fields before the migration is created.<br/>
                                In this table we show the first three rows of the uploaded CSV file<br/><br/>",
    ];

    # Loop through the CSV file and grab the first 3 rows
    $row = 0;
    $import_rows = [];
    if (($handle = fopen($file, "rt")) !== FALSE) {
      while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        if ($row > 3) {
          continue;
        }
        $import_rows[$row] = $data;
        $row++;
      }
      fclose($handle);
    }

    # Set the Header
    $header = [
      "id" => "#",
      "field" => $this->t('Field'),
      "row_1" => $this->t('First Row (header)'),
      "row_2" => $this->t('Second Row'),
      "row_3" => $this->t('Third Row')
    ];

    # Remove headers we dont need
    if (!isset($import_rows[1])) {
      unset($header['row_2']);
    }
    if (!isset($import_rows[2])) {
      unset($header['row_3']);
    }


    # Generate a temporary migration from the template we have so we can use the fields
    $migration = $this->sitefarmMigrate->createMigration('sf_csv_people', uniqid("temp_"));

    # Migration is buggy. If it didn't work, show a message
    if ($migration === FALSE) {
      $form['error'] = [
        '#markup' => "There was an error generating a temporary migration.
                Try clearing caches and uninstalling/reinstalling the migrate modules"
      ];
      return $form;
    }

    # Loop through the column names and create the table rows
    $rows = [];
    foreach ($migration->source['column_names'] as $id => $name) {
      $rows[$id]['id'] = $id;
      foreach ($name as $n) {
        $rows[$id]['field'] = $n;
      }
      $rows[$id]['row_1'] = $import_rows[0][$id];
      if (isset($import_rows[1])) {
        $rows[$id]['row_2'] = $import_rows[1][$id];
        if (isset($import_rows[2])) {
          $rows[$id]['row_3'] = $import_rows[2][$id];
        }
      }
    }

    $form['migrations'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#weight' => 1
    ];

    return $form;
  }

  function validateForm(array &$form, FormStateInterface $form_state) {
    # Set the label and ID
    $label = "People CSV Import 3/3 - People";
    $label_tags = "People CSV Import 2/3 - Tags";
    $label_pt = "People CSV Import 1/3 - Person Types";

    $migrationId = "people_csv_people_" . date("mdy_His");
    $migrationId_tags = "people_csv_tags_" . date("mdy_His");
    $migrationId_pt = "people_csv_pt_" . date("mdy_His");

    # Generate the migration
    $migration = $this->sitefarmMigrate->createMigration('sf_csv_people', $migrationId);
    // Migration is buggy. If it didn't work, show a message
    if ($migration === FALSE) {
      $form_state->setError($form, "We weren't able to generate a migration.");
      return;
    }

    // Finish saving the migration
    $migration->set("label", $label);
    $source = $migration->get("source");
    $source['plugin'] = "sitefarm_csv_people";
    $source['path'] = $this->privateTempStorage->get("file");
    $migration->set("source", $source);

    $migration->set('migration_dependencies', [
      'required' => [
        $migrationId_tags,
        $migrationId_pt
      ]
    ]);

    $this->sitefarmMigrate->saveMigration($migration);

    // Saving the migration for Tags
    $migration_tags = $this->sitefarmMigrate->createMigration('sf_csv_people_tags', $migrationId_tags);
    $migration_tags->set("label", $label_tags);
    $source = $migration_tags->get("source");
    $source['path'] = $this->privateTempStorage->get("file_tags");
    $migration_tags->set("source", $source);

    $this->sitefarmMigrate->saveMigration($migration_tags);

    // Saving the migration for Person Types
    $migration_pt = $this->sitefarmMigrate->createMigration('sf_csv_people_pt', $migrationId_pt);
    $migration_pt->set("label", $label_pt);
    $source = $migration_pt->get("source");
    $source['path'] = $this->privateTempStorage->get("file");
    $migration_pt->set("source", $source);

    $this->sitefarmMigrate->saveMigration($migration_pt);


    drupal_set_message("Migrations created");

    // Redirect to the migration overview
    $form_state->setRedirect("entity.migration_group.list");
  }

  function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to generate the migration script?');
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
    return $this->t('Generate Migration');
  }

  /**
   * {@inheritdoc}.
   */
  protected function getEditableConfigNames() {
    return [];
  }
}
