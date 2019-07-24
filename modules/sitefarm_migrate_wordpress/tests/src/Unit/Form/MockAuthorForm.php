<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Form;

use Drupal\sitefarm_migrate_wordpress\Form\AuthorForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Mock class for testing
 */
class MockAuthorForm extends AuthorForm {

  /**
   * @param $name
   *
   * @return bool|object
   */
  protected function userLoadByName($name) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');

    $form['overview'] = [
      '#markup' => $this->t('<p>Choose an existing Drupal account which will be the author of all WordPress content imported.</p>'),
    ];

    $form['default_author'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username of default content author:'),
      '#default_value' => (isset($cached_values['default_author'])) ? $cached_values['default_author'] : "Admin",
      '#autocomplete_path' => 'user/autocomplete',
    ];
    return $form;
  }

}
