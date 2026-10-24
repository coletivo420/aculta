<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Lms\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderIdentityInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicy;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies LMS digest providers participate in the shared Messenger delivery.
 */
#[CoversClass(AvisosResumo::class)]
#[Group('aculta_messenger')]
final class LearningNoticeDigestIntegrationTest extends UnitTestCase {

  /**
   * Confirms successful delivery acknowledges LMS digest rows.
   */
  public function testSuccessfulDigestDeliveryAcknowledgesLmsRows(): void {
    $provider = new TestLearningDigestProvider([27]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->once())
      ->method('deliver')
      ->with($this->callback(static fn (MailMessage $message): bool => $message->langcode === 'pt-br'))
      ->willReturn(SendResult::sent());
    $summary = $this->createSummary($provider, $mailer, TRUE);

    $this->assertSame(1, $summary->enviarResumos());
    $this->assertSame([27], $provider->marked);
    $this->assertSame([[501]], $provider->markedDeliveryIds, 'Only entries included in the delivered email are acknowledged.');
    $this->assertSame([], $provider->discarded);
  }

  /**
   * A failed email must leave LMS digest rows available for a later run.
   */
  public function testFailedDigestDeliveryKeepsLmsRowsPending(): void {
    $provider = new TestLearningDigestProvider([27]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->once())
      ->method('deliver')
      ->willReturn(SendResult::failed(DeliveryError::Temporary, 450));
    $summary = $this->createSummary($provider, $mailer, TRUE);

    $this->assertSame(0, $summary->enviarResumos());
    $this->assertSame([], $provider->marked);
    $this->assertSame([], $provider->discarded);
  }

  /**
   * A thrown transport exception keeps digest rows retryable.
   */
  public function testTransportExceptionKeepsLmsRowsPending(): void {
    $provider = new TestLearningDigestProvider([27]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $attempt = 0;
    $mailer->expects($this->exactly(2))
      ->method('deliver')
      ->willReturnCallback(static function () use (&$attempt): SendResult {
        if (++$attempt === 1) {
          throw new \RuntimeException('Synthetic transport failure.');
        }
        return SendResult::sent();
      });
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->exactly(2))->method('acquire')->with('aculta_messenger.avisos_resumo.27', 180.0)->willReturn(TRUE);
    $lock->expects($this->exactly(2))->method('release')->with('aculta_messenger.avisos_resumo.27');
    $summary = $this->createSummary($provider, $mailer, TRUE, $lock);

    $this->assertSame(0, $summary->enviarResumos());
    $this->assertSame(1, $summary->enviarResumos(), 'The same pending rows are retried after the failed transport attempt.');
    $this->assertSame([27], $provider->marked);
    $this->assertSame([[501]], $provider->markedDeliveryIds);
    $this->assertSame([], $provider->discarded);
  }

  /**
   * Combines adapters and retries without early acknowledgements.
   */
  public function testCombinedAdaptersRetryWithoutAcknowledgingAnyRowsEarly(): void {
    $lmsProvider = new TestLearningDigestProvider([27], TRUE, 'aprendizagem', 501, 'Atividade disponível');
    $wikiProvider = new TestLearningDigestProvider([27], TRUE, 'wiki', 802, 'Revisão publicada');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $capturedRows = [];
    $correlations = [];
    $mailer->expects($this->exactly(2))
      ->method('deliver')
      ->willReturnCallback(static function (MailMessage $message) use (&$capturedRows, &$correlations): SendResult {
        $capturedRows[] = $message->context['resumo'];
        $correlations[] = $message->correlationId;
        return count($capturedRows) === 1
          ? SendResult::failed(DeliveryError::Temporary, 450)
          : SendResult::sent();
      });
    $summary = $this->createSummary([$lmsProvider, $wikiProvider], $mailer, TRUE);

    $this->assertSame(0, $summary->enviarResumos());
    $this->assertSame([], $lmsProvider->marked);
    $this->assertSame([], $wikiProvider->marked);
    $this->assertSame(1, $summary->enviarResumos());
    $rows = array_map('trim', explode("\n", $capturedRows[1]));
    $this->assertSame(['Atividade disponível', 'wiki em "Revisão publicada": 1'], $rows);
    $this->assertSame([], $lmsProvider->discarded);
    $this->assertSame([], $wikiProvider->discarded);
    $this->assertSame([[501]], $lmsProvider->markedDeliveryIds);
    $this->assertSame([[802]], $wikiProvider->markedDeliveryIds);
    $this->assertSame($correlations[0], $correlations[1], 'The same pending batch reuses its idempotency correlation.');
  }

  /**
   * Reuses correlation after accepted mail and failed provider acknowledgement.
   */
  public function testAcceptedDigestAcknowledgementRetryKeepsCorrelation(): void {
    $provider = new TestLearningDigestProvider([27]);
    $provider->failAcknowledgeAttempts = 1;
    $correlations = [];
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->once())
      ->method('deliver')
      ->willReturnCallback(static function (MailMessage $message) use (&$correlations): SendResult {
        $correlations[] = $message->correlationId;
        return SendResult::sent();
      });
    $mailer->expects($this->once())
      ->method('wasAcceptedCorrelation')
      ->willReturnCallback(static function (string $correlationId, string $type, ?string $recipient = NULL) use (&$correlations): bool {
        self::assertSame($correlations[0], $correlationId);
        self::assertSame(AvisosResumo::CHAVE_MAIL, $type);
        self::assertSame('student@example.test', $recipient);
        return TRUE;
      });
    $failedTransaction = new TestLearningDigestTransaction();
    $successfulTransaction = new TestLearningDigestTransaction();
    $database = $this->createMock(Connection::class);
    $database->expects($this->exactly(2))->method('startTransaction')->willReturnOnConsecutiveCalls($failedTransaction, $successfulTransaction);
    $summary = $this->createSummary($provider, $mailer, TRUE, NULL, $database);

    $this->assertSame(0, $summary->enviarResumos(), 'The accepted e-mail is not acknowledged when provider persistence fails.');
    $provider->additionalRows[] = [
      'tipo' => 'aprendizagem',
      'titulo' => 'Aviso posterior',
      'quantidade' => 1,
      'delivery_id' => 502,
    ];
    $this->assertSame(1, $summary->enviarResumos());
    $this->assertTrue($failedTransaction->rolledBack);
    $this->assertFalse($failedTransaction->committed);
    $this->assertTrue($successfulTransaction->committed);
    $this->assertFalse($successfulTransaction->rolledBack);
    $this->assertCount(1, $correlations, 'A Mailer-accepted batch is reconciled without a second delivery attempt.');
    $this->assertSame([27], $provider->marked);
    $this->assertSame([[501]], $provider->markedDeliveryIds, 'A later digest row is not acknowledged as part of the already accepted batch.');
  }

  /**
   * A different batch receives a different correlation.
   */
  public function testDistinctDigestBatchGetsDistinctCorrelation(): void {
    $correlations = [];
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->exactly(2))
      ->method('deliver')
      ->willReturnCallback(static function (MailMessage $message) use (&$correlations): SendResult {
        $correlations[] = $message->correlationId;
        return SendResult::sent();
      });
    $first = $this->createSummary(new TestLearningDigestProvider([27], TRUE, 'aprendizagem', 501), $mailer, TRUE);
    $second = $this->createSummary(new TestLearningDigestProvider([27], TRUE, 'aprendizagem', 502), $mailer, TRUE);

    $this->assertSame(1, $first->enviarResumos());
    $this->assertSame(1, $second->enviarResumos());
    $this->assertNotSame($correlations[0], $correlations[1], 'A new delivery ID must not be suppressed as an already accepted digest.');
  }

  /**
   * Provider refactors retain the correlation of an otherwise identical batch.
   */
  public function testDigestCorrelationUsesStableProviderIdInsteadOfPhpClass(): void {
    $correlations = [];
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->exactly(2))
      ->method('deliver')
      ->willReturnCallback(static function (MailMessage $message) use (&$correlations): SendResult {
        $correlations[] = $message->correlationId;
        return SendResult::sent();
      });
    $beforeRefactor = $this->createSummary(new TestLearningDigestProvider([27]), $mailer, TRUE);
    $afterRefactor = $this->createSummary(new RefactoredTestLearningDigestProvider([27]), $mailer, TRUE);

    $this->assertSame(1, $beforeRefactor->enviarResumos());
    $this->assertSame(1, $afterRefactor->enviarResumos());
    $this->assertSame($correlations[0], $correlations[1], 'A PHP class rename does not create a new digest identity.');
  }

  /**
   * Existing providers keep working without the optional identity contract.
   */
  public function testLegacyDigestProviderWithoutIdentityStillDelivers(): void {
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->once())->method('deliver')->willReturn(SendResult::sent());
    $summary = $this->createSummary(new LegacyTestLearningDigestProvider([27]), $mailer, TRUE);

    $this->assertSame(1, $summary->enviarResumos());
  }

  /**
   * A competing full digest run cannot enter while delivery is in progress.
   */
  public function testConcurrentDigestRunDuringTransportDoesNotDuplicateDelivery(): void {
    $provider = new TestLearningDigestProvider([27]);
    $lockHeld = FALSE;
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->exactly(2))
      ->method('acquire')
      ->willReturnCallback(static function () use (&$lockHeld): bool {
        if ($lockHeld) {
          return FALSE;
        }
        $lockHeld = TRUE;
        return TRUE;
      });
    $lock->expects($this->once())
      ->method('release')
      ->willReturnCallback(static function () use (&$lockHeld): void {
        $lockHeld = FALSE;
      });
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $competingSummary = $this->createSummary($provider, $mailer, TRUE, $lock);
    $competingResult = NULL;
    $mailer->expects($this->once())
      ->method('deliver')
      ->willReturnCallback(static function () use ($competingSummary, &$competingResult): SendResult {
        $competingResult = $competingSummary->enviarResumos();
        return SendResult::sent();
      });
    $summary = $this->createSummary($provider, $mailer, TRUE, $lock);

    $this->assertSame(1, $summary->enviarResumos());
    $this->assertSame(0, $competingResult, 'A competing run skips the locked recipient while the first transport is active.');
    $this->assertSame([[501]], $provider->markedDeliveryIds);
  }

  /**
   * A definitive failure discards only the e-mail copy of a digest entry.
   */
  public function testPermanentMailerFailureDiscardsProviderEmailRows(): void {
    $provider = new TestLearningDigestProvider([27]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->once())
      ->method('deliver')
      ->willReturn(SendResult::failed(DeliveryError::Permanent, 550));
    $summary = $this->createSummary($provider, $mailer, TRUE);

    $this->assertSame(0, $summary->enviarResumos());
    $this->assertSame([], $provider->marked);
    $this->assertSame([27], $provider->discarded);
  }

  /**
   * A provider row without a stable delivery ID is not sent repeatedly.
   */
  public function testRowsWithoutDeliveryIdsAreNotSent(): void {
    $provider = new TestLearningDigestProvider([27], FALSE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->never())->method('deliver');
    $summary = $this->createSummary($provider, $mailer, TRUE);

    $this->assertSame(0, $summary->enviarResumos());
    $this->assertSame([], $provider->marked);
    $this->assertSame([27], $provider->discarded, 'An invalid provider row is discarded instead of being sent repeatedly.');
  }

  /**
   * Opt-out discards email rows without attempting to send a digest.
   */
  public function testOptOutDiscardsLmsRowsWithoutSending(): void {
    $provider = new TestLearningDigestProvider([27]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->never())->method('deliver');
    $summary = $this->createSummary($provider, $mailer, FALSE);

    $this->assertSame(0, $summary->enviarResumos());
    $this->assertSame([], $provider->marked);
    $this->assertSame([27], $provider->discarded);
  }

  /**
   * Builds the real Messenger digest service with an LMS provider.
   */
  private function createSummary(AvisoDigestProviderInterface|array $provider, AcultaMailerInterface $mailer, bool $optIn, ?LockBackendInterface $lock = NULL, ?Connection $database = NULL): AvisosResumo {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($query);
    $noticeStorage->method('loadMultiple')->willReturn([]);
    $account = $this->createMock(UserInterface::class);
    $account->method('isActive')->willReturn(TRUE);
    $account->method('id')->willReturn('27');
    $account->method('getEmail')->willReturn('student@example.test');
    $account->method('getPreferredLangcode')->willReturn('pt-br');
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(27)->willReturn($account);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn (string $type): EntityStorageInterface => $type === 'user' ? $userStorage : $noticeStorage);

    $data = [];
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static function (string $module, int $uid, string $key) use ($optIn, &$data): mixed {
      if (array_key_exists($key, $data[$module][$uid] ?? [])) {
        return $data[$module][$uid][$key];
      }
      return match ($key) {
        'avisos_resumo_email' => $optIn,
        'email_confirmed' => TRUE,
        'avisos_resumo_ultimo' => 0,
        default => NULL,
      };
    });
    $userData->method('set')->willReturnCallback(static function (string $module, int $uid, string $key, mixed $value) use (&$data): void {
      $data[$module][$uid][$key] = $value;
    });
    $userData->method('delete')->willReturnCallback(static function (string $module, int $uid, string $key) use (&$data): void {
      unset($data[$module][$uid][$key]);
    });
    $emailPolicy = new EmailConfirmationPolicy(
      $userData,
      $entityTypeManager,
      $this->createMock(ModuleHandlerInterface::class),
    );

    return new AvisosResumo(
      $entityTypeManager,
      new AvisoPreferencias($userData),
      $mailer,
      $emailPolicy,
      $userData,
      $this->createConfiguredMock(TimeInterface::class, ['getRequestTime' => 1_800_000_000]),
      new AvisoService(
        $entityTypeManager,
        $this->createMock(FlagServiceInterface::class),
        new ForumNoticeAccessPolicy(
          $entityTypeManager,
          new DomainPurposeManager(
            $entityTypeManager,
            $this->createMock(DomainNegotiatorInterface::class),
            $this->createMock(ConfigFactoryInterface::class),
            new RequestStack(),
          ),
        ),
      ),
      new Settings(['hash_salt' => 'unit-test-hash-salt']),
      $database ?? $this->createDatabaseConnection(),
      $lock ?? $this->createConfiguredMock(LockBackendInterface::class, ['acquire' => TRUE]),
      is_array($provider) ? $provider : [$provider],
    );
  }

  /**
   * Creates a connection for tests without rollback assertions.
   */
  private function createDatabaseConnection(): Connection {
    $database = $this->createMock(Connection::class);
    $database->method('startTransaction')->willReturn(new TestLearningDigestTransaction());
    return $database;
  }

}

/**
 * Minimal digest provider standing in for the LMS Message-backed provider.
 */
class TestLearningDigestProvider implements AvisoDigestProviderIdentityInterface {

  /**
   * {@inheritdoc}
   */
  public function digestProviderId(): string {
    return 'lms';
  }

  /**
   * Number of acknowledgement attempts to fail before recording state.
   */
  public int $failAcknowledgeAttempts = 0;

  /**
   * Digest rows that arrive after the currently accepted batch was assembled.
   *
   * @var array<array{tipo: string, titulo: string, quantidade: int, delivery_id: int}>
   */
  public array $additionalRows = [];

  /**
   * Recipients whose entries were acknowledged after successful delivery.
   *
   * @var int[]
   */
  public array $marked = [];

  /**
   * Message IDs acknowledged after successful delivery.
   *
   * @var int[][]
   */
  public array $markedDeliveryIds = [];

  /**
   * Recipients whose entries were discarded after opting out.
   *
   * @var int[]
   */
  public array $discarded = [];

  /**
   * Constructs provider with pending recipients.
   *
   * @param int[] $recipients
   *   Accounts with pending digest rows.
   * @param bool $includeDeliveryId
   *   Whether to return the required stable delivery ID.
   * @param string $type
   *   Notice category for this provider.
   * @param int $deliveryId
   *   Stable ID for the returned digest row.
   * @param string $title
   *   Plain text summary for the returned row.
   */
  public function __construct(
    private readonly array $recipients,
    private readonly bool $includeDeliveryId = TRUE,
    private readonly string $type = 'aprendizagem',
    private readonly int $deliveryId = 501,
    private readonly string $title = 'Atividade no curso',
  ) {}

  /**
   * {@inheritdoc}
   */
  public function pendingRecipientIds(): array {
    return $this->recipients;
  }

  /**
   * {@inheritdoc}
   */
  public function digestRowsFor(int $uid): array {
    $row = ['tipo' => $this->type, 'titulo' => $this->title, 'quantidade' => 1];
    if ($this->includeDeliveryId) {
      $row['delivery_id'] = $this->deliveryId;
    }
    return [$row, ...$this->additionalRows];
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    if ($this->failAcknowledgeAttempts > 0) {
      $this->failAcknowledgeAttempts--;
      throw new \RuntimeException('Synthetic provider acknowledgement failure.');
    }
    $this->marked[] = $uid;
    $this->markedDeliveryIds[] = $deliveryIds;
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {
    $this->discarded[] = $uid;
  }

}

/**
 * Simulates a class refactor while preserving the LMS digest provider identity.
 */
final class RefactoredTestLearningDigestProvider extends TestLearningDigestProvider {}

/**
 * Existing third-party style provider that has not adopted stable identities.
 */
final class LegacyTestLearningDigestProvider implements AvisoDigestProviderInterface {

  /**
   * Provider used to delegate the existing digest contract methods.
   */
  private readonly TestLearningDigestProvider $provider;

  public function __construct(array $recipients) {
    $this->provider = new TestLearningDigestProvider($recipients);
  }

  /**
   * {@inheritdoc}
   */
  public function pendingRecipientIds(): array {
    return $this->provider->pendingRecipientIds();
  }

  /**
   * {@inheritdoc}
   */
  public function digestRowsFor(int $uid): array {
    return $this->provider->digestRowsFor($uid);
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $this->provider->markDigestSent($uid, $deliveryIds);
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {
    $this->provider->discardDigest($uid);
  }

}

/**
 * Transaction double that records explicit commit and rollback calls.
 */
final class TestLearningDigestTransaction extends Transaction {

  /**
   * Whether the transaction committed.
   */
  public bool $committed = FALSE;

  /**
   * Whether the transaction rolled back.
   */
  public bool $rolledBack = FALSE;

  /**
   * Creates the transaction double without opening a database transaction.
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public function commitOrRelease(): void {
    $this->committed = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function rollBack(): void {
    $this->rolledBack = TRUE;
  }

  /**
   * No database resources are held by this test double.
   */
  public function __destruct() {}

}
