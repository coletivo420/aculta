<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Experience;

use Drupal\aculta_wiki\Service\WikiContributorWorkspace;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * WE SATIVA adapter; Wiki owns the contributor data and access decisions.
 */
final class WeSativaExperienceProvider implements StudioExperienceProviderInterface {

  public function __construct(
    private readonly ?WikiContributorWorkspace $workspace,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Returns the stable provider identifier.
   */
  public function id(): string {
    return 'we_sativa';
  }

  /**
   * Returns the experience name.
   */
  public function label(): string {
    return 'WE SATIVA';
  }

  /**
   * Returns the purpose used by Studio.
   */
  public function purpose(): string {
    return 'account';
  }

  /**
   * Returns the canonical Studio route name.
   */
  public function routeName(): string {
    return 'aculta_studio.we_sativa';
  }

  /**
   * Determines whether the Wiki provider is available.
   */
  public function available(): bool {
    return $this->workspace !== NULL;
  }

  /**
   * Delegates access to the Wiki workspace service.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return $this->workspace?->access($account)
      ?? AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions']);
  }

  /**
   * Builds the authorized contributor workspace.
   */
  public function build(): array {
    if ($this->workspace === NULL) {
      throw new NotFoundHttpException();
    }
    return $this->workspace->build($this->currentUser);
  }

}
