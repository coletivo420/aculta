<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;

/**
 * Adapts published Forum activity to the shared Messenger event contract.
 */
final class ForumNoticeEventProducer implements ForumNoticeEventProducerInterface {

  public const COMMENT_PUBLISHED = 'forum.comment_published';

  public const POST_PUBLISHED = 'forum.post_published';

  public const TOPIC_UPDATED = 'forum.topic_updated';

  public function __construct(
    private readonly FlagServiceInterface $flagService,
    private readonly ForumNoticeAccessPolicyInterface $accessPolicy,
    private readonly MessengerNoticePublisherInterface $publisher,
  ) {}

  /**
   * Publishes one event for each eligible follower of a published Comment.
   */
  public function publishComment(CommentInterface $comment): int {
    if (!$comment->isPublished() || $comment->bundle() !== 'aculta_discussion_post'
      || ($comment instanceof SynchronizableInterface && $comment->isSyncing())) {
      return 0;
    }
    $topic = $comment->getCommentedEntity();
    if (!$topic instanceof NodeInterface || !$this->accessPolicy->supportsTopic($topic)) {
      return 0;
    }
    return $this->publishToFollowers(
      $comment,
      $topic,
      (int) $comment->getOwnerId(),
      self::COMMENT_PUBLISHED,
      NULL,
      $comment->getChangedTime(),
      fn(int $uid): bool => $this->accessPolicy->canViewComment($comment, $uid),
    );
  }

  /**
   * Publishes one event for each eligible follower of a first-published post.
   */
  public function publishPost(ForumPost $post): int {
    if (!$post->isPublished() || !$post->isDefaultRevision()
      || ($post instanceof SynchronizableInterface && $post->isSyncing())
      || !$post->hasField('topic') || $post->get('topic')->isEmpty()) {
      return 0;
    }
    $topic = $post->get('topic')->entity;
    $revisionId = (int) $post->getRevisionId();
    if (!$topic instanceof NodeInterface || !$this->accessPolicy->supportsTopic($topic)
      || $revisionId <= 0) {
      return 0;
    }
    return $this->publishToFollowers(
      $post,
      $topic,
      (int) $post->getOwnerId(),
      self::POST_PUBLISHED,
      (string) $revisionId,
      $post->hasField('first_published') && !$post->get('first_published')->isEmpty()
        ? (int) $post->get('first_published')->value
        : $post->getChangedTime(),
      fn(int $uid): bool => $this->accessPolicy->canViewPost($post, $uid),
    );
  }

  /**
   * Publishes a topic revision to eligible followers.
   */
  public function publishTopicUpdate(NodeInterface $topic, int $revisionId): int {
    if (!$this->accessPolicy->supportsTopic($topic) || !$topic->isPublished() || $revisionId <= 0
      || (int) $topic->getRevisionId() !== $revisionId) {
      return 0;
    }
    $authorUid = method_exists($topic, 'getRevisionUserId') ? (int) $topic->getRevisionUserId() : 0;
    return $this->publishToFollowers(
      $topic,
      $topic,
      $authorUid,
      self::TOPIC_UPDATED,
      (string) $revisionId,
      $topic->getChangedTime(),
      fn(int $uid): bool => $this->accessPolicy->canViewTopic($topic, $uid),
    );
  }

  /**
   * Publishes a minimal envelope to each currently eligible follower.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source
   *   Source entity represented by the event.
   * @param \Drupal\node\NodeInterface $topic
   *   Followed Forum topic.
   * @param int $authorUid
   *   Account responsible for the source event.
   * @param string $eventType
   *   Stable shared event type.
   * @param string|null $version
   *   Source revision or version, when applicable.
   * @param int $occurredAt
   *   Unix timestamp for the source event.
   * @param callable(int): bool $canView
   *   Current access check for the follower and source event.
   */
  private function publishToFollowers(
    EntityInterface $source,
    NodeInterface $topic,
    int $authorUid,
    string $eventType,
    ?string $version,
    int $occurredAt,
    callable $canView,
  ): int {
    $flag = $this->flagService->getFlagById('seguir_topico');
    $uuid = $source->uuid();
    if ($flag === NULL || $uuid === '') {
      return 0;
    }
    $published = 0;
    foreach ($this->flagService->getEntityFlaggings($flag, $topic) as $flagging) {
      $uid = (int) $flagging->getOwnerId();
      if ($uid <= 0 || $uid === $authorUid || !$canView($uid)) {
        continue;
      }
      $keyParts = ['forum', $eventType, $uuid];
      if ($version !== NULL) {
        $keyParts[] = $version;
      }
      $keyParts[] = (string) $uid;
      $this->publisher->publish(new MessengerNoticeEvent(
        $eventType,
        'aculta_forum',
        $source->getEntityTypeId(),
        $uuid,
        $version,
        $uid,
        'forum',
        (new \DateTimeImmutable('@' . $occurredAt))->setTimezone(new \DateTimeZone('UTC')),
        implode(':', $keyParts),
      ));
      $published++;
    }
    return $published;
  }

}
