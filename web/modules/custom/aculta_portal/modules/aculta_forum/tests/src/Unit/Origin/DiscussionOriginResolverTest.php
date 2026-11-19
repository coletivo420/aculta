<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Origin;

use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginClassifierInterface;
use Drupal\aculta_forum\Origin\DiscussionOriginResolver;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the fail-closed combination rules for origin providers.
 *
 * @group aculta_forum
 * @coversDefaultClass \Drupal\aculta_forum\Origin\DiscussionOriginResolver
 */
final class DiscussionOriginResolverTest extends UnitTestCase {

  /**
   * Unknown is returned when no optional classifier owns the entity.
   *
   * @covers ::classify
   */
  public function testUnclaimedEntityFailsClosed(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects(self::once())->method('getCacheTags')->willReturn(['entity:1']);

    $result = (new DiscussionOriginResolver([]))->classify($entity);

    self::assertSame(DiscussionOrigin::UNKNOWN, $result->origin);
    self::assertContains('entity:1', $result->getCacheTags());
  }

  /**
   * Contradictory non-archive claims fail closed.
   *
   * @covers ::classify
   */
  public function testConflictingClaimsFailClosed(): void {
    $entity = $this->createMock(EntityInterface::class);
    $current = $this->classifierClaim(DiscussionOrigin::CURRENT);
    $contextual = $this->classifierClaim(DiscussionOrigin::CONTEXTUAL);

    $result = (new DiscussionOriginResolver([$current, $contextual]))->classify($entity);

    self::assertSame(DiscussionOrigin::UNKNOWN, $result->origin);
  }

  /**
   * A provider's explicit unknown claim cannot be overridden by another.
   *
   * @covers ::classify
   */
  public function testExplicitUnknownClaimWins(): void {
    $entity = $this->createMock(EntityInterface::class);
    $current = $this->classifierClaim(DiscussionOrigin::CURRENT);
    $unknown = $this->classifierClaim(DiscussionOrigin::UNKNOWN);

    $result = (new DiscussionOriginResolver([$current, $unknown]))->classify($entity);

    self::assertSame(DiscussionOrigin::UNKNOWN, $result->origin);
  }

  /**
   * Archive evidence wins over the generic host category.
   *
   * @covers ::classify
   */
  public function testArchiveClaimOverridesGenericHostClaim(): void {
    $entity = $this->createMock(EntityInterface::class);
    $current = $this->classifierClaim(DiscussionOrigin::CURRENT);
    $archive = $this->classifierClaim(DiscussionOrigin::ARCHIVE);

    $result = (new DiscussionOriginResolver([$current, $archive]))->classify($entity);

    self::assertSame(DiscussionOrigin::ARCHIVE, $result->origin);
  }

  /**
   * Creates a classifier returning the requested origin.
   */
  private function classifierClaim(string $origin): DiscussionOriginClassifierInterface {
    $classifier = $this->createMock(DiscussionOriginClassifierInterface::class);
    $classifier->method('classify')->willReturn(
      DiscussionOriginClassification::from($origin, new CacheableMetadata()),
    );
    return $classifier;
  }

}
