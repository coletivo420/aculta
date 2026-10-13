<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Bio\AccountBioCard;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Caixa de bio na visão geral da Minha conta. Substitui o resumo de Cursos (aculta_portal).
 */
final class AccountDashboardHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.account_bio_card')]
    private readonly AccountBioCard $bioCard,
  ) {}

  /**
   * Adiciona a caixa Bio à visão geral. O Portal dispara aculta_portal_dashboard.
   */
  #[Hook('aculta_portal_dashboard_alter')]
  public function dashboardAlter(array &$build, AccountInterface $account): void {
    $build['bio'] = $this->bioCard->build($account);
  }

}
