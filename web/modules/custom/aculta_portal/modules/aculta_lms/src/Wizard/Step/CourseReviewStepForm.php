<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard\Step;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Terceira etapa do Wizard: revisao e confirmacao da publicacao.
 */
class CourseReviewStepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_course_builder_wizard_review';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $cached_values = $form_state->getTemporaryValue('wizard') ?? [];

    $title = !empty($cached_values['title']) ? htmlspecialchars((string) $cached_values['title']) : '-';
    $description = !empty($cached_values['description']) ? nl2br(htmlspecialchars((string) $cached_values['description'])) : '-';
    $lessons = !empty($cached_values['initial_lessons']) ? nl2br(htmlspecialchars((string) $cached_values['initial_lessons'])) : '-';

    $form['review_summary'] = [
      '#type' => 'item',
      '#markup' => '<h3>' . $this->t('Resumo da Configuracao') . '</h3>'
        . '<p><strong>' . $this->t('Titulo:') . '</strong> ' . $title . '</p>'
        . '<p><strong>' . $this->t('Descricao:') . '</strong><br>' . $description . '</p>'
        . '<p><strong>' . $this->t('Aulas:') . '</strong><br>' . $lessons . '</p>',
    ];

    $form['publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Publicar curso ao finalizar'),
      '#default_value' => $cached_values['publish'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $cached_values = $form_state->getTemporaryValue('wizard') ?? [];
    $cached_values['publish'] = (bool) $form_state->getValue('publish');
    $form_state->setTemporaryValue('wizard', $cached_values);
  }

}
