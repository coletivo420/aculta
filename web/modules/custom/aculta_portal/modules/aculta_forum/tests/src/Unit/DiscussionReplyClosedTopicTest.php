<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\TopicAuthorDeletion;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves a topic closed by moderation refuses new replies.
 */
#[CoversClass(DiscussionReplyAccess::class)]
#[Group('aculta_forum')]
final class DiscussionReplyClosedTopicTest extends UnitTestCase {

  /**
   * A closed topic refuses replies even when the rest of the rules would allow them.
   */
  public function testClosedTopicRefusesNewReplies(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn(5);
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheTags')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(-1);
    $state = $this->createMock(TopicAuthorDeletion::class);
    $state->method('isClosed')->with(5)->willReturn(TRUE);

    $access = $this->access($state);
    $result = $access->checkAccess($topic, $this->createMock(AccountInterface::class));

    self::assertFalse($result->isAllowed());
  }

  /**
   * Builds the access service with an empty context resolver and the given topic state.
   */
  private function access(TopicAuthorDeletion $state): DiscussionReplyAccess {
    return new DiscussionReplyAccess(
      new DiscussionContextResolver([]),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DiscussionOriginResolverInterface::class),
      $state,
    );
  }

}
