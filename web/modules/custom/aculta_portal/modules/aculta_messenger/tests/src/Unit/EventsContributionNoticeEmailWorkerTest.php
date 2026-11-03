<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\message\MessageInterface;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Integration\Events\EventsContributionNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Events\Plugin\QueueWorker\EventsContributionNoticeEmailWorker;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests direct e-mail delivery policy for Events proposal notices. */
#[CoversClass(EventsContributionNoticeEmailWorker::class)]
final class EventsContributionNoticeEmailWorkerTest extends UnitTestCase {

  /**
   * A current opt-out cancels queued delivery before resolving proposal data. */
  public function testCurrentOptOutCancelsQueuedEmail(): void {
    $message = $this->createMock(MessageInterface::class);
    $message->method('bundle')->willReturn('aculta_msg_events_contrib_notice');
    $message->method('getOwnerId')->willReturn(27);
    $message->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => $this->field(match ($field) {
      'field_aculta_msg_delivery' => 'pending',
      'field_aculta_messenger_attempts' => 0,
      default => throw new \InvalidArgumentException('Unexpected field.'),
    }));
    $message->expects(self::once())->method('set')->with('field_aculta_msg_delivery', 'cancelled')->willReturnSelf();
    $message->expects(self::once())->method('save')->willReturn(1);

    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn('27');
    $user->method('isActive')->willReturn(TRUE);
    $user->method('getEmail')->willReturn('member@example.invalid');
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('loadUnchanged')->with(501)->willReturn($message);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->with(27)->willReturn($user);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'message' => $messageStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage.'),
    });

    $notices = $this->createMock(EventsContributionNoticeManagerInterface::class);
    $notices->expects(self::never())->method('resolve');
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->expects(self::once())->method('emailMode')
      ->with(27, AvisoPreferencias::EMAIL_EVENTS_CONTRIBUTIONS)
      ->willReturn(AvisoPreferencias::EMAIL_OFF);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->with('aculta_portal', 27, 'email_confirmed')->willReturn(TRUE);
    $emailPolicy = new EmailConfirmationPolicy($userData, $entities, $this->createMock(ModuleHandlerInterface::class));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);

    $worker = new EventsContributionNoticeEmailWorker([], 'test', [], $entities, $notices, $preferences, $emailPolicy, $mailer, $lock);
    $worker->processItem(['message_id' => 501]);
  }

  /**
   * Creates a scalar field fixture. */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

}
