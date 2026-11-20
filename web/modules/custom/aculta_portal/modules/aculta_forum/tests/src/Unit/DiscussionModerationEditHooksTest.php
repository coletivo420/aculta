<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\DiscussionModerationEditHooks;
use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Session\AccountProxyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Proves only moderator edits are marked "editado pela moderação".
 */
#[CoversClass(DiscussionModerationEditHooks::class)]
#[Group('aculta_forum')]
final class DiscussionModerationEditHooksTest extends TestCase {

  /**
   * An edit by the author is not marked; an edit by a moderator is marked.
   */
  public function testOnlyModeratorEditsAreMarked(): void {
    $factory = new KeyValueMemoryFactory();
    $author = $this->createMock(AccountProxyInterface::class);
    $author->method('id')->willReturn(7);
    $moderator = $this->createMock(AccountProxyInterface::class);
    $moderator->method('id')->willReturn(99);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $authorHooks = new DiscussionModerationEditHooks($factory, $author, $time);
    $modHooks = new DiscussionModerationEditHooks($factory, $moderator, $time);
    $hooks = $modHooks;

    $authorEdit = $this->comment(12, 7);
    $authorHooks->commentUpdate($authorEdit);
    self::assertFalse($hooks->isEditedByModeration(12));

    $moderatorEdit = $this->comment(13, 7);
    $modHooks->commentUpdate($moderatorEdit);
    self::assertTrue($hooks->isEditedByModeration(13));
    self::assertSame([['uid' => 99, 'time' => 1700000000]], $hooks->editLog(13));
    self::assertSame([], $hooks->editLog(12));
  }

  /**
   * Builds a comment double with an id, bundle and owner.
   */
  private function comment(int $id, int $owner): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('id')->willReturn($id);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getOwnerId')->willReturn($owner);
    return $comment;
  }

}
