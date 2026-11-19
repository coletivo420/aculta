<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Hook\AppearanceHooks;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Portal's appearance-related library integration.
 *
 * @group aculta_portal
 */
final class AppearanceHooksTest extends UnitTestCase {

  /**
   * Keeps Locale's request-specific translation file out of JS aggregates.
   */
  public function testLocaleTranslationPlaceholderIsNotAggregated(): void {
    $hooks = (new \ReflectionClass(AppearanceHooks::class))->newInstanceWithoutConstructor();
    $libraries = [
      'translations' => [
        'js' => [
          'locale.translation.js' => [],
        ],
      ],
    ];

    $hooks->alterLibraries($libraries, 'locale');

    self::assertFalse($libraries['translations']['js']['locale.translation.js']['preprocess']);
  }

}
