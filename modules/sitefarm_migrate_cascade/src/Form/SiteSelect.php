<?php

namespace Drupal\sitefarm_migrate_cascade\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\user\PrivateTempStoreFactory;

class SiteSelect extends FormBase implements ContainerInjectionInterface {

  /**
   * @var \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate
   */
  protected $cascadeMigrate;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;

  /**
   * @param \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate $cascade_migrate
   * @param \Drupal\user\PrivateTempStoreFactory $privateTempStorage
   */
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
    return "sitefarm.cascade_migrate.site_select";
  }

  function buildForm(array $form, FormStateInterface $form_state) {
    # Grab the website list
    $websites = $this->cascadeMigrate->get_website_list();

    if ($websites === FALSE || empty($websites)) {
      $form['description'] = [
        "#markup" => t("No websites were retrieved.
            Check your credentials and permissions in Cascade")
      ];
      return $form;
    }

    $form['overview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cascade Migration - Step 2/4 - Site Select'),
    ];

    $form['description'] = [
      '#weight' => 0,
      '#markup' => "Select what website you want to import from Cascade. If your website is not displayed,
              make sure the user you used to log in has administrator privileges on this website in Cascade"
    ];

    $form['site_id'] = [
      '#type' => 'select',
      '#title' => t('Cascade Website'),
      '#maxlength' => 255,
      '#description' => t('Select the website you wish to import. Only websites you have access to are shown'),
      "#options" => $websites,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Select',
    ];

    return $form;
  }

  function validateForm(array &$form, FormStateInterface $form_state) {
    $site = $form_state->getValue('site_id');
    if (empty($site)) {
      $form_state->setErrorByName("site_id", "Please select a site");
    }
  }

  function submitForm(array &$form, FormStateInterface $form_state) {
    # Set the variables
    $this->privateTempStorage->set('site_id', $form_state->getValue('site_id'));

    # Continue to the next step
    $form_state->setRedirect("sitefarm.cascade_migrate_site.options");
  }

}
