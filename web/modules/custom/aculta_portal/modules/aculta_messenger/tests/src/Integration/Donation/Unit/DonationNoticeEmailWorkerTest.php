<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Donation\Unit;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManager;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Donation\Plugin\QueueWorker\DonationNoticeEmailWorker;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Container;

/**
 * Tests Donation direct email through Mailer with current policy checks.
 */
#[CoversClass(DonationNoticeEmailWorker::class)]
#[Group('aculta_messenger')]
final class DonationNoticeEmailWorkerTest extends UnitTestCase {

  /**
   * Immediate preference sends a minimal approved-payment summary. */
  public function testImmediatePreferenceSendsThroughMailer(): void {
    $message = $this->message('pending');
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    [$entities, $recipient] = $this->entities($message);
    $notices = $this->createMock(DonationNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'O pagamento da sua doação foi aprovado.',
      'url' => Url::fromUri('https://aculta.org/meu-apoio/pedidos/100'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'doacao')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')->with(self::callback(static fn (MailMessage $mail): bool =>
      $mail->type === 'doacao_aviso_pessoal'
      && $mail->to === 'donor@example.invalid'
      && $mail->langcode === 'pt-br'
      && $mail->context['summary'] === 'O pagamento da sua doação foi aprovado.'
    ))->willReturn(SendResult::sent());

    $this->withUrlAssembler(function () use ($entities, $notices, $preferences, $mailer): void {
      $this->worker($entities, $notices, $preferences, $mailer)
        ->processItem(['message_id' => 101]);
    });
  }

  /**
   * Opt-out between queue creation and processing suppresses the email. */
  public function testCurrentOptOutCancelsQueuedEmail(): void {
    $message = $this->message('pending');
    $message->expects(self::exactly(2))->method('set')->willReturnCallback(
      static function (string $field, mixed $value) use ($message): MessageInterface {
        self::assertContains($field, ['field_aculta_messenger_attempts', 'field_aculta_msg_delivery']);
        return $message;
      },
    );
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    [$entities] = $this->entities($message);
    $notices = $this->createMock(DonationNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->willReturn([
      'email_summary' => 'Aviso de doação.',
      'url' => Url::fromUri('https://aculta.org/meu-apoio/pedidos/100'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');

    $this->worker($entities, $notices, $preferences, $mailer)
      ->processItem(['message_id' => 101]);
  }

  /**
   * A payment notice is cancelled when the donor loses order access.
   */
  public function testRevokedOrderAccessCancelsQueuedEmail(): void {
    $message = $this->message('pending');
    $state = ['field_aculta_msg_delivery' => 'pending', 'field_aculta_messenger_attempts' => 0];
    $message->expects(self::exactly(2))->method('set')->willReturnCallback(
      static function (string $field, mixed $value) use (&$state, $message): MessageInterface {
        $state[$field] = $value;
        return $message;
      },
    );
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    [$entities, $recipient] = $this->entities($message);
    $notices = $this->createMock(DonationNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn(NULL);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'doacao')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');

    $this->worker($entities, $notices, $preferences, $mailer)->processItem(['message_id' => 101]);

    self::assertSame(1, $state['field_aculta_messenger_attempts']);
    self::assertSame('cancelled', $state['field_aculta_msg_delivery']);
  }

  /**
   * Permanent failure state prevents duplicate queue delivery.
   */
  public function testPermanentFailureReplayIsSkipped(): void {
    $message = $this->message('failed_permanent');
    [$entities] = $this->entities($message);
    $notices = $this->createMock(DonationNoticeManagerInterface::class);
    $notices->expects(self::never())->method('resolve');
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');

    $this->worker($entities, $notices, $preferences, $mailer)
      ->processItem(['message_id' => 101]);
  }

  /**
   * A thrown Mailer error persists an attempt and retries below the limit.
   */
  public function testMailerExceptionConsumesAttemptAndRetries(): void {
    $message = $this->message('pending');
    $setCalls = [];
    $message->expects(self::exactly(2))->method('set')->willReturnCallback(static function (string $field, mixed $value) use (&$setCalls, $message): MessageInterface {
      $setCalls[] = [$field, $value];
      return $message;
    });
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    [$entities, $recipient] = $this->entities($message);
    $notices = $this->createMock(DonationNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'Aviso de doação.',
      'url' => Url::fromUri('https://aculta.org/meu-apoio/pedidos/100'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'doacao')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')->willThrowException(new \RuntimeException('Transport failure'));

    $this->withUrlAssembler(function () use ($entities, $notices, $preferences, $mailer): void {
      try {
        $this->worker($entities, $notices, $preferences, $mailer)->processItem(['message_id' => 101]);
        self::fail('A transient Mailer exception must be rethrown for queue retry.');
      }
      catch (\RuntimeException $exception) {
        self::assertSame('Donation notice e-mail delivery failed.', $exception->getMessage());
      }
    });
    self::assertSame([
      ['field_aculta_messenger_attempts', 1],
      ['field_aculta_msg_delivery', 'pending'],
    ], $setCalls);
  }

  /**
   * A permanent Mailer result is terminal without queue retry.
   */
  public function testPermanentMailerFailureIsTerminal(): void {
    $message = $this->message('pending');
    $setCalls = [];
    $message->expects(self::exactly(2))->method('set')->willReturnCallback(static function (string $field, mixed $value) use (&$setCalls, $message): MessageInterface {
      $setCalls[] = [$field, $value];
      return $message;
    });
    $message->expects(self::exactly(2))->method('save')->willReturn(1);
    [$entities, $recipient] = $this->entities($message);
    $notices = $this->createMock(DonationNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'Aviso de doação.',
      'url' => Url::fromUri('https://aculta.org/meu-apoio/pedidos/100'),
    ]);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(27, 'doacao')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')
      ->willReturn(SendResult::failed(DeliveryError::Permanent, 550));

    $this->withUrlAssembler(function () use ($entities, $notices, $preferences, $mailer): void {
      $this->worker($entities, $notices, $preferences, $mailer)
        ->processItem(['message_id' => 101]);
    });
    self::assertSame([
      ['field_aculta_messenger_attempts', 1],
      ['field_aculta_msg_delivery', 'failed_permanent'],
    ], $setCalls);
  }

  /**
   * Creates Message and active donor fixtures for the queue worker. */
  private function entities(MessageInterface $message): array {
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('id')->willReturn('27');
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('donor@example.invalid');
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(101)->willReturn($message);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($recipient);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn (string $type): EntityStorageInterface => match ($type) {
      'message' => $messageStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    return [$entities, $recipient];
  }

  /**
   * Creates a pending Donation Message fixture. */
  private function message(string $delivery): MessageInterface {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn(DonationNoticeManager::TEMPLATE);
    $message->method('getOwnerId')->willReturn(27);
    $message->method('uuid')->willReturn('01234567-89ab-4cde-8fab-0123456789ab');
    $message->method('get')->willReturnCallback(fn (string $field): FieldItemListInterface => $this->field(match ($field) {
      'field_aculta_msg_delivery' => $delivery,
      'field_aculta_messenger_attempts' => 0,
      default => throw new \InvalidArgumentException('Unexpected field: ' . $field),
    }));
    $message->method('set')->willReturnSelf();
    return $message;
  }

  /**
   * Instantiates the worker with the real email-confirmation policy. */
  private function worker(
    EntityTypeManagerInterface $entities,
    DonationNoticeManagerInterface $notices,
    AvisoPreferenciasInterface $preferences,
    AcultaMailerInterface $mailer,
  ): DonationNoticeEmailWorker {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->with('aculta_portal', 27, 'email_confirmed')->willReturn(TRUE);
    $emailPolicy = new EmailConfirmationPolicy(
      $userData,
      $entities,
      $this->createMock(ModuleHandlerInterface::class),
    );
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return new DonationNoticeEmailWorker(
      [],
      'test',
      [],
      $entities,
      $notices,
      $preferences,
      $emailPolicy,
      $mailer,
      $lock,
    );
  }

  /**
   * Creates a scalar field item list. */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

  /**
   * Runs the worker with a controlled URL assembler. */
  private function withUrlAssembler(callable $callback): void {
    $assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $assembler->method('assemble')->willReturn('https://aculta.org/meu-apoio/pedidos/100');
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
