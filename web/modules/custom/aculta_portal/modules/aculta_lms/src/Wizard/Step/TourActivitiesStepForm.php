<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard\Step;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Etapa 3 do Tour Guiado: Atividades e Avaliacoes.
 */
class TourActivitiesStepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_tour_activities_step';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['activities_info'] = [
      '#type' => 'item',
      '#markup' => '<h3>' . $this->t('Atividades Praticas e Exercicios') . '</h3>'
        . '<p>' . $this->t('Cada aula pode conter atividades interativas, questionarios e leituras complementares. Seu progresso e salvo automaticamente a cada etapa concluida.') . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
