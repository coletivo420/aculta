<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\DiscussionCommentPermalinkBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests DiscussionCommentPermalinkBuilder.
 */
#[Group('aculta_forum')]
final class DiscussionCommentPermalinkBuilderTest extends TestCase {

  /**
   * Tests permalink points to host canonical with anchor and pager 0.
   */
  public function testBuildsHostPermalinkWithAnchorAndPager(): void {
    $pagerManager = $this->createMock(PagerManagerInterface::class);
    $pagerManager->method('findPage')->with(0)->willReturn(2);
    $pagerManager->method('getUpdatedParameters')->willReturnCallback(
      static fn (array $query, int $element, int $page): array => ['page' => '2'] + $query
    );

    $builder = new DiscussionCommentPermalinkBuilder($pagerManager);

    $hostUrl = Url::fromRoute('entity.node.canonical', ['node' => 123]);
    $host = $this->createMock(NodeInterface::class);
    $host->method('toUrl')->with('canonical')->willReturn($hostUrl);

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('id')->willReturn(456);

    $url = $builder->build($comment);
    self::assertNotNull($url);
    self::assertSame('entity.node.canonical', $url->getRouteName());
    self::assertSame('comment-456', $url->getOption('fragment'));
    self::assertSame(['page' => '2'], $url->getOption('query'));
  }

  /**
   * Returns NULL if host entity is missing.
   */
  public function testReturnsNullWithoutHostEntity(): void {
    $pagerManager = $this->createMock(PagerManagerInterface::class);
    $builder = new DiscussionCommentPermalinkBuilder($pagerManager);

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn(NULL);

    self::assertNull($builder->build($comment));
  }

}
