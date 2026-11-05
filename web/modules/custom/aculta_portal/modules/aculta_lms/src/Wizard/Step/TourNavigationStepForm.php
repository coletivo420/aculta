<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard\Step;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Etapa 2 do Tour Guiado: Navegacao e Sala de Aula.
 */
class TourNavigationStepForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_tour_navigation_step';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['navigation_info'] = [
      '#type' => 'item',
      '#markup' => '<h3>' . $this->t('Sala de Aula e Trilhas de Aprendizagem') . '</h3>'
        . '<p>' . $this->t('Na pagina do curso, voce encontra a listagem de aulas e modulos organizados em ordem pedagogica. Utilize o menu lateral ou os botoes de navegacao para avancar entre os conteudos.') . '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
