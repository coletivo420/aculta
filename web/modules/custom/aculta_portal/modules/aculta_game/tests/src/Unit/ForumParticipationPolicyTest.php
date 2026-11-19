<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_game\Unit;

use Drupal\aculta_game\ForumParticipationPolicy;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the exact entity context allowed to feed the Forum Game trigger.
 */
#[Group('aculta_game')]
final class ForumParticipationPolicyTest extends UnitTestCase {

  /**
   * A new published reply in a public topic is eligible. */
  public function testPublishedReplyOnCurrentPublicTopicIsEligible(): void {
    self::assertTrue(
      $this->policy()->isEligible($this->comment(), $this->topic()),
    );
  }

  /**
   * A pending reply does not count until publication. */
  public function testPendingReplyIsNotEligible(): void {
    self::assertFalse(
      $this->policy()->isEligible($this->comment(published: FALSE), $this->topic()),
    );
  }

  /**
   * Approving a pending reply makes its first publication eligible once.
   */
  public function testApprovedPendingReplyIsEligibleOnUpdate(): void {
    $original = $this->createMock(CommentInterface::class);
    $original->method('isPublished')->willReturn(FALSE);

    self::assertTrue(
      $this->policy()->isEligible(
        $this->comment(original: $original),
        $this->topic(),
      ),
    );
  }

  /**
   * Anonymous comments cannot qualify for an account achievement.
   */
  public function testAnonymousReplyIsNotEligible(): void {
    self::assertFalse(
      $this->policy()->isEligible($this->comment(uid: 0), $this->topic()),
    );
  }

  /**
   * Editing an already published reply does not repeat the trigger. */
  public function testAlreadyPublishedReplyEditIsNotEligibleAgain(): void {
    $original = $this->createMock(CommentInterface::class);
    $original->method('isPublished')->willReturn(TRUE);

    self::assertFalse(
      $this->policy()->isEligible($this->comment(original: $original), $this->topic()),
    );
  }

  /**
   * Publication comments do not feed the public Forum trigger. */
  public function testOtherHostContextIsNotEligible(): void {
    self::assertFalse(
      $this->policy()->isEligible($this->comment(), $this->topic('article')),
    );
  }

  /**
   * LMS participation never awards the public Forum achievement.
   */
  public function testLmsTopicDoesNotEarnPublicForumParticipation(): void {
    self::assertFalse(
      $this->policy()->isEligible(
        $this->comment(),
        $this->topic('aculta_lms_topic'),
      ),
    );
  }

  /**
   * Either historical author marker excludes a contemporaneous award. */
  public function testLegacyAuthorshipOnEitherEntityIsNotEligible(): void {
    $policy = $this->policy(DiscussionOrigin::ARCHIVE);
    self::assertFalse($policy->isEligible($this->comment(), $this->topic()));
    self::assertFalse($policy->isEligible($this->comment(), $this->topic()));
  }

  /**
   * Drupal Forum contributed content is legacy and cannot earn new badges.
   */
  public function testDrupalForumContributedBundleIsNotEligible(): void {
    $comment = $this->comment(bundle: 'comment_forum', field: 'comment_forum');
    self::assertFalse($this->policy()->isEligible($comment, $this->topic('forum')));
  }

  /**
   * A mismatched Comment field is not the public Forum context. */
  public function testMismatchedBundleAndFieldAreNotEligible(): void {
    $comment = $this->comment(bundle: 'aculta_discussion_post', field: 'other_comment_field');
    self::assertFalse($this->policy()->isEligible($comment, $this->topic()));
  }

  /**
   * Builds a published or historical forum topic. */
  private function topic(string $bundle = 'aculta_forum_topic'): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn($bundle);
    $topic->method('isPublished')->willReturn(TRUE);
    return $topic;
  }

  /**
   * Builds a Comment for a selected host/field. */
  private function comment(
    bool $published = TRUE,
    ?CommentInterface $original = NULL,
    string $bundle = 'aculta_discussion_post',
    string $field = 'field_aculta_discussion',
    int $uid = 1,
  ): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn($bundle);
    $comment->method('getFieldName')->willReturn($field);
    $comment->method('isPublished')->willReturn($published);
    $comment->method('getOwnerId')->willReturn($uid);
    $comment->method('getOriginal')->willReturn($original);
    return $comment;
  }

  /**
   * Creates a policy with a generic origin classification.
   */
  private function policy(string $origin = DiscussionOrigin::CURRENT): ForumParticipationPolicy {
    $resolver = $this->createMock(DiscussionOriginResolverInterface::class);
    $resolver->method('classify')->willReturn(
      DiscussionOriginClassification::from($origin, new CacheableMetadata()),
    );
    return new ForumParticipationPolicy($resolver);
  }

}
