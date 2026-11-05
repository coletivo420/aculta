<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard\Step;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Segunda etapa do Wizard: modulos e aulas do curso.
 */
class CourseStructureStepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_course_builder_wizard_structure';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $cached_values = $form_state->getTemporaryValue('wizard') ?? [];

    $form['structure_summary'] = [
      '#type' => 'item',
      '#markup' => '<p>' . $this->t('Defina a sequencia pedagogica inicial de modulos e aulas.') . '</p>',
    ];

    $form['initial_lessons'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Topicos ou Aulas Iniciais (uma por linha)'),
      '#default_value' => $cached_values['initial_lessons'] ?? '',
      '#description' => $this->t('As aulas informadas serao criadas ou associadas a trilha pedagogica do curso.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $cached_values = $form_state->getTemporaryValue('wizard') ?? [];
    $cached_values['initial_lessons'] = $form_state->getValue('initial_lessons');
    $form_state->setTemporaryValue('wizard', $cached_values);
  }

}
