<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Plugin\QueueWorker\PrivateMessageNotificationWorker;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifyInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Ensures a persisted successful notification is not delivered twice.
 *
 * @group aculta_messenger
 */
#[Group('aculta_messenger')]
final class PrivateMessageNotificationWorkerTest extends UnitTestCase {

  public function testAlreadySentEventDoesNotCallMailTransportAgain(): void {
    $privateMessage = $this->createMock(PrivateMessageInterface::class);
    $privateMessage->method('getOwnerId')->willReturn(1);
    $privateMessageStorage = $this->createMock(EntityStorageInterface::class);
    $privateMessageStorage->method('load')->with(15)->willReturn($privateMessage);

    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('hasPermission')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('member@example.test');
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(2)->willReturn($recipient);

    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembersId')->willReturn([1, 2]);
    $thread->method('access')->with('view', $recipient, TRUE)->willReturn(\Drupal\Core\Access\AccessResult::allowed());
    $messageService = $this->createMock(PrivateMessageServiceInterface::class);
    $messageService->method('getThreadFromMessage')->with($privateMessage)->willReturn($thread);

    $banQuery = $this->createMock(QueryInterface::class);
    $banQuery->method('accessCheck')->with(FALSE)->willReturnSelf();
    $banQuery->method('condition')->willReturnSelf();
    $banQuery->method('range')->willReturnSelf();
    $banQuery->method('execute')->willReturn([]);
    $banStorage = $this->createMock(EntityStorageInterface::class);
    $banStorage->method('getQuery')->willReturn($banQuery);

    $dedupeConditions = [];
    $sentQuery = $this->createMock(QueryInterface::class);
    $sentQuery->method('accessCheck')->with(FALSE)->willReturnSelf();
    $sentQuery->method('condition')->willReturnCallback(static function (string $field, mixed $value, ?string $operator = NULL) use (&$dedupeConditions, $sentQuery): QueryInterface {
      $dedupeConditions[] = [$field, $value, $operator];
      return $sentQuery;
    });
    $sentQuery->method('range')->willReturnSelf();
    $sentQuery->method('execute')->willReturn([88]);
    $status = $this->createMock(FieldItemListInterface::class);
    $status->method('__get')->with('value')->willReturn('sent');
    $notification = $this->createMock(MessageInterface::class);
    $notification->method('get')->with('field_aculta_msg_delivery')->willReturn($status);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($sentQuery);
    $messageStorage->method('load')->with(88)->willReturn($notification);

    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->willReturnMap([
      ['private_message', $privateMessageStorage],
      ['user', $userStorage],
      ['private_message_ban', $banStorage],
      ['message', $messageStorage],
    ]);

    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['enable_notifications', TRUE],
      ['notify_by_default', TRUE],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('private_message.settings')->willReturn($settings);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->expects($this->once())->method('release')->with('aculta_messenger:private_message:15:2');
    $notifier = $this->createMock(MessageNotifyInterface::class);
    $notifier->expects($this->never())->method('send');

    $worker = new PrivateMessageNotificationWorker(
      [],
      'aculta_msg_private_notification',
      [],
      $entityTypes,
      $messageService,
      $notifier,
      $this->domainPurposeManager($entityTypes),
      $lock,
      $this->createMock(UserDataInterface::class),
      $configFactory,
    );
    $worker->processItem(['private_message_id' => 15]);

    $this->assertContains(['template', 'aculta_msg_private_notification', NULL], $dedupeConditions);
    $this->assertContains(['uid', 2, NULL], $dedupeConditions);
    $this->assertContains(['field_aculta_messenger_source_pm', 15, NULL], $dedupeConditions);
  }

  private function domainPurposeManager(EntityTypeManagerInterface $entityTypes): DomainPurposeManager {
    return new DomainPurposeManager(
      $entityTypes,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
