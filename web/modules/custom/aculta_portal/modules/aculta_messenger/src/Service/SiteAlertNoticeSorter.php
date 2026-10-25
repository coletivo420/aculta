<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\site_alert\Entity\SiteAlert;

/**
 * Sorts active site alerts for stable, newest-first notice pagination.
 */
final class SiteAlertNoticeSorter {

  /**
   * Sorts by creation time, using the entity ID only as a stable tie-breaker.
   *
   * @param \Drupal\site_alert\Entity\SiteAlert[] $alerts
   *   Active alerts already filtered by access.
   *
   * @return \Drupal\site_alert\Entity\SiteAlert[]
   *   Alerts ordered newest first.
   */
  public static function sort(array $alerts): array {
    usort($alerts, static function (SiteAlert $a, SiteAlert $b): int {
      return (self::createdTime($b) <=> self::createdTime($a))
        ?: ((int) $b->id() <=> (int) $a->id());
    });
    return $alerts;
  }

  /**
   * Returns the alert timestamp, or zero for legacy records without one.
   */
  private static function createdTime(SiteAlert $alert): int {
    if (!$alert->hasField('created') || $alert->get('created')->isEmpty()) {
      return 0;
    }
    return (int) $alert->get('created')->value;
  }

}
