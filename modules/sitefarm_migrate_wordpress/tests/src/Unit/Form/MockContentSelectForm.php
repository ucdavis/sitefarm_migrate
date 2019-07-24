<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Form;

use Drupal\sitefarm_migrate_wordpress\Form\ContentSelectForm;

/**
 * Mock class for testing
 */
class MockContentSelectForm extends ContentSelectForm {

  /**
   * @return array
   */
  protected function getArticleTypes() {
    return ['Category', 'Blog'];
  }

  /**
   * @return array
   */
  protected function getNewsTypes() {
    return ['University', 'dummy'];
  }

  /**
   * @return array|\Drupal\node\NodeTypeInterface[]
   */
  protected function nodeTypeGetTypes() {
    return [];
  }

}
