<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_user\Identity\AccountMenuPresentationBuilder;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides identity render data for the institutional account-menu SDC. */
final class AccountMenuPresentationHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.portal_menu_presentation')]
    private readonly AccountMenuPresentationBuilder $builder,
  ) {}

  /**
 * Supplies only Portal-owned, policy-approved display data to the theme. */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $variables['aculta_account_avatar'] = NULL;
    $variables['aculta_account_name'] = NULL;
    if (!$this->currentUser->isAuthenticated()) {
      return;
    }

    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if (!$account instanceof UserInterface) {
      return;
    }
    $presentation = $this->builder->build($account);
    $variables['aculta_account_avatar'] = $presentation['avatar'];
    $variables['aculta_account_name'] = $presentation['account_name'];
    if (isset($variables['page']) && is_array($variables['page'])) {
      $presentation['cacheability']->applyTo($variables['page']);
    }
  }

}
