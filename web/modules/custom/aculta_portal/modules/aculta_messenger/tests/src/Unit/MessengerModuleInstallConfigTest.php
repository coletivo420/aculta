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
    $infoContents = file_get_contents($modulePath . '/aculta_messenger.info.yml');
    self::assertIsString($infoContents);
    $info = Yaml::decode($infoContents);
    self::assertIsArray($info);
    self::assertIsArray($info['dependencies'] ?? NULL);
    $formDisplay = 'core.entity_form_display.node.aculta_notice.default.yml';
    $optionalPath = $modulePath . '/config/optional/' . $formDisplay;
    $installPath = $modulePath . '/config/install/' . $formDisplay;

    self::assertContains('drupal:menu_ui', $info['dependencies']);
    self::assertFileExists($optionalPath);
    self::assertFileDoesNotExist($installPath);

    $configContents = file_get_contents($optionalPath);
    self::assertIsString($configContents);
    $config = Yaml::decode($configContents);
    self::assertIsArray($config);
    self::assertIsArray($config['dependencies'] ?? NULL);
    self::assertIsArray($config['dependencies']['config'] ?? NULL);
    self::assertContains('field.field.node.aculta_notice.field_domain_source', $config['dependencies']['config']);
    self::assertContains('workflows.workflow.editorial', $config['dependencies']['config']);
  }

  /**
   * Structural communication defaults match between install and Config Sync.
   */
  public function testCommunicationDefaultsMatchConfigSync(): void {
    $modulePath = dirname(__DIR__, 3);
    $installContents = file_get_contents($modulePath . '/config/install/aculta_messenger.settings.yml');
    self::assertIsString($installContents);
    $install = Yaml::decode($installContents);
    self::assertIsArray($install);
    $syncContents = file_get_contents(dirname($modulePath, 6) . '/config/sync/aculta_messenger.settings.yml');
    self::assertIsString($syncContents);
    $sync = Yaml::decode($syncContents);
    self::assertIsArray($sync);
    self::assertIsArray($install['email_modes'] ?? NULL);
    self::assertIsArray($sync['email_modes'] ?? NULL);
    self::assertIsArray($install['magazine_events'] ?? NULL);
    self::assertIsArray($sync['magazine_events'] ?? NULL);

    self::assertSame($install['email_modes'], $sync['email_modes']);
    self::assertSame($install['magazine_events'], $sync['magazine_events']);
    self::assertSame($install['private_message_digest_anchor'], $sync['private_message_digest_anchor']);
  }

}
