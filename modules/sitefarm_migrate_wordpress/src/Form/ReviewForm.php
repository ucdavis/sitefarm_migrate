<?php

namespace Drupal\sitefarm_migrate_wordpress\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Simple wizard step form.
 */
class ReviewForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitefarm_migrate_wordpress_review_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo: Display details of the configuration.
    // @link: https://www.drupal.org/node/2742289
    $form['description'] = [
      '#markup' => $this->t('When you submit this form, migration processes will be created and you will be left at the migration dashboard.<br/><br/>'),
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    $cached_values['group_id'] = "sitefarmwordpress";
    $cached_values['prefix'] = "sf_";
    $form_state->setTemporaryValue('wizard', $cached_values);
  }

}
