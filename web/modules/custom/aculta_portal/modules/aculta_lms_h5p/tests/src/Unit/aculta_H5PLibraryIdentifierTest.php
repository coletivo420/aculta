<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PLibraryIdentifier;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(aculta_H5PLibraryIdentifier::class)]
final class aculta_H5PLibraryIdentifierTest extends UnitTestCase {

  #[DataProvider('libraryMetadataProvider')]
  public function testConvertsH5PLibraryMetadata(array|\stdClass $library, string $expected): void {
    self::assertSame($expected, aculta_H5PLibraryIdentifier::toDrupalLibraryId($library));
  }

  public static function libraryMetadataProvider(): array {
    return [
      'database dependency' => [(object) ['name' => 'H5P.HelloWorld', 'major' => 1, 'minor' => 0], 'h5p.helloworld-1.0'],
      'editor metadata' => [['machineName' => 'H5P.MultiChoice', 'majorVersion' => 1, 'minorVersion' => 16], 'h5p.multichoice-1.16'],
      'bare library name' => [['machineName' => 'HelloWorld', 'majorVersion' => '1', 'minorVersion' => '0'], 'h5p.helloworld-1.0'],
    ];
  }

  public function testRejectsMalformedLibraryIdentifierParts(): void {
    $this->expectException(\InvalidArgumentException::class);
    aculta_H5PLibraryIdentifier::toDrupalLibraryId([
      'machineName' => '../H5P.Bad',
      'majorVersion' => 1,
      'minorVersion' => 0,
    ]);
  }

}
