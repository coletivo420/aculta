<?php

declare(strict_types=1);

namespace Drupal\aculta_user;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\node\NodeInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Enforces each discussion consumer's author edit window.
 */
final class ParticipationEditWindow {

  /**
   * @param iterable<\Drupal\aculta_user\EditWindowExemptionInterface> $exemptions
   *   Consumer exemptions, such as contextual educator rights in a course.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
    private readonly iterable $exemptions = [],
  ) {}

  /**
   * Returns the access restriction for an author update, or neutral otherwise.
   */
  public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'update' || !$entity instanceof EntityOwnerInterface || (string) $entity->getOwnerId() !== (string) $account->id()) {
      return AccessResult::neutral();
    }

    $adminPermission = $entity->getEntityTypeId() === 'comment'
      ? 'administer comments'
      : 'administer aculta forum posts';
    if ($account->hasPermission($adminPermission)) {
      return AccessResult::neutral();
    }

    [$configName, $key] = $this->settingFor($entity);
    $config = $this->configFactory->get($configName);
    $minutes = max(0, (int) ($config->get($key) ?? 10));
    $publishedAt = $this->publishedAt($entity);
    $cacheContexts = ['user', 'user.permissions'];

    // Consumers decide who keeps contextual rights, for example educators in
    // their course. Only an allowed exemption lifts the window.
    foreach ($this->exemptions as $exemption) {
      $exempt = $exemption->access($entity, $account);
      if ($exempt->isAllowed()) {
        return AccessResult::neutral()
          ->addCacheableDependency($entity)
          ->addCacheableDependency($config)
          ->addCacheableDependency($exempt)
          ->addCacheContexts($cacheContexts);
      }
    }

    if ($minutes === 0) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($config)
        ->addCacheContexts($cacheContexts);
    }

    // Without a first-publication marker the window cannot be measured, so the
    // creation time is the conservative bound: it never gives more time than the
    // policy allows. Content with neither value is locked, not left open.
    if ($publishedAt === NULL) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($config)
        ->addCacheContexts($cacheContexts);
    }

    $secondsRemaining = ($publishedAt + ($minutes * 60)) - $this->time->getRequestTime();
    if ($secondsRemaining <= 0) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($config)
        ->addCacheContexts($cacheContexts);
    }

    // This policy only restricts editing; it must never grant access that the
    // entity access handler would otherwise deny (for example, missing the
    // Core "edit own comments" permission).
    return AccessResult::neutral()
      ->addCacheableDependency($entity)
      ->addCacheableDependency($config)
      ->addCacheContexts($cacheContexts)
      ->setCacheMaxAge($secondsRemaining);
  }

  /**
   * Gets the separate setting owned by the consumer's administrative panel.
   *
   * @return array{0: string, 1: string}
   *   Config object name and integer key.
   */
  private function settingFor(EntityInterface $entity): array {
    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      return ['aculta_forum.settings', 'edit_window_minutes'];
    }

    // Only the two discussion hosts carry field_aculta_discussion, so any other
    // host falls back to the public Forum window.
    if ($entity instanceof CommentInterface) {
      $host = $entity->getCommentedEntity();
      if ($host instanceof NodeInterface && $host->bundle() === 'aculta_lms_topic') {
        return ['aculta_forum_lms.settings', 'edit_window_minutes'];
      }
    }

    return ['aculta_forum.settings', 'edit_window_minutes'];
  }

  /**
   * Reads the immutable first-publication timestamp for the entity.
   */
  private function publishedAt(EntityInterface $entity): ?int {
    $field = match ($entity->getEntityTypeId()) {
      'comment' => 'field_aculta_published_at',
      'aculta_forum_post' => 'first_published',
      default => NULL,
    };
    if ($field !== NULL && $entity->hasField($field) && !$entity->get($field)->isEmpty()) {
      return (int) $entity->get($field)->value;
    }

    if ($entity->hasField('created') && !$entity->get('created')->isEmpty()) {
      return (int) $entity->get('created')->value;
    }

    return NULL;
  }

}
