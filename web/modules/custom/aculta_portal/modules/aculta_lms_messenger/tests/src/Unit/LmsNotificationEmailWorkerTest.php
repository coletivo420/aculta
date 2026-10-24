<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\aculta_lms_messenger\LearningNoticeManagerInterface;
use Drupal\aculta_lms_messenger\Plugin\QueueWorker\LmsNotificationEmailWorker;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\Core\Url;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests LMS transactional delivery through the shared ACULTA Mailer API.
 */
#[CoversClass(LmsNotificationEmailWorker::class)]
#[Group('aculta_lms_messenger')]
final class LmsNotificationEmailWorkerTest extends UnitTestCase {

  /**
   * Revalidates LMS access and sends only scalar context through ACULTA Mailer.
   */
  public function testSendsThroughMailerAfterCurrentAccessCheck(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn('aculta_msg_lms_notice');
    $message->method('getOwnerId')->willReturn(27);
    $message->method('uuid')->willReturn('01234567-89ab-4cde-8fab-0123456789ab');
    $message->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => $this->field(match ($field) {
      'field_aculta_msg_delivery' => 'pending',
      'field_aculta_messenger_attempts' => 0,
      default => throw new \InvalidArgumentException('Unexpected field: ' . $field),
    }));
    $message->method('set')->willReturnSelf();
    $message->expects(self::exactly(2))->method('save')->willReturn(1);

    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('student@example.invalid');

    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('load')->with(101)->willReturn($message);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($recipient);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'message' => $messageStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $notices = $this->createMock(LearningNoticeManagerInterface::class);
    $notices->expects(self::once())->method('resolve')->with($message, $recipient)->willReturn([
      'email_summary' => 'Certificado emitido.',
      'url' => Url::fromUri('https://aculta.org/minha-conta/certificados'),
    ]);

    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('send')->with(self::callback(static fn(MailMessage $mail): bool =>
      $mail->type === 'lms_transactional_notice'
      && $mail->to === 'student@example.invalid'
      && $mail->langcode === 'pt-br'
      && $mail->context === [
        'summary' => 'Certificado emitido.',
        'url' => 'https://aculta.org/minha-conta/certificados',
      ]
      && $mail->correlationId === '01234567-89ab-4cde-8fab-0123456789ab'
    ))->willReturn(TRUE);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->with('aculta_lms_messenger:message:101', 60.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_lms_messenger:message:101');
    $worker = new LmsNotificationEmailWorker([], 'test', [], $entityTypeManager, $notices, $mailer, $lock);

    $urlAssembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $urlAssembler->method('assemble')->willReturn('https://aculta.org/minha-conta/certificados');
    $container = new Container();
    $container->set('unrouted_url_assembler', $urlAssembler);
    \Drupal::setContainer($container);
    try {
      $worker->processItem(['message_id' => 101]);
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * Creates a field-list fixture with one value.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

}
