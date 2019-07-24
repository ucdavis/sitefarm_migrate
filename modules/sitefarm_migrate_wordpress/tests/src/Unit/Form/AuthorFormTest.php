<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Form;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * @coversDefaultClass \Drupal\sitefarm_migrate_wordpress\Form\AuthorForm
 * @group sitefarm_migrate_wordpress
 */
class AuthorFormTest extends UnitTestCase {

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Form State stub.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * @var \Drupal\sitefarm_migrate_wordpress\Form\AuthorForm
   */
  protected $formClass;

  /**
   * Create the setup of the $formClass to test against.
   */
  protected function setUp() {
    parent::setUp();

    // Mock the configFactory
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::CLASS);

    // Mock the formState
    $this->formState = $this->createMock(FormStateInterface::CLASS);

    // Create the form Class to test against
    $this->formClass = new MockAuthorForm();

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
    $this->assertEquals('sitefarm_migrate_wordpress_author_form', $this->formClass->getFormId());
  }

  /**
   * Tests the buildForm method.
   *
   * @see ::buildForm()
   */
  public function testBuildForm() {
    $form = [];
    $result = $this->formClass->buildForm($form, $this->formState);

    $this->assertEquals('textfield', $result['default_author']['#type']);
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
    $formClass = new MockAuthorForm();

    $form = [];
    $result = $formClass->submitForm($form, $this->formState);

    $this->assertNull($result);
  }

}
