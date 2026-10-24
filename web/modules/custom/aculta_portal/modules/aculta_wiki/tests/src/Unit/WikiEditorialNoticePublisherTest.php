<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\EventSubscriber\WikiEditorialNoticePublisher;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\aculta_wiki\Event\WikiProposalOutcomeRecordedEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests that stale Wiki events are rejected before reaching Messenger.
 */
#[Group('aculta_wiki')]
final class WikiEditorialNoticePublisherTest extends TestCase {

  /**
   * Does not publish when the referenced revision no longer exists.
   */
  public function testMissingRevisionIsIgnored(): void {
    $storage = $this->createMock(RevisionableStorageInterface::class);
    $storage->expects(self::once())
      ->method('loadRevision')
      ->with(17)
      ->willReturn(NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($storage);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::never())->method('publish');

    $subscriber = new WikiEditorialNoticePublisher(
      $entityTypeManager,
      new DomainPurposeManager(
        $entityTypeManager,
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
      $publisher,
      $this->createMock(TimeInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    $subscriber->reviewRequested(new WikiEntryReviewRequestedEvent(
      '550e8400-e29b-41d4-a716-446655440000',
      17,
    ));

    self::assertSame([
      WikiEntryReviewRequestedEvent::NAME => 'reviewRequested',
      WikiProposalOutcomeRecordedEvent::NAME => 'proposalOutcome',
    ], WikiEditorialNoticePublisher::getSubscribedEvents());
  }

}
