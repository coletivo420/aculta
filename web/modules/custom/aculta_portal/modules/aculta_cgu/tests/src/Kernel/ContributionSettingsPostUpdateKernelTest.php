<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies migration of the consolidated CGU configuration owner.
 */
#[Group('aculta_cgu')]
final class ContributionSettingsPostUpdateKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'options', 'views'];

  /**
   * Moves configured flood limits and removes the former config ID.
   */
  public function testFloodSettingsMoveToCguOwner(): void {
    $this->container->get('module_installer')->install(['aculta_cgu'], FALSE);
    $this->container->get('config.storage')->write('aculta_contributions.settings', [
      'flood_limit' => 23,
      'flood_window' => 7200,
    ]);

    require_once DRUPAL_ROOT . '/' . $this->container->get('extension.list.module')->getPath('aculta_cgu') . '/aculta_cgu.post_update.php';
    aculta_cgu_post_update_move_flood_settings_to_cgu();

    self::assertSame(23, $this->config('aculta_cgu.settings')->get('flood_limit'));
    self::assertSame(7200, $this->config('aculta_cgu.settings')->get('flood_window'));
    self::assertTrue($this->config('aculta_contributions.settings')->isNew());
  }

}
