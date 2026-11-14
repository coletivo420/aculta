<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard\Step;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Etapa 4 do Tour Guiado: Conclusao e Certificados.
 */
class TourCompleteStepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_tour_complete_step';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['complete_info'] = [
      '#type' => 'item',
      '#markup' => '<h3>' . $this->t('Conclusao e Certificado Digital') . '</h3>'
        . '<p>' . $this->t('Ao finalizar todas as atividades do curso, seu certificado com validacao publica e QR code ficara disponivel na sua area do estudante.') . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
