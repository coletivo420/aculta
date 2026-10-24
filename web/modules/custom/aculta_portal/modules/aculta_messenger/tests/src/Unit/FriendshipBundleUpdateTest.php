<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Config\ConfigInstaller;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the additive installer for the friendship Message bundle. */
#[CoversNothing]
#[Group('aculta_messenger')]
final class FriendshipBundleUpdateTest extends UnitTestCase {

  /**
   * The update installs missing defaults without replacing existing config. */
  public function testUpdateInstallsDefaultFriendshipMessageConfiguration(): void {
    $installer = $this->createMock(ConfigInstaller::class);
    $installer->expects(self::once())->method('installDefaultConfig')->with('module', 'aculta_messenger');
    $container = new ContainerBuilder();
    $container->set('config.installer', $installer);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10007();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

}
