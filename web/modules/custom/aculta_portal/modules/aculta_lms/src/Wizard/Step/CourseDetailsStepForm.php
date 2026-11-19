<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard\Step;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Primeira etapa do Wizard: dados basicos do curso.
 */
class CourseDetailsStepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_course_builder_wizard_details';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $cached_values = $form_state->getTemporaryValue('wizard') ?? [];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Titulo do Curso'),
      '#required' => TRUE,
      '#default_value' => $cached_values['title'] ?? '',
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Descricao e Apresentacao'),
      '#default_value' => $cached_values['description'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $cached_values = $form_state->getTemporaryValue('wizard') ?? [];
    $cached_values['title'] = $form_state->getValue('title');
    $cached_values['description'] = $form_state->getValue('description');
    $form_state->setTemporaryValue('wizard', $cached_values);
  }

}
