<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\Entity\H5PContent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(H5PContent::class)]
final class H5PContentTest extends UnitTestCase {

  #[DataProvider('embedTypesProvider')]
  public function testDivPlaybackMatchesExplicitEmbedType(string $embedTypes, bool $expected): void {
    $content = (new \ReflectionClass(H5PContent::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(H5PContent::class, 'library'))->setValue($content, (object) ['embed_types' => $embedTypes]);

    self::assertSame($expected, $content->isDivEmbeddable());
  }

  public static function embedTypesProvider(): array {
    return [
      'div only' => ['div', TRUE],
      'iframe only' => ['iframe', FALSE],
      'both supported' => ['iframe, div', TRUE],
      'unknown type' => ['iframe, external', FALSE],
    ];
  }

}
