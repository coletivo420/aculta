<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Events\EventSubscriber\EventsContributionNoticeConsumer;
use Drupal\aculta_messenger\Integration\Events\EventsContributionNoticeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the allowlisted Messenger consumer for Events contributions. */
#[CoversClass(EventsContributionNoticeConsumer::class)]
final class EventsContributionNoticeConsumerTest extends UnitTestCase {

  /**
   * Valid outcome envelopes reach the owner manager without proposal data. */
  public function testConsumesMinimalLocationOutcome(): void {
    $manager = $this->createMock(EventsContributionNoticeManagerInterface::class);
    $manager->expects(self::once())->method('record')->with(self::callback(static fn(MessengerNoticeEvent $event): bool =>
      $event->sourceEntityType() === 'aculta_contribution'
      && $event->metadata()['proposal_source'] === 'events_location'
      && $event->metadata()['notice_type'] === 'accepted'
      && !array_key_exists('payload', $event->metadata())
    ));
    $consumer = new EventsContributionNoticeConsumer($manager);
    $consumer->consume($this->event([
      'mail_policy' => 'none',
      'notice_type' => 'accepted',
      'proposal_source' => 'events_location',
    ]));
  }

  /**
   * Event proposal outcomes use the shared private-notice contract.
   */
  public function testConsumesMinimalEventOutcome(): void {
    $manager = $this->createMock(EventsContributionNoticeManagerInterface::class);
    $manager->expects(self::once())->method('record')->with(self::callback(static fn(MessengerNoticeEvent $event): bool =>
      $event->metadata()['proposal_source'] === 'events_event'
      && $event->metadata()['notice_type'] === 'needs_changes'
      && !array_key_exists('payload', $event->metadata())
    ));
    $consumer = new EventsContributionNoticeConsumer($manager);
    $consumer->consume($this->event([
      'mail_policy' => 'none',
      'notice_type' => 'needs_changes',
      'proposal_source' => 'events_event',
    ]));
  }

  /**
   * Author submission notices use the same minimal private-notice contract.
   */
  public function testConsumesMinimalSubmissionNotice(): void {
    $manager = $this->createMock(EventsContributionNoticeManagerInterface::class);
    $manager->expects(self::once())->method('record')->with(self::callback(static fn(MessengerNoticeEvent $event): bool =>
      $event->metadata()['proposal_source'] === 'events_event'
      && $event->metadata()['notice_type'] === 'submitted'
      && !array_key_exists('payload', $event->metadata())
    ));
    $consumer = new EventsContributionNoticeConsumer($manager);
    $consumer->consume($this->event([
      'mail_policy' => 'none',
      'notice_type' => 'submitted',
      'proposal_source' => 'events_event',
    ]));
  }

  /**
   * Unknown source and mail policy contracts fail closed. */
  public function testIgnoresUnsupportedSourceAndMailPolicy(): void {
    $manager = $this->createMock(EventsContributionNoticeManagerInterface::class);
    $manager->expects(self::never())->method('record');
    $consumer = new EventsContributionNoticeConsumer($manager);
    $consumer->consume($this->event([
      'notice_type' => 'accepted',
      'proposal_source' => 'events_location',
      'mail_policy' => 'user_preference',
    ]));
  }

  /**
   * Builds a valid minimal event with caller-selected metadata. */
  private function event(array $metadata): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'events.organization_proposal_outcome',
      'aculta_events',
      'aculta_contribution',
      'f6be4a3e-6d93-40c8-a47a-379d170dc852',
      '12',
      7,
      'eventos',
      new \DateTimeImmutable('2026-11-02T12:00:00Z'),
      'events-proposal:f6be4a3e-6d93-40c8-a47a-379d170dc852:12:7',
      $metadata,
    );
  }

}
