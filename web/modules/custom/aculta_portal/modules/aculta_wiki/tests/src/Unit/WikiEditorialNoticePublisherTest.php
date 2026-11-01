<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Wiki\EditorialReviewNoticeInterface;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\EventSubscriber\WikiEditorialNoticePublisher;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainInterface;
use Drupal\aculta_wiki\Event\WikiProposalOutcomeRecordedEvent;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
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
    $editorial = $this->createMock(EditorialReviewNoticeInterface::class);
    $editorial->expects(self::never())->method('notifyReview');

    $subscriber = new WikiEditorialNoticePublisher(
      $entityTypeManager,
      new DomainPurposeManager(
        $entityTypeManager,
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
      $publisher,
      $editorial,
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

  /**
   * Publishes a minimal review notice only to an authorized active editor.
   */
  public function testReviewNoticeUsesMinimalEnvelopeForAuthorizedEditor(): void {
    $revision = $this->createMock(NodeInterface::class);
    $revision->method('uuid')->willReturn('550e8400-e29b-41d4-a716-446655440000');
    $revision->method('bundle')->willReturn('wiki_entry');
    $revision->method('isDefaultRevision')->willReturn(TRUE);
    $revision->method('getOwnerId')->willReturn(2);
    $revision->method('hasField')->with('moderation_state')->willReturn(TRUE);
    $revision->method('get')->with('moderation_state')->willReturn((object) ['value' => 'review']);
    $revision->method('access')->willReturn(AccessResult::allowed());

    $nodeStorage = $this->createMock(RevisionableStorageInterface::class);
    $nodeStorage->method('loadRevision')->with(17)->willReturn($revision);
    $editor = $this->createMock(UserInterface::class);
    $editor->method('isActive')->willReturn(TRUE);
    $editor->method('id')->willReturn('9');
    $editor->method('hasPermission')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadByProperties')->with([
      'status' => 1,
      'roles' => 'content_editor',
    ])->willReturn([$editor]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface|RevisionableStorageInterface => match ($type) {
      'node' => $nodeStorage,
      'user' => $userStorage,
      default => throw new \LogicException('Unexpected storage: ' . $type),
    });

    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())
      ->method('publish')
      ->with(self::callback(static function (MessengerNoticeEvent $event): bool {
        return $event->eventType() === 'wiki.review_requested'
          && $event->sourceModule() === 'aculta_wiki'
          && $event->sourceEntityType() === 'node'
          && $event->sourceEntityUuid() === '550e8400-e29b-41d4-a716-446655440000'
          && $event->sourceRevisionOrVersion() === '17'
          && $event->recipientUid() === 9
          && $event->purpose() === 'wiki'
          && $event->idempotencyKey() === 'wiki.review_requested:550e8400-e29b-41d4-a716-446655440000:17:9'
          && $event->metadata() === ['notice_type' => 'review_requested', 'mail_policy' => 'user_preference'];
      }));

    $subscriber = $this->subscriber($entityTypeManager, $publisher);
    $subscriber->reviewRequested(new WikiEntryReviewRequestedEvent(
      '550e8400-e29b-41d4-a716-446655440000',
      17,
    ));
  }

  /**
   * Does not notify an editor who cannot view the source revision.
   */
  public function testReviewNoticeIsSuppressedWithoutRevisionAccess(): void {
    $revision = $this->createMock(NodeInterface::class);
    $revision->method('uuid')->willReturn('550e8400-e29b-41d4-a716-446655440000');
    $revision->method('bundle')->willReturn('wiki_entry');
    $revision->method('isDefaultRevision')->willReturn(TRUE);
    $revision->method('getOwnerId')->willReturn(2);
    $revision->method('hasField')->with('moderation_state')->willReturn(TRUE);
    $revision->method('get')->with('moderation_state')->willReturn((object) ['value' => 'review']);
    $revision->method('access')->willReturn(AccessResult::forbidden());

    $nodeStorage = $this->createMock(RevisionableStorageInterface::class);
    $nodeStorage->method('loadRevision')->willReturn($revision);
    $editor = $this->createMock(UserInterface::class);
    $editor->method('isActive')->willReturn(TRUE);
    $editor->method('id')->willReturn('9');
    $editor->method('hasPermission')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadByProperties')->willReturn([$editor]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface|RevisionableStorageInterface => match ($type) {
      'node' => $nodeStorage,
      'user' => $userStorage,
      default => throw new \LogicException('Unexpected storage: ' . $type),
    });

    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::never())->method('publish');
    $this->subscriber($entityTypeManager, $publisher)->reviewRequested(new WikiEntryReviewRequestedEvent(
      '550e8400-e29b-41d4-a716-446655440000',
      17,
    ));
  }

  /**
   * Creates the Wiki publisher with a domain manager scoped to a test Wiki.
   */
  private function subscriber(
    EntityTypeManagerInterface $entityTypeManager,
    MessengerNoticePublisherInterface $publisher,
  ): WikiEditorialNoticePublisher {
    $domain = $this->createMock(DomainInterface::class);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('wiki420_aculta_org')->willReturn($domain);
    $domainManager = $this->createMock(EntityTypeManagerInterface::class);
    $domainManager->method('getStorage')->with('domain')->willReturn($domainStorage);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn(NULL);
    $domainPurposes = new DomainPurposeManager(
      $domainManager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_793_520_000);

    return new WikiEditorialNoticePublisher(
      $entityTypeManager,
      $domainPurposes,
      $publisher,
      $this->createMock(EditorialReviewNoticeInterface::class),
      $time,
      $this->createMock(LoggerInterface::class),
    );
  }

}
