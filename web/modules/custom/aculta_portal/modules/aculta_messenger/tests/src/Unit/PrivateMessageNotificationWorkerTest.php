<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\aculta_messenger\Plugin\QueueWorker\PrivateMessageNotificationWorker;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifyInterface;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Ensures legacy queue items do not claim notifications owned by the Mailer.
 *
 * @group aculta_messenger
 */
#[Group('aculta_messenger')]
final class PrivateMessageNotificationWorkerTest extends UnitTestCase {

  /**
   * Old queue records do not try to deliver a row owned by the Mailer.
   */
  public function testLegacyQueueDoesNotHandleMailerManagedNotification(): void {
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
    $thread->method('access')->with('view', $recipient, TRUE)->willReturn(AccessResult::allowed());
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
    $status->method('__get')->with('value')->willReturn('pending');
    $mailerManaged = $this->createMock(FieldItemListInterface::class);
    $mailerManaged->method('__get')->with('value')->willReturn(TRUE);
    $notification = $this->createMock(MessageInterface::class);
    $notification->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface =>
      $field === 'field_aculta_msg_delivery' ? $status : $mailerManaged);
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
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->never())->method('deliver');

    $worker = new PrivateMessageNotificationWorker(
      [],
      'aculta_msg_private_notification',
      [],
      $entityTypes,
      $messageService,
      $notifier,
      $mailer,
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

  /**
   * New notices use the Mailer and omit private message content.
   */
  public function testMailerManagedNoticeUsesPrivateTemplate(): void {
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
    $thread->method('access')->with('view', $recipient, TRUE)->willReturn(AccessResult::allowed());
    $messageService = $this->createMock(PrivateMessageServiceInterface::class);
    $messageService->method('getThreadFromMessage')->with($privateMessage)->willReturn($thread);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $banStorage = $this->createMock(EntityStorageInterface::class);
    $banStorage->method('getQuery')->willReturn($query);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $status = $this->field('pending');
    $attempts = $this->field(0);
    $managed = $this->field(TRUE);
    $notice = $this->createMock(MessageInterface::class);
    $notice->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface => match ($field) {
      'field_aculta_msg_delivery' => $status,
      'field_aculta_messenger_attempts' => $attempts,
      default => $managed,
    });
    $notice->method('set')->willReturnSelf();
    $notice->method('setArguments')->willReturnSelf();
    $notice->method('save')->willReturn(1);
    $noticeStorage->expects(self::once())->method('create')
      ->with(self::callback(static fn(array $values): bool => ($values['field_aculta_mailer_managed'] ?? FALSE) === TRUE))
      ->willReturn($notice);

    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('conta_aculta_org')->willReturn($domain);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->willReturnMap([
      ['private_message', $privateMessageStorage],
      ['user', $userStorage],
      ['private_message_ban', $banStorage],
      ['message', $noticeStorage],
      ['domain', $domainStorage],
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
    $legacy = $this->createMock(MessageNotifyInterface::class);
    $legacy->expects(self::never())->method('send');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')
      ->with(self::callback(static fn(MailMessage $mail): bool =>
        $mail->type === 'private_message_notice'
        && $mail->to === 'member@example.test'
        && $mail->langcode === 'pt-br'
        && !str_contains(serialize($mail->context), 'private body')))
      ->willReturn(SendResult::sent());
    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->expects(self::exactly(2))->method('generateFromRoute')
      ->with('aculta_portal.dashboard')
      ->willReturn('https://account.example.test/minha-conta');
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);
    try {
      $worker = new PrivateMessageNotificationWorker(
        [], 'aculta_msg_private_notification', [], $entityTypes, $messageService, $legacy, $mailer,
        $this->domainPurposeManager($entityTypes), $lock, $this->createMock(UserDataInterface::class), $configFactory,
      );
      $worker->processItem(['private_message_id' => 15, 'mailer_managed' => TRUE]);
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * Creates a scalar Field API item mock for worker state.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

  /**
   * Creates the canonical account URL builder used by this worker.
   */
  private function domainPurposeManager(EntityTypeManagerInterface $entityTypes): DomainPurposeManager {
    return new DomainPurposeManager(
      $entityTypes,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
