<?php

namespace Drupal\sitefarm_migrate_cascade\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate;
use Drupal;
use Drupal\Component\Utility\UrlHelper;
use Drupal\sitefarm_migrate_cascade\Cascade;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

class LoginForm extends FormBase implements ContainerInjectionInterface {


  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate
   */
  protected $cascadeMigrate;

  /**
   * @var \Drupal\Component\Utility\UrlHelper
   */
  protected $urlHelper;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $privateTempStorage;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;


  public function __construct(ModuleHandlerInterface $module_handler, CascadeMigrate $cascade_migrate, PrivateTempStoreFactory $privateTempStorage,  Connection $database) {
    $this->moduleHandler = $module_handler;
    $this->cascadeMigrate = $cascade_migrate;
    $this->privateTempStorage = $privateTempStorage->get("sitefarm_migrate_cascade");
    $this->database = $database;
    $this->initializeUrlHelper();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('sitefarm_migrate_cascade.cascade_migrate'),
      $container->get('user.private_tempstore'),
      $container->get('database')
    );
  }

  /**
   * Initialize the URL Helper
   */
  function initializeUrlHelper() {
    $this->urlHelper = new UrlHelper();
  }



  function getFormId() {
    return "sitefarm.cascade_migrate.login";
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

    $form['overview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cascade Migration - Step 1/4 - Login'),
    ];

    $form['description'] = [
      '#weight' => 0,
      '#markup' => "This wizard generates a migration to import your website from a Cascade instance. In this step
                we log in to Cascade. You can choose to use the default login, or to login with a custom account"
    ];

    $form['default_login'] = [
      '#type' => "select",
      '#options' => ["default" => 'Default', "custom" => 'Custom'],
      '#default_value' => "Default",
      '#description' => t('Use default login or custom login')
    ];

    $form['cascade_url'] = [
      '#type' => 'textfield',
      '#title' => t('Cascade URL'),
      '#maxlength' => 255,
      '#description' => t('The Cascade URl used to log in to cascade'),
      "#default_value" => "http://cascade.ucdavis.edu",
      '#states' => [
        'visible' => [
          ':input[name="default_login"]' => ['value' => 'custom'],
        ]
      ]
    ];
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => t('Cascade Username'),
      '#maxlength' => 255,
      '#default_value' => "",
      '#description' => t('The Cascade username. Make sure this user has full access to the website you want to import'),
      '#states' => [
      'visible' => [
        ':input[name="default_login"]' => ['value' => 'custom'],
      ]
    ]
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => t('Cascade User Password'),
      '#maxlength' => 255,
      '#description' => t('The Cascade user password'),
      '#states' => [
        'visible' => [
          ':input[name="default_login"]' => ['value' => 'custom'],
        ]
      ]
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Check login',
    ];
    $form['actions']['reset'] = [
      '#type' => 'button',
      '#value' => 'Reset',
    ];

    return $form;
  }

  function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('op') == "Check login") {
      if($form_state->getValue("default_login") == "custom") {
        $url = $form_state->getValue('cascade_url');
        $user = $form_state->getValue('username');
        $password = $form_state->getValue('password');

        # Check url
        if ($this->urlHelper->isValid($url) !== TRUE) {
          $form_state->setErrorByName('cascade_url', t('The cascade url is incorrect'));
        }
        else {
          # Check username
          if (empty($user)) {
            $form_state->setErrorByName('username', t('Username is empty'));
            # Check password
          }
          elseif (empty($password)) {
            $form_state->setErrorByName('password', t('Password is empty'));
            # Check if we can log in with these credentials
          }
          else {

            $this->cascadeMigrate->setConnectionDetails($user, $password, $url);
            if (!$this->cascadeMigrate->check_cascade_login()) {
              $form_state->setErrorByName("", "Login Incorrect");
            }

          }
        }
      }else{
        if($this->cascadeMigrate->haserror()){
          $form_state->setErrorByName('default_login', $this->cascadeMigrate->haserror());
        }
      }
    }
    elseif ($form_state->getValue('op') == "Reset") {

      # Reset the session variables
      drupal_set_message(t("Login information cleared"));
      $this->privateTempStorage->set('username', "");
      $this->privateTempStorage->set('password', "");
      $this->privateTempStorage->set('cascade_url', "");
      return new RedirectResponse(new Url("sitefarm.cascade_migrate.login"));
    }
  }

  function submitForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->getValue('op') == "Check login") {
      # Set the variables
      $this->privateTempStorage->set('username', $form_state->getValue('username'));
      $this->privateTempStorage->set('password', $this->cascadeMigrate->simple_encrypt($form_state->getValue('password')));
      $this->privateTempStorage->set('cascade_url', $form_state->getValue('cascade_url'));

      # Continue to the next step
      $form_state->setRedirect("sitefarm.cascade_migrate.site_select");

    }
  }
}