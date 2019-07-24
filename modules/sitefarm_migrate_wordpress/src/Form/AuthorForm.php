<?php

namespace Drupal\sitefarm_migrate_wordpress\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Simple wizard step form.
 */
class AuthorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitefarm_migrate_wordpress_author_form';
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
      '#title' => t('Username of default content author:'),
      '#default_value' => (isset($cached_values['default_author'])) ? $cached_values['default_author'] : \Drupal::currentUser()
        ->getAccountName(),
      '#autocomplete_path' => 'user/autocomplete',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('perform_user_migration')) {
      $account = user_load_by_name($form_state->getValue('default_author'));
      if (!$account) {
        $form_state->setErrorByName('default_author_uid', $this->t('@name is not a valid username',
          ['@name' => $form_state->getValue('default_author')]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    $account = user_load_by_name($form_state->getValue('default_author'));
    if ($account) {
      $cached_values['default_author'] = $form_state->getValue('default_author');
    }

    $form_state->setTemporaryValue('wizard', $cached_values);
  }

}
