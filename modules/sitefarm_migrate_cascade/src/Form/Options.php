<?php

namespace Drupal\sitefarm_migrate_cascade\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityTypeManager;

class Options extends ConfigFormBase implements ContainerInjectionInterface {

  /**
   * @var array
   */
  private $blockSelection = [];

  /**
   * The different content types
   * @var array
   */
  protected $contentTypes = [];

  /**
   * The different article types
   * @var array
   */
  protected $articleTypes = [];

  /**
   * The different news types
   * @var array
   */
  protected $newsTypes = [];

  /**
   * @var array|bool
   */
  protected $rootFolders = [];

  /**
   * @var array|bool
   */
  protected $dontImportFolders = [];

  /**
   * @var \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate
   */
  protected $cascadeMigrate;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;


  /**
   * @param \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate $cascade_migrate
   * @param \Drupal\user\PrivateTempStoreFactory $privateTempStorage
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  public function __construct(CascadeMigrate $cascade_migrate, PrivateTempStoreFactory $privateTempStorage, EntityTypeManager $entityTypeManager) {
    $this->cascadeMigrate = $cascade_migrate;
    $this->privateTempStorage = $privateTempStorage->get("sitefarm_migrate_cascade");
    $this->entityTypeManager = $entityTypeManager;

    # TODO: Remove todo when item is done
    $this->blockSelection = [
      "menu" => "Menu Structure",
      "pages" => "Standard and Flex Pages",
      "people" => "People",
      "feature_blocks" => "Feature Blocks"
    ];

    $this->contentTypes = [
      "no_import" => "Do not import",
      "sf_page" => "Basic Page (Default)",
      "sf_article" => "Article"
    ];

    $this->dontImportFolders = [
      "_common" => TRUE,
      "_user-blocks" => TRUE,
      "_configuration" => TRUE,
      "_internal" => TRUE,
      "local_resources" => TRUE,
      "manager_resources" => TRUE
    ];

    $this->articleTypes = $this->getArticleTypes();
    $this->newsTypes = $this->getNewsTypes();

    # Grab the website list
    $this->rootFolders = $this->cascadeMigrate->getSiteRootFolders();

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sitefarm_migrate_cascade.cascade_migrate'),
      $container->get('user.private_tempstore'),
      $container->get('entity_type.manager')
    );
  }

  protected function getArticleTypes(){
    $articleTypes = [];
    $types = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => "sf_article_type"]);
    foreach($types as $id => $term){
      $articleTypes[$id] = $term->get('name')->getValue()[0]['value'];
    }
    return $articleTypes;
  }

  protected function getNewsTypes(){
    $newsTypes = [];
    $types = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => "sf_article_category"]);
    foreach($types as $id => $term){
      $newsTypes[$id] = $term->get('name')->getValue()[0]['value'];
    }
    return $newsTypes;
  }

  /**
   * Return if this folder should be imported.
   * @param string $folder
   * @return bool
   */
  protected function shouldImportFolder($folder){
    return !isset($this->dontImportFolders[trim(strtolower($folder))]);
  }

  public  function getFormId() {
    return "sitefarm.cascade_migrate.options";
  }

  public function buildForm(array $form, FormStateInterface $form_state) {


    if ($this->rootFolders === FALSE || !is_array($this->rootFolders)) {
      $form['description'] = [
        '#weight' => 0,
        '#markup' => "No folders found?"
      ];
    }

    $form['overview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cascade Migration - Step 3/4 - Options'),
    ];

    $form['content_types'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Item Selection'),
      '#markup' => "Please select the items you would like to import from Cascade:",
    );

    $form['content_types']['content_types'] = [
      '#type' => 'checkboxes',
      '#options' => $this->blockSelection,
      '#default_value' => ['pages', 'menu', 'people', 'feature_blocks']
    ];

    $form['pages_import'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Page Import Options'),
      '#markup' => "Please select the content types for each root folder. The items within this folder will be imported in this content type:",
      '#states' => [
        'visible' => [
          ':input[name="content_types[pages]"]' => ['checked' => TRUE],
        ]
      ]
    ];

    foreach ($this->rootFolders as $folder => $type) {
      if(!$this->shouldImportFolder($folder)){
        continue;
      }

      $form['pages_import']["folder_" . $folder . "fieldset"] = [
        '#type' => 'fieldset',
        '#title' => $folder
      ];

      $form['pages_import']["folder_" . $folder . "fieldset"]['folders']["folder_" . $folder] = [
        '#type' => "select",
        '#title' => "Content Type",
        '#options' => $this->contentTypes,
        '#default_value' => $type,
        '#states' => [
          'visible' => [
            ':input[name="content_types[pages]"]' => ['checked' => TRUE],
          ]
        ],
      ];
      $form['pages_import']["folder_" . $folder . "fieldset"]['folders']["folder_" . $folder . "_atype"] = [
        '#type' => "select",
        '#title' => "Article Type",
        '#options' => $this->articleTypes,
        '#default_value' => array_keys($this->articleTypes)[0],
        '#states' => [
          'visible' => [
            ':input[name="folder_' . $folder . '"]' => ['value' => "sf_article"],
          ]
        ],
      ];
      $form['pages_import']["folder_" . $folder . "fieldset"]['folders']["folder_" . $folder . "_category"] = [
        '#type' => "select",
        '#title' => "Category",
        '#options' => $this->newsTypes,
        '#default_value' => array_keys($this->newsTypes)[0],
        '#states' => [
          'visible' => [
            ':input[name="folder_' . $folder . '"]' => ['value' => "sf_article"],
          ]
        ],
      ];
      $form['pages_import']["folder_" . $folder . "fieldset"]['folders']["folder_" . $folder . "_tags"] = [
        '#type' => "textfield",
        '#title' => "Tags",
        '#states' => [
          'visible' => [
            ':input[name="content_types[pages]"]' => ['checked' => TRUE],
          ]
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Next',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $content_types = $form_state->getValue('content_types');
    $types = 0;
    foreach ($content_types as $content_type => $enabled) {
      if ($content_type == $enabled) {
        $types++;
      }
    }
    if ($types < 1) {
      $form_state->setErrorByName('content_types', t('You need to select something to import'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $content_types = $form_state->getValue('content_types');
    $types = [];
    $folderConfig = [];
    $folderArticleTypes = [];
    $folderCategories = [];
    $folderTags = [];

    # Grab all the content types we need to import
    foreach ($content_types as $content_type => $enabled) {
      if ($enabled !== FALSE && $enabled !== 0 && isset($this->blockSelection[$content_type])) {
        $types[$content_type] = $content_type;
      }
    }

    # Grab the content types for each folder
    foreach($this->rootFolders as $folder => $type){
      if(empty($type) || !$this->shouldImportFolder($folder)){
        continue;
      }
      $type = $form_state->getValue('folder_' . $folder);
      $folderConfig[$type][] = $folder;

      $atype = $type == "sf_article" ? $form_state->getValue('folder_' . $folder . "_atype") : 0;
      $folderArticleTypes[$folder] = $atype;
      $category = $type == "sf_article" ? $form_state->getValue('folder_' . $folder . "_category") : 0;
      $folderCategories[$folder] = $category;
      $tags = $form_state->getValue('folder_' . $folder . "_tags");
      $folderTags[$folder] = $tags;
    }

    # Store it in the temp config
    $this->privateTempStorage->set("content_types", $types);
    $this->privateTempStorage->set("folder_config", $folderConfig);
    $this->privateTempStorage->set("folder_config_atypes", $folderArticleTypes);
    $this->privateTempStorage->set("folder_config_categories", $folderCategories);
    $this->privateTempStorage->set("folder_config_tags", $folderTags);

    # Continue to the next step
    $form_state->setRedirect("sitefarm.cascade_migrate.confirm");
  }

  /**
   * {@inheritdoc}.
   */
  protected function getEditableConfigNames() {
    return [
      'sitefarm_migrate.settings',
    ];
  }
}
