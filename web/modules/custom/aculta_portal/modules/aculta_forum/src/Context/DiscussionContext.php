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

  public function getHostEntity(): EntityInterface {
    return $this->hostEntity;
  }

  public function getType(): string {
    return $this->type;
  }

  public function getStableId(): string {
    return $this->stableId;
  }

  public function getReadAccess(): AccessResultInterface {
    return $this->readAccess;
  }

  public function getWriteAccess(): AccessResultInterface {
    return $this->writeAccess;
  }

  public function getModerationAccess(): AccessResultInterface {
    return $this->moderationAccess;
  }

  public function getCanonicalUrl(): Url {
    return $this->canonicalUrl;
  }

  public function getVisibilityPolicy(): string {
    return $this->visibilityPolicy;
  }

  public function getDiscoveryPolicy(): string {
    return $this->discoveryPolicy;
  }

  public function getNotificationPolicy(): string {
    return $this->notificationPolicy;
  }

  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

}
