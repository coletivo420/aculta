<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Context;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Describes a discussion attached to its real host entity.
 */
interface DiscussionContextInterface extends CacheableDependencyInterface {

  /**
   * Returns the entity that owns the discussion. */
  public function getHostEntity(): EntityInterface;

  /**
   * Returns a stable, namespaced context type. */
  public function getType(): string;

  /**
   * Returns a stable key that does not depend on a request slug. */
  public function getStableId(): string;

  /**
   * Returns current host-entity read access. */
  public function getReadAccess(): AccessResultInterface;

  /**
   * Returns whether the current account may write in this discussion. */
  public function getWriteAccess(): AccessResultInterface;

  /**
   * Returns a fail-closed moderation access result for this phase. */
  public function getModerationAccess(): AccessResultInterface;

  /**
   * Returns the canonical URL of the host entity. */
  public function getCanonicalUrl(): Url;

  /**
   * Returns the public visibility policy identifier. */
  public function getVisibilityPolicy(): string;

  /**
   * Returns the discovery policy identifier. */
  public function getDiscoveryPolicy(): string;

  /**
   * Returns the notification policy identifier. */
  public function getNotificationPolicy(): string;

}
