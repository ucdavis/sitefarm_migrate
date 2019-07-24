<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Form;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @coversDefaultClass \Drupal\sitefarm_migrate_wordpress\Form\SourceSelectForm
 * @group sitefarm_migrate_wordpress
 */
class SourceSelectFormTest extends UnitTestCase {

  /**
   * Form State stub.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * @var \Drupal\sitefarm_migrate_wordpress\Form\SourceSelectForm
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
    $this->formClass = new MockSourceSelectForm();

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
    $this->assertEquals('sitefarm_migrate_wordpress_source_select_form', $this->formClass->getFormId());
  }

  /**
   * Tests the buildForm method.
   *
   * @see ::buildForm()
   */
  public function testBuildForm() {
    $form = [];
    $result = $this->formClass->buildForm($form, $this->formState);

    $this->assertEquals('file', $result['wxr_file']['#type']);
    $this->assertArrayHasKey('#markup', $result['overview']);
    $this->assertArrayHasKey('#markup', $result['description']);
  }

  /**
   * Tests the submitForm method.
   *
   * @see ::submitForm()
   */
  public function testSubmitForm() {
    $formClass = new MockSourceSelectForm();

    $translator = $this->getStringTranslationStub();
    $formClass->setStringTranslation($translator);

    $form = [];
    $result = $formClass->submitForm($form, $this->formState);

    $this->assertNull($result);
  }

}
