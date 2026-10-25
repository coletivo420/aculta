<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Email\EmailConfirmationRequestResult;
use Drupal\aculta_portal\Email\EmailConfirmationRequester;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\Component\Uuid\Php as UuidGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/** Tests reliable confirmation request ordering, throttling, and failure handling. */
#[Group('aculta_portal')]
final class EmailConfirmationRequesterTest extends UnitTestCase {

  public function testAcceptedTransportPersistsNewTokenBeforeCancellingOldToken(): void {
    $events = [];
    $old = $this->confirmation(10, 7, $events, 'old');
    $new = $this->confirmation(11, 7, $events, 'new');
    $requester = $this->requester([$old], $new, $events, TRUE, $this->managerFor($new, $events));

    $this->assertSame(EmailConfirmationRequestResult::ACCEPTED, $requester->request($this->account(7)));
    $this->assertSame(['new-save', 'send', 'new-save', 'old-cancel', 'old-save'], $events);
  }

  public function testRejectedTransportPreservesPreviousConfirmation(): void {
    $events = [];
    $old = $this->confirmation(10, 7, $events, 'old');
    $new = $this->confirmation(11, 7, $events, 'new');
    $new->expects($this->once())->method('delete')->willReturnCallback(static function () use (&$events): void {
      $events[] = 'new-delete';
    });
    $manager = $this->managerFor($new, $events);
    $manager->expects($this->never())->method('cancelConfirmation');
    $requester = $this->requester([$old], $new, $events, FALSE, $manager);

    $this->assertSame(EmailConfirmationRequestResult::FAILED, $requester->request($this->account(7)));
    $this->assertSame(['new-save', 'send', 'new-delete'], $events);
  }

  public function testInitialPersistenceFailureDoesNotSendOrCancelOldConfirmation(): void {
    $events = [];
    $old = $this->confirmation(10, 7, $events, 'old');
    $new = $this->confirmation(11, 7, $events, 'new');
    $new->expects($this->once())->method('save')->willThrowException(new \RuntimeException('simulated storage failure'));
    $manager = $this->managerFor($new, $events);
    $manager->expects($this->never())->method('sendRequest');
    $manager->expects($this->never())->method('cancelConfirmation');
    $requester = $this->requester([$old], $new, $events, TRUE, $manager);

    $this->assertSame(EmailConfirmationRequestResult::FAILED, $requester->request($this->account(7)));
    $this->assertSame(['new-save'], $events);
  }

  public function testFloodLimitBlocksRepeatedRequest(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->once())->method('isAllowed')->with('aculta_portal.email_confirmation_request', 1, 900, '7')->willReturn(FALSE);
    $flood->expects($this->never())->method('register');
    $manager = $this->createMock(AcultaMailConfirmerManagerInterface::class);
    $manager->expects($this->never())->method('createConfirmation');
    $lock = $this->lock(TRUE);
    $requester = new EmailConfirmationRequester(
      $manager,
      $this->createMock(EntityTypeManagerInterface::class),
      $flood,
      $lock,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(AcultaMailerInterface::class),
      $this->configFactory(),
      new UuidGenerator(),
      $this->createMock(UrlGeneratorInterface::class),
    );

    $this->assertSame(EmailConfirmationRequestResult::RATE_LIMITED, $requester->request($this->account(7)));
  }

  public function testConcurrentRequestFailsClosedWhenLockIsHeld(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->never())->method('isAllowed');
    $lock = $this->lock(FALSE);
    $manager = $this->createMock(AcultaMailConfirmerManagerInterface::class);
    $manager->expects($this->never())->method('createConfirmation');
    $requester = new EmailConfirmationRequester(
      $manager,
      $this->createMock(EntityTypeManagerInterface::class),
      $flood,
      $lock,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(AcultaMailerInterface::class),
      $this->configFactory(),
      new UuidGenerator(),
      $this->createMock(UrlGeneratorInterface::class),
    );

    $this->assertSame(EmailConfirmationRequestResult::BUSY, $requester->request($this->account(7)));
  }

  public function testInvalidAccountCannotRequestConfirmation(): void {
    $account = $this->account(0);
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->never())->method('isAllowed');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->never())->method('acquire');
    $requester = new EmailConfirmationRequester(
      $this->createMock(AcultaMailConfirmerManagerInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $flood,
      $lock,
      $this->createMock(LoggerChannelInterface::class),
      $this->createMock(AcultaMailerInterface::class),
      $this->configFactory(),
      new UuidGenerator(),
      $this->createMock(UrlGeneratorInterface::class),
    );

    $this->assertSame(EmailConfirmationRequestResult::INVALID_ACCOUNT, $requester->request($account));
  }

  public function testPreviousConfirmationForDifferentAccountIsNotCancelled(): void {
    $events = [];
    $other = $this->confirmation(10, 8, $events, 'other');
    $new = $this->confirmation(11, 7, $events, 'new');
    $manager = $this->managerFor($new, $events);
    $manager->expects($this->never())->method('cancelConfirmation');
    $requester = $this->requester([$other], $new, $events, TRUE, $manager);

    $this->assertSame(EmailConfirmationRequestResult::ACCEPTED, $requester->request($this->account(7)));
    $this->assertSame(['new-save', 'send', 'new-save'], $events);
  }

  private function managerFor(AcultaMailConfirmationInterface $new, array &$events): AcultaMailConfirmerManagerInterface {
    $manager = $this->createMock(AcultaMailConfirmerManagerInterface::class);
    $manager->method('createConfirmation')->with('person@example.invalid')->willReturn($new);
    $manager->method('getHash')->willReturn('hash');
    $manager->method('isPending')->willReturn(TRUE);
    $manager->method('cancelConfirmation')->willReturnCallback(static function (AcultaMailConfirmationInterface $confirmation) use (&$events): void {
      $events[] = ($confirmation->id() === '10' ? 'old' : 'new') . '-cancel';
    });
    return $manager;
  }

  private function requester(array $existing, AcultaMailConfirmationInterface $new, array &$events, bool $accepted, AcultaMailConfirmerManagerInterface $manager): EmailConfirmationRequester {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn(array_keys($existing));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn($existing);

    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->with('aculta_mail_confirmer')->willReturn($storage);

    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->once())->method('isAllowed')->with('aculta_portal.email_confirmation_request', 1, 900, '7')->willReturn(TRUE);
    $flood->expects($this->once())->method('register')->with('aculta_portal.email_confirmation_request', 900, '7');

    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->method('send')->willReturnCallback(static function (MailMessage $message) use (&$events, $accepted): bool {
      $events[] = 'send';
      self::assertSame('email_confirmation_request', $message->type);
      self::assertSame('https://example.invalid/aculta-mail-confirmer/responder/uuid-11/hash', $message->context['confirm_url']);
      return $accepted;
    });
    $urls = $this->createMock(UrlGeneratorInterface::class);
    $urls->method('generateFromRoute')->willReturn('https://example.invalid/aculta-mail-confirmer/responder/uuid-11/hash');

    return new EmailConfirmationRequester($manager, $entity_manager, $flood, $this->lock(TRUE), $this->createMock(LoggerChannelInterface::class), $mailer, $this->configFactory(), new UuidGenerator(), $urls);
  }

  private function configFactory(): ConfigFactoryInterface {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('resendrequest_delay')->willReturn(900);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_mail_confirmer.settings')->willReturn($settings);
    return $factory;
  }

  private function confirmation(int $id, int $uid, array &$events, string $prefix): AcultaMailConfirmationInterface {
    $confirmation = $this->createMock(AcultaMailConfirmationInterface::class);
    $confirmation->method('isRequestSent')->willReturn(FALSE);
    $confirmation->method('getEmail')->willReturn('person@example.invalid');
    $confirmation->method('uuid')->willReturn('uuid-' . $id);
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $confirmation->method('language')->willReturn($language);
    $confirmation->method('id')->willReturn((string) $id);
    $confirmation->method('getProperty')->with('user')->willReturn($uid);
    $confirmation->method('setRealm')->willReturnSelf();
    $confirmation->method('setProperty')->willReturnSelf();
    $confirmation->method('setPrivate')->willReturnSelf();
    $confirmation->method('setResponseUrl')->willReturnCallback(function ($url, $operation) use ($confirmation): AcultaMailConfirmationInterface {
      $this->assertSame('aculta_portal.dashboard', $url->getRouteName());
      $this->assertSame('confirm', $operation);
      return $confirmation;
    });
    $confirmation->method('save')->willReturnCallback(static function () use (&$events, $prefix): int {
      $events[] = $prefix . '-save';
      return 2;
    });
    return $confirmation;
  }

  private function account(int $uid): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn((string) $uid);
    $account->method('getEmail')->willReturn('person@example.invalid');
    return $account;
  }

  private function lock(bool $acquired): LockBackendInterface {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->with('aculta_portal.email_confirmation_requester.7', 60.0)->willReturn($acquired);
    if ($acquired) {
      $lock->expects($this->once())->method('release')->with('aculta_portal.email_confirmation_requester.7');
    }
    return $lock;
  }

}
