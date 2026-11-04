<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Hook\CoreEditorAssetOrderingHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests safe ordering for Core editor prerequisites and dependent assets.
 *
 * @coversDefaultClass \Drupal\aculta_lms\Hook\CoreEditorAssetOrderingHooks
 */
#[CoversClass(CoreEditorAssetOrderingHooks::class)]
final class CoreEditorAssetOrderingHooksTest extends UnitTestCase {

  /**
   * @covers ::placeCoreJQueryBeforeDependents
   */
  public function testCorePrerequisitesAreOrderedBeforeDependentAggregates(): void {
    $libraries = [
      'jquery' => [
        'js' => [
          'assets/vendor/jquery/jquery.min.js' => ['preprocess' => FALSE, 'minified' => TRUE],
        ],
      ],
      'ckeditor5.translations' => ['js' => ['assets/vendor/ckeditor5/translation.js' => []]],
      'ckeditor5' => ['js' => ['assets/vendor/ckeditor5/ckeditor5-dll/ckeditor5-dll.js' => ['preprocess' => FALSE]]],
      'drupal.ajax' => ['js' => ['misc/ajax.js' => []]],
    ];

    (new CoreEditorAssetOrderingHooks())->placeCoreEditorPrerequisites($libraries, 'core');

    self::assertSame(-100, $libraries['jquery']['js']['assets/vendor/jquery/jquery.min.js']['weight']);
    self::assertSame(-91, $libraries['ckeditor5.translations']['js']['assets/vendor/ckeditor5/translation.js']['weight']);
    self::assertSame(-90, $libraries['ckeditor5']['js']['assets/vendor/ckeditor5/ckeditor5-dll/ckeditor5-dll.js']['weight']);
    self::assertArrayNotHasKey('weight', $libraries['drupal.ajax']['js']['misc/ajax.js']);
  }

  /**
   * @covers ::placeCoreJQueryBeforeDependents
   */
  public function testOtherExtensionsAreNotAltered(): void {
    $libraries = ['jquery' => ['js' => ['jquery.min.js' => ['preprocess' => FALSE]]]];

    (new CoreEditorAssetOrderingHooks())->placeCoreEditorPrerequisites($libraries, 'aculta_lms');

    self::assertArrayNotHasKey('weight', $libraries['jquery']['js']['jquery.min.js']);
  }

}
