<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Form;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @coversDefaultClass \Drupal\sitefarm_migrate_wordpress\Form\VocabularySelectForm
 * @group sitefarm_migrate_wordpress
 */
class VocabularySelectFormTest extends UnitTestCase {

  /**
   * Form State stub.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * @var \Drupal\sitefarm_migrate_wordpress\Form\VocabularySelectForm
   */
  protected $formClass;

  /**
   * Create the setup of the $formClass to test against.
   */
  protected function setUp() {
    parent::setUp();

    // Mock the formState
    $this->formState = $this->createMock(FormStateInterface::CLASS);

    // Create the form Class to test against
    $this->formClass = new MockVocabularySelectForm();

    // Create a translation stub for the t() method
    $translator = $this->getStringTranslationStub();
    $this->formClass->setStringTranslation($translator);
  }

  /**
   * Tests the getFormId method.
   *
   * @see ::getFormId()
   */
  public function testGetFormId() {
    $this->assertEquals('sitefarm_migrate_wordpress_vocabulary_select_form', $this->formClass->getFormId());
  }

  /**
   * Tests the buildForm method.
   *
   * @see ::buildForm()
   */
  public function testBuildForm() {
    $form = [];
    $result = $this->formClass->buildForm($form, $this->formState);

    $this->assertArrayHasKey('#markup', $result['overview']);
    $this->assertEquals('select', $result['tag_vocabulary']['#type']);
    $this->assertEquals('select', $result['category_vocabulary']['#type']);
  }

  /**
   * Tests the validateForm method.
   *
   * @see ::validateForm()
   */
  public function testValidateForm() {
    $form = [];
    $result = $this->formClass->validateForm($form, $this->formState);

    $this->assertNull($result);
  }

  /**
   * Tests the submitForm method.
   *
   * @see ::submitForm()
   */
  public function testSubmitForm() {

    $form = [];
    $result = $this->formClass->submitForm($form, $this->formState);

    $this->assertNull($result);
  }

}
