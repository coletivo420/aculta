<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Form\AccountBioForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Caixa de bio na visão geral da Minha conta. Substitui o resumo de Cursos (aculta_portal).
 */
final class AccountDashboardHooks {

  public function __construct(
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Adiciona a caixa Bio à visão geral. O Portal dispara aculta_portal_dashboard.
   */
  #[Hook('aculta_portal_dashboard_alter')]
  public function dashboardAlter(array &$build, AccountInterface $account): void {
    $build['bio'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'account-bio-card'],
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => t('Bio')],
      'form' => $this->formBuilder->getForm(AccountBioForm::class),
    ];
  }

}
