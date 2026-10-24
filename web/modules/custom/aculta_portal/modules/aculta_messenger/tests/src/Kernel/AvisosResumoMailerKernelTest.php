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
   * Proves two independent Drupal boots serialize a digest for one recipient.
   */
  public function testIndependentDrupalWorkersDoNotSendConcurrentDigests(): void {
    if (!function_exists('proc_open')) {
      self::markTestSkipped('proc_open is required for the independent Drupal worker test.');
    }

    $account = User::create([
      'name' => 'digest_worker_competition',
      'mail' => 'digest-workers@example.test',
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

    // KernelTestBase overrides the default backend globally; replace that
    // test-only override so this case can hold the real Mailer transport.
    $GLOBALS['config']['system.mail']['interface']['default'] = 'aculta_digest_blocking_test';
    $database = $this->container->get('database');
    $provider = new StableKernelDigestProvider($uid);
    $entityTypeManager = $this->container->get('entity_type.manager');
    $access = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $access->method('supportsTopic')->willReturn(FALSE);
    $avisos = new AvisoService(
      $entityTypeManager,
      $this->createMock(FlagServiceInterface::class),
      $access,
      $database,
      $this->container->get('datetime.time'),
      $this->container->get('lock'),
    );
    $summary = new AvisosResumo(
      $entityTypeManager,
      $preferences,
      $this->container->get('aculta_mailer.mailer'),
      new EmailConfirmationPolicy(
        $userData,
        $entityTypeManager,
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

    $projectRoot = dirname(DRUPAL_ROOT);
    $barrierDirectory = sys_get_temp_dir() . '/aculta-digest-worker-' . bin2hex(random_bytes(8));
    if (!mkdir($barrierDirectory, 0700) && !is_dir($barrierDirectory)) {
      throw new \RuntimeException('Could not create the digest worker barrier directory.');
    }
    $originalBarrier = getenv('ACULTA_DIGEST_BARRIER_DIR');
    putenv('ACULTA_DIGEST_BARRIER_DIR=' . $barrierDirectory);
    putenv('ACULTA_DIGEST_RECIPIENT_UID=' . $uid);
    putenv('ACULTA_DIGEST_SHARED_TEST_PREFIX=' . $database->getPrefix());

    $pipes = [];
    $process = NULL;
    try {
      $workerFixture = 'web/modules/custom/aculta_portal/modules/aculta_messenger/tests/fixtures/DigestCompetingWorkerKernelTest.php';
      $process = proc_open([
        PHP_BINARY,
        $projectRoot . '/vendor/bin/phpunit',
        '-c',
        $projectRoot . '/web/core/phpunit.xml.dist',
        $projectRoot . '/' . $workerFixture,
      ], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
      ], $pipes, $projectRoot);
      self::assertIsResource($process);
      fclose($pipes[0]);
      stream_set_blocking($pipes[1], FALSE);
      stream_set_blocking($pipes[2], FALSE);

      $sentCount = $summary->enviarResumos();
      [$stdout, $stderr, $exitCode] = $this->collectWorkerProcess($process, $pipes);
      $process = NULL;
      self::assertSame(0, $exitCode, 'The competing Drupal worker failed: ' . trim($stderr . "\n" . $stdout));
      self::assertSame(1, $sentCount, 'The first worker did not complete delivery.');
      self::assertSame('locked', trim((string) file_get_contents($barrierDirectory . '/competitor-done')));
      self::assertSame([[502]], $provider->acknowledged);
      self::assertCount(1, $this->collectedMails());
      self::assertCount(1, $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')
        ->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));
    }
    finally {
      if (is_resource($process)) {
        if (!is_file($barrierDirectory . '/competitor-done')) {
          file_put_contents($barrierDirectory . '/competitor-done', 'abort', LOCK_EX);
        }
        proc_terminate($process);
        foreach ($pipes as $pipe) {
          if (is_resource($pipe)) {
            fclose($pipe);
          }
        }
        proc_close($process);
      }
      if ($originalBarrier === FALSE) {
        putenv('ACULTA_DIGEST_BARRIER_DIR');
      }
      else {
        putenv('ACULTA_DIGEST_BARRIER_DIR=' . $originalBarrier);
      }
      putenv('ACULTA_DIGEST_RECIPIENT_UID');
      putenv('ACULTA_DIGEST_SHARED_TEST_PREFIX');
      foreach (['transport-started', 'competitor-done'] as $filename) {
        @unlink($barrierDirectory . '/' . $filename);
      }
      @rmdir($barrierDirectory);
    }
  }

  /**
   * Returns messages captured by Drupal's test mail collector.
   */
  private function collectedMails(): array {
    return $this->container->get('state')->get('system.test_mail_collector', []);
  }

  /**
   * Collects process output until the independent worker exits.
   *
   * @param resource $process
   *   The child process resource.
   * @param array<int, resource> $pipes
   *   Process pipes, closed by this method.
   *
   * @return array{string, string, int}
   *   Standard output, standard error, and exit code.
   */
  private function collectWorkerProcess($process, array &$pipes): array {
    $stdout = '';
    $stderr = '';
    $exitCode = NULL;
    $deadline = microtime(TRUE) + 45;
    do {
      $stdout .= (string) stream_get_contents($pipes[1]);
      $stderr .= (string) stream_get_contents($pipes[2]);
      $status = proc_get_status($process);
      if (!$status['running']) {
        $exitCode = $status['exitcode'];
        break;
      }
      if (microtime(TRUE) >= $deadline) {
        throw new \RuntimeException('Timed out waiting for the competing Drupal worker to exit.');
      }
      usleep(10_000);
    } while (TRUE);

    $stdout .= (string) stream_get_contents($pipes[1]);
    $stderr .= (string) stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) {
      if (is_resource($pipe)) {
        fclose($pipe);
      }
    }
    $pipes = [];
    $closeCode = proc_close($process);
    return [$stdout, $stderr, is_int($exitCode) && $exitCode >= 0 ? $exitCode : $closeCode];
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

/**
 * Stable provider for the concurrent two-worker test.
 */
final class StableKernelDigestProvider implements AvisoDigestProviderInterface {

  /**
   * Acknowledged delivery ID batches.
   *
   * @var array<int, int[]>
   */
  public array $acknowledged = [];

  public function __construct(
    private readonly int $uid,
  ) {}

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
        'titulo' => 'Revisão concorrente',
        'quantidade' => 1,
        'delivery_id' => 502,
      ],
    ] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $this->acknowledged[] = $deliveryIds;
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {}

}
