<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Applies event entity access to public event and individual ICS routes.
 */
final class EventRouteAccess {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Checks access to an event route without revealing hidden event existence.
   */
  public function access(string $slug, ?string $id = NULL): AccessResult {
    $events = $this->entityTypeManager->getStorage('aculta_event')->loadByProperties([
      'field_slug' => $slug,
    ]);
    $event = $events ? reset($events) : NULL;
    if ($event === NULL) {
      return AccessResult::forbidden()->cachePerUser()->setCacheMaxAge(0);
    }

    return $event->access('view', $this->currentUser, TRUE);
  }

}
