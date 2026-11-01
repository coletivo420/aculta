<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestSelectiveDiscardInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderIdentityInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests shared digest category preferences and per-recipient locking.
 */
#[CoversClass(AvisosResumo::class)]
#[Group('aculta_messenger')]
final class AvisosResumoProviderPreferenceTest extends UnitTestCase {

  /**
   * Recipient discovery hydrates Forum notices in cursor-based batches.
   */
  public function testForumRecipientDiscoveryLoadsAtMostFiveHundredNoticesAtOnce(): void {
    $firstIds = range(1, 500);
    $lastIds = [501];
    $notices = [];
    foreach (range(1, 501) as $id) {
      $uidField = $this->createMock(FieldItemListInterface::class);
      $uidField->method('__get')->with('target_id')->willReturn(27);
      $notice = $this->createMock(ContentEntityInterface::class);
      $notice->method('get')->with('uid')->willReturn($uidField);
      $notices[$id] = $notice;
    }

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $conditionCalls = [];
    $query->method('condition')->willReturnCallback(static function (string $field, mixed $value, ?string $operator = NULL) use (&$conditionCalls, $query): QueryInterface {
      $conditionCalls[] = [$field, $value, $operator];
      return $query;
    });
    $query->method('sort')->with('id', 'ASC')->willReturnSelf();
    $query->method('range')->with(0, 500)->willReturnSelf();
    $query->method('execute')->willReturnOnConsecutiveCalls($firstIds, $lastIds, []);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->expects(self::exactly(3))->method('getQuery')->willReturn($query);
    $loadedBatches = [];
    $noticeStorage->expects(self::exactly(3))->method('loadMultiple')->willReturnCallback(
      static function (array $ids) use (&$loadedBatches, $notices): array {
        $loadedBatches[] = $ids;
        self::assertLessThanOrEqual(500, count($ids));
        return array_intersect_key($notices, array_fill_keys($ids, TRUE));
      },
    );

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('getEmail')->willReturn('digest-batch@example.test');
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('loadUnchanged')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(
      static function (string $module, ?int $uid, ?string $key): mixed {
        return match ([$module, $uid, $key]) {
          ['aculta_messenger', NULL, 'avisos_resumo_em_andamento'] => [],
          ['aculta_messenger', 27, 'avisos_resumo_em_andamento'] => NULL,
          ['aculta_messenger', 27, 'avisos_resumo_ultimo'] => 0,
          ['aculta_portal', 27, 'email_confirmed'] => TRUE,
          default => NULL,
        };
      },
    );
    $preferences = new AvisoPreferencias($userData);
    $avisos = (new \ReflectionClass(AvisoService::class))->newInstanceWithoutConstructor();
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)->willReturn(TRUE);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_800_000_000);
    $summary = new AvisosResumo(
      $entities,
      $preferences,
      $mailer,
      new EmailConfirmationPolicy($userData, $entities, $this->createMock(ModuleHandlerInterface::class)),
      $userData,
      $time,
      $avisos,
      new Settings(['hash_salt' => 'unit-test-hash-salt']),
      $this->databaseConnection(),
      $lock,
    );

    self::assertSame(0, $summary->enviarResumos());
    self::assertSame([$firstIds, $lastIds, []], $loadedBatches);
    self::assertSame([
      ['contador_email', 0, '>'],
      ['id', 0, '>'],
      ['contador_email', 0, '>'],
      ['id', 500, '>'],
      ['uid', 27, NULL],
      ['contador_email', 0, '>'],
    ], $conditionCalls);
  }

  /**
   * Malformed provider rows are not broadly discarded with valid digest rows.
   */
  public function testMalformedProviderRowsAreNotBroadlyDiscarded(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('getEmail')->willReturn('member@example.test');
    $noticeQuery = $this->createMock(QueryInterface::class);
    $noticeQuery->method('accessCheck')->willReturnSelf();
    $noticeQuery->method('condition')->willReturnSelf();
    $noticeQuery->method('sort')->willReturnSelf();
    $noticeQuery->method('range')->willReturnSelf();
    $noticeQuery->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($noticeQuery);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, ?int $uid, ?string $key): mixed => match ($key) {
      'avisos_email_mode_wiki' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_resumo_ultimo' => 0,
      'email_confirmed' => TRUE,
      'avisos_resumo_em_andamento' => [],
      default => NULL,
    });
    $provider = $this->createMock(AvisoDigestProviderInterface::class);
    $provider->method('pendingRecipientIds')->willReturn([27]);
    $provider->method('digestRowsFor')->with(27)->willReturn([
      ['tipo' => 'wiki', 'titulo' => 'Revisão publicada', 'quantidade' => 1],
      ['tipo' => 'wiki', 'titulo' => 'Outra revisão', 'quantidade' => 1, 'delivery_id' => 502],
    ]);
    $provider->expects(self::never())->method('discardDigest');
    $provider->expects(self::once())->method('markDigestSent')->with(27, [502]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')->willReturn(SendResult::sent());
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(2))->method('acquire')
      ->with('aculta_messenger.avisos_resumo.27', 180.0)
      ->willReturnOnConsecutiveCalls(TRUE, TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger.avisos_resumo.27');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('warning')
      ->with(self::stringContains('returned malformed delivery rows'), ['@provider' => get_class($provider)]);
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
      [$provider],
      $logger,
    );

    self::assertSame(1, $summary->enviarResumos());
  }

  /**
   * A stale cached active account cannot receive a digest after deactivation.
   */
  public function testDigestSkipsAccountThatIsInactiveInUnchangedStorage(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(FALSE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::never())->method('load');
    $userStorage->expects(self::once())->method('loadUnchanged')->with(27)->willReturn($account);
    $noticeQuery = $this->createMock(QueryInterface::class);
    $noticeQuery->method('accessCheck')->willReturnSelf();
    $noticeQuery->method('condition')->willReturnSelf();
    $noticeQuery->method('sort')->willReturnSelf();
    $noticeQuery->method('range')->willReturnSelf();
    $noticeQuery->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($noticeQuery);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $provider = $this->createMock(AvisoDigestProviderInterface::class);
    $provider->method('pendingRecipientIds')->willReturn([27]);
    $provider->expects(self::never())->method('digestRowsFor');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')
      ->with('aculta_messenger.avisos_resumo.27', 180.0)
      ->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger.avisos_resumo.27');
    $userData = $this->createMock(UserDataInterface::class);
    $summary = new AvisosResumo(
      $entities,
      new AvisoPreferencias($userData),
      $mailer,
      new EmailConfirmationPolicy($userData, $entities, $this->createMock(ModuleHandlerInterface::class)),
      $userData,
      $this->createMock(TimeInterface::class),
      (new \ReflectionClass(AvisoService::class))->newInstanceWithoutConstructor(),
      new Settings(['hash_salt' => 'unit-test-hash-salt']),
      $this->databaseConnection(),
      $lock,
      [$provider],
    );

    self::assertSame(0, $summary->enviarResumos());
  }

  /**
   * Discards only the changed category while delivering eligible categories.
   */
  #[DataProvider('nonDigestEmailModes')]
  public function testDiscardsCategoryAfterSwitchToNonDigestMode(string $mode, bool $supportsSelectiveDiscard): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('getEmail')->willReturn('member@example.test');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static function (string $module, ?int $uid, ?string $key) use ($mode): mixed {
      if ($key === 'avisos_email_mode_loja') {
        return $mode;
      }
      if ($key === 'avisos_email_mode_wiki') {
        return AvisoPreferencias::EMAIL_DIGEST;
      }
      return match ($key) {
        'avisos_resumo_ultimo' => 0,
        'email_confirmed' => TRUE,
        default => NULL,
      };
    });
    $provider = $supportsSelectiveDiscard
      ? $this->createMockForIntersectionOfInterfaces([
        AvisoDigestProviderInterface::class,
        AvisoDigestSelectiveDiscardInterface::class,
      ])
      : $this->createMock(AvisoDigestProviderInterface::class);
    $provider->method('pendingRecipientIds')->willReturn([27]);
    $provider->method('digestRowsFor')->with(27)->willReturn([
      ['tipo' => 'loja', 'titulo' => 'Pedido recebido', 'quantidade' => 1, 'delivery_id' => 501],
      ['tipo' => 'wiki', 'titulo' => 'Revisão publicada', 'quantidade' => 1, 'delivery_id' => 502],
    ]);
    if ($supportsSelectiveDiscard) {
      $provider->expects(self::once())->method('discardDigestItems')->with(27, [501]);
      $provider->expects(self::once())->method('markDigestSent')->with(27, [502]);
    }
    else {
      $provider->expects(self::never())->method('discardDigest');
      $provider->expects(self::never())->method('markDigestSent');
    }
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($supportsSelectiveDiscard ? self::once() : self::never())
      ->method('deliver')->willReturn(SendResult::sent());
    $mailer->expects(self::never())->method('send');

    $time = $this->createMock(TimeInterface::class);
    $time->method("getRequestTime")->willReturn(1_792_800_000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly($supportsSelectiveDiscard ? 3 : 1))
      ->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)
      ->willReturnOnConsecutiveCalls(...($supportsSelectiveDiscard ? [TRUE, TRUE, FALSE] : [TRUE]));
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

    self::assertSame($supportsSelectiveDiscard ? 1 : 0, $summary->enviarResumos());
    if (!$supportsSelectiveDiscard) {
      return;
    }
    self::assertSame(0, $summary->enviarResumos(), 'A concurrent digest run skips a recipient whose lock is held.');
  }

  /**
   * Provides all modes that must suppress pending digest rows. */
  public static function nonDigestEmailModes(): array {
    return [
      'opt-out with selective discard' => [AvisoPreferencias::EMAIL_OFF, TRUE],
      'direct delivery with selective discard' => [AvisoPreferencias::EMAIL_IMMEDIATE, TRUE],
      'opt-out with legacy provider' => [AvisoPreferencias::EMAIL_OFF, FALSE],
      'direct delivery with legacy provider' => [AvisoPreferencias::EMAIL_IMMEDIATE, FALSE],
    ];
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
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->willReturnCallback(static fn(int $uid): ?UserInterface => $accounts[$uid] ?? NULL);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static function (string $module, ?int $uid, ?string $key): mixed {
      if ($uid === NULL) {
        return [];
      }
      if ($module === 'aculta_portal' && $key === 'email_confirmed') {
        return TRUE;
      }
      if ($module !== 'aculta_messenger') {
        return NULL;
      }
      return match ($key) {
          'avisos_email_mode_wiki' => AvisoPreferencias::EMAIL_DIGEST,
        'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
        'avisos_resumo_ultimo' => 0,
        default => NULL,
      };
    });
    $provider = $this->createMock(AvisoDigestProviderIdentityInterface::class);
    $provider->method('digestProviderId')->willReturn('wiki');
    $provider->method('pendingRecipientIds')->willReturn([27, 28]);
    $provider->method('digestRowsFor')->willReturnCallback(static function (int $uid): array {
      if ($uid === 27) {
        throw new \RuntimeException('Provider fixture failure');
      }
      return [['tipo' => 'wiki', 'titulo' => 'Revisão publicada', 'quantidade' => 1, 'delivery_id' => 528]];
    });
    $provider->expects(self::once())->method('markDigestSent')->with(28, [528]);
    $healthyProvider = $this->createMock(AvisoDigestProviderIdentityInterface::class);
    $healthyProvider->method('digestProviderId')->willReturn('commerce');
    $healthyProvider->method('pendingRecipientIds')->willReturn([27]);
    $healthyProvider->method('digestRowsFor')->willReturnCallback(static fn(int $uid): array => $uid === 27 ? [
      ['tipo' => 'loja', 'titulo' => 'Pedido recebido', 'quantidade' => 1, 'delivery_id' => 527],
    ] : []);
    $healthyProvider->expects(self::once())->method('markDigestSent')->with(27, [527]);
    $failedLookupProvider = $this->createMock(AvisoDigestProviderIdentityInterface::class);
    $failedLookupProvider->method('digestProviderId')->willReturn('unavailable');
    $failedLookupProvider->expects(self::once())->method('pendingRecipientIds')->willThrowException(new \RuntimeException('Lookup fixture failure'));
    $failedLookupProvider->method('digestRowsFor')->willReturn([]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::exactly(2))->method('deliver')->with(self::isInstanceOf(MailMessage::class))->willReturn(SendResult::sent());
    $userData->expects(self::exactly(4))
      ->method('set')
      ->willReturnCallback(static function (string $module, int $uid, string $key, mixed $value): void {
        self::assertSame('aculta_messenger', $module);
        self::assertContains($uid, [27, 28]);
        self::assertSame('avisos_resumo_em_andamento', $key);
        self::assertTrue($value['provider_lookup_failed']);
      });

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $logger = $this->createMock(LoggerInterface::class);
    $loggedErrors = [];
    $logger->expects(self::exactly(2))
      ->method('error')
      ->willReturnCallback(static function (string $message, array $context) use (&$loggedErrors): void {
        $loggedErrors[] = [$message, $context];
      });
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
    self::assertSame([
      [
        'Daily notice digest recipient lookup failed for provider @provider with @exception.',
        [
          '@provider' => get_class($failedLookupProvider),
          '@exception' => \RuntimeException::class,
        ],
      ],
      [
        'Daily notice digest row lookup failed for provider @provider with @exception.',
        [
          '@provider' => get_class($provider),
          '@exception' => \RuntimeException::class,
        ],
      ],
    ], $loggedErrors, 'Logs contain only adapter and exception classes, never exception messages or recipient data.');
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
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, ?int $uid, ?string $key): mixed => match ($key) {
      'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_resumo_ultimo' => 0,
      'email_confirmed' => TRUE,
      default => NULL,
    });
    $userData->expects(self::once())
      ->method('set')
      ->with('aculta_messenger', 27, 'avisos_resumo_em_andamento', self::isType('array'));
    $provider = $this->createMockForIntersectionOfInterfaces([
      AvisoDigestProviderInterface::class,
      AvisoDigestSelectiveDiscardInterface::class,
    ]);
    $provider->method('pendingRecipientIds')->willReturn([27]);
    $provider->method('digestRowsFor')->with(27)->willReturn([
      ['tipo' => 'loja', 'titulo' => 'Pedido recebido', 'quantidade' => 1, 'delivery_id' => 501],
    ]);
    $provider->expects(self::once())->method('discardDigestItems')->with(27, [501]);
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
    $lock->expects(self::exactly(2))->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)->willReturn(TRUE);
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
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, ?int $uid, ?string $key): mixed => match ($key) {
      'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_resumo_ultimo' => 0,
      'email_confirmed' => TRUE,
      default => NULL,
    });
    $userData->expects(self::once())
      ->method('set')
      ->with('aculta_messenger', 27, 'avisos_resumo_em_andamento', self::isType('array'));
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
    $lock->expects(self::exactly(2))->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)->willReturn(TRUE);
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
   * A retry after the transport window reuses the same batch correlation.
   */
  public function testTemporaryFailureRetryKeepsBatchCorrelationId(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('getEmail')->willReturn('member@example.test');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->with([])->willReturn([]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->with(27)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'aculta_aviso' => $noticeStorage,
      'user' => $userStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $snapshot = NULL;
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static function (string $module, ?int $uid, ?string $key) use (&$snapshot): mixed {
      return match ($key) {
          'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
        'avisos_resumo_ultimo' => 0,
        'avisos_resumo_em_andamento' => $snapshot,
        'email_confirmed' => TRUE,
        default => NULL,
      };
    });
    $userData->method('set')->willReturnCallback(static function (string $module, int $uid, string $key, mixed $value) use (&$snapshot): void {
      if ($key === 'avisos_resumo_em_andamento') {
        $snapshot = $value;
      }
    });
    $userData->method('delete')->willReturnCallback(static function (string $module, int $uid, string $key) use (&$snapshot): void {
      if ($key === 'avisos_resumo_em_andamento') {
        $snapshot = NULL;
      }
    });
    $provider = $this->createMock(AvisoDigestProviderIdentityInterface::class);
    $provider->method('digestProviderId')->willReturn('loja');
    $provider->method('pendingRecipientIds')->willReturn([27]);
    $provider->method('digestRowsFor')->with(27)->willReturn([
      [
        'tipo' => 'loja',
        'titulo' => 'Duas mensagens agrupadas',
        'quantidade' => 2,
        'delivery_id' => 501,
        'delivery_ids' => [501, 502],
      ],
    ]);
    $provider->expects(self::once())->method('markDigestSent')->with(27, [501, 502]);
    $correlations = [];
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::exactly(2))
      ->method('deliver')
      ->willReturnCallback(static function (MailMessage $message) use (&$correlations): SendResult {
        $correlations[] = $message->correlationId;
        return count($correlations) === 1
          ? SendResult::failed(DeliveryError::Temporary, NULL)
          : SendResult::sent();
      });
    $mailer->method('wasAcceptedCorrelation')->willReturn(FALSE);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnOnConsecutiveCalls(1_792_800_000, 1_792_800_181, 1_792_800_181);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(4))->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)->willReturn(TRUE);
    $lock->expects(self::exactly(2))->method('release')->with('aculta_messenger.avisos_resumo.27');
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

    self::assertSame(0, $summary->enviarResumos(), 'A temporary failure leaves the digest available for retry.');
    self::assertSame(1, $summary->enviarResumos(), 'The unchanged pending batch is delivered once the transport window has elapsed.');
    self::assertCount(2, $correlations);
    self::assertSame($correlations[0], $correlations[1], 'A retry of the same logical batch uses the same Mailer idempotency key.');
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
