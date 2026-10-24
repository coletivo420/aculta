<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Psr\Log\LoggerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests shared digest category preferences and per-recipient locking.
 */
#[CoversClass(AvisosResumo::class)]
#[Group('aculta_messenger')]
final class AvisosResumoProviderPreferenceTest extends UnitTestCase {

  /**
   * Discards a pending category when the current mode is no longer digest.
   */
  public function testDiscardsCategoryAfterSwitchToOff(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('getEmail')->willReturn('member@example.test');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_resumo_email' => TRUE,
      'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_OFF,
      'avisos_resumo_ultimo' => 0,
      'email_confirmed' => TRUE,
      default => NULL,
    });
    $provider = $this->createMock(AvisoDigestProviderInterface::class);
    $provider->method('pendingRecipientIds')->willReturn([27]);
    $provider->method('digestRowsFor')->with(27)->willReturn([
      ['tipo' => 'loja', 'titulo' => 'Pedido recebido', 'quantidade' => 1, 'delivery_id' => 501],
    ]);
    $provider->expects(self::once())->method('discardDigest')->with(27);
    $provider->expects(self::never())->method('markDigestSent');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('send');

    $time = $this->createMock(TimeInterface::class);
    $time->method("getRequestTime")->willReturn(1_792_800_000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(2))->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)->willReturnOnConsecutiveCalls(TRUE, FALSE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger.avisos_resumo.27');
    $summary = new AvisosResumo(
      $entities,
      new AvisoPreferencias($userData),
      $mailer,
      new EmailConfirmationPolicy(
        $userData,
        $entities,
        $this->createMock(ModuleHandlerInterface::class),
      ),
      $userData,
      $time,
      (new \ReflectionClass(AvisoService::class))->newInstanceWithoutConstructor(),
      new Settings(['hash_salt' => 'unit-test-hash-salt']),
      $this->databaseConnection(),
      $lock,
      [$provider],
    );

    self::assertSame(0, $summary->enviarResumos());
    self::assertSame(0, $summary->enviarResumos(), 'A concurrent digest run skips a recipient whose lock is held.');
  }

  /**
   * A provider failure for one recipient does not abort other digest delivery.
   */
  public function testProviderFailureIsIsolatedPerRecipient(): void {
    $accounts = [];
    foreach ([27, 28] as $uid) {
      $account = $this->createMock(UserInterface::class);
      $account->method('id')->willReturn((string) $uid);
      $account->method('isActive')->willReturn(TRUE);
      $account->method('getEmail')->willReturn('member' . $uid . '@example.test');
      $accounts[$uid] = $account;
    }

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->willReturnCallback(static fn(int $uid): ?UserInterface => $accounts[$uid] ?? NULL);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static function (string $module, int $uid, string $key): mixed {
      if ($module === 'aculta_portal' && $key === 'email_confirmed') {
        return TRUE;
      }
      if ($module !== 'aculta_messenger') {
        return NULL;
      }
      return match ($key) {
        'avisos_resumo_email' => TRUE,
        'avisos_email_mode_wiki' => AvisoPreferencias::EMAIL_DIGEST,
        'avisos_resumo_ultimo' => 0,
        default => NULL,
      };
    });
    $provider = $this->createMock(AvisoDigestProviderInterface::class);
    $provider->method('pendingRecipientIds')->willReturn([27, 28]);
    $provider->method('digestRowsFor')->willReturnCallback(static function (int $uid): array {
      if ($uid === 27) {
        throw new \RuntimeException('Provider fixture failure');
      }
      return [['tipo' => 'wiki', 'titulo' => 'Revisão publicada', 'quantidade' => 1, 'delivery_id' => 528]];
    });
    $provider->expects(self::once())->method('markDigestSent')->with(28, [528]);
    $healthyProvider = $this->createMock(AvisoDigestProviderInterface::class);
    $healthyProvider->method('pendingRecipientIds')->willReturn([27]);
    $healthyProvider->method('digestRowsFor')->willReturnCallback(static fn(int $uid): array => $uid === 27 ? [
      ['tipo' => 'loja', 'titulo' => 'Pedido recebido', 'quantidade' => 1, 'delivery_id' => 527],
    ] : []);
    $healthyProvider->expects(self::once())->method('markDigestSent')->with(27, [527]);
    $failedLookupProvider = $this->createMock(AvisoDigestProviderInterface::class);
    $failedLookupProvider->expects(self::once())->method('pendingRecipientIds')->willThrowException(new \RuntimeException('Lookup fixture failure'));
    $failedLookupProvider->method('digestRowsFor')->willReturn([]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::exactly(2))->method('deliver')->with(self::isInstanceOf(MailMessage::class))->willReturn(SendResult::sent());
    $userData->expects(self::once())
      ->method('set')
      ->with('aculta_messenger', 28, 'avisos_resumo_ultimo', 1_792_800_000);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::exactly(2))->method('error');
    $summary = new AvisosResumo(
      $entities,
      new AvisoPreferencias($userData),
      $mailer,
      new EmailConfirmationPolicy($userData, $entities, $this->createMock(ModuleHandlerInterface::class)),
      $userData,
      $time,
      (new \ReflectionClass(AvisoService::class))->newInstanceWithoutConstructor(),
      new Settings(['hash_salt' => 'unit-test-hash-salt']),
      $this->databaseConnection(),
      $lock,
      [$failedLookupProvider, $provider, $healthyProvider],
      $logger,
    );

    self::assertSame(2, $summary->enviarResumos());
  }

  /**
   * Administrative cancellation discards email rows without marking delivery.
   */
  public function testCancelledMailerPolicyDiscardsDigestRows(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('getEmail')->willReturn('member@example.test');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_resumo_email' => TRUE,
      'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_resumo_ultimo' => 0,
      'email_confirmed' => TRUE,
      default => NULL,
    });
    $userData->expects(self::never())->method('set');
    $provider = $this->createMock(AvisoDigestProviderInterface::class);
    $provider->method('pendingRecipientIds')->willReturn([27]);
    $provider->method('digestRowsFor')->with(27)->willReturn([
      ['tipo' => 'loja', 'titulo' => 'Pedido recebido', 'quantidade' => 1, 'delivery_id' => 501],
    ]);
    $provider->expects(self::once())->method('discardDigest')->with(27);
    $provider->expects(self::never())->method('markDigestSent');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())
      ->method('deliver')
      ->with(self::isInstanceOf(MailMessage::class))
      ->willReturn(SendResult::cancelled());
    $mailer->expects(self::never())->method('send');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger.avisos_resumo.27');
    $summary = new AvisosResumo(
      $entities,
      new AvisoPreferencias($userData),
      $mailer,
      new EmailConfirmationPolicy(
        $userData,
        $entities,
        $this->createMock(ModuleHandlerInterface::class),
      ),
      $userData,
      $time,
      (new \ReflectionClass(AvisoService::class))->newInstanceWithoutConstructor(),
      new Settings(['hash_salt' => 'unit-test-hash-salt']),
      $this->databaseConnection(),
      $lock,
      [$provider],
    );

    self::assertSame(0, $summary->enviarResumos());
  }

  /**
   * A temporary Mailer failure keeps provider rows pending for a safe retry.
   */
  public function testTemporaryMailerFailureKeepsDigestRowsPending(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('getEmail')->willReturn('member@example.test');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_resumo_email' => TRUE,
      'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_resumo_ultimo' => 0,
      'email_confirmed' => TRUE,
      default => NULL,
    });
    $userData->expects(self::never())->method('set');
    $provider = $this->createMock(AvisoDigestProviderInterface::class);
    $provider->method('pendingRecipientIds')->willReturn([27]);
    $provider->method('digestRowsFor')->with(27)->willReturn([
      ['tipo' => 'loja', 'titulo' => 'Pedido recebido', 'quantidade' => 1, 'delivery_id' => 501],
    ]);
    $provider->expects(self::never())->method('discardDigest');
    $provider->expects(self::never())->method('markDigestSent');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())
      ->method('deliver')
      ->with(self::isInstanceOf(MailMessage::class))
      ->willReturn(SendResult::failed(DeliveryError::Temporary, NULL));
    $mailer->expects(self::never())->method('send');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger.avisos_resumo.27');
    $summary = new AvisosResumo(
      $entities,
      new AvisoPreferencias($userData),
      $mailer,
      new EmailConfirmationPolicy(
        $userData,
        $entities,
        $this->createMock(ModuleHandlerInterface::class),
      ),
      $userData,
      $time,
      (new \ReflectionClass(AvisoService::class))->newInstanceWithoutConstructor(),
      new Settings(['hash_salt' => 'unit-test-hash-salt']),
      $this->databaseConnection(),
      $lock,
      [$provider],
    );

    self::assertSame(0, $summary->enviarResumos());
  }

  /**
   * Creates a connection with transaction doubles for digest acknowledgement.
   */
  private function databaseConnection(): Connection {
    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new TestAvisosDigestTransaction());
    return $database;
  }

}

/**
 * Transaction double without database side effects.
 */
final class TestAvisosDigestTransaction extends Transaction {

  /**
   * Creates a transaction double without database side effects.
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public function commitOrRelease(): void {}

  /**
   * {@inheritdoc}
   */
  public function rollBack() {}

  /**
   * No database resources are held by this test double.
   */
  public function __destruct() {}

}
