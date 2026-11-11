<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Wiki\EditorialReviewNoticeInterface;
use Drupal\aculta_messenger\Integration\Wiki\EventSubscriber\WikiNoticeEventSubscriber;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
use Drupal\aculta_messenger\Service\MessengerNoticePublisher;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\EventSubscriber\WikiEditorialNoticePublisher as WikiNoticeProducer;
use Drupal\aculta_wiki\Plugin\QueueWorker\WikiEditorialEventWorker;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the Wiki editorial queue worker and Messenger publisher integration.
 */
#[Group('aculta_wiki')]
final class WikiEditorialNoticePipelineTest extends TestCase {

  /**
   * A queued review event reaches Messenger only for an authorized editor.
   */
  public function testQueueWorkerDispatchesAuthorizedReviewNoticeToMessenger(): void {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $revision = $this->createMock(NodeInterface::class);
    $revision->method('uuid')->willReturn($uuid);
    $revision->method('bundle')->willReturn('wiki_entry');
    $revision->method('isDefaultRevision')->willReturn(TRUE);
    $revision->method('getOwnerId')->willReturn(2);
    $revision->method('hasField')->with('moderation_state')->willReturn(TRUE);
    $revision->method('get')->with('moderation_state')->willReturn((object) ['value' => 'review']);
    $revision->method('access')->willReturn(AccessResult::allowed());

    $nodeStorage = $this->createMock(RevisionableStorageInterface::class);
    $nodeStorage->expects(self::once())->method('loadRevision')->with(17)->willReturn($revision);
    $editor = $this->createMock(UserInterface::class);
    $editor->method('isActive')->willReturn(TRUE);
    $editor->method('id')->willReturn('9');
    $editor->method('hasPermission')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('loadByProperties')->with([
      'status' => 1,
      'roles' => 'content_editor',
    ])->willReturn([$editor]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(
      static fn(string $type): EntityStorageInterface|RevisionableStorageInterface => match ($type) {
        'node' => $nodeStorage,
        'user' => $userStorage,
        default => throw new \LogicException('Unexpected storage: ' . $type),
      },
    );

    $manager = $this->createMock(WikiNoticeManagerInterface::class);
    $manager->expects(self::once())->method('record')->with(self::callback(
      static fn(MessengerNoticeEvent $event): bool => $event->eventType() === 'wiki.review_requested'
        && $event->sourceModule() === 'aculta_wiki'
        && $event->sourceEntityType() === 'node'
        && $event->sourceEntityUuid() === $uuid
        && $event->sourceRevisionOrVersion() === '17'
        && $event->recipientUid() === 9
        && $event->purpose() === 'wiki'
        && $event->metadata() === ['notice_type' => 'review_requested', 'mail_policy' => 'user_preference'],
    ));

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_793_520_000);
    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new WikiNoticeProducer(
      $entityTypeManager,
      $this->siteManager(),
      new MessengerNoticePublisher($dispatcher),
      $this->createMock(EditorialReviewNoticeInterface::class),
      $time,
      new NullLogger(),
    ));
    $dispatcher->addSubscriber(new WikiNoticeEventSubscriber($manager));

    $worker = new WikiEditorialEventWorker([], 'aculta_wiki_editorial_events', ['cron' => ['time' => 60]], $dispatcher);
    $worker->processItem([
      'schema_version' => 1,
      'event' => WikiEntryReviewRequestedEvent::NAME,
      'source_uuid' => $uuid,
      'source_revision_id' => 17,
    ]);
  }

  /**
   * Builds a Wiki purpose manager for the producer's access revalidation.
   */
  private function siteManager(): DomainSiteManager {
    $domain = $this->createMock(DomainInterface::class);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('wiki420_aculta_org')->willReturn($domain);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($domainStorage);

    return new DomainSiteManager(
      $entityTypeManager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
