<?php

namespace Drupal\sitefarm_migrate_wordpress\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Simple wizard step form.
 */
class SourceSelectForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitefarm_migrate_wordpress_source_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo Make sure we have a private directory configured.
    // @link https://www.drupal.org/node/2742291
    // @todo Support importing directly from WP admin interface.
    // @link https://www.drupal.org/node/2742293
    // @todo Support importing from the directory directly.
    // @link https://www.drupal.org/node/2742295
    // @todo Support importing from a database.
    // @link https://www.drupal.org/node/2742299

    // Start clean in case we came here via Previous.
    $form_state->setTemporaryValue('wizard', []);

    $form['overview'] = [
      '#markup' => $this->t('<p>This wizard supports importing into your Drupal site from a WordPress blog. To be able to use this wizard, you must have an XML file exported from the blog.</p>'),
    ];
    $form['description'] = [
      '#markup' => $this->t('<p>You will be led through a series of steps, allowing you to customize what will be imported into Drupal and how it will be mapped. At the end of this process, a migration group will be generated.</p>'),
    ];
    $form['wxr_file'] = [
      '#type' => 'file',
      '#title' => $this->t('WordPress exported file (WXR)'),
      '#description' => $this->t('Select an exported WordPress file. Maximum file size is @size.',
        ['@size' => format_size(file_upload_max_size())]),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $all_files = $this->getRequest()->files->get('files', []);
    if (empty($all_files['wxr_file'])) {
      $form_state->setErrorByName('wxr_file', $this->t('You must upload a file to continue.'));
    }
    else {
      $file = $all_files['wxr_file']->getRealPath();
      if (!strstr(file_get_contents($file), "<wp:wxr_version>")) {
        $form_state->setErrorByName('wxr_file', $this->t('This does not seem like a wordpress file'));
      }
      $xmlcheck = simplexml_load_file($file);
      if ($xmlcheck === FALSE) {
        $form_state->setErrorByName('wxr_file', $this->t('The XML in this file is corrupt. Make sure the file is UTF-8 encoded'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['xml']];
    if ($file = file_save_upload('wxr_file', $validators, 'public://', 0)) {
      $cached_values = $form_state->getTemporaryValue('wizard');
      $cached_values['file_uri'] = $file->getFileUri();
      $form_state->setTemporaryValue('wizard', $cached_values);
      // @todo: Preprocess the file
      // @link https://www.drupal.org/node/2742301
    }
    else {
      drupal_set_message($this->t('File upload failed.'));
    }
  }

}
