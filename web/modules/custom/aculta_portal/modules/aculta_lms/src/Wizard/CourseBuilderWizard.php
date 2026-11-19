<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard;

use Drupal\aculta_lms\Wizard\Step\CourseReviewStepForm;
use Drupal\aculta_lms\Wizard\Step\CourseStructureStepForm;
use Drupal\aculta_lms\Wizard\Step\CourseDetailsStepForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ctools\Wizard\FormWizardBase;

/**
 * CTools Form Wizard multietapa para criacao e estruturacao no Course Builder.
 */
class CourseBuilderWizard extends FormWizardBase {

  /**
   * {@inheritdoc}
   */
  public function getWizardLabel(): string {
    return (string) $this->t('Course Builder Wizard');
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineLabel(): string {
    return (string) $this->t('Identificador do Curso');
  }

  /**
   * {@inheritdoc}
   */
  public function getTempstoreId(): string {
    return 'aculta_lms.course_builder_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations($cached_values): array {
    return [
      'details' => [
        'title' => (string) $this->t('Dados do Curso'),
        'form' => CourseDetailsStepForm::class,
      ],
      'structure' => [
        'title' => (string) $this->t('Estrutura e Aulas'),
        'form' => CourseStructureStepForm::class,
      ],
      'review' => [
        'title' => (string) $this->t('Revisao e Publicacao'),
        'form' => CourseReviewStepForm::class,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName(): string {
    return 'aculta_lms.course_builder_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function finish(array &$form, FormStateInterface $form_state): void {
    $cached_values = $form_state->getTemporaryValue('wizard') ?? [];

    // Mensagem informativa ao concluir o percurso do wizard.
    $this->messenger()->addStatus($this->t('O percurso de estruturacao do curso foi concluido com sucesso.'));

    // Redireciona para a rota padrao de cursos/estrutura caso disponivel.
    if (!empty($cached_values['course_id'])) {
      $form_state->setRedirect('entity.group.canonical', ['group' => $cached_values['course_id']]);
    }

    parent::finish($form, $form_state);
  }

}
