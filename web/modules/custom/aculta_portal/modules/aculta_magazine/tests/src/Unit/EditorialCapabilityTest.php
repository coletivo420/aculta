<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\Access\EditorialCapability;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests role capability resolution and its cache dependency. */
final class EditorialCapabilityTest extends UnitTestCase {

  /**
   * Selected roles receive only their configured ACULTA capability.
   */
  public function testConfiguredRoleGrantsOnlyTheAcultaCapability(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['authoring_roles', ['content_editor']],
      ['review_roles', ['reviewer']],
      ['comments_roles', ['administrator']],
    ]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_magazine.settings')->willReturn($config);
    $capability = new EditorialCapability($factory);
    $editor = $this->createMock(AccountInterface::class);
    $editor->method('getRoles')->willReturn(['authenticated', 'content_editor']);

    self::assertTrue($capability->access(EditorialCapability::AUTHORING, $editor)->isAllowed());
    self::assertFalse($capability->access(EditorialCapability::REVIEW, $editor)->isAllowed());
    self::assertContains('config:aculta_magazine.settings', $capability->access(EditorialCapability::AUTHORING, $editor)->getCacheTags());
  }

  /**
   * Rejects unknown capability IDs rather than granting access.
   */
  public function testUnknownCapabilityFailsClosed(): void {
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $account = $this->createMock(AccountInterface::class);
    self::assertTrue((new EditorialCapability($factory))->access('not-a-capability', $account)->isForbidden());
  }

}
