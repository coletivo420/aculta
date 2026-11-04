<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Hook\JqueryAssetOrderingHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests safe ordering for Core jQuery and dependent assets.
 *
 * @coversDefaultClass \Drupal\aculta_lms\Hook\JqueryAssetOrderingHooks
 */
#[CoversClass(JqueryAssetOrderingHooks::class)]
final class JqueryAssetOrderingHooksTest extends UnitTestCase {

  /**
   * @covers ::placeCoreJQueryBeforeDependents
   */
  public function testCoreJqueryGetsWeightBeforeNegativeWeightedDependencies(): void {
    $libraries = [
      'jquery' => [
        'js' => [
          'assets/vendor/jquery/jquery.min.js' => ['preprocess' => FALSE, 'minified' => TRUE],
        ],
      ],
      'drupal.ajax' => ['js' => ['misc/ajax.js' => []]],
    ];

    (new JqueryAssetOrderingHooks())->placeCoreJqueryBeforeDependents($libraries, 'core');

    self::assertSame(-12, $libraries['jquery']['js']['assets/vendor/jquery/jquery.min.js']['weight']);
    self::assertArrayNotHasKey('weight', $libraries['drupal.ajax']['js']['misc/ajax.js']);
  }

  /**
   * @covers ::placeCoreJQueryBeforeDependents
   */
  public function testOtherExtensionsAreNotAltered(): void {
    $libraries = ['jquery' => ['js' => ['jquery.min.js' => ['preprocess' => FALSE]]]];

    (new JqueryAssetOrderingHooks())->placeCoreJqueryBeforeDependents($libraries, 'aculta_lms');

    self::assertArrayNotHasKey('weight', $libraries['jquery']['js']['jquery.min.js']);
  }

}
