<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_links\Entity\LinkItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Restricts a page item to the owner of its page and destination.
 */
final class LinkItemAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    private readonly TimeInterface $time,
    private readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('datetime.time'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer aculta links')) {
      return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
    }

    if (!$entity instanceof LinkItem || !in_array($operation, [
      'view',
      'update',
      'delete',
      'view revision',
      'view all revisions',
      'revert',
      'delete revision',
    ], TRUE)) {
      return AccessResult::forbidden()->cachePerPermissions()->addCacheableDependency($entity);
    }

    $page = $entity->get('page')->entity;
    $link = $entity->get('link')->entity;
    if (!$page || !$link) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    if ($operation === 'view' && $entity->isPublished() && $page->isPublished() && $link->isPublished()) {
      $access = AccessResult::allowed()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($page)
        ->addCacheableDependency($link)
        ->cachePerPermissions();

      // Private previews deliberately show future/expired publication windows;
      // the preview controller first checks update access to the parent page.
      if ($this->routeMatch->getRouteName() === 'aculta_links.page_preview') {
        return $access->addCacheContexts(['route.name']);
      }

      [$active, $next_transition] = $this->scheduleState($entity);
      $access = AccessResult::allowedIf($active)
        ->addCacheableDependency($entity)
        ->addCacheableDependency($page)
        ->addCacheableDependency($link)
        ->cachePerPermissions()
        ->addCacheContexts(['route.name']);
      if ($next_transition !== NULL) {
        $access->setCacheMaxAge(max(0, $next_transition - $this->time->getRequestTime()));
      }
      return $access;
    }

    $can_manage = $account->hasPermission('manage own aculta links');
    $owns_item = (string) $entity->getOwnerId() === (string) $account->id();
    $owns_page = (string) $page->getOwnerId() === (string) $account->id();
    $owns_link = (string) $link->getOwnerId() === (string) $account->id();
    $result = AccessResult::allowedIf($can_manage && $owns_item && $owns_page && $owns_link)
      ->cachePerUser()
      ->cachePerPermissions()
      ->addCacheableDependency($entity)
      ->addCacheableDependency($page)
      ->addCacheableDependency($link);

    return $result;
  }

  /**
   * Returns schedule visibility and its next cache-expiry timestamp.
   *
   * @return array{bool, int|null}
   *   Whether the item is active, and the next future boundary if any.
   */
  private function scheduleState(LinkItem $item): array {
    $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $from_value = $item->get('available_from')->value;
    $until_value = $item->get('available_until')->value;
    $from = $from_value
      ? \DateTimeImmutable::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $from_value, $timezone)
      : NULL;
    $until = $until_value
      ? \DateTimeImmutable::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $until_value, $timezone)
      : NULL;
    if (($from_value && !$from) || ($until_value && !$until)) {
      return [FALSE, NULL];
    }

    $now = $this->time->getRequestTime();
    if ($from !== NULL && $from->getTimestamp() > $now) {
      return [FALSE, $from->getTimestamp()];
    }
    if ($until !== NULL && $until->getTimestamp() <= $now) {
      return [FALSE, NULL];
    }
    return [TRUE, $until?->getTimestamp()];
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'manage own aculta links')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer aculta links'));
  }

}
