<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\EventSubscriber;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_messenger\Aviso\ForumNoticeEventProducer;
use Drupal\aculta_messenger\Aviso\ForumNoticeRegistrarInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Revalidates and persists Forum notice events through existing storage.
 */
final class ForumNoticeEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ForumNoticeRegistrarInterface $avisos,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [MessengerNoticeEvent::EVENT_NAME => 'consume'];
  }

  /**
   * Dispatches a supported event to the existing idempotent Forum aggregate.
   */
  public function consume(MessengerNoticeEvent $event): void {
    if ($event->sourceModule() !== 'aculta_forum' || $event->purpose() !== 'forum'
      || $event->recipientUid() < 1 || !$this->hasCanonicalKey($event)) {
      return;
    }

    switch ($event->eventType()) {
      case ForumNoticeEventProducer::COMMENT_PUBLISHED:
        $this->consumeComment($event);
        break;

      case ForumNoticeEventProducer::POST_PUBLISHED:
        $this->consumePost($event);
        break;

      case ForumNoticeEventProducer::TOPIC_UPDATED:
        $this->consumeTopicUpdate($event);
        break;
    }
  }

  /**
   * Revalidates a Comment UUID before registration.
   */
  private function consumeComment(MessengerNoticeEvent $event): void {
    if ($event->sourceEntityType() !== 'comment' || $event->sourceRevisionOrVersion() !== NULL) {
      return;
    }
    $comments = $this->entityTypeManager->getStorage('comment')
      ->loadByProperties(['uuid' => $event->sourceEntityUuid()]);
    $comment = reset($comments);
    if ($comment instanceof CommentInterface) {
      $this->avisos->registrarResposta($comment, $event->recipientUid());
    }
  }

  /**
   * Revalidates a ForumPost revision before registration.
   */
  private function consumePost(MessengerNoticeEvent $event): void {
    $revisionId = $event->sourceRevisionOrVersion();
    if ($event->sourceEntityType() !== 'aculta_forum_post' || $revisionId === NULL
      || !ctype_digit($revisionId) || (int) $revisionId < 1) {
      return;
    }
    $post = $this->entityTypeManager->getStorage('aculta_forum_post')->loadRevision((int) $revisionId);
    if ($post instanceof ForumPost && $post->uuid() === $event->sourceEntityUuid()) {
      $this->avisos->registrarPost($post, $event->recipientUid());
    }
  }

  /**
   * Revalidates a published topic revision before registration.
   */
  private function consumeTopicUpdate(MessengerNoticeEvent $event): void {
    $revisionId = $event->sourceRevisionOrVersion();
    if ($event->sourceEntityType() !== 'node' || $revisionId === NULL
      || !ctype_digit($revisionId) || (int) $revisionId < 1) {
      return;
    }
    $topic = $this->entityTypeManager->getStorage('node')->loadRevision((int) $revisionId);
    if ($topic instanceof NodeInterface && $topic->uuid() === $event->sourceEntityUuid()) {
      $this->avisos->notificarAtualizacao($topic, (int) $revisionId, $event->recipientUid());
    }
  }

  /**
   * Ensures event replay keys use the public producer's canonical format.
   */
  private function hasCanonicalKey(MessengerNoticeEvent $event): bool {
    $parts = ['forum', $event->eventType(), $event->sourceEntityUuid()];
    if ($event->sourceRevisionOrVersion() !== NULL) {
      $parts[] = $event->sourceRevisionOrVersion();
    }
    $parts[] = (string) $event->recipientUid();
    return hash_equals(implode(':', $parts), $event->idempotencyKey());
  }

}
