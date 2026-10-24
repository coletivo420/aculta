<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Commerce\Unit;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Integration\Commerce\CommerceOrderNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Commerce\Plugin\QueueWorker\CommerceOrderNoticeEmailWorker;
use Drupal\aculta_messenger\Integration\Wiki\Plugin\QueueWorker\WikiNoticeEmailWorker;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Container;

/**
 * Covers queue replay and retry bounds for Commerce and Wiki email workers.
 */
#[CoversClass(CommerceOrderNoticeEmailWorker::class)]
#[CoversClass(WikiNoticeEmailWorker::class)]
#[Group('aculta_messenger')]
final class CommerceWikiNoticeEmailWorkerSafetyTest extends UnitTestCase {

  /**
   * A duplicate Commerce item re-reads sent state after it gets the lock.
   */
  public function testCommerceReplayReloadsSentMessageAfterLock(): void {
    $this->assertReplayIsSkippedAfterLock('commerce', 'aculta_msg_commerce_order_notice');
  }

  /**
   * A duplicate Wiki item re-reads sent state after it gets the lock.
   */
  public function testWikiReplayReloadsSentMessageAfterLock(): void {
    $this->assertReplayIsSkippedAfterLock('wiki', 'aculta_msg_wiki_notice');
  }

  /**
   * Mailer exceptions count toward the bounded Commerce retry limit.
   */
  public function testCommerceMailerExceptionConsumesOneAttempt(): void {
    $this->assertMailerExceptionConsumesAttempt('commerce', 'aculta_msg_commerce_order_notice');
  }

  /**
   * Mailer exceptions count toward the bounded Wiki retry limit.
   */
  public function testWikiMailerExceptionConsumesOneAttempt(): void {
    $this->assertMailerExceptionConsumesAttempt('wiki', 'aculta_msg_wiki_notice');
  }

  /**
   * Permanent Commerce failures become terminal without a retry.
   */
  public function testCommercePermanentMailerFailureIsTerminal(): void {
    $this->assertPermanentMailerFailureIsTerminal('commerce', 'aculta_msg_commerce_order_notice');
  }

  /**
   * Permanent Wiki failures become terminal without a retry.
   */
  public function testWikiPermanentMailerFailureIsTerminal(): void {
    $this->assertPermanentMailerFailureIsTerminal('wiki', 'aculta_msg_wiki_notice');
  }

  /**
   * Checks a stale duplicate queue item against the persisted state.
   */
  private function assertReplayIsSkippedAfterLock(string $adapter, string $bundle): void {
    $message = $this->message($bundle, 'sent', 1);
    $locked = FALSE;
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->willReturnCallback(static function () use (&$locked): bool {
      $locked = TRUE;
      return TRUE;
    });
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with(101)->willReturnCallback(static function () use (&$locked, $message): MessageInterface {
      self::assertTrue($locked, 'The current Message state is loaded only after acquiring the lock.');
      return $message;
    });
    $entities = $this->entityManager($storage);
    $manager = $this->createAdapterManager($adapter);
    $manager->expects(self::never())->method('resolve');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');

    $this->worker($adapter, $entities, $manager, $mailer, $lock)->processItem(['message_id' => 101]);
  }

  /**
   * Checks the persisted attempt counter when Mailer throws.
   */
  private function assertMailerExceptionConsumesAttempt(string $adapter, string $bundle): void {
    $message = $this->message($bundle, 'pending', 0);
    $state = ['field_aculta_msg_delivery' => 'pending', 'field_aculta_messenger_attempts' => 0];
    $message->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => $this->field($state[$field]));
    $message->expects(self::exactly(3))->method('set')->willReturnCallback(static function (string $field, mixed $value) use (&$state, $message): MessageInterface {
      $state[$field] = $value;
      return $message;
    });
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('member@example.invalid');
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(101)->willReturn($message);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($recipient);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'message' => $messageStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $manager = $this->createAdapterManager($adapter);
    $manager->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'Aviso disponível.',
      'url' => Url::fromUri('https://aculta.example/aviso'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, $adapter === 'commerce' ? 'loja' : 'wiki')
      ->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')->willThrowException(new \RuntimeException('Transport failure'));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);

    $this->withUrlAssembler(function () use ($adapter, $entities, $manager, $preferences, $mailer, $lock): void {
      try {
        $this->worker($adapter, $entities, $manager, $mailer, $lock, $preferences)->processItem(['message_id' => 101]);
        self::fail('A transient Mailer exception must be rethrown for queue retry.');
      }
      catch (\RuntimeException $exception) {
        $expected = $adapter === 'commerce'
          ? 'Commerce order notice email delivery failed.'
          : 'Wiki notice email delivery failed.';
        self::assertSame($expected, $exception->getMessage());
      }
    });
    self::assertSame(1, $state['field_aculta_messenger_attempts']);
    self::assertSame('failed', $state['field_aculta_msg_delivery']);
  }

  /**
   * Checks a permanent Mailer result is not retried by the queue worker.
   */
  private function assertPermanentMailerFailureIsTerminal(string $adapter, string $bundle): void {
    $message = $this->message($bundle, 'pending', 0);
    $state = ['field_aculta_msg_delivery' => 'pending', 'field_aculta_messenger_attempts' => 0];
    $message->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => $this->field($state[$field]));
    $message->expects(self::exactly(3))->method('set')->willReturnCallback(static function (string $field, mixed $value) use (&$state, $message): MessageInterface {
      $state[$field] = $value;
      return $message;
    });
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('member@example.invalid');
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(101)->willReturn($message);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($recipient);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'message' => $messageStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $manager = $this->createAdapterManager($adapter);
    $manager->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'Aviso disponível.',
      'url' => Url::fromUri('https://aculta.example/aviso'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, $adapter === 'commerce' ? 'loja' : 'wiki')
      ->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')
      ->willReturn(SendResult::failed(DeliveryError::Permanent, 550));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);

    $this->withUrlAssembler(function () use ($adapter, $entities, $manager, $mailer, $lock, $preferences): void {
      $this->worker($adapter, $entities, $manager, $mailer, $lock, $preferences)
        ->processItem(['message_id' => 101]);
    });
    self::assertSame(1, $state['field_aculta_messenger_attempts']);
    self::assertSame('failed', $state['field_aculta_msg_delivery']);
  }

  /**
   * Creates the adapter-specific worker dependencies.
   *
   * @param string $adapter
   *   Either `commerce` or `wiki`.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entities
   *   Mocked entity manager.
   * @param \Drupal\aculta_messenger\Integration\Commerce\CommerceOrderNoticeManagerInterface|\Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface $manager
   *   Mocked adapter manager.
   * @param \Drupal\aculta_mailer\Api\AcultaMailerInterface $mailer
   *   Mocked Mailer service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   Mocked lock backend.
   * @param \Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface|null $preferences
   *   Optional preference mock.
   */
  private function worker(
    string $adapter,
    EntityTypeManagerInterface $entities,
    CommerceOrderNoticeManagerInterface|WikiNoticeManagerInterface $manager,
    AcultaMailerInterface $mailer,
    LockBackendInterface $lock,
    ?AvisoPreferenciasInterface $preferences = NULL,
  ): CommerceOrderNoticeEmailWorker|WikiNoticeEmailWorker {
    $preferences ??= $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    return $adapter === 'commerce'
      ? new CommerceOrderNoticeEmailWorker([], 'test', [], $entities, $manager, $preferences, $mailer, $lock)
      : new WikiNoticeEmailWorker([], 'test', [], $entities, $manager, $preferences, $mailer, $lock);
  }

  /**
   * Creates the matching typed adapter manager mock.
   */
  private function createAdapterManager(string $adapter): CommerceOrderNoticeManagerInterface|WikiNoticeManagerInterface {
    return $adapter === 'commerce'
      ? $this->createMock(CommerceOrderNoticeManagerInterface::class)
      : $this->createMock(WikiNoticeManagerInterface::class);
  }

  /**
   * Creates a Message fixture with delivery state and attempts.
   */
  private function message(string $bundle, string $delivery, int $attempts): MessageInterface {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn($bundle);
    $message->method('id')->willReturn('101');
    $message->method('getOwnerId')->willReturn(27);
    $message->method('uuid')->willReturn('01234567-89ab-4cde-8fab-0123456789ab');
    $message->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => $this->field(match ($field) {
      'field_aculta_msg_delivery' => $delivery,
      'field_aculta_messenger_attempts' => $attempts,
      default => throw new \InvalidArgumentException('Unexpected field: ' . $field),
    }));
    $message->method('set')->willReturnSelf();
    return $message;
  }

  /**
   * Creates an entity manager using the supplied Message storage.
   */
  private function entityManager(EntityStorageInterface $messageStorage): EntityTypeManagerInterface {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($messageStorage);
    return $entities;
  }

  /**
   * Creates a field item list exposing a scalar value.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

  /**
   * Provides the global URL assembler required by Url::toString().
   */
  private function withUrlAssembler(callable $callback): void {
    $assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $assembler->method('assemble')->willReturn('https://aculta.example/aviso');
    $container = new Container();
    $container->set('unrouted_url_assembler', $assembler);
    \Drupal::setContainer($container);
    try {
      $callback();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

}
