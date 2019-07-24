<?php

namespace Drupal\sitefarm_migrate_csv_people\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\PrivateTempStoreFactory;
use \Drupal\Core\Extension\ModuleHandlerInterface;
use \Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

class UploadForm extends FormBase implements ContainerInjectionInterface {

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;


  public function __construct(PrivateTempStoreFactory $privateTempStorage, ModuleHandlerInterface $module_handler, FileSystemInterface $file_system, RequestStack $request_stack, Connection $database) {
    $this->privateTempStorage = $privateTempStorage->get("sitefarm_migrate_csv_people");
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->requestStack = $request_stack;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('request_stack'),
      $container->get('database')
    );
  }

  function getFormId() {
    return "sitefarm.migrate_csv_people.login";
  }

  function buildForm(array $form, FormStateInterface $form_state) {

    # Make sure migrate_drupal is disabled. Otherwise are not able to generate a migration (see readme.md)
    # TODO: Find a way go not call Database statically?
    if ($this->moduleHandler->moduleExists('migrate_drupal') && !Database::getConnectionInfo('migrate')) {
      $form['error'] = [
        '#weight' => 0,
        '#markup' => "This module is incompatible with the module migrate_drupal.<br/>
                Please disable module \"migrate_drupal\" or put this line in settings.php: <b>\$databases['migrate']['default'] = \$databases['default']['default'];</b>"
      ];
      return $form;
    }

    # Make sure there is a private folder set
    if ($this->fileSystem->realpath("private://") == FALSE) {
      $form['error'] = [
        '#weight' => 0,
        '#markup' => "ERROR: The private file system is not set. Please configure the private file system in settings.php."
      ];
      return $form;
    }

    # Generate a URL to the help page
    $url = new URL("help.page", ['name' => 'sitefarm_migrate_csv_people']);
    $link = new Link("these instructions", $url);
    $link = $link->toString();

    $form['overview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('People CSV Import'),
    ];

    $form['description'] = [
      '#weight' => 0,
      '#markup' => "This wizard generates a migration to import people from a CSV File.<br/>
                Please follow  " . $link . " to create a CSV
                file of all the people that need to be imported"
    ];

    $form['file'] = [
      '#type' => 'file',
      '#title' => t('CSV file with People'),
      '#description' => t('Upload your CSV file here'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
        'file_validate_size' => [25600000],
      ],
      '#upload_location' => 'private://csv_import/',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Upload File',
    ];

    return $form;
  }

  function validateForm(array &$form, FormStateInterface $form_state) {
    # Hey, some stuff is hardcoded here. Check this if you change the migrate template.
    $needed_rows = 30;

    # Grab the file
    $file = @$this->requestStack->getCurrentRequest()->files->all()['files']['file']->getRealPath(); //->files->file;

    $file = !empty($file) && file_exists($file) ? $file : FALSE;

    if ($file === FALSE) {
      $form_state->setErrorByName('file', t('Please upload a file.'));
      return;
    }

    # Replace all line endings with a proper line ending so the CSV parser can read it
    file_put_contents($file, preg_replace('~\R~u', "\r\n", file_get_contents($file)));

    # We loop through the file line by line and check if each line has the correct number of fields.
    # This will make sure the file can be imported.
    $row = 1;
    $tags = [];
    if (($handle = fopen($file, "rt")) !== FALSE) {
      while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        $num = count($data);
        if ($num != $needed_rows) {
          $form_state->setErrorByName('file', t('The uploaded file is not in correct format. Row ' . $row .
            ' doesn\'t have the right amount of fields (' . $num . ' instead of ' . $needed_rows . '). Please
                    correct the file and try again.'));
          fclose($handle);
          return;
        }

        // Collect the tags and put them in an array for later use
        $tag = explode("|", $data[25]);
        foreach ($tag as $t) {
          $t = trim(trim($t), '"');
          $tags[$t] = $t;
        }
        $row++;
      }
      fclose($handle);
    }
    if ($row == 1) {
      $form_state->setErrorByName('file', t('The uploaded file is not in correct format. Please use the CSV file from the example to create your import file and make sure to save the file with UTF-8 encoding'));
    }

    // Generate a filename
    $filename = "private://csv_import/" . md5(file_get_contents($file)) . ".csv";

    // Generate a file for the tags. We have to create a separate file so we can import and revert all tags
    $filename_tags = "private://csv_import/" . md5(file_get_contents($file)) . "_tags.csv";

    // Make sure the folder exists
    if(!file_exists("private://csv_import")){
      mkdir("private://csv_import");
    }

    // Save the file in the private storage
    file_put_contents($filename, file_get_contents($file));

    // Write the tags into a csv file
    file_put_contents($filename_tags, implode("\n", $tags));

    # Make sure the uploaded file is saved and the same as the stored file
    if (!file_exists($filename) || md5(file_get_contents($file)) != md5(file_get_contents($filename))) {
      $form_state->setErrorByName('file', t('There was a problem uploading the file to the private folder.
            Please make sure the private folder in Drupal is set correctly.'));
    }

    // Save the file location so we can use it in the next step
    $this->privateTempStorage->set('file', $filename);
    $this->privateTempStorage->set('file_tags', $filename_tags);

  }

  function submitForm(array &$form, FormStateInterface $form_state) {
    // Continue to the next step
    $form_state->setRedirect("sitefarm.csv.people.confirm");
  }
}