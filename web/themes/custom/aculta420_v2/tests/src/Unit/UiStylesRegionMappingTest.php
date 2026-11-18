<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Protects the reviewed ACULTA420 V2 regional UI Styles mapping.
 */
#[CoversNothing]
#[Group('aculta420_v2')]
final class UiStylesRegionMappingTest extends UnitTestCase {

  /**
   * Applies the neutral raised compact surface only to the first sidebar.
   */
  public function testOnlySidebarFirstReceivesTheReviewedSurfaceStyles(): void {
    $root = dirname(__DIR__, 7);
    $settings = Yaml::parseFile($root . '/config/sync/aculta420_v2.settings.yml');
    self::assertIsArray($settings);

    $thirdParty = $settings['third_party_settings'] ?? NULL;
    self::assertIsArray($thirdParty);
    $uiStylesPage = $thirdParty['ui_styles_page'] ?? NULL;
    self::assertIsArray($uiStylesPage);
    $regions = $uiStylesPage['regions'] ?? NULL;
    self::assertIsArray($regions);
    self::assertSame(['sidebar_first'], array_keys($regions));

    $sidebar = $regions['sidebar_first'] ?? NULL;
    self::assertIsArray($sidebar);
    self::assertSame('', $sidebar['extra'] ?? NULL);
    $selected = $sidebar['selected'] ?? NULL;
    self::assertIsArray($selected);
    self::assertSame([
      'surface_style' => 'aculta-surface aculta-surface--tone-raised',
      'spacing_section' => 'aculta-surface aculta-surface--spacing-compact',
      'surface_radius_style' => 'aculta-surface--radius-md',
    ], $selected);
  }

}
