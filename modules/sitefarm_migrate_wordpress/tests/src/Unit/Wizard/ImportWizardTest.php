<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Wizard;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sitefarm_migrate_wordpress\Wizard\ImportWizard;

/**
 * @coversDefaultClass \Drupal\sitefarm_migrate_wordpress\Form\ReviewForm
 * @group sitefarm_migrate_wordpress
 */
class ImportWizardTest extends UnitTestCase {

  /**
   * @var
   */
  protected $wizard;

  /**
   * Create the setup of the $formClass to test against.
   */
  protected function setUp() {
    parent::setUp();

    $this->wizard = new MockImportWizard();

  }

  /**
   * Tests the getOperations method.
   *
   * @see ::getOperations()
   */
  public function testGetOperations() {
    $values = $this->wizard->getOperations([]);
    $this->assertEquals('Drupal\sitefarm_migrate_wordpress\Form\SourceSelectForm', $values['source_select']['form']);
    $this->assertEquals('Drupal\sitefarm_migrate_wordpress\Form\AuthorForm', $values['authors']['form']);
    $this->assertEquals('Drupal\sitefarm_migrate_wordpress\Form\VocabularySelectForm', $values['vocabulary_select']['form']);
    $this->assertEquals('Drupal\sitefarm_migrate_wordpress\Form\ContentSelectForm', $values['content_select']['form']);
    $this->assertEquals('Drupal\sitefarm_migrate_wordpress\Form\ReviewForm', $values['review']['form']);
  }

  /**
   * Tests the getRouteName method.
   *
   * @see ::getRouteName()
   */
  public function testGetRouteName() {
    $this->assertEquals('sitefarm_migrate_wordpress.wizard.import.step', $this->wizard->getRouteName());
  }


}
