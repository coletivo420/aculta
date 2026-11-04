<?php

declare(strict_types=1);

namespace Drupal\aculta_events\EventSubscriber;

use Drupal\aculta_contributions\Entity\Contribution;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Publishes validated Events proposal outcomes as minimal Messenger events.
 */
final class OrganizationContributionNoticePublisher implements EventSubscriberInterface {

  /**
   * Creates a publisher with optional Messenger integration services.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?DomainPurposeManager $domainPurposes,
    private readonly ?MessengerNoticePublisherInterface $publisher,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns the Events proposal outcome subscription.
   */
  public static function getSubscribedEvents(): array {
    return [OrganizationContributionOutcomeRecordedEvent::NAME => 'proposalOutcome'];
  }

  /**
   * Revalidates the persisted proposal and recipient before publishing.
   */
  public function proposalOutcome(OrganizationContributionOutcomeRecordedEvent $event): void {
    if ($this->publisher === NULL || $this->domainPurposes === NULL) {
      return;
    }
    $recipient = $this->entityTypeManager->getStorage('user')->loadUnchanged($event->recipientUid);
    if (!$recipient instanceof UserInterface || !$recipient->isActive()) {
      return;
    }
    $proposal = $this->entityTypeManager->getStorage('aculta_contribution')->loadRevision($event->proposalRevisionId);
    $allowedIntents = [
      'events_organization' => ['organization_update', 'organization_create'],
      'events_location' => ['location_update', 'location_create'],
      'events_event' => ['event_create', 'event_update'],
    ];
    if (!$proposal instanceof Contribution || $proposal->get('source')->value !== $event->source
      || !in_array($proposal->get('intent')->value, $allowedIntents[$event->source] ?? [], TRUE)
      || $proposal->uuid() !== $event->proposalUuid
      || (int) $proposal->getRevisionId() !== $event->proposalRevisionId
      || (int) $proposal->getOwnerId() !== $event->recipientUid
      || $proposal->get('state')->value !== $event->outcome) {
      return;
    }
    $allowed = $this->domainPurposes->runInPurpose('eventos', static fn(): bool =>
      $proposal->access('view', $recipient, TRUE)->isAllowed()
    );
    if (!$allowed) {
      return;
    }
    try {
      $type = 'events.organization_proposal_outcome';
      $this->publisher->publish(new MessengerNoticeEvent(
        $type,
        'aculta_events',
        'aculta_contribution',
        $proposal->uuid(),
        (string) $event->proposalRevisionId,
        $event->recipientUid,
        'eventos',
        new \DateTimeImmutable('@' . $this->time->getRequestTime()),
        $type . ':' . $proposal->uuid() . ':' . $event->proposalRevisionId . ':' . $event->recipientUid,
        [
          'notice_type' => $event->outcome,
          'proposal_source' => $event->source,
          'mail_policy' => 'user_preference',
        ],
      ));
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not publish an Events contribution outcome notice.');
      throw $exception;
    }
  }

}
