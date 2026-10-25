<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Config\ConfigInstaller;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests additive Message configuration updates. */
#[CoversNothing]
#[Group('aculta_messenger')]
final class MessengerDefaultConfigUpdateTest extends UnitTestCase {

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

  /**
   * The additive private-message Mailer update installs default field config.
   */
  public function testUpdateInstallsPrivateMessageMailerMarkerConfiguration(): void {
    $installer = $this->createMock(ConfigInstaller::class);
    $installer->expects(self::once())->method('installDefaultConfig')->with('module', 'aculta_messenger');
    $container = new ContainerBuilder();
    $container->set('config.installer', $installer);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10008();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

}
