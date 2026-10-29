<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\Entity\aculta_H5PContent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 */
#[CoversClass(aculta_H5PContent::class)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
final class aculta_H5PContentTest extends UnitTestCase {

  /**
 *
 */
  #[DataProvider('embedTypesProvider')]
  public function testEmbedModeUsesSupportedH5PCoreEmbedTypes(string $embedTypes, ?string $expected): void {
    $content = (new \ReflectionClass(aculta_H5PContent::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(aculta_H5PContent::class, 'library'))->setValue($content, (object) ['embed_types' => $embedTypes]);

    self::assertSame($expected, $content->getEmbedType());
    self::assertSame($expected === 'div', $content->isDivEmbeddable());
  }

  /**
   *
   */
  public static function embedTypesProvider(): array {
    return [
      'div only' => ['div', 'div'],
      'iframe only' => ['iframe', 'iframe'],
      'both supported prefer div' => ['iframe, div', 'div'],
      'unknown type' => ['external', NULL],
      'empty type' => ['', NULL],
    ];
  }

}
