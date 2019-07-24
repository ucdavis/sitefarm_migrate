<?php

namespace Drupal\sitefarm_migrate_wordpress\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Simple wizard step form.
 */
class ContentSelectForm extends FormBase implements ContainerInjectionInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->articleTypes = $this->getArticleTypes();
    $this->newsTypes = $this->getNewsTypes();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitefarm_migrate_wordpress_content_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');

    $form['overview'] = [
      '#markup' => $this->t('<p>WordPress blogs contain two primary kinds of content: blog posts and pages. Here you may choose what types of Drupal nodes to create from each of those content types, or omit one or both from the import entirely.</p>'),
    ];

    // Get destination node type(s)
    $node_types = node_type_get_types();
    $options = ['' => $this->t('Do not import')];
    foreach ($node_types as $node_type => $info) {
      $options[$node_type] = $info->get('name');
    }

    $form['blog_post_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Import WordPress blog posts as'),
      '#default_value' => (isset($cached_values['post']['type'])) ? $cached_values['post']['type'] : 'sf_article',
      '#options' => $options,
    ];

    $form['blog_post_type_container'] = [
      '#type' => 'container',
      '#attributes' => ['style' => ['margin-bottom: 2em; margin-left: 2em']],
      '#states' => [
        'visible' => [
          ':input[name="blog_post_type"]' => ['value' => "sf_article"],
        ],
      ],
    ];
    $form['blog_post_type_container']['blog_post_type_article_type'] = [
      '#type' => "select",
      '#title' => "Article Type",
      '#options' => $this->articleTypes,
      '#default_value' => (isset($cached_values['post']['article_type'])) ? $cached_values['post']['article_type'] : array_keys($this->articleTypes)[0],
      '#states' => [
        'visible' => [
          ':input[name="blog_post_type"]' => ['value' => "sf_article"],
        ],
      ],
    ];
    $form['blog_post_type_container']['blog_post_type_article_category'] = [
      '#type' => "select",
      '#title' => "Category",
      '#options' => $this->newsTypes,
      '#default_value' => (isset($cached_values['post']['article_category'])) ? $cached_values['post']['article_category'] : array_keys($this->newsTypes)[0],
    ];

    $form['page_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Import WordPress pages as'),
      '#default_value' => (isset($cached_values['page']['type'])) ? $cached_values['page']['type'] : 'sf_page',
      '#options' => $options,
    ];

    $form['page_type_container'] = [
      '#type' => 'container',
      '#attributes' => ['style' => ['margin-bottom: 2em; margin-left: 2em']],
      '#states' => [
        'visible' => [
          ':input[name="page_type"]' => ['value' => "sf_article"],
        ],
      ],
    ];
    $form['page_type_container']['page_type_article_type'] = [
      '#type' => "select",
      '#title' => "Article Type",
      '#options' => $this->articleTypes,
      '#default_value' => (isset($cached_values['page']['article_type'])) ? $cached_values['page']['article_type'] : array_keys($this->articleTypes)[0],
    ];
    $form['page_type_container']['page_type_article_category'] = [
      '#type' => "select",
      '#title' => "Category",
      '#options' => $this->newsTypes,
      '#default_value' => (isset($cached_values['page']['article_category'])) ? $cached_values['page']['article_category'] : array_keys($this->newsTypes)[0],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('blog_post_type') && $form_state->getValue('page_type')) {
      $form_state->setErrorByName('', $this->t('Please select at least one content type to import.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    $cached_values['post']['type'] = $form_state->getValue('blog_post_type');
    $cached_values['post']['article_type'] = $form_state->getValue('blog_post_type_article_type');
    $cached_values['post']['article_category'] = $form_state->getValue('blog_post_type_article_category');
    $cached_values['page']['type'] = $form_state->getValue('page_type');
    $cached_values['page']['article_type'] = $form_state->getValue('page_type_article_type');
    $cached_values['page']['article_category'] = $form_state->getValue('page_type_article_category');
    $form_state->setTemporaryValue('wizard', $cached_values);
  }

  /**
   * @return array
   */
  protected function getArticleTypes() {
    $articleTypes = [];
    $types = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => "sf_article_type"]);
    foreach ($types as $id => $term) {
      $articleTypes[$id] = $term->get('name')->getValue()[0]['value'];
    }
    return $articleTypes;
  }

  /**
   * @return array
   */
  protected function getNewsTypes() {
    $newsTypes = [];
    $types = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => "sf_article_category"]);
    foreach ($types as $id => $term) {
      $newsTypes[$id] = $term->get('name')->getValue()[0]['value'];
    }
    return $newsTypes;
  }

}
