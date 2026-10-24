<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Database\Connection;
use Drupal\flag\FlagServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests digest recovery against the real ACULTA Mailer and persisted Mail Log.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class AvisosResumoMailerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'comment',
    'aculta_forum_post_test',
    'aculta_messenger_aviso_test',
    'aculta_mailer',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_aviso');
    $this->installEntitySchema('aculta_mail_log');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('aculta_messenger_aviso_test', [
      'aculta_messenger_forum_comment_notice',
      'aculta_messenger_forum_notice_event',
    ]);
    $this->installConfig(['system', 'user']);
    $this->config('aculta_mailer.log')->set('enabled', TRUE)->save();
  }

  /**
   * Recovers one Mailer-accepted digest after a transactional ack failure.
   */
  public function testAcceptedDigestIsNotTransportedAgainOnRecovery(): void {
    $account = User::create([
      'name' => 'digest_mailer_recovery',
      'mail' => 'digest-recovery@example.test',
      'status' => 1,
      'langcode' => 'pt-br',
      'preferred_langcode' => 'pt-br',
    ]);
    $account->save();
    $uid = (int) $account->id();

    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    $preferences = new AvisoPreferencias($userData);
    $preferences->salvar($uid, TRUE, TRUE, TRUE);

    $database = $this->container->get('database');
    $provider = new FailOnceMailerDigestProvider($uid, $database);
    $noticeAccess = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $noticeAccess->method('supportsTopic')->willReturn(FALSE);
    $flags = $this->createMock(FlagServiceInterface::class);
    $avisos = new AvisoService(
      $this->container->get('entity_type.manager'),
      $flags,
      $noticeAccess,
      $database,
      $this->container->get('datetime.time'),
      $this->container->get('lock'),
    );
    $mailer = $this->container->get('aculta_mailer.mailer');
    $summary = new AvisosResumo(
      $this->container->get('entity_type.manager'),
      $preferences,
      $mailer,
      new EmailConfirmationPolicy(
        $userData,
        $this->container->get('entity_type.manager'),
        $this->container->get('module_handler'),
      ),
      $userData,
      $this->container->get('datetime.time'),
      $avisos,
      $this->container->get('settings'),
      $database,
      $this->container->get('lock.persistent'),
      [$provider],
    );

    self::assertSame(0, $summary->enviarResumos(), 'The transport accepted the email, but the first acknowledgement failed.');
    $mailLogStorage = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log');
    $logs = $mailLogStorage->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]);
    self::assertCount(1, $logs);
    self::assertSame(MailStatus::Sent->value, reset($logs)->get('status')->value);
    $mails = $this->collectedMails();
    self::assertCount(1, $mails);
    self::assertSame('digest-recovery@example.test', $mails[0]['to']);
    self::assertSame('Resumo diário de avisos', $mails[0]['subject']);
    self::assertStringContainsString('Revisão publicada', (string) $mails[0]['body']);

    self::assertSame(0, $summary->enviarResumos(), 'The retry reconciles the accepted manifest and does not make another transport call.');
    self::assertSame([[501]], $provider->acknowledged);
    self::assertCount(1, $this->collectedMails());
    self::assertCount(1, $mailLogStorage->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));
    self::assertNull($userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento'));
    self::assertSame(1, (int) $database->select('aculta_messenger_mailer_digest_test', 'digest')
      ->fields('digest', ['acknowledged'])
      ->condition('id', 1)
      ->execute()
      ->fetchField());
  }

  /**
   * Returns messages captured by Drupal's test mail collector.
   */
  private function collectedMails(): array {
    return $this->container->get('state')->get('system.test_mail_collector', []);
  }

}

/**
 * Digest provider that fails its first acknowledgement after Mailer acceptance.
 */
final class FailOnceMailerDigestProvider implements AvisoDigestProviderInterface {

  /**
   * Successfully acknowledged batches.
   *
   * @var array<int, int[]>
   */
  public array $acknowledged = [];

  /**
   * Whether the synthetic acknowledgement failure already occurred.
   */
  private bool $failed = FALSE;

  public function __construct(
    private readonly int $uid,
    private readonly Connection $database,
  ) {
    $this->database->schema()->createTable('aculta_messenger_mailer_digest_test', [
      'fields' => [
        'id' => ['type' => 'int', 'not null' => TRUE],
        'acknowledged' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
    ]);
    $this->database->insert('aculta_messenger_mailer_digest_test')
      ->fields(['id' => 1, 'acknowledged' => 0])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function pendingRecipientIds(): array {
    return [$this->uid];
  }

  /**
   * {@inheritdoc}
   */
  public function digestRowsFor(int $uid): array {
    return $uid === $this->uid ? [
      [
        'tipo' => 'wiki',
        'titulo' => 'Revisão publicada',
        'quantidade' => 1,
        'delivery_id' => 501,
      ],
    ] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $this->database->update('aculta_messenger_mailer_digest_test')
      ->fields(['acknowledged' => 1])
      ->condition('id', 1)
      ->execute();
    if (!$this->failed) {
      $this->failed = TRUE;
      throw new \RuntimeException('Synthetic digest acknowledgement failure.');
    }
    $this->acknowledged[] = $deliveryIds;
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {}

}
