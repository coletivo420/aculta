<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard\Step;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Etapa 1 do Tour Guiado: Boas-vindas.
 */
class TourWelcomeStepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_tour_welcome_step';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#type' => 'item',
      '#markup' => '<h3>' . $this->t('Bem-vindo ao Ambiente de Aprendizagem ACULTA') . '</h3>'
        . '<p>' . $this->t('Este breve guia vai apresentar as principais ferramentas disponiveis para a sua jornada de estudos.') . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
