<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki_messenger\EventSubscriber;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\Event\WikiProposalOutcomeRecordedEvent;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Publishes Wiki-owned facts to Messenger after revalidating their recipients. */
final class WikiEditorialNoticePublisher implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly MessengerNoticePublisherInterface $publisher,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [
      WikiEntryReviewRequestedEvent::NAME => 'reviewRequested',
      WikiProposalOutcomeRecordedEvent::NAME => 'proposalOutcome',
    ];
  }

  /**
   * Publishes to active editors with current Wiki revision access. */
  public function reviewRequested(WikiEntryReviewRequestedEvent $event): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $revision = $storage->loadRevision($event->entryRevisionId);
    if (!$revision || $revision->uuid() !== $event->entryUuid || $revision->bundle() !== 'wiki_entry'
      || !$revision->isDefaultRevision() || !$revision->hasField('moderation_state')
      || $revision->get('moderation_state')->value !== 'review') {
      return;
    }
    $authorUid = (int) $revision->getOwnerId();
    $editors = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'status' => 1,
      'roles' => 'content_editor',
    ]);
    foreach ($editors as $editor) {
      if (!$editor instanceof UserInterface || !$editor->isActive()
        || (int) $editor->id() === $authorUid
        || !$editor->hasPermission('edit any wiki_entry content')
        || (!$editor->hasPermission('use wiki_editorial transition review_to_draft')
          && !$editor->hasPermission('use wiki_editorial transition review_to_published'))) {
        continue;
      }
      $allowed = $this->domainPurposes->runInPurpose('wiki', static fn(): bool =>
        $revision->access('view', $editor, TRUE)->isAllowed()
        && $revision->access('view revision', $editor, TRUE)->isAllowed()
      );
      if (!$allowed) {
        continue;
      }
      $this->publish(
        'wiki.review_requested',
        'node',
        $event->entryUuid,
        (string) $event->entryRevisionId,
        (int) $editor->id(),
        'review_requested',
        'wiki.review_requested:' . $event->entryUuid . ':' . $event->entryRevisionId . ':' . $editor->id(),
      );
    }
  }

  /**
   * Publishes a private outcome only to its active, authorized proposal author. */
  public function proposalOutcome(WikiProposalOutcomeRecordedEvent $event): void {
    $proposalStorage = $this->entityTypeManager->getStorage('aculta_wiki_proposal');
    $proposal = $proposalStorage->loadRevision($event->proposalRevisionId);
    $recipient = $this->entityTypeManager->getStorage('user')->load($event->recipientUid);
    if (!$proposal || !$recipient instanceof UserInterface || !$recipient->isActive()
      || $proposal->uuid() !== $event->proposalUuid
      || (int) $proposal->getRevisionId() !== $event->proposalRevisionId
      || (int) $proposal->getOwnerId() !== $event->recipientUid
      || $proposal->get('state')->value !== $event->outcome
      || !$proposal->access('view', $recipient, TRUE)->isAllowed()) {
      return;
    }
    $this->publish(
      'wiki.proposal_outcome',
      'aculta_wiki_proposal',
      $event->proposalUuid,
      (string) $event->proposalRevisionId,
      $event->recipientUid,
      'proposal_outcome',
      'wiki.proposal_outcome:' . $event->proposalUuid . ':' . $event->proposalRevisionId . ':' . $event->recipientUid,
    );
  }

  /**
   * Builds the minimal envelope shared with Messenger consumers. */
  private function publish(string $type, string $sourceType, string $uuid, string $revision, int $uid, string $noticeType, string $key): void {
    try {
      $this->publisher->publish(new MessengerNoticeEvent(
        $type,
        'aculta_wiki',
        $sourceType,
        $uuid,
        $revision,
        $uid,
        'wiki',
        new \DateTimeImmutable('@' . $this->time->getRequestTime()),
        $key,
        ['notice_type' => $noticeType, 'mail_policy' => 'user_preference'],
      ));
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not publish Wiki notice event @event.', ['@event' => $type]);
      throw $exception;
    }
  }

}
