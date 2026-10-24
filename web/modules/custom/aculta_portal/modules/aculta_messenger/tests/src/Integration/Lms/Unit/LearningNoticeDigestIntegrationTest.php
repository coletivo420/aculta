<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Lms\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
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
      ->method('send')
      ->with($this->callback(static fn (MailMessage $message): bool => $message->langcode === 'pt-br'))
      ->willReturn(TRUE);
    $summary = $this->createSummary($provider, $mailer, TRUE);

    $this->assertSame(1, $summary->enviarResumos());
    $this->assertSame([27], $provider->marked);
    $this->assertSame([], $provider->discarded);
  }

  /**
   * A failed email must leave LMS digest rows available for a later run.
   */
  public function testFailedDigestDeliveryKeepsLmsRowsPending(): void {
    $provider = new TestLearningDigestProvider([27]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->once())->method('send')->willReturn(FALSE);
    $summary = $this->createSummary($provider, $mailer, TRUE);

    $this->assertSame(0, $summary->enviarResumos());
    $this->assertSame([], $provider->marked);
    $this->assertSame([], $provider->discarded);
  }

  /**
   * Opt-out discards email rows without attempting to send a digest.
   */
  public function testOptOutDiscardsLmsRowsWithoutSending(): void {
    $provider = new TestLearningDigestProvider([27]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects($this->never())->method('send');
    $summary = $this->createSummary($provider, $mailer, FALSE);

    $this->assertSame(0, $summary->enviarResumos());
    $this->assertSame([], $provider->marked);
    $this->assertSame([27], $provider->discarded);
  }

  /**
   * Builds the real Messenger digest service with an LMS provider.
   */
  private function createSummary(TestLearningDigestProvider $provider, AcultaMailerInterface $mailer, bool $optIn): AvisosResumo {
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

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static function (string $module, int $uid, string $key) use ($optIn): mixed {
      return match ($key) {
        'avisos_resumo_email' => $optIn,
        'email_confirmed' => TRUE,
        'avisos_resumo_ultimo' => 0,
        default => NULL,
      };
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
      $this->createConfiguredMock(UuidInterface::class, ['generate' => '00000000-0000-4000-8000-000000000001']),
      [$provider],
    );
  }

}

/**
 * Minimal digest provider standing in for the LMS Message-backed provider.
 */
final class TestLearningDigestProvider implements AvisoDigestProviderInterface {

  /**
   * Recipients whose entries were acknowledged after successful delivery.
   *
   * @var int[]
   */
  public array $marked = [];

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
   */
  public function __construct(
    private readonly array $recipients,
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
    return [['tipo' => 'aprendizagem', 'titulo' => 'Atividade no curso', 'quantidade' => 1]];
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid): void {
    $this->marked[] = $uid;
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {
    $this->discarded[] = $uid;
  }

}
