<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests explicit relation identity validation.
 */
#[Group('aculta_index')]
final class ContentIndexRelationTest extends UnitTestCase {

  /**
   * Tests malformed relation fields are rejected.
   */
  #[DataProvider('invalidRelationProvider')]
  public function testRejectsInvalidRelation(array $arguments, string $message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    new ContentIndexRelation(...$arguments);
  }

  /**
   * Provides invalid relation constructor arguments.
   *
   * @return array<string, array{0: array, 1: string}>
   *   Relation arguments and expected validation message.
   */
  public static function invalidRelationProvider(): array {
    return [
      'invalid relation type' => [['Related', 'node', '1'], 'Content Index relation type is invalid.'],
      'invalid target entity type' => [['related', 'bad-type', '1'], 'Content Index relation target type is invalid.'],
      'empty target identity' => [['related', 'node', ''], 'Content Index relation target ID is invalid.'],
    ];
  }

}
