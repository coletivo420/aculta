<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Experience;

use Drupal\aculta_commerce\Access\GreenStashAccessPolicy;
use Drupal\aculta_studio\Experience\StudioExperienceProviderInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Independent Studio experience for store management.
 *
 * No order/product listing is exposed until entity-level ACL is integrated.
 */
final class GreenStashExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
    private readonly GreenStashAccessPolicy $accessPolicy,
  ) {}

  public function id(): string {
    return 'green_stash';
  }

  public function label(): string {
    return 'Green Stash';
  }

  public function purpose(): string {
    return 'account';
  }

  public function routeName(): string {
    return 'aculta_commerce.green_stash';
  }

  public function available(): bool {
    return TRUE;
  }

  public function access(AccountInterface $account): AccessResultInterface {
    return $this->accessPolicy->access($account);
  }

  public function build(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'green-stash-entry'],
      'notice' => [
        '#type' => 'item',
        '#markup' => new TranslatableMarkup('Green Stash: store management is being prepared. Product, order and payment operations are not yet available in this interface.'),
      ],
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

}
