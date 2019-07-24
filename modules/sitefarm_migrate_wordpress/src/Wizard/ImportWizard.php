<?php

namespace Drupal\sitefarm_migrate_wordpress\Wizard;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ctools\Wizard\FormWizardBase;
use Drupal;

class ImportWizard extends FormWizardBase {

  /**
   * @var mixed
   */
  protected $generator;

  /**
   * {@inheritdoc}
   */
  public function getOperations($cached_values) {
    $steps = [
      'source_select' => [
        'form' => 'Drupal\sitefarm_migrate_wordpress\Form\SourceSelectForm',
        'title' => $this->t('Data source'),
      ],
      'authors' => [
        'form' => 'Drupal\sitefarm_migrate_wordpress\Form\AuthorForm',
        'title' => $this->t('Authors'),
      ],
      'vocabulary_select' => [
        'form' => 'Drupal\sitefarm_migrate_wordpress\Form\VocabularySelectForm',
        'title' => $this->t('Vocabularies'),
      ],
      'content_select' => [
        'form' => 'Drupal\sitefarm_migrate_wordpress\Form\ContentSelectForm',
        'title' => $this->t('Content'),
      ],
    ];
    $steps += [
      'review' => [
        'form' => 'Drupal\sitefarm_migrate_wordpress\Form\ReviewForm',
        'title' => $this->t('Review'),
        'values' => ['wordpress_content_type' => ''],
      ],
    ];
    return $steps;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return 'sitefarm_migrate_wordpress.wizard.import.step';
  }

  /**
   * {@inheritdoc}
   */
  public function finish(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');

    # Dependency injection doesnt work for wizards yet
    if (!$this->generator) {
      /** @var \Drupal\sitefarm_migrate_wordpress\WordPressMigrationGenerator generator */
      $this->generator = Drupal::service('sitefarm_migrate_wordpress.wordpressmigrationgenerator');
    }

    $this->generator->createMigrations($cached_values);
    // Go to the dashboard for this migration group.
    $form_state->setRedirect('entity.migration_group.list');
    parent::finish($form, $form_state);
  }

}
