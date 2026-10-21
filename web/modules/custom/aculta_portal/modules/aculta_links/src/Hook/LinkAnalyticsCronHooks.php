<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_links\Service\LinkAnalytics;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applies the configured analytics retention during Drupal cron.
 */
final class LinkAnalyticsCronHooks {

  public function __construct(
    #[Autowire(service: 'aculta_links.analytics')]
    private readonly LinkAnalytics $analytics,
  ) {}

  /**
   * Deletes daily aggregate records outside the 90-day retention period.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->analytics->purgeExpired();
  }

}
