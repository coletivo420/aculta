<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Hook\CoreEditorAssetOrderingHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests safe ordering for Core editor prerequisites and dependent assets.
 *
 */
#[CoversClass(CoreEditorAssetOrderingHooks::class)]
final class CoreEditorAssetOrderingHooksTest extends UnitTestCase {

  /**
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

    $jquery = $this->stringKeyedMap($libraries['jquery'] ?? NULL);
    $jqueryAssets = $this->stringKeyedMap($jquery['js'] ?? NULL);
    $jqueryAsset = $this->stringKeyedMap($jqueryAssets['assets/vendor/jquery/jquery.min.js'] ?? NULL);
    self::assertSame(-100, $jqueryAsset['weight'] ?? NULL);
    $translations = $this->stringKeyedMap($libraries['ckeditor5.translations'] ?? NULL);
    $translationAssets = $this->stringKeyedMap($translations['js'] ?? NULL);
    $translationAsset = $this->stringKeyedMap($translationAssets['assets/vendor/ckeditor5/translation.js'] ?? NULL);
    self::assertSame(-91, $translationAsset['weight'] ?? NULL);
    $ckeditor = $this->stringKeyedMap($libraries['ckeditor5'] ?? NULL);
    $ckeditorAssets = $this->stringKeyedMap($ckeditor['js'] ?? NULL);
    $ckeditorAsset = $this->stringKeyedMap($ckeditorAssets['assets/vendor/ckeditor5/ckeditor5-dll/ckeditor5-dll.js'] ?? NULL);
    self::assertSame(-90, $ckeditorAsset['weight'] ?? NULL);
    $ajax = $this->stringKeyedMap($libraries['drupal.ajax'] ?? NULL);
    $ajaxAssets = $this->stringKeyedMap($ajax['js'] ?? NULL);
    self::assertArrayNotHasKey('weight', $this->stringKeyedMap($ajaxAssets['misc/ajax.js'] ?? NULL));
  }

  /**
   */
  public function testOtherExtensionsAreNotAltered(): void {
    $libraries = ['jquery' => ['js' => ['jquery.min.js' => ['preprocess' => FALSE]]]];

    (new CoreEditorAssetOrderingHooks())->placeCoreEditorPrerequisites($libraries, 'aculta_lms');

    $jquery = $this->stringKeyedMap($libraries['jquery'] ?? NULL);
    $assets = $this->stringKeyedMap($jquery['js'] ?? NULL);
    self::assertArrayNotHasKey('weight', $this->stringKeyedMap($assets['jquery.min.js'] ?? NULL));
  }

  /**
   * Validates a string-keyed library definition map.
   *
   * @param mixed $value
   *   The library definition or asset.
   *
   * @return array<string, mixed>
   *   The validated map.
   */
  private function stringKeyedMap(mixed $value): array {
    self::assertIsArray($value);
    $map = [];
    foreach ($value as $key => $item) {
      self::assertIsString($key);
      $map[$key] = $item;
    }
    return $map;
  }

}
