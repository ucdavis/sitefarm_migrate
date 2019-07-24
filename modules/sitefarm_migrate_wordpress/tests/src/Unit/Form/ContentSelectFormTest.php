<?php

namespace Drupal\Tests\sitefarm_migrate_wordpress\Unit\Form;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * @coversDefaultClass \Drupal\sitefarm_migrate_wordpress\Form\ContentSelectForm
 * @group sitefarm_migrate_wordpress
 */
class ContentSelectFormTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Form State stub.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * @var \Drupal\sitefarm_migrate_wordpress\Form\ContentSelectForm
   */
  protected $formClass;

  /**
   * Create the setup of the $formClass to test against.
   */
  protected function setUp() {
    parent::setUp();

    // Mock the EntityTypeManager
    $this->entityTypeManager = $this->prophesize(EntityTypeManager::CLASS);

    // Mock the formState
    $this->formState = $this->createMock(FormStateInterface::CLASS);

    // Create the form Class to test against
    $this->formClass = new MockContentSelectForm($this->entityTypeManager->reveal());

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
    $this->assertEquals('sitefarm_migrate_wordpress_content_select_form', $this->formClass->getFormId());
  }

  /**
   * Tests the buildForm method.
   *
   * @see ::buildForm()
   */
  public function testBuildForm() {
    $form = [];
    $result = $this->formClass->buildForm($form, $this->formState);

    $this->assertEquals('select', $result['blog_post_type']['#type']);
    $this->assertEquals('container', $result['blog_post_type_container']['#type']);
    $this->assertEquals('select', $result['blog_post_type_container']['blog_post_type_article_type']['#type']);
    $this->assertEquals('select', $result['blog_post_type_container']['blog_post_type_article_category']['#type']);
    $this->assertEquals('select', $result['page_type']['#type']);
    $this->assertEquals('container', $result['page_type_container']['#type']);
    $this->assertEquals('select', $result['page_type_container']['page_type_article_type']['#type']);
    $this->assertEquals('select', $result['page_type_container']['page_type_article_category']['#type']);
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
    $formClass = new MockContentSelectForm($this->entityTypeManager->reveal());

    $form = [];
    $result = $formClass->submitForm($form, $this->formState);

    $this->assertNull($result);
  }

}
