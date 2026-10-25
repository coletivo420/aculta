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

}
