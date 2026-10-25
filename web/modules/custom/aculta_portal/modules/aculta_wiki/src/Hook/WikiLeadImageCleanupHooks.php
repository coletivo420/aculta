<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\aculta_wiki\Cleanup\WikiLeadImageCleanup;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs bounded cleanup of stale, unreferenced lead images during cron.
 */
final class WikiLeadImageCleanupHooks {

  public function __construct(
    #[Autowire(service: 'aculta_wiki.lead_image_cleanup')]
    private readonly WikiLeadImageCleanup $cleanup,
  ) {}

  /**
   * Removes only old orphan images after Portal reference checks.
   */
  #[Hook('cron')]
  public function cleanupWikiLeadImages(): void {
    $this->cleanup->cleanup();
  }

}
