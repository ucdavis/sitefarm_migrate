<?php

namespace Drupal\sitefarm_migrate_cascade\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sitefarm_migrate_cascade\Controller\CascadeMigrate;
use Drupal\sitefarm_migrate_cascade\Controller\QueueGenerator;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;

class Test extends FormBase {

  function getFormId() {
    return "sitefarm.cascade_migrate.test";
  }

  function buildForm(array $form, FormStateInterface $form_state) {

    $t = MenuLinkContent::load(492)
      ->getPluginId();
    $test = 1;
    return;

    $_SESSION['cascade_cache'] = array();
    $mi = new CascadeMigrate();
    $mi->setSavedCredentials();

    $data = $mi->queryCascadeById("page", "0d9702fc8078218400fe3dfe6f7eae98");

    $test = 1;


    return "";
  }

  function validateForm(array &$form, FormStateInterface $form_state) {

  }

  function submitForm(array &$form, FormStateInterface $form_state) {

  }


  /**
   * {@inheritdoc}.
   */
  protected function getEditableConfigNames() {
    return [];
  }
}
