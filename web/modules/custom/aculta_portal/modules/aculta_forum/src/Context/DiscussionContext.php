<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Context;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Immutable discussion context with cacheable access decisions.
 */
final class DiscussionContext implements DiscussionContextInterface {

  /**
   * Cacheability collected from the host and access results.
   */
  private readonly CacheableMetadata $cacheability;

  public function __construct(
    private readonly EntityInterface $hostEntity,
    private readonly string $type,
    private readonly string $stableId,
    private readonly AccessResultInterface $readAccess,
    private readonly AccessResultInterface $writeAccess,
    private readonly AccessResultInterface $moderationAccess,
    private readonly Url $canonicalUrl,
    private readonly string $visibilityPolicy,
    private readonly string $discoveryPolicy,
    private readonly string $notificationPolicy,
  ) {
    $this->cacheability = CacheableMetadata::createFromObject($hostEntity)
      ->addCacheableDependency($readAccess)
      ->addCacheableDependency($writeAccess)
      ->addCacheableDependency($moderationAccess);
  }

  /**
   * Returns the entity that owns the discussion.
   */
  public function getHostEntity(): EntityInterface {
    return $this->hostEntity;
  }

  /**
   * Returns the stable context type.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Returns the stable context identifier.
   */
  public function getStableId(): string {
    return $this->stableId;
  }

  /**
   * Returns the host entity's read access result.
   */
  public function getReadAccess(): AccessResultInterface {
    return $this->readAccess;
  }

  /**
   * Returns the host entity's write access result.
   */
  public function getWriteAccess(): AccessResultInterface {
    return $this->writeAccess;
  }

  /**
   * Returns the host entity's moderation access result.
   */
  public function getModerationAccess(): AccessResultInterface {
    return $this->moderationAccess;
  }

  /**
   * Returns the canonical host URL.
   */
  public function getCanonicalUrl(): Url {
    return $this->canonicalUrl;
  }

  /**
   * Returns the visibility policy identifier.
   */
  public function getVisibilityPolicy(): string {
    return $this->visibilityPolicy;
  }

  /**
   * Returns the discovery policy identifier.
   */
  public function getDiscoveryPolicy(): string {
    return $this->discoveryPolicy;
  }

  /**
   * Returns the notification policy identifier.
   */
  public function getNotificationPolicy(): string {
    return $this->notificationPolicy;
  }

  /**
   * Returns cache contexts collected from the host and access results.
   *
   * @return string[]
   *   The cache contexts.
   */
  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  /**
   * Returns cache tags collected from the host and access results.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  /**
   * Returns the maximum cache age collected from the context dependencies.
   *
   * @return int
   *   The maximum cache age in seconds, or -1 for permanent.
   */
  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

}
