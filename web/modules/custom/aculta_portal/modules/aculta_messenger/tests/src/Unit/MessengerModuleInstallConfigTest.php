<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects optional configuration from requiring global site configuration.
 */
#[Group('aculta_messenger')]
final class MessengerModuleInstallConfigTest extends UnitTestCase {

  /**
   * The notice form display depends on global Domain and editorial config.
   */
  public function testNoticeFormDisplayRemainsOptional(): void {
    $modulePath = dirname(__DIR__, 3);
    $info = Yaml::decode(file_get_contents($modulePath . '/aculta_messenger.info.yml'));
    $formDisplay = 'core.entity_form_display.node.aculta_notice.default.yml';
    $optionalPath = $modulePath . '/config/optional/' . $formDisplay;
    $installPath = $modulePath . '/config/install/' . $formDisplay;

    self::assertContains('drupal:menu_ui', $info['dependencies']);
    self::assertFileExists($optionalPath);
    self::assertFileDoesNotExist($installPath);

    $config = Yaml::decode(file_get_contents($optionalPath));
    self::assertContains('field.field.node.aculta_notice.field_domain_source', $config['dependencies']['config']);
    self::assertContains('workflows.workflow.editorial', $config['dependencies']['config']);
  }

  /**
   * Structural communication defaults match between install and Config Sync.
   */
  public function testCommunicationDefaultsMatchConfigSync(): void {
    $modulePath = dirname(__DIR__, 3);
    $install = Yaml::decode(file_get_contents($modulePath . '/config/install/aculta_messenger.settings.yml'));
    $sync = Yaml::decode(file_get_contents(dirname($modulePath, 6) . '/config/sync/aculta_messenger.settings.yml'));

    self::assertSame($install['email_modes'], $sync['email_modes']);
    self::assertSame($install['magazine_events'], $sync['magazine_events']);
    self::assertArrayNotHasKey('private_message_digest_anchor', $sync);
  }

}
