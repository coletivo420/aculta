<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;

/**
 * Tests extraction of explicit entity owner IDs.
 *
 * @group aculta_index
 */
final class ContentIndexOwnerResolverTest extends UnitTestCase {

  /**
   * Tests owner UIDs are normalized without loading owner entities.
   */
  public function testResolvesDeclaredOwnerUid(): void {
    $entity = $this->createMockForIntersectionOfInterfaces([
      ContentEntityInterface::class,
      EntityOwnerInterface::class,
    ]);
    $entity->method('getOwnerId')->willReturn('42');

    self::assertSame(42, ContentIndexOwnerResolver::resolve($entity));
  }

  /**
   * Tests missing and malformed owners remain absent from the projection.
   *
   * @dataProvider invalidOwnerProvider
   */
  public function testRejectsMissingOrInvalidOwner(mixed $ownerId): void {
    $entity = $this->createMockForIntersectionOfInterfaces([
      ContentEntityInterface::class,
      EntityOwnerInterface::class,
    ]);
    $entity->method('getOwnerId')->willReturn($ownerId);

    self::assertNull(ContentIndexOwnerResolver::resolve($entity));
  }

  /**
   * Provides owner values which cannot be projected.
   */
  public static function invalidOwnerProvider(): array {
    return [
      'null' => [NULL],
      'negative' => [-1],
      'nondigit string' => ['user-42'],
      'too large' => ['4294967296'],
    ];
  }

  /**
   * Tests entities without the owner contract are not assigned an owner.
   */
  public function testEntityWithoutOwnerContractReturnsNull(): void {
    self::assertNull(ContentIndexOwnerResolver::resolve($this->createMock(ContentEntityInterface::class)));
  }

}
