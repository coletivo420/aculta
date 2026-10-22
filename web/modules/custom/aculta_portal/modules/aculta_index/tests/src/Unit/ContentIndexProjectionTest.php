<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the allowlisted content projection value object.
 *
 * @group aculta_index
 */
final class ContentIndexProjectionTest extends UnitTestCase {

  /**
   * Tests a valid entity-language projection and explicit relation.
   */
  public function testAcceptsEntityLanguageProjection(): void {
    $relation = new ContentIndexRelation('related', 'node', '42', 'pt-br');
    $projection = new ContentIndexProjection(
      'node',
      '1',
      'pt-br',
      'revista',
      'source-uuid',
      'Título',
      'aculta',
      TRUE,
      1,
      2,
      3,
      [$relation],
    );

    self::assertSame('1', $projection->entityId);
    self::assertSame('pt-br', $projection->langcode);
    self::assertSame([$relation], $projection->relations);
  }

  /**
   * Tests projections reject malformed identity and non-relation values.
   *
   * @dataProvider invalidProjectionProvider
   */
  public function testRejectsInvalidProjection(array $arguments, string $message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    new ContentIndexProjection(...$arguments);
  }

  /**
   * Provides invalid projection constructor arguments.
   *
   * @return array<string, array{0: array, 1: string}>
   *   Projection arguments and expected validation message.
   */
  public static function invalidProjectionProvider(): array {
    $base = ['node', '1', 'pt-br', 'revista', 'uuid', 'Título', NULL, TRUE, 1, 2];
    return [
      'invalid entity type' => [['bad-type', ...array_slice($base, 1)], 'Content Index entity type ID is invalid.'],
      'empty entity ID' => [['node', '', ...array_slice($base, 2)], 'Content Index entity ID is invalid.'],
      'negative timestamp' => [
        ['node', '1', 'pt-br', 'revista', 'uuid', 'Título', NULL, TRUE, -1, 2],
        'Content Index timestamps must be non-negative.',
      ],
      'invalid relation value' => [
        [...$base, NULL, ['not-a-relation']],
        'Content Index projections accept only ContentIndexRelation values.',
      ],
    ];
  }

}
