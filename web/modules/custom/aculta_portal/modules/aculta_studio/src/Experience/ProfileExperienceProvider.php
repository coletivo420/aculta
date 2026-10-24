<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Separate Studio experience for a member's community-facing identity.
 */
final class ProfileExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(private readonly ModuleHandlerInterface $modules) {}

  public function id(): string {
    return 'perfil';
  }

  public function label(): string {
    return 'Meu Perfil';
  }

  public function purpose(): string {
    return 'account';
  }

  public function routeName(): string {
    return 'aculta_studio.profile';
  }

  public function available(): bool {
    return $this->modules->moduleExists('aculta_user');
  }

  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->andIf(AccessResult::allowedIf($this->available()))
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-profile-entry'],
      'link' => [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Editar meu perfil'),
        '#url' => Url::fromRoute($this->routeName()),
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site'],
      ],
    ];
  }

}
