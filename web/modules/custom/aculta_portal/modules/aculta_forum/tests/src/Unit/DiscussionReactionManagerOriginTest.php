<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Analytics\ForumAnalyticsScopeResolver;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests that archived reaction rows are not exposed by the summary API.
 *
 * @group aculta_forum
 * @coversDefaultClass \Drupal\aculta_forum\Service\DiscussionReactionManager
 */
final class DiscussionReactionManagerOriginTest extends UnitTestCase {

  /**
   * Archive summaries are empty without reading reaction rows.
   *
   * @covers ::loadReactions
   */
  public function testArchiveReactionSummaryIsHidden(): void {
    $target = $this->createMock(EntityInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(17)->willReturn($target);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('comment')->willReturn($storage);

    $database = $this->createMock(Connection::class);
    $database->expects(self::never())->method('select');

    $originResolver = $this->createMock(DiscussionOriginResolverInterface::class);
    $originResolver->method('classify')->with($target)->willReturn(
      DiscussionOriginClassification::from(DiscussionOrigin::ARCHIVE, new CacheableMetadata()),
    );

    $manager = new DiscussionReactionManager(
      $database,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(ForumAnalyticsScopeResolver::class),
      $this->createMock(EventDispatcherInterface::class),
      $this->createMock(TimeInterface::class),
      $entityTypeManager,
      $originResolver,
    );

    self::assertSame([
      'total_count' => 0,
      'positive_count' => 0,
      'dislike_count' => 0,
      'user_reaction' => NULL,
      'reactions' => [],
    ], $manager->loadReactions('comment', 17, 2));
  }

}
