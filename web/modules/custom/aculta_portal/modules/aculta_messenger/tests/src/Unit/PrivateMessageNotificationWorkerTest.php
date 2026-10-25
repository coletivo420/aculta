<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\aculta_messenger\Plugin\QueueWorker\PrivateMessageNotificationWorker;
use Drupal\aculta_messenger\Service\ConversationWriteLock;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\aculta_messenger\Mail\PrivateMessageNoticeMailType;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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
   * A queued notice is suppressed after the recipient loses thread access.
   */
  public function testRevokedThreadAccessSuppressesQueuedNotice(): void {
    $this->assertNotificationSuppressed(FALSE, NULL);
  }

  /**
   * A queued notice is suppressed after the member clears its message history.
   */
  public function testClearedMessageDoesNotTriggerQueuedNotice(): void {
    $this->assertNotificationSuppressed(TRUE, NULL, 100);
  }

  /**
   * A bilateral block suppresses queued notifications in either direction.
   */
  public function testEitherBlockDirectionSuppressesQueuedNotice(): void {
    $this->assertNotificationSuppressed(TRUE, [1, 2]);
    $this->assertNotificationSuppressed(TRUE, [2, 1]);
  }

  /**
   * Inactive recipients and accounts without inbox permissions get no email.
   */
  #[DataProvider('ineligibleRecipientCases')]
  public function testIneligibleRecipientCannotReceiveNotice(
    bool $active,
    bool $canUsePrivateMessages,
    bool $canAccessProfiles,
  ): void {
    $this->assertNotificationSuppressed(
      TRUE,
      NULL,
      NULL,
      $active,
      $canUsePrivateMessages,
      $canAccessProfiles,
    );
  }

  /**
   * Old queue records do not try to deliver a row owned by the Mailer.
   */
  public function testLegacyQueueDoesNotHandleMailerManagedNotification(): void {
    $privateMessage = $this->createMock(PrivateMessageInterface::class);
    $privateMessage->method('getOwnerId')->willReturn(1);
    $privateMessageStorage = $this->createMock(EntityStorageInterface::class);
    $privateMessageStorage->method('load')->with(15)->willReturn($privateMessage);

    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('id')->willReturn('2');
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('hasPermission')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('member@example.test');
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(2)->willReturn($recipient);

    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('id')->willReturn('123');
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
      ['private_message_thread', $this->threadStorage($thread)],
    ]);

    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
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
      $this->conversationWriteLock($entityTypes),
      $notifier,
      $mailer,
      $this->domainPurposeManager($entityTypes),
      $lock,
      $preferences,
      $this->emailPolicy($entityTypes),
    );
    $worker->processItem(['private_message_id' => 15]);

    $this->assertContains(['template', 'aculta_msg_private_notification', NULL], $dedupeConditions);
    $this->assertContains(['uid', 2, NULL], $dedupeConditions);
    $this->assertContains(['field_aculta_messenger_source_pm', 15, NULL], $dedupeConditions);
  }

  /**
   * New notices use the Mailer and omit private message content.
   */
  #[DataProvider('emailConfirmationCases')]
  public function testMailerManagedNoticeUsesPrivateTemplate(
    bool $emailConfirmed,
    string $emailMode,
    bool $existingPending,
    string $initialStatus = 'pending',
    bool $directDeliveryAlreadyAccepted = FALSE,
  ): void {
    $privateMessage = $this->createMock(PrivateMessageInterface::class);
    $privateMessage->method('getOwnerId')->willReturn(1);
    $privateMessage->method('getCreatedTime')->willReturn(101);
    $privateMessageStorage = $this->createMock(EntityStorageInterface::class);
    $privateMessageStorage->method('load')->with(15)->willReturn($privateMessage);
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('id')->willReturn('2');
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('hasPermission')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('member@example.test');
    $sender = $this->createMock(UserInterface::class);
    $sender->method('getDisplayName')->willReturn('Remetente');
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->willReturnCallback(fn(int $uid): UserInterface => $uid === 2 ? $recipient : $sender);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('id')->willReturn('123');
    $thread->method('getMembersId')->willReturn([1, 2]);
    $thread->method('access')->with('view', $recipient, TRUE)->willReturn(AccessResult::allowed());
    $thread->method('getLastDeleteTimestamp')->with($recipient)->willReturn(100);
    $messageService = $this->createMock(PrivateMessageServiceInterface::class);
    $messageService->method('getThreadFromMessage')->with($privateMessage)->willReturn($thread);

    $banQuery = $this->createMock(QueryInterface::class);
    $banQuery->method('accessCheck')->willReturnSelf();
    $banQuery->method('condition')->willReturnSelf();
    $banQuery->method('range')->willReturnSelf();
    $banQuery->method('execute')->willReturn([]);
    $banStorage = $this->createMock(EntityStorageInterface::class);
    $banStorage->method('getQuery')->willReturn($banQuery);
    $noticeQuery = $this->createMock(QueryInterface::class);
    $noticeQuery->method('accessCheck')->willReturnSelf();
    $noticeQuery->method('condition')->willReturnSelf();
    $noticeQuery->method('range')->willReturnSelf();
    $noticeQuery->method('execute')->willReturn($existingPending ? [88] : []);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($noticeQuery);
    $deliveryStatus = $initialStatus;
    $status = $this->createMock(FieldItemListInterface::class);
    $status->method('__get')->with('value')->willReturnCallback(static fn(): string => $deliveryStatus);
    $attempts = $this->field(0);
    $managed = $this->field(TRUE);
    $digestSent = $this->field(0);
    $digestSentValue = 0;
    $notice = $this->createMock(MessageInterface::class);
    $notice->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface => match ($field) {
      'field_aculta_msg_delivery' => $status,
      'field_aculta_messenger_attempts' => $attempts,
      'field_aculta_pm_digest_sent' => $digestSent,
      default => $managed,
    });
    $notice->method('set')->willReturnCallback(function (string $field, mixed $value) use ($notice, &$deliveryStatus, &$digestSentValue): MessageInterface {
      if ($field === 'field_aculta_msg_delivery') {
        $deliveryStatus = $value;
      }
      if ($field === 'field_aculta_pm_digest_sent') {
        $digestSentValue = $value;
      }
      return $notice;
    });
    $notice->method('setArguments')->willReturnSelf();
    $notice->method('save')->willReturn(1);
    $recordRetryable = in_array($initialStatus, ['pending', 'failed'], TRUE);
    $shouldSend = $recordRetryable && $emailConfirmed && $emailMode === AvisoPreferencias::EMAIL_IMMEDIATE;
    $shouldQueueDigest = $recordRetryable && $emailConfirmed && $emailMode === AvisoPreferencias::EMAIL_DIGEST && !$directDeliveryAlreadyAccepted;
    $noticeStorage->expects($existingPending ? self::never() : (($shouldSend || $shouldQueueDigest) ? self::once() : self::never()))->method('create')
      ->with(self::callback(fn(array $values): bool => ($values['field_aculta_mailer_managed'] ?? FALSE) === TRUE
        && (!$shouldQueueDigest || ($values['field_aculta_pm_digest_sent'] ?? NULL) === 0)))
      ->willReturn($notice);
    $noticeStorage->method('load')->with(88)->willReturn($notice);

    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('conta_aculta_org')->willReturn($domain);
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->expects(self::once())->method('loadUnchanged')->with(123)->willReturn($thread);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->willReturnMap([
      ['private_message', $privateMessageStorage],
      ['user', $userStorage],
      ['private_message_ban', $banStorage],
      ['message', $noticeStorage],
      ['domain', $domainStorage],
      ['private_message_thread', $threadStorage],
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(2, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES)
      ->willReturn($emailMode);
    $lockCalls = [];
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(static function (string $name, float $timeout) use (&$lockCalls): bool {
      $lockCalls[] = ['acquire', $name, $timeout];
      return TRUE;
    });
    $lock->method('release')->willReturnCallback(static function (string $name) use (&$lockCalls): void {
      $lockCalls[] = ['release', $name];
    });
    $legacy = $this->createMock(MessageNotifyInterface::class);
    $legacy->expects(self::never())->method('send');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $sentMail = NULL;
    $mailer->expects($shouldSend ? self::once() : self::never())->method('deliver')
      ->with(self::callback(static function (MailMessage $mail) use (&$sentMail): bool {
        $sentMail = $mail;
        return TRUE;
      }))
      ->willReturn(SendResult::sent());
    if ($emailMode === AvisoPreferencias::EMAIL_DIGEST && $emailConfirmed && $existingPending) {
      $mailer->expects(self::once())->method('wasAcceptedCorrelation')
        ->with(
          MessageIdFactory::correlationIdFor('aculta_messenger:private-message:15:2:' . PrivateMessageNoticeMailType::ID),
          PrivateMessageNoticeMailType::ID,
          'member@example.test',
        )
        ->willReturn($directDeliveryAlreadyAccepted);
    }
    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->expects($shouldSend ? self::exactly(2) : self::never())->method('generateFromRoute')
      ->with('aculta_portal.dashboard')
      ->willReturn('https://account.example.test/minha-conta');
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);
    try {
      $worker = new PrivateMessageNotificationWorker(
        [], 'aculta_msg_private_notification', [], $entityTypes, $messageService,
        new ConversationWriteLock($lock, $entityTypes), $legacy, $mailer,
        $this->domainPurposeManager($entityTypes), $lock,
        $preferences, $this->emailPolicy($entityTypes, $emailConfirmed),
      );
      $worker->processItem(['private_message_id' => 15, 'mailer_managed' => TRUE]);
    }
    finally {
      \Drupal::unsetContainer();
    }
    if ($existingPending && !$shouldSend && !$shouldQueueDigest && !$directDeliveryAlreadyAccepted) {
      self::assertSame(
        in_array($initialStatus, ['cancelled', 'failed_permanent'], TRUE) ? $initialStatus : 'cancelled',
        $deliveryStatus,
      );
    }
    if ($directDeliveryAlreadyAccepted) {
      self::assertSame('sent', $deliveryStatus);
      self::assertSame(1, $digestSentValue);
    }
    if ($shouldSend) {
      self::assertSame('private_message_notice', $sentMail->type);
      self::assertSame('member@example.test', $sentMail->to);
      self::assertSame('pt-br', $sentMail->langcode);
      self::assertSame('Você tem uma nova mensagem de Remetente.', $sentMail->context['summary']);
      self::assertStringNotContainsString('private body', serialize($sentMail->context));
    }
    self::assertSame([
      ['acquire', 'aculta_messenger:conversation_write:123', 180.0],
      ['acquire', 'aculta_messenger:private_message:15:2', 180.0],
      ['release', 'aculta_messenger:private_message:15:2'],
      ['release', 'aculta_messenger:conversation_write:123'],
    ], $lockCalls);
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
   * Asserts the queue worker does not persist or email an ineligible notice.
   *
   * @param bool $threadAccessible
   *   Whether the recipient can still view the thread.
   * @param array|null $blockedPair
   *   Owner and target IDs for the block that applies, or NULL for no block.
   * @param int|null $lastDeleteTimestamp
   *   The recipient's history cutoff, or NULL when history was not cleared.
   * @param bool $recipientActive
   *   Whether the account is active.
   * @param bool $canUsePrivateMessages
   *   Whether the account retains the private-message permission.
   * @param bool $canAccessProfiles
   *   Whether the account retains profile access.
   */
  private function assertNotificationSuppressed(
    bool $threadAccessible,
    ?array $blockedPair,
    ?int $lastDeleteTimestamp = NULL,
    bool $recipientActive = TRUE,
    bool $canUsePrivateMessages = TRUE,
    bool $canAccessProfiles = TRUE,
  ): void {
    $privateMessage = $this->createMock(PrivateMessageInterface::class);
    $privateMessage->method('getOwnerId')->willReturn(1);
    $privateMessage->method('getCreatedTime')->willReturn(100);
    $privateMessageStorage = $this->createMock(EntityStorageInterface::class);
    $privateMessageStorage->method('load')->with(15)->willReturn($privateMessage);
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('id')->willReturn('2');
    $recipient->method('isActive')->willReturn($recipientActive);
    $recipient->method('hasPermission')->willReturnCallback(static fn(string $permission): bool => match ($permission) {
      'use private messaging system' => $canUsePrivateMessages,
      'access user profiles' => $canAccessProfiles,
      default => FALSE,
    });
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(2)->willReturn($recipient);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('id')->willReturn('123');
    $thread->method('getMembersId')->willReturn([1, 2]);
    $recipientEligible = $recipientActive && $canUsePrivateMessages && $canAccessProfiles;
    $thread->expects($recipientEligible ? self::once() : self::never())
      ->method('access')->with('view', $recipient, TRUE)->willReturn(
      $threadAccessible ? AccessResult::allowed() : AccessResult::forbidden(),
    );
    $thread->method('getLastDeleteTimestamp')->with($recipient)->willReturn($lastDeleteTimestamp);
    $privateMessageService = $this->createMock(PrivateMessageServiceInterface::class);
    $privateMessageService->method('getThreadFromMessage')->with($privateMessage)->willReturn($thread);

    $banStorage = $this->createMock(EntityStorageInterface::class);
    $queryCount = !$recipientEligible || !$threadAccessible ? 0 : ($blockedPair === [1, 2] ? 1 : 2);
    $banStorage->expects(self::exactly($queryCount))->method('getQuery')->willReturnCallback(function () use ($blockedPair): QueryInterface {
      $conditions = [];
      $query = $this->createMock(QueryInterface::class);
      $query->method('accessCheck')->with(FALSE)->willReturnSelf();
      $query->method('condition')->willReturnCallback(
        static function (string $field, mixed $value, ?string $operator = NULL) use (&$conditions, $query): QueryInterface {
          $conditions[$field] = $value;
          return $query;
        },
      );
      $query->method('range')->willReturnSelf();
      $query->method('execute')->willReturnCallback(static function () use (&$conditions, $blockedPair): array {
        return $blockedPair !== NULL
          && (int) ($conditions['owner'] ?? 0) === $blockedPair[0]
          && (int) ($conditions['target'] ?? 0) === $blockedPair[1]
          ? [501]
          : [];
      });
      return $query;
    });
    $notificationStorage = $this->createMock(EntityStorageInterface::class);
    $notificationStorage->expects(self::never())->method('getQuery');
    $notificationStorage->expects(self::never())->method('create');
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['private_message', $privateMessageStorage],
      ['user', $userStorage],
      ['private_message_ban', $banStorage],
      ['message', $notificationStorage],
      ['private_message_thread', $this->threadStorage($thread)],
    ]);
    $lockId = 'aculta_messenger:private_message:15:2';
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->with($lockId, 180.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with($lockId);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');
    $messageNotify = $this->createMock(MessageNotifyInterface::class);
    $messageNotify->expects(self::never())->method('send');
    $domainPurposes = $this->domainPurposeManager($entities);
    $worker = new PrivateMessageNotificationWorker(
      [],
      'aculta_msg_private_notification',
      [],
      $entities,
      $privateMessageService,
      $this->conversationWriteLock($entities),
      $messageNotify,
      $mailer,
      $domainPurposes,
      $lock,
      $this->createMock(AvisoPreferenciasInterface::class),
      $this->emailPolicy($entities),
    );

    $worker->processItem(['private_message_id' => 15, 'mailer_managed' => TRUE]);
  }

  /**
   * Builds the shared conversation lock without coupling it to email locks.
   */
  private function conversationWriteLock(EntityTypeManagerInterface $entityTypes): ConversationWriteLock {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return new ConversationWriteLock($lock, $entityTypes);
  }

  /**
   * Returns a thread storage that reloads the requested conversation.
   */
  private function threadStorage(PrivateMessageThreadInterface $thread): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadUnchanged')->with(123)->willReturn($thread);
    return $storage;
  }

  /**
   * Provides both email confirmation states for the notification worker.
   */
  public static function emailConfirmationCases(): array {
    return [
      'confirmed and enabled' => [TRUE, AvisoPreferencias::EMAIL_IMMEDIATE, FALSE],
      'weekly digest queues without sending' => [TRUE, AvisoPreferencias::EMAIL_DIGEST, FALSE],
      'accepted direct email is reconciled before weekly digest' => [
        TRUE,
        AvisoPreferencias::EMAIL_DIGEST,
        TRUE,
        'pending',
        TRUE,
      ],
      'unconfirmed email' => [FALSE, AvisoPreferencias::EMAIL_IMMEDIATE, FALSE],
      'opt-out cancels persisted pending email' => [TRUE, AvisoPreferencias::EMAIL_OFF, TRUE],
      'opt-out cancellation remains terminal after opt-in' => [
        TRUE,
        AvisoPreferencias::EMAIL_IMMEDIATE,
        TRUE,
        'cancelled',
      ],
      'permanent delivery failure remains terminal' => [
        TRUE,
        AvisoPreferencias::EMAIL_IMMEDIATE,
        TRUE,
        'failed_permanent',
      ],
    ];
  }

  /**
   * Provides account eligibility conditions that suppress delivery.
   *
   * @return array<string, array{bool, bool, bool}>
   *   Account status, private-message permission, and profile permission.
   */
  public static function ineligibleRecipientCases(): array {
    return [
      'inactive account' => [FALSE, TRUE, TRUE],
      'private messaging permission revoked' => [TRUE, FALSE, TRUE],
      'profile access permission revoked' => [TRUE, TRUE, FALSE],
    ];
  }

  /**
   * Creates the configured confirmation policy for a test recipient.
   */
  private function emailPolicy(EntityTypeManagerInterface $entityTypes, bool $confirmed = TRUE): EmailConfirmationPolicy {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturn($confirmed);
    return new EmailConfirmationPolicy($userData, $entityTypes, $this->createMock(ModuleHandlerInterface::class));
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
