<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_contributions\Kernel;

use Drupal\aculta_contributions\Contract\ContributionAdapterInterface;
use Drupal\aculta_contributions\Entity\Contribution;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Test adapter with a single safe user-name field. */
final class ContributionTestAdapter implements ContributionAdapterInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly bool $failAfterApply = FALSE,
  ) {}

  /**
   * {@inheritdoc} */
  public function id(): string {
    return 'test_user';
  }

  /**
   * {@inheritdoc} */
  public function moduleId(): string {
    return 'aculta_user';
  }

  /**
   * {@inheritdoc} */
  public function purpose(?EntityInterface $target): string {
    return 'account';
  }

  /**
   * {@inheritdoc} */
  public function entityAccess(AccountInterface $account, Contribution $contribution, string $operation): AccessResultInterface {
    $owner = (int) $contribution->getOwnerId() === (int) $account->id();
    $allowed = match ($operation) {
      'view', 'view revision' => ($owner && $account->hasPermission('view own aculta contributions'))
        || $account->hasPermission('view any aculta contributions'),
      'update' => $account->hasPermission('review aculta contributions')
        || ($owner && $contribution->get('state')->value === 'needs_changes'
          && $account->hasPermission('submit aculta contributions')),
      default => FALSE,
    };
    return AccessResult::allowedIf($allowed)->cachePerUser()->cachePerPermissions();
  }

  /**
   * {@inheritdoc} */
  public function submitAccess(AccountInterface $account, string $intent, ?EntityInterface $target): bool {
    return $intent === 'update' && $target !== NULL && $account->hasPermission('submit aculta contributions');
  }

  /**
   * {@inheritdoc} */
  public function reviewAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return $intent === 'update' && $target !== NULL && $reviewer->hasPermission('review aculta contributions');
  }

  /**
   * {@inheritdoc} */
  public function applyAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return $this->reviewAccess($reviewer, $intent, $target);
  }

  /**
   * {@inheritdoc} */
  public function loadTarget(string $intent, ?string $targetUuid): ?EntityInterface {
    if ($intent !== 'update' || $targetUuid === NULL) {
      return NULL;
    }
    $targets = $this->entityTypeManager->getStorage('user')->loadByProperties(['uuid' => $targetUuid]);
    $target = reset($targets);
    return $target instanceof EntityInterface ? $target : NULL;
  }

  /**
   * {@inheritdoc} */
  public function validatePayload(string $intent, array $payload, ?EntityInterface $target = NULL): array {
    if ($intent !== 'update' || array_keys($payload) !== ['name'] || !is_string($payload['name']) || trim($payload['name']) === '') {
      throw new \InvalidArgumentException('Only a non-empty account name is allowed.');
    }
    return ['name' => trim($payload['name'])];
  }

  /**
   * {@inheritdoc}
   */
  public function validateSourceData(string $intent, array $sourceData): array {
    if ($intent !== 'update' || $sourceData !== []) {
      throw new \InvalidArgumentException('This fixture has no source context.');
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateDecisionData(string $intent, array $selectedFields, array $decisionData): array {
    if ($intent !== 'update' || $decisionData !== []) {
      throw new \InvalidArgumentException('This fixture has no application choices.');
    }
    return [];
  }

  /**
   * {@inheritdoc} */
  public function currentVersion(string $intent, ?EntityInterface $target): string {
    return hash('sha256', $intent . ':' . ($target?->label() ?? 'missing'));
  }

  /**
   * {@inheritdoc}
   */
  public function recordDecision(Contribution $proposal, string $outcome): void {}

  /**
   * {@inheritdoc} */
  public function apply(string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
    if (!$target || !hash_equals($expectedVersion, $this->currentVersion($intent, $target)) || array_keys($selectedPayload) !== ['name']) {
      throw new \RuntimeException('The source changed or the selected field is invalid.');
    }
    $target->set('name', $selectedPayload['name']);
    $target->save();
    if ($this->failAfterApply) {
      throw new \RuntimeException('Fixture failure after the source entity save.');
    }
    return $target;
  }

}
