<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Form;

use Drupal\sitefarm_migrate_wordpress\Form\VocabularySelectForm;

/**
 * Mock class for testing
 */
class MockVocabularySelectForm extends VocabularySelectForm {

  /**
   * @return array|\Drupal\Core\Entity\EntityInterface[]|\Drupal\taxonomy\Entity\Vocabulary[]
   */
  protected function loadVocabularies() {
    return [];
  }
}
