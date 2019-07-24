<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Wizard;

use Drupal\sitefarm_migrate_wordpress\Wizard\ImportWizard;

/**
 * @coversDefaultClass \Drupal\sitefarm_migrate_wordpress\Form\ReviewForm
 * @group sitefarm_migrate_wordpress
 */
class MockImportWizard extends ImportWizard {

  /**
   * MockImportWizard constructor.
   */
  public function __construct() {
    // Dummy
  }

  /**
   * @param string $string
   * @param array $args
   * @param array $options
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|mixed
   */
  protected function t($string, array $args = [], array $options = []) {
    return $string;
  }


}
