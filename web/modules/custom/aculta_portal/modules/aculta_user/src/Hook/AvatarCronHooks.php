<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Avatar\AvatarAssetCleanup;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cron do aculta_user: limpeza de assets temporários de avatar (ver AvatarAssetCleanup).
 */
final class AvatarCronHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.avatar_asset_cleanup')]
    private readonly AvatarAssetCleanup $cleanup,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->cleanup->run();
  }

}
