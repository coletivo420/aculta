<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_game\Unit;

use Drupal\aculta_game\ForumParticipationPolicy;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
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
      (new ForumParticipationPolicy())->isEligible($this->comment(), $this->topic()),
    );
  }

  /**
   * A pending reply does not count until publication. */
  public function testPendingReplyIsNotEligible(): void {
    self::assertFalse(
      (new ForumParticipationPolicy())->isEligible($this->comment(published: FALSE), $this->topic()),
    );
  }

  /**
   * Approving a pending reply makes its first publication eligible once.
   */
  public function testApprovedPendingReplyIsEligibleOnUpdate(): void {
    $original = $this->createMock(CommentInterface::class);
    $original->method('isPublished')->willReturn(FALSE);

    self::assertTrue(
      (new ForumParticipationPolicy())->isEligible(
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
      (new ForumParticipationPolicy())->isEligible($this->comment(uid: 0), $this->topic()),
    );
  }

  /**
   * Editing an already published reply does not repeat the trigger. */
  public function testAlreadyPublishedReplyEditIsNotEligibleAgain(): void {
    $original = $this->createMock(CommentInterface::class);
    $original->method('isPublished')->willReturn(TRUE);

    self::assertFalse(
      (new ForumParticipationPolicy())->isEligible($this->comment(original: $original), $this->topic()),
    );
  }

  /**
   * Publication comments do not feed the public Forum trigger. */
  public function testOtherHostContextIsNotEligible(): void {
    self::assertFalse(
      (new ForumParticipationPolicy())->isEligible($this->comment(), $this->topic('article')),
    );
  }

  /**
   * LMS participation never awards the public Forum achievement.
   */
  public function testLmsTopicDoesNotEarnPublicForumParticipation(): void {
    self::assertFalse(
      (new ForumParticipationPolicy())->isEligible(
        $this->comment(),
        $this->topic('aculta_lms_topic'),
      ),
    );
  }

  /**
   * Either historical author marker excludes a contemporaneous award. */
  public function testLegacyAuthorshipOnEitherEntityIsNotEligible(): void {
    $policy = new ForumParticipationPolicy();
    self::assertFalse($policy->isEligible($this->comment(), $this->topic(legacyAuthor: TRUE)));
    self::assertFalse($policy->isEligible($this->comment(legacyAuthor: TRUE), $this->topic()));
  }

  /**
   * Drupal Forum contributed content is legacy and cannot earn new badges.
   */
  public function testDrupalForumContributedBundleIsNotEligible(): void {
    $comment = $this->comment(bundle: 'comment_forum', field: 'comment_forum');
    self::assertFalse((new ForumParticipationPolicy())->isEligible($comment, $this->topic('forum')));
  }

  /**
   * A mismatched Comment field is not the public Forum context. */
  public function testMismatchedBundleAndFieldAreNotEligible(): void {
    $comment = $this->comment(bundle: 'aculta_discussion_post', field: 'other_comment_field');
    self::assertFalse((new ForumParticipationPolicy())->isEligible($comment, $this->topic()));
  }

  /**
   * Builds a published or historical forum topic. */
  private function topic(string $bundle = 'aculta_forum_topic', bool $legacyAuthor = FALSE): NodeInterface {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn($bundle);
    $topic->method('isPublished')->willReturn(TRUE);
    $this->legacyAuthorField($topic, $legacyAuthor);
    return $topic;
  }

  /**
   * Builds a Comment for a selected host/field. */
  private function comment(
    bool $published = TRUE,
    bool $legacyAuthor = FALSE,
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
    $this->legacyAuthorField($comment, $legacyAuthor);
    return $comment;
  }

  /**
   * Sets an empty or populated legacy-author field on an entity mock. */
  private function legacyAuthorField(FieldableEntityInterface $entity, bool $hasLegacyAuthor): void {
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('isEmpty')->willReturn(!$hasLegacyAuthor);
    $entity->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $entity->method('get')->with('field_autor_legado')->willReturn($items);
  }

}
