<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\DiscussionReactionEntityViewHooks;
use Drupal\aculta_forum\Service\DiscussionReactionBuilder;
use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests discussion reaction entity view hooks.
 */
#[Group('aculta_forum')]
final class DiscussionReactionEntityViewHooksTest extends TestCase {

  /**
   * Existing cache tags survive author statistics enrichment.
   */
  public function testAuthorStatisticsPreserveExistingCacheTags(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getEntityTypeId')->willReturn('node');
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('getOwnerId')->willReturn('17');

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $builder = $this->createMock(DiscussionReactionBuilder::class);
    $builder->method('build')->with($node, $currentUser)->willReturn(['#type' => 'container']);
    $manager = $this->createMock(DiscussionReactionManager::class);
    $manager->method('getUserStats')->with(17)->willReturn([
      'reactions_received' => 4,
      'reactions_given' => 2,
    ]);
    $manager->method('getAuthorPublicPostCount')->with(17)->willReturn(3);

    $hook = new DiscussionReactionEntityViewHooks(
      $builder,
      $manager,
      $currentUser,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(KeyValueFactoryInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
    );
    $build = [
      '#cache' => ['tags' => ['topic:17']],
      'author_card' => [
        '#cache' => ['tags' => ['author-card:17']],
      ],
    ];

    $hook->entityView(
      $build,
      $node,
      $this->createMock(EntityViewDisplayInterface::class),
      'full',
    );

    $cache = $build['#cache'] ?? NULL;
    self::assertIsArray($cache);
    self::assertSame(['topic:17', 'aculta_user_reactions:17'], $cache['tags']);
    $authorCard = $build['author_card'] ?? NULL;
    self::assertIsArray($authorCard);
    $authorCardCache = $authorCard['#cache'] ?? NULL;
    self::assertIsArray($authorCardCache);
    self::assertSame(['author-card:17', 'aculta_user_reactions:17'], $authorCardCache['tags']);
    $reactionBar = $build['reaction_bar'] ?? NULL;
    self::assertIsArray($reactionBar);
    self::assertSame(20, $reactionBar['#weight']);
    self::assertSame(3, $build['#author_posts']);
  }

  /**
   * Root comments are weighted by reaction totals and retain their render data.
   */
  public function testCommentOrderUsesReactionTotals(): void {
    $first = $this->comment(101, 0);
    $second = $this->comment(102, 10);
    $manager = $this->createMock(DiscussionReactionManager::class);
    $manager->method('loadReactions')->willReturnCallback(static fn (string $entityType, int $entityId): array => [
      'total_count' => $entityId === 101 ? 3 : 1,
      'user_reaction' => NULL,
      'reactions' => [
        'like' => [
          'id' => 'like',
          'count' => $entityId === 101 ? 3 : 1,
          'user_reacted' => FALSE,
        ],
      ],
    ]);
    $hook = $this->hook($manager);
    $block = $this->createMock(BlockPluginInterface::class);
    $block->method('getPluginId')->willReturn('field_block:node:revista:field_aculta_discussion');
    $build = [
      'comments' => [
        101 => ['#comment' => $first, '#existing' => 'first'],
        102 => ['#comment' => $second, '#existing' => 'second'],
      ],
    ];

    $hook->revistaCommentBlockOrder($build, $block);

    $comments = $build['comments'] ?? NULL;
    self::assertIsArray($comments);
    $firstBuild = $comments[101] ?? NULL;
    self::assertIsArray($firstBuild);
    $secondBuild = $comments[102] ?? NULL;
    self::assertIsArray($secondBuild);
    self::assertSame(0, $firstBuild['#weight']);
    self::assertSame(1, $secondBuild['#weight']);
    self::assertSame('first', $firstBuild['#existing']);
    self::assertSame('second', $secondBuild['#existing']);
  }

  /**
   * Creates a comment with a root parent and known creation time.
   *
   * @return CommentInterface&MockObject
   *   The configured comment double.
   */
  private function comment(int $id, int $created): CommentInterface&MockObject {
    $parent = $this->createMock(FieldItemListInterface::class);
    $parent->method('__get')->with('target_id')->willReturn(NULL);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('id')->willReturn($id);
    $comment->method('getCreatedTime')->willReturn($created);
    $comment->method('get')->with('pid')->willReturn($parent);
    return $comment;
  }

  /**
   * Creates the hook with replaceable reaction dependencies.
   */
  private function hook(?DiscussionReactionManager $manager = NULL): DiscussionReactionEntityViewHooks {
    return new DiscussionReactionEntityViewHooks(
      $this->createMock(DiscussionReactionBuilder::class),
      $manager ?? $this->createMock(DiscussionReactionManager::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(KeyValueFactoryInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
    );
  }

}
