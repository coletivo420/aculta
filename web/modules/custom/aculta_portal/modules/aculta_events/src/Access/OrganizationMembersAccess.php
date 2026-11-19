<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_user\Organization\OrganizationAccessService;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Acesso à tela de gestão de membros e delegações da organização.
 */
final class OrganizationMembersAccess {

  public function __construct(
    private readonly ?OrganizationAccessService $organizationAccess,
  ) {}

  /**
   * Checks access to the organization's member management screen.
   */
  public function access(AccountInterface $account, EventOrganization $aculta_event_organization): AccessResultInterface {
    if ($account->hasPermission('administer aculta events') || $account->hasPermission('administer aculta claims')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Apenas organizações reivindicadas possuem gestão interna de membros.
    if ($aculta_event_organization->isUnclaimed()) {
      return AccessResult::forbidden('Organização não reivindicada não possui gestão de membros.')
        ->addCacheableDependency($aculta_event_organization);
    }

    // Titular tem acesso irrestrito à gestão de membros.
    if ($aculta_event_organization->getOwnerId() === (int) $account->id()) {
      return AccessResult::allowed()->cachePerUser()->addCacheableDependency($aculta_event_organization);
    }

    // Coproprietário com poder delegado de gerenciar membros.
    if ($this->organizationAccess?->canManageOrganization($account, (string) $aculta_event_organization->id())) {
      return AccessResult::allowed()->cachePerUser()->addCacheableDependency($aculta_event_organization);
    }

    return AccessResult::forbidden('Apenas o titular ou coproprietário com delegação podem gerenciar acessos.')
      ->cachePerUser()
      ->addCacheableDependency($aculta_event_organization);
  }

}
