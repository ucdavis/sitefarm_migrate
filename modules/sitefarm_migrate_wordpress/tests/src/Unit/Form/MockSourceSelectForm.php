<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Form;

use Drupal\sitefarm_migrate_wordpress\Form\SourceSelectForm;

/**
 * Mock class for testing
 */
class MockSourceSelectForm extends SourceSelectForm {

  /**
   * @param $field
   * @param $validator
   * @param $dest
   * @param $delta
   *
   * @return array|bool|\Drupal\file\FileInterface|false|null
   */
  protected function fileSaveUpload($field, $validator, $dest, $delta) {
    return FALSE;
  }

  /**
   * @param $message
   */
  protected function drupalSetMessage($message) {
    unset($message);
  }

  /**
   * @param $data
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected function formatSize($data) {
    return $data;
  }

  protected function fileUploadMaxSize() {
    return 25600000;
  }

}
