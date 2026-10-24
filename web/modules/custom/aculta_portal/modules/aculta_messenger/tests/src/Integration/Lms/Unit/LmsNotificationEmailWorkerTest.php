<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Lms\Unit;

use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Lms\Plugin\QueueWorker\LmsNotificationEmailWorker;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
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
 * Tests LMS email delivery through ACULTA Mailer and current preferences.
 */
#[CoversClass(LmsNotificationEmailWorker::class)]
#[Group('aculta_messenger')]
final class LmsNotificationEmailWorkerTest extends UnitTestCase {

  /**
   * Sends through Mailer only when the user currently selects direct delivery.
   */
  public function testImmediatePreferenceSendsAfterCurrentAccessCheck(): void {
    $message = $this->message('pending');
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    [$entities, $recipient] = $this->entities($message);
    $notices = $this->createMock(LearningNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'Certificado emitido.',
      'url' => Url::fromUri('https://aculta.org/minha-conta/certificados'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'aprendizagem')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')->with(self::callback(static fn(MailMessage $mail): bool =>
      $mail->type === 'lms_transactional_notice'
      && $mail->to === 'student@example.invalid'
      && $mail->langcode === 'pt-br'
      && $mail->context['summary'] === 'Certificado emitido.'
    ))->willReturn(SendResult::sent());
    $lock = $this->lock();

    $this->withUrlAssembler(function () use ($entities, $notices, $preferences, $mailer, $lock): void {
      $this->worker($entities, $notices, $preferences, $mailer, $lock)->processItem(['message_id' => 101]);
    });
  }

  /**
   * Cancels a queued direct email if the user changed the mode before delivery.
   */
  public function testEmailOptOutCancelsPendingDirectSend(): void {
    $message = $this->message('pending');
    $message->expects(self::once())->method('set')->with('field_aculta_msg_delivery', 'cancelled')->willReturnSelf();
    $message->expects(self::once())->method('save')->willReturn(1);
    [$entities] = $this->entities($message);
    $notices = $this->createMock(LearningNoticeManagerInterface::class);
    $notices->expects(self::never())->method('resolve');
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');
    $lock = $this->lock();

    $this->worker($entities, $notices, $preferences, $mailer, $lock)->processItem(['message_id' => 101]);
  }

  /**
   * A duplicate queue item re-reads terminal state after acquiring its lock.
   */
  public function testSentMessageIsSkippedAfterLock(): void {
    $message = $this->message('sent', 1);
    $locked = FALSE;
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->willReturnCallback(static function () use (&$locked): bool {
      $locked = TRUE;
      return TRUE;
    });
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->expects(self::once())->method('load')->with(101)->willReturnCallback(static function () use (&$locked, $message): MessageInterface {
      self::assertTrue($locked, 'Message state must be read only after the worker has acquired its lock.');
      return $message;
    });
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('message')->willReturn($messageStorage);
    $notices = $this->createMock(LearningNoticeManagerInterface::class);
    $notices->expects(self::never())->method('resolve');
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');

    $this->worker($entities, $notices, $preferences, $mailer, $lock)->processItem(['message_id' => 101]);
  }

  /**
   * A thrown Mailer error is persisted as a bounded retryable failure.
   */
  public function testMailerExceptionConsumesAttemptAndMarksFailed(): void {
    $message = $this->message('pending');
    $setCalls = [];
    $message->expects(self::exactly(3))->method('set')->willReturnCallback(static function (string $field, mixed $value) use (&$setCalls, $message): MessageInterface {
      $setCalls[] = [$field, $value];
      return $message;
    });
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    [$entities, $recipient] = $this->entities($message);
    $notices = $this->createMock(LearningNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'Certificado emitido.',
      'url' => Url::fromUri('https://aculta.org/minha-conta/certificados'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'aprendizagem')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')->willThrowException(new \RuntimeException('Transport failure'));
    $lock = $this->lock();

    $this->withUrlAssembler(function () use ($entities, $notices, $preferences, $mailer, $lock): void {
      try {
        $this->worker($entities, $notices, $preferences, $mailer, $lock)->processItem(['message_id' => 101]);
        self::fail('A transient Mailer exception must be rethrown for queue retry.');
      }
      catch (\RuntimeException $exception) {
        self::assertSame('LMS transactional notification email delivery failed.', $exception->getMessage());
      }
    });
    self::assertSame([
      ['field_aculta_messenger_attempts', 1],
      ['field_aculta_msg_delivery', 'pending'],
      ['field_aculta_msg_delivery', 'failed'],
    ], $setCalls);
  }

  /**
   * A permanent Mailer failure is terminal after one attempt.
   */
  public function testPermanentMailerFailureDoesNotRequestQueueRetry(): void {
    $message = $this->message('pending');
    $setCalls = [];
    $message->expects(self::exactly(3))->method('set')->willReturnCallback(static function (string $field, mixed $value) use (&$setCalls, $message): MessageInterface {
      $setCalls[] = [$field, $value];
      return $message;
    });
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    [$entities, $recipient] = $this->entities($message);
    $notices = $this->createMock(LearningNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'Certificado emitido.',
      'url' => Url::fromUri('https://aculta.org/minha-conta/certificados'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'aprendizagem')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')
      ->willReturn(SendResult::failed(DeliveryError::Permanent, 550));

    $this->withUrlAssembler(function () use ($entities, $notices, $preferences, $mailer): void {
      $this->worker($entities, $notices, $preferences, $mailer, $this->lock())
        ->processItem(['message_id' => 101]);
    });
    self::assertSame([
      ['field_aculta_messenger_attempts', 1],
      ['field_aculta_msg_delivery', 'pending'],
      ['field_aculta_msg_delivery', 'failed'],
    ], $setCalls);
  }

  /**
   * Creates a queued notice message mock.
   *
   * @param string $delivery
   *   Current delivery state.
   * @param int $attempts
   *   Persisted delivery attempts.
   *
   * @return \Drupal\message\MessageInterface
   *   The configured message mock.
   */
  private function message(string $delivery, int $attempts = 0): MessageInterface {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn('aculta_msg_lms_notice');
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
   * Creates entity storage mocks for the worker.
   *
   * @param \Drupal\message\MessageInterface $message
   *   Notice message to return from storage.
   *
   * @return array{0: \Drupal\Core\Entity\EntityTypeManagerInterface, 1: \Drupal\user\UserInterface}
   *   Entity manager and recipient account.
   */
  private function entities(MessageInterface $message): array {
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('id')->willReturn('27');
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('student@example.invalid');
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
    return [$entities, $recipient];
  }

  /**
   * Instantiates the worker with controlled dependencies.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entities
   *   Entity manager mock.
   * @param \Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface $notices
   *   Notice manager mock.
   * @param \Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface $preferences
   *   Preference service mock.
   * @param \Drupal\aculta_mailer\Api\AcultaMailerInterface $mailer
   *   Mailer mock.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   Lock backend mock.
   *
   * @return \Drupal\aculta_messenger\Integration\Lms\Plugin\QueueWorker\LmsNotificationEmailWorker
   *   Configured worker.
   */
  private function worker(
    EntityTypeManagerInterface $entities,
    LearningNoticeManagerInterface $notices,
    AvisoPreferenciasInterface $preferences,
    AcultaMailerInterface $mailer,
    LockBackendInterface $lock,
  ): LmsNotificationEmailWorker {
    return new LmsNotificationEmailWorker([], 'test', [], $entities, $notices, $preferences, $mailer, $lock);
  }

  /**
   * Creates an available lock backend mock. */
  private function lock(): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return $lock;
  }

  /**
   * Creates a field list mock containing one scalar value.
   *
   * @param mixed $value
   *   Field value.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   Field list mock.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

  /**
   * Runs a callback with a controlled URL assembler in Drupal's container.
   *
   * @param callable $callback
   *   Callback to execute.
   */
  private function withUrlAssembler(callable $callback): void {
    $assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $assembler->method('assemble')->willReturn('https://aculta.org/minha-conta/certificados');
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
