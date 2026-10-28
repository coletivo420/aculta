<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderIdentityInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Compares enviarResumos() observable outcomes with the pre-refactor fixture.
 *
 * The fixture records the return code and the ordered side effects for fixed
 * provider-driven scenarios. It was generated from the original monolithic
 * enviarParaComLock() and must stay unchanged after the split. Regeneration is
 * explicit: ACULTA_UPDATE_AVISOS_FIXTURE=1.
 */
final class AvisosResumoEquivalenceTest extends UnitTestCase {

  /**
   * Fixed scenario matrix.
   */
  private const SCENARIOS = [
    'provider_sent',
    'provider_cooldown',
    'provider_temporary_failure',
    'provider_cancelled',
    'no_pending',
    'lock_not_acquired',
  ];

  /**
   * Versioned fixture path relative to the module tests directory.
   */
  private const FIXTURE_FILE = 'avisos_resumo_outcomes.json';

  /**
   * Ensures enviarResumos() keeps the pre-refactor outcomes and sequence.
   */
  public function testEnviarResumosMatchesVersionedFixture(): void {
    $actual = [];
    foreach (self::SCENARIOS as $scenario) {
      $actual[$scenario] = $this->runScenario($scenario);
    }

    $fixture_path = dirname(__DIR__, 2) . '/fixtures/' . self::FIXTURE_FILE;
    if (getenv('ACULTA_UPDATE_AVISOS_FIXTURE') === '1') {
      if (!is_dir(dirname($fixture_path))) {
        mkdir(dirname($fixture_path), 0775, TRUE);
      }
      file_put_contents(
        $fixture_path,
        json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
      );
    }

    self::assertFileExists($fixture_path);
    $expected = json_decode((string) file_get_contents($fixture_path), TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertSame($expected, $actual);
  }

  /**
   * Runs one fixed scenario and returns its normalized observable outcome.
   */
  private function runScenario(string $scenario): array {
    $now = 1_792_800_000;
    $last = 0;
    $active = TRUE;
    $lock_available = TRUE;
    $pending = [27];
    $send = SendResult::sent();

    switch ($scenario) {
      case 'provider_sent':
        break;

      case 'provider_cooldown':
        $last = $now - 3600;
        break;

      case 'provider_temporary_failure':
        $send = SendResult::failed(DeliveryError::Temporary, NULL);
        break;

      case 'provider_cancelled':
        $send = SendResult::cancelled();
        break;

      case 'no_pending':
        $pending = [];
        break;

      case 'lock_not_acquired':
        $lock_available = FALSE;
        break;

      default:
        throw new \InvalidArgumentException("Cenário desconhecido: {$scenario}");
    }

    $events = [];
    $snapshot = NULL;

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('27');
    $account->method('isActive')->willReturn($active);
    $account->method('getEmail')->willReturn('member@example.test');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $notice_storage = $this->createMock(EntityStorageInterface::class);
    $notice_storage->method('getQuery')->willReturn($query);
    $notice_storage->method('loadMultiple')->willReturn([]);

    $user_storage = $this->createMock(EntityStorageInterface::class);
    $user_storage->method('loadUnchanged')->with(27)->willReturn($account);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(
      static fn(string $type): EntityStorageInterface => match ($type) {
        'aculta_aviso' => $notice_storage,
        'user' => $user_storage,
        default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
      },
    );

    $user_data = $this->createMock(UserDataInterface::class);
    $user_data->method('get')->willReturnCallback(
      static function (string $module, ?int $uid, ?string $key) use (&$snapshot, $last): mixed {
        if ($key === 'avisos_resumo_em_andamento') {
          return $uid === NULL ? NULL : $snapshot;
        }
        if ($key === 'avisos_resumo_ultimo') {
          return $last;
        }
        if ($key === 'email_confirmed') {
          return TRUE;
        }
        if (is_string($key) && str_starts_with($key, 'avisos_email_mode_')) {
          return AvisoPreferencias::EMAIL_DIGEST;
        }
        return NULL;
      },
    );
    $user_data->method('set')->willReturnCallback(
      static function (string $module, int $uid, string $key, mixed $value) use (&$snapshot, &$events): void {
        if ($key === 'avisos_resumo_em_andamento') {
          $snapshot = $value;
        }
        $events[] = ['event' => 'userData.set', 'key' => $key];
      },
    );
    $user_data->method('delete')->willReturnCallback(
      static function (string $module, int $uid, string $key) use (&$snapshot, &$events): void {
        if ($key === 'avisos_resumo_em_andamento') {
          $snapshot = NULL;
        }
        $events[] = ['event' => 'userData.delete', 'key' => $key];
      },
    );

    $provider = $this->createMock(AvisoDigestProviderIdentityInterface::class);
    $provider->method('digestProviderId')->willReturn('loja');
    $provider->method('pendingRecipientIds')->willReturn($pending);
    $provider->method('digestRowsFor')->with(27)->willReturn([
      [
        'tipo' => 'loja',
        'titulo' => 'Duas mensagens agrupadas',
        'quantidade' => 2,
        'delivery_id' => 501,
        'delivery_ids' => [501, 502],
      ],
    ]);
    $provider->method('markDigestSent')->willReturnCallback(
      static function (int $uid, array $ids) use (&$events): void {
        $events[] = ['event' => 'provider.mark', 'provider' => 'loja', 'ids' => array_values($ids)];
      },
    );
    $provider->method('discardDigest')->willReturnCallback(
      static function (int $uid) use (&$events): void {
        $events[] = ['event' => 'provider.discard', 'provider' => 'loja'];
      },
    );

    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->method('deliver')->willReturnCallback(
      static function (MailMessage $message) use (&$events, $send): SendResult {
        $events[] = [
          'event' => 'deliver',
          'type' => $message->type,
          'to' => $message->to,
          'langcode' => $message->langcode,
          'context' => $message->context,
          'correlation' => $message->correlationId,
        ];
        return $send;
      },
    );
    $mailer->method('wasAcceptedCorrelation')->willReturn(FALSE);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(
      static function (string $name, float $timeout) use (&$events, $lock_available): bool {
        $events[] = ['event' => 'lock.acquire', 'name' => $name];
        return $lock_available;
      },
    );
    $lock->method('release')->willReturnCallback(
      static function (string $name) use (&$events): void {
        $events[] = ['event' => 'lock.release', 'name' => $name];
      },
    );

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($now);

    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new AvisosEquivalenceTransaction());

    $summary = new AvisosResumo(
      $entities,
      new AvisoPreferencias($user_data),
      $mailer,
      new EmailConfirmationPolicy($user_data, $entities, $this->createMock(ModuleHandlerInterface::class)),
      $user_data,
      $time,
      (new \ReflectionClass(AvisoService::class))->newInstanceWithoutConstructor(),
      new Settings(['hash_salt' => 'unit-test-hash-salt']),
      $database,
      $lock,
      [$provider],
    );

    return [
      'result' => $summary->enviarResumos(),
      'events' => $events,
    ];
  }

}

/**
 * Transaction double without database side effects.
 */
final class AvisosEquivalenceTransaction extends Transaction {

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
  public function rollBack(): void {}

  /**
   * No database resources are held by this test double.
   */
  public function __destruct() {}

}
