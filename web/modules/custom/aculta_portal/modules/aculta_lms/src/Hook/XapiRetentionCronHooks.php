<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Xapi\XapiRetention;
use Drupal\aculta_lms\Xapi\XapiStatementRetentionPurger;
use Drupal\aculta_lms\Xapi\XapiTokenSettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Runs the bounded monthly xAPI raw statement retention task. */
final class XapiRetentionCronHooks {

  public function __construct(
    private readonly XapiStatementRetentionPurger $purger,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
 *
 */
  #[Hook('cron')]
  public function cron(): void {
    $months = $this->configFactory->get(XapiTokenSettings::CONFIG_NAME)->get(XapiRetention::KEY_MONTHS);
    $this->purger->purge(XapiRetention::monthsFrom($months));
  }

}
