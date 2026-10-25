<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Aviso\PrivateMessageDigestProvider;
use Drupal\Core\Access\AccessResult;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\message\MessageInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests weekly private-message digest eligibility. */
#[CoversClass(PrivateMessageDigestProvider::class)]
#[Group('aculta_messenger')]
final class PrivateMessageDigestProviderTest extends UnitTestCase {

  /**
   * The admin anchor and per-user seven-day window gate discovery.
   */
  public function testPendingRecipientMustBePastAnchorAndSevenDays(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('getOwnerId')->willReturn(9);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($this->pendingQuery([41]));
    $messageStorage->method('loadMultiple')->with([41])->willReturn([41 => $message]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($messageStorage);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(9, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES)
      ->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $configFactory = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => ['private_message_digest_anchor' => '2026-10-26'],
    ]);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->with('aculta_messenger', 9, 'private_message_digest_last_sent')
      ->willReturn(strtotime('2026-10-27 00:00:00 UTC'));
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnOnConsecutiveCalls(
      (int) strtotime('2026-11-02 23:59:59 UTC'),
      (int) strtotime('2026-11-03 00:00:00 UTC'),
    );
    $provider = new PrivateMessageDigestProvider(
      $entities,
      $this->createMock(PrivateMessageServiceInterface::class),
      $preferences,
      $configFactory,
      $userData,
      $time,
    );

    self::assertSame([], $provider->pendingRecipientIds());
    self::assertSame([9], $provider->pendingRecipientIds());
  }

  /**
   * The administrator's date starts at midnight in the site's timezone.
   */
  public function testAnchorUsesConfiguredSiteTimezone(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('getOwnerId')->willReturn(9);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($this->pendingQuery([41]));
    $messageStorage->method('loadMultiple')->with([41])->willReturn([41 => $message]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($messageStorage);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(9, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES)
      ->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $configFactory = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => ['private_message_digest_anchor' => '2026-10-26'],
      'system.date' => ['timezone' => ['default' => 'America/Sao_Paulo']],
    ]);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturn(0);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnOnConsecutiveCalls(
      (int) strtotime('2026-10-26 02:59:59 UTC'),
      (int) strtotime('2026-10-26 03:00:00 UTC'),
    );
    $provider = new PrivateMessageDigestProvider(
      $entities,
      $this->createMock(PrivateMessageServiceInterface::class),
      $preferences,
      $configFactory,
      $userData,
      $time,
    );

    self::assertSame([], $provider->pendingRecipientIds());
    self::assertSame([9], $provider->pendingRecipientIds());
  }

  /**
   * A malformed administrator anchor fails closed instead of enabling delivery.
   */
  public function testMalformedAnchorDoesNotEnableWeeklyDelivery(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('getOwnerId')->willReturn(9);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($this->pendingQuery([41]));
    $messageStorage->method('loadMultiple')->with([41])->willReturn([41 => $message]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($messageStorage);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(9, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES)
      ->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $provider = new PrivateMessageDigestProvider(
      $entities,
      $this->createMock(PrivateMessageServiceInterface::class),
      $preferences,
      $this->getConfigFactoryStub([
        'aculta_messenger.settings' => ['private_message_digest_anchor' => 'not-a-date'],
        'system.date' => ['timezone' => ['default' => 'UTC']],
      ]),
      $this->createMock(UserDataInterface::class),
      $this->createMock(TimeInterface::class),
    );

    self::assertSame([], $provider->pendingRecipientIds());
  }

  /**
   * Selective cancellation affects only the named recipient's email record.
   */
  public function testDiscardDigestCancelsSelectedRowsWithoutAdvancingWindow(): void {
    $notice = $this->createMock(MessageInterface::class);
    $notice->method('bundle')->willReturn('aculta_msg_private_notification');
    $notice->method('getOwnerId')->willReturn(9);
    $fields = [];
    $notice->expects(self::exactly(2))->method('set')->willReturnCallback(
      static function (string $field, mixed $value) use (&$fields, $notice): MessageInterface {
        $fields[$field] = $value;
        return $notice;
      },
    );
    $notice->expects(self::once())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('loadMultiple')->with([41])->willReturn([41 => $notice]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())->method('getStorage')->with('message')->willReturn($storage);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::never())->method('set');
    $provider = new PrivateMessageDigestProvider(
      $entities,
      $this->createMock(PrivateMessageServiceInterface::class),
      $this->createMock(AvisoPreferenciasInterface::class),
      $this->getConfigFactoryStub([]),
      $userData,
      $this->createMock(TimeInterface::class),
    );

    $provider->discardDigestItems(9, [41]);

    self::assertSame([
      'field_aculta_pm_digest_sent' => 1,
      'field_aculta_msg_delivery' => 'cancelled',
    ], $fields);
  }

  /**
   * Groups safe weekly summaries without reading private message bodies.
   */
  public function testDigestRowsGroupSendersWithoutReadingPrivateMessageBody(): void {
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('hasPermission')->willReturn(TRUE);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('access')->with('view', $recipient, TRUE)->willReturn(AccessResult::allowed());
    $thread->method('getMembersId')->willReturn([9, 33]);
    $thread->method('getLastDeleteTimestamp')->willReturn(0);
    $hiddenThread = $this->createMock(PrivateMessageThreadInterface::class);
    $hiddenThread->method('access')->with('view', $recipient, TRUE)->willReturn(AccessResult::forbidden());
    $notices = [];
    $discardedNoticeFields = [];
    foreach ([41 => 101, 42 => 102, 43 => 103] as $noticeId => $privateMessageId) {
      $notice = $this->createMock(MessageInterface::class);
      $notice->method('id')->willReturn((string) $noticeId);
      $notice->method('bundle')->willReturn('aculta_msg_private_notification');
      $notice->method('getOwnerId')->willReturn(9);
      $notice->method('get')->willReturnCallback(function (string $field) use ($privateMessageId): FieldItemListInterface {
        if ($field !== 'field_aculta_messenger_source_pm') {
          throw new \LogicException('The digest must not read private-message body or other fields.');
        }
        $item = $this->createMock(FieldItemListInterface::class);
        $item->method('__isset')->with('target_id')->willReturn(TRUE);
        $item->method('__get')->with('target_id')->willReturn($privateMessageId);
        return $item;
      });
      if ($noticeId === 43) {
        $notice->expects(self::exactly(2))->method('set')->willReturnCallback(
          static function (string $field, mixed $value) use (&$discardedNoticeFields): void {
            $discardedNoticeFields[] = [$field, $value];
          },
        );
        $notice->expects(self::once())->method('save')->willReturn(1);
      }
      $notices[$noticeId] = $notice;
    }
    $messageQuery = $this->pendingQuery([41, 42, 43]);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->expects(self::once())->method('getQuery')->willReturn($messageQuery);
    $messageStorage->method('loadMultiple')->with([41, 42, 43])->willReturn($notices);

    $privateMessages = [];
    foreach ([101 => 33, 102 => 33, 103 => 44] as $id => $senderId) {
      $privateMessage = $this->createMock(PrivateMessageInterface::class);
      $privateMessage->method('getOwnerId')->willReturn($senderId);
      $privateMessage->method('getCreatedTime')->willReturn(1_800_000_000);
      $privateMessages[$id] = $privateMessage;
    }
    $privateMessageStorage = $this->createMock(EntityStorageInterface::class);
    $loadedPrivateMessageIds = [];
    $privateMessageStorage->expects(self::exactly(3))->method('load')
      ->willReturnCallback(static function (int $id) use (&$loadedPrivateMessageIds, $privateMessages): ?PrivateMessageInterface {
        $loadedPrivateMessageIds[] = $id;
        return $privateMessages[$id] ?? NULL;
      });

    $users = [9 => $recipient];
    foreach ([33 => 'Remetente', 44 => 'Sem acesso'] as $uid => $name) {
      $sender = $this->createMock(UserInterface::class);
      $sender->method('isActive')->willReturn(TRUE);
      $sender->method('getDisplayName')->willReturn($name);
      $users[$uid] = $sender;
    }
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->willReturnCallback(static fn(int $uid): ?UserInterface => $users[$uid] ?? NULL);

    $banQuery = $this->pendingQuery([]);
    $banStorage = $this->createMock(EntityStorageInterface::class);
    $banStorage->method('getQuery')->willReturn($banQuery);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'message' => $messageStorage,
      'private_message' => $privateMessageStorage,
      'private_message_ban' => $banStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $privateMessageService = $this->createMock(PrivateMessageServiceInterface::class);
    $privateMessageService->method('getThreadFromMessage')->willReturnCallback(
      static fn(PrivateMessageInterface $message): PrivateMessageThreadInterface => $message === $privateMessages[103] ? $hiddenThread : $thread,
    );
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(9, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES)
      ->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn((int) strtotime('2026-10-26 00:00:00 UTC'));
    $provider = new PrivateMessageDigestProvider(
      $entities,
      $privateMessageService,
      $preferences,
      $this->getConfigFactoryStub(['aculta_messenger.settings' => ['private_message_digest_anchor' => '2020-01-01']]),
      $this->createMock(UserDataInterface::class),
      $time,
    );
    $provider->setStringTranslation($this->getStringTranslationStub());

    $rows = $provider->digestRowsFor(9);

    self::assertSame([101, 102, 103], $loadedPrivateMessageIds);
    self::assertSame([[
      'tipo' => AvisoPreferencias::EMAIL_PRIVATE_MESSAGES,
      'titulo' => 'Você tem 2 novas mensagens de Remetente.',
      'quantidade' => 2,
      'delivery_id' => 41,
      'delivery_ids' => [41, 42],
    ],
    ], $rows);
    self::assertSame([
      ['field_aculta_pm_digest_sent', 1],
      ['field_aculta_msg_delivery', 'cancelled'],
    ], $discardedNoticeFields);
    self::assertStringNotContainsString('corpo privado', serialize($rows));
  }

  /**
   * Builds the idempotent pending-message query.
   */
  private function pendingQuery(array $ids): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($ids);
    return $query;
  }

}
