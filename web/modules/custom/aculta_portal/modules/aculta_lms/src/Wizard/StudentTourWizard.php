<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Wizard;

use Drupal\aculta_lms\Wizard\Step\TourCompleteStepForm;
use Drupal\aculta_lms\Wizard\Step\TourActivitiesStepForm;
use Drupal\aculta_lms\Wizard\Step\TourNavigationStepForm;
use Drupal\aculta_lms\Wizard\Step\TourWelcomeStepForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ctools\Wizard\FormWizardBase;

/**
 * CTools Form Wizard multietapa para o tour guiado do estudante no LMS.
 */
class StudentTourWizard extends FormWizardBase {

  /**
   * {@inheritdoc}
   */
  public function getWizardLabel(): string {
    return (string) $this->t('Tour do Estudante');
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineLabel(): string {
    return (string) $this->t('Identificador do Tour');
  }

  /**
   * {@inheritdoc}
   */
  public function getTempstoreId(): string {
    return 'aculta_lms.student_tour_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations($cached_values): array {
    return [
      'welcome' => [
        'title' => (string) $this->t('Boas-vindas'),
        'form' => TourWelcomeStepForm::class,
      ],
      'navigation' => [
        'title' => (string) $this->t('Navegacao e Sala de Aula'),
        'form' => TourNavigationStepForm::class,
      ],
      'activities' => [
        'title' => (string) $this->t('Atividades e Avaliacoes'),
        'form' => TourActivitiesStepForm::class,
      ],
      'complete' => [
        'title' => (string) $this->t('Conclusao e Certificados'),
        'form' => TourCompleteStepForm::class,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName(): string {
    return 'aculta_lms.student_tour_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function finish(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('Voce concluiu o tour guiado! Bons estudos.'));
    parent::finish($form, $form_state);
  }

}
