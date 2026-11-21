<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_legacy\Unit;

use Drupal\aculta_forum_legacy\Author\LegacyForumUserCardBuilder;
use Drupal\aculta_forum_legacy\Hook\LegacyForumUserAuthorHooks;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies historical author presentation without a User account or profile.
 */
#[Group('aculta_forum_legacy')]
final class LegacyForumUserCardBuilderTest extends UnitTestCase {

  /**
   * Builds a cacheable historical Forum card and adds the source suffix.
   */
  public function testBuildForumCardUsesLegacyEntityAndNoProfileUrl(): void {
    $author = $this->createMock(ContentEntityInterface::class);
    $author->method('getEntityTypeId')->willReturn('aculta_forum_legacy_user');
    $author->method('label')->willReturn('Pessoa histórica');
    $author->method('getCacheTags')->willReturn(['aculta_forum_legacy_user:7']);
    $author->method('getCacheContexts')->willReturn([]);
    $author->method('getCacheMaxAge')->willReturn(3600);

    $card = (new LegacyForumUserCardBuilder())->buildForum($author);

    self::assertSame('aculta_user_author_card', $card['#theme']);
    self::assertSame('author_growroom_historico', $card['#variant']);
    self::assertSame('Pessoa histórica-gr', $card['#display_name']);
    self::assertNull($card['#profile_url']);
    self::assertSame([], $card['#actions']);
    $cache = $card['#cache'];
    self::assertIsArray($cache);
    $tags = $cache['tags'] ?? NULL;
    self::assertIsArray($tags);
    self::assertContains('aculta_forum_legacy_user:7', $tags);
  }

  /**
   * Refuses to render a different content entity as a historical author.
   */
  public function testBuildForumRejectsOtherEntityTypes(): void {
    $author = $this->createMock(ContentEntityInterface::class);
    $author->method('getEntityTypeId')->willReturn('user');

    $this->expectException(\InvalidArgumentException::class);
    (new LegacyForumUserCardBuilder())->buildForum($author);
  }

  /**
   * Never reads the legacy author field from a non-Forum host.
   */
  public function testLegacyHookDoesNotInspectOtherHostBundles(): void {
    $hooks = new LegacyForumUserAuthorHooks(new LegacyForumUserCardBuilder());
    $build = [];
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('wiki_entry');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('getCommentedEntity')->willReturn($host);

    $hooks->entityView($build, $comment, $this->createMock(EntityViewDisplayInterface::class), 'full');

    self::assertArrayNotHasKey('author_card', $build);
  }

}
