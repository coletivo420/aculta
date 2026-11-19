<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Contract;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_cgu\Entity\Contribution;

/**
 * Domain-owned validation and selective application for a proposal. */
interface ContributionAdapterInterface {

  /**
   * Stable machine name, registered by the service container. */
  public function id(): string;

  /**
   * Stable owner module machine name. */
  public function moduleId(): string;

  /**
   * Returns the active domain purpose from trusted server context. */
  public function purpose(?EntityInterface $target): string;

  /**
   * Returns cacheable, domain-owned access for a stored proposal operation. */
  public function entityAccess(AccountInterface $account, Contribution $contribution, string $operation): AccessResultInterface;

  /**
   * Checks whether this account may submit this operation. */
  public function submitAccess(AccountInterface $account, string $intent, ?EntityInterface $target): bool;

  /**
   * Revalidates this account's authority to review the proposal. */
  public function reviewAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool;

  /**
   * Revalidates reviewer authority to apply this proposal in the domain. */
  public function applyAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool;

  /**
   * Resolves a target from its UUID under this adapter's fixed entity policy. */
  public function loadTarget(string $intent, ?string $targetUuid): ?EntityInterface;

  /**
   * Validates and normalizes payload using server-side field allowlists. */
  public function validatePayload(string $intent, array $payload, ?EntityInterface $target = NULL): array;

  /**
   * Validates source-owned proposal context separately from applicable values.
   *
   * @param string $intent
   *   Adapter-defined operation intent.
   * @param array<string, mixed> $sourceData
   *   Context that is private and never passed to the target applier.
   *
   * @return array<string, mixed>
   *   Normalized, adapter-allowlisted source context.
   */
  public function validateSourceData(string $intent, array $sourceData): array;

  /**
   * Validates domain-specific application choices for selected payload keys.
   *
   * @param string $intent
   *   Adapter-defined operation intent.
   * @param list<string> $selectedFields
   *   Applicable payload keys selected by the reviewer.
   * @param array<string, mixed> $decisionData
   *   Adapter-specific choices, such as append/replace modes for list fields.
   *
   * @return array<string, mixed>
   *   Normalized, adapter-allowlisted application choices.
   */
  public function validateDecisionData(string $intent, array $selectedFields, array $decisionData): array;

  /**
   * Returns the current origin version or a stable fingerprint. */
  public function currentVersion(string $intent, ?EntityInterface $target): string;

  /**
   * Records the domain-owned notification for a committed proposal outcome.
   *
   * Called inside the entity transaction so a failed event enqueue rolls back
   * the decision revision as well.
   */
  public function recordDecision(Contribution $proposal, string $outcome): void;

  /**
   * Revalidates policy and applies selected keys only. */
  public function apply(string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface;

}
