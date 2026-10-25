<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_messenger_aviso_test\Kernel\DigestRecoveryProvider;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\flag\FlagServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Lock\DatabaseLockBackend;
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
  protected function bootEnvironment(): void {
    parent::bootEnvironment();
    new Settings(['hash_salt' => 'aculta-messenger-shared-digest-kernel-test']);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // KernelTestBase replaces Drupal's database lock service with a no-op.
    $this->container->set('lock', new DatabaseLockBackend($this->container->get('database')));
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
    $preferences->saveEmailMode($uid, 'wiki', AvisoPreferencias::EMAIL_DIGEST);

    $database = $this->container->get('database');
    $provider = new DigestRecoveryProvider($uid, $database, failFirstAcknowledgement: TRUE);
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
      $this->container->get('lock'),
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
    self::assertSame('Resumo de avisos', $mails[0]['subject']);
    self::assertStringContainsString('Revisão publicada', (string) $mails[0]['body']);

    self::assertSame(0, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
      ->fields('digest', ['acknowledged'])
      ->condition('id', 1)
      ->execute()
      ->fetchField(), 'The failed acknowledgement transaction rolls back provider state.');

    // Simulate normal Mail Log retention before a disabled adapter returns.
    // The persisted Messenger snapshot must remain enough to recover safely.
    $database->delete('aculta_mail_log')
      ->condition('id', reset($logs)->id())
      ->execute();
    self::assertSame([], $mailLogStorage->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));

    $projectRoot = dirname(DRUPAL_ROOT);
    $originalUid = getenv('ACULTA_DIGEST_RECIPIENT_UID');
    $originalPrefix = getenv('ACULTA_DIGEST_SHARED_TEST_PREFIX');
    putenv('ACULTA_DIGEST_RECIPIENT_UID=' . $uid);
    putenv('ACULTA_DIGEST_SHARED_TEST_PREFIX=' . $database->getPrefix());
    $pipes = [];
    $process = NULL;
    try {
      $workerFixture = 'web/modules/custom/aculta_portal/modules/aculta_messenger/tests/fixtures/DigestRecoveryWorkerKernelTest.php';
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
      [$stdout, $stderr, $exitCode] = $this->collectWorkerProcess($process, $pipes);
      $process = NULL;
      self::assertSame(0, $exitCode, 'The restarted Drupal digest worker failed: ' . trim($stderr . "\n" . $stdout));
    }
    finally {
      if (is_resource($process)) {
        proc_terminate($process);
        foreach ($pipes as $pipe) {
          if (is_resource($pipe)) {
            fclose($pipe);
          }
        }
        proc_close($process);
      }
      foreach ([
        'ACULTA_DIGEST_RECIPIENT_UID' => $originalUid,
        'ACULTA_DIGEST_SHARED_TEST_PREFIX' => $originalPrefix,
      ] as $name => $value) {
        if ($value === FALSE) {
          putenv($name);
        }
        else {
          putenv($name . '=' . $value);
        }
      }
    }

    self::assertSame(1, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
      ->fields('digest', ['acknowledged'])
      ->condition('id', 1)
      ->execute()
      ->fetchField());
    self::assertCount(1, $this->collectedMails());
    self::assertSame([], $mailLogStorage->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));
    self::assertNull($userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento'));
  }

  /**
   * Keeps the manifest pending after SMTP 421 and retries with its correlation.
   */
  public function testTemporaryTransportFailureRetriesSameDigestCorrelation(): void {
    $account = User::create([
      'name' => 'digest_temporary_retry',
      'mail' => 'digest-temporary@example.test',
      'status' => 1,
      'langcode' => 'pt-br',
      'preferred_langcode' => 'pt-br',
    ]);
    $account->save();
    $uid = (int) $account->id();

    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    $preferences = new AvisoPreferencias($userData);
    $preferences->saveEmailMode($uid, 'wiki', AvisoPreferencias::EMAIL_DIGEST);

    $database = $this->container->get('database');
    $provider = new DigestRecoveryProvider($uid, $database);
    $database->update(DigestRecoveryProvider::TABLE)
      ->fields(['acknowledged' => 0])
      ->condition('id', 1)
      ->execute();
    $noticeAccess = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $noticeAccess->method('supportsTopic')->willReturn(FALSE);
    $flags = $this->createMock(FlagServiceInterface::class);
    $now = 1_800_000_000;
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(static function () use (&$now): int {
      return $now;
    });
    $avisos = new AvisoService(
      $this->container->get('entity_type.manager'),
      $flags,
      $noticeAccess,
      $database,
      $time,
      $this->container->get('lock'),
    );
    $summary = new AvisosResumo(
      $this->container->get('entity_type.manager'),
      $preferences,
      $this->container->get('aculta_mailer.mailer'),
      new EmailConfirmationPolicy(
        $userData,
        $this->container->get('entity_type.manager'),
        $this->container->get('module_handler'),
      ),
      $userData,
      $time,
      $avisos,
      $this->container->get('settings'),
      $database,
      $this->container->get('lock'),
      [$provider],
    );

    $GLOBALS['config']['system.mail']['interface']['default'] = 'aculta_digest_retry_test';
    $this->container->get('config.factory')->reset('system.mail');
    self::assertSame(0, $summary->enviarResumos());
    self::assertSame(0, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
      ->fields('digest', ['acknowledged'])
      ->condition('id', 1)
      ->execute()
      ->fetchField(), 'A temporary SMTP failure must leave the site notice pending.');
    self::assertNull($userData->get('aculta_messenger', $uid, 'avisos_resumo_ultimo'));

    $userState = $userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
    self::assertIsArray($userState);
    self::assertArrayNotHasKey('accepted_at', $userState);
    $logs = $this->container->get('entity_type.manager')
      ->getStorage('aculta_mail_log')
      ->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]);
    self::assertCount(1, $logs);
    self::assertSame('failed', reset($logs)->get('status')->value);
    self::assertSame('smtp_421', reset($logs)->get('error_code')->value);

    // Let the in-flight safety window expire before the next cron retry.
    $now += 181;
    self::assertSame(1, $summary->enviarResumos());

    $state = $this->container->get('state');
    $correlations = $state->get('aculta_messenger_aviso_test.digest_retry_correlations');
    self::assertCount(2, $correlations);
    self::assertNotSame('', $correlations[0]);
    self::assertSame($correlations[0], $correlations[1], 'An unchanged pending manifest must reuse its correlation.');
    self::assertSame(1, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
      ->fields('digest', ['acknowledged'])
      ->condition('id', 1)
      ->execute()
      ->fetchField());
    self::assertSame(2, (int) $state->get('aculta_messenger_aviso_test.digest_retry_attempts'));
    $updatedLogs = $this->container->get('entity_type.manager')
      ->getStorage('aculta_mail_log')
      ->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]);
    self::assertCount(1, $updatedLogs, 'The Mailer updates the failed correlation instead of creating a duplicate log row.');
    self::assertSame('sent', reset($updatedLogs)->get('status')->value);
    self::assertSame($now, (int) $userData->get('aculta_messenger', $uid, 'avisos_resumo_ultimo'));
    self::assertNull($userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento'));
  }

  /**
   * Uses the real Mailer to test interruption before the accepted marker write.
   */
  public function testAcceptedDigestRecoversWhenMarkerWriteIsInterrupted(): void {
    $account = User::create([
      'name' => 'digest_marker_interruption',
      'mail' => 'digest-marker@example.test',
      'status' => 1,
      'langcode' => 'pt-br',
      'preferred_langcode' => 'pt-br',
    ]);
    $account->save();
    $uid = (int) $account->id();
    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    $preferences = new AvisoPreferencias($userData);
    $preferences->saveEmailMode($uid, 'wiki', AvisoPreferencias::EMAIL_DIGEST);

    $database = $this->container->get('database');
    $provider = new DigestRecoveryProvider($uid, $database);
    $entityTypeManager = $this->container->get('entity_type.manager');
    $noticeAccess = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $noticeAccess->method('supportsTopic')->willReturn(FALSE);
    $avisos = new AvisoService(
      $entityTypeManager,
      $this->createMock(FlagServiceInterface::class),
      $noticeAccess,
      $database,
      $this->container->get('datetime.time'),
      $this->container->get('lock'),
    );
    $realMailer = $this->container->get('aculta_mailer.mailer');
    $mailer = new class($realMailer) implements AcultaMailerInterface {

      /**
       * Whether the first accepted transport should simulate a process stop.
       */
      private bool $interruptAfterAcceptance = TRUE;

      /**
       * Wraps the real ACULTA Mailer.
       */
      public function __construct(private readonly AcultaMailerInterface $inner) {}

      /**
       * {@inheritdoc}
       */
      public function send(MailMessage $message): bool {
        return $this->inner->send($message);
      }

      /**
       * {@inheritdoc}
       */
      public function deliver(MailMessage $message, ?string $transport = NULL): SendResult {
        $result = $this->inner->deliver($message, $transport);
        if ($result->sent && $this->interruptAfterAcceptance) {
          $this->interruptAfterAcceptance = FALSE;
          throw new \RuntimeException('Simulated process interruption after Mailer acceptance.');
        }
        return $result;
      }

      /**
       * {@inheritdoc}
       */
      public function wasAcceptedCorrelation(string $correlationId, string $type, ?string $recipient = NULL): bool {
        return $this->inner->wasAcceptedCorrelation($correlationId, $type, $recipient);
      }

    };
    $summary = new AvisosResumo(
      $entityTypeManager,
      $preferences,
      $mailer,
      new EmailConfirmationPolicy($userData, $entityTypeManager, $this->container->get('module_handler')),
      $userData,
      $this->container->get('datetime.time'),
      $avisos,
      $this->container->get('settings'),
      $database,
      $this->container->get('lock'),
      [$provider],
    );

    self::assertSame(0, $summary->enviarResumos());
    $mailLogStorage = $entityTypeManager->getStorage('aculta_mail_log');
    self::assertCount(1, $mailLogStorage->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));
    self::assertCount(1, $this->collectedMails(), 'The real Mailer accepted and transported one email.');
    $snapshot = $userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
    self::assertIsArray($snapshot);
    self::assertArrayNotHasKey('accepted_at', $snapshot, 'The interruption left only the pre-transport snapshot.');

    self::assertSame(0, $summary->enviarResumos(), 'Retry reconciles the Mailer log instead of transporting the digest again.');
    self::assertCount(1, $this->collectedMails());
    self::assertSame(1, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
      ->fields('digest', ['acknowledged'])
      ->condition('id', 1)
      ->execute()
      ->fetchField());
    self::assertNull($userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento'));
    self::assertCount(1, $mailLogStorage->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));
  }

  /**
   * Defers the stale worker after resuming with its recipient lease replaced.
   */
  public function testSuspendedWorkerCannotAcknowledgeAfterRecipientLeaseExpires(): void {
    if (!function_exists('posix_kill') || !function_exists('proc_open')) {
      self::markTestSkipped('POSIX process control and proc_open are required for the suspended-worker test.');
    }

    $account = User::create([
      'name' => 'digest_worker_suspend',
      'mail' => 'digest-suspend@example.test',
      'status' => 1,
      'langcode' => 'pt-br',
      'preferred_langcode' => 'pt-br',
    ]);
    $account->save();
    $uid = (int) $account->id();
    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    (new AvisoPreferencias($userData))->saveEmailMode($uid, 'wiki', AvisoPreferencias::EMAIL_DIGEST);

    $database = $this->container->get('database');
    $provider = new DigestRecoveryProvider($uid, $database);
    $barrierDirectory = sys_get_temp_dir() . '/aculta-digest-suspend-' . bin2hex(random_bytes(8));
    if (!mkdir($barrierDirectory, 0700) && !is_dir($barrierDirectory)) {
      throw new \RuntimeException('Could not create the suspended digest marker directory.');
    }
    $acceptedMarker = $barrierDirectory . '/accepted-worker-pid';
    $resumeMarker = $barrierDirectory . '/resume';
    $transportCountMarker = $barrierDirectory . '/transport-count';
    $environmentNames = [
      'ACULTA_DIGEST_RECIPIENT_UID',
      'ACULTA_DIGEST_SHARED_TEST_PREFIX',
      'ACULTA_DIGEST_CRASH_MARKER',
      'ACULTA_DIGEST_CRASH_COUNT_MARKER',
      'ACULTA_DIGEST_SUSPEND_AFTER_ACCEPTANCE',
      'ACULTA_DIGEST_RESUME_MARKER',
    ];
    $originalEnvironment = [];
    foreach ($environmentNames as $name) {
      $originalEnvironment[$name] = getenv($name);
    }
    putenv('ACULTA_DIGEST_RECIPIENT_UID=' . $uid);
    putenv('ACULTA_DIGEST_SHARED_TEST_PREFIX=' . $database->getPrefix());
    putenv('ACULTA_DIGEST_CRASH_MARKER=' . $acceptedMarker);
    putenv('ACULTA_DIGEST_CRASH_COUNT_MARKER=' . $transportCountMarker);
    putenv('ACULTA_DIGEST_SUSPEND_AFTER_ACCEPTANCE=1');
    putenv('ACULTA_DIGEST_RESUME_MARKER=' . $resumeMarker);

    $process = NULL;
    $pipes = [];
    $workerPid = NULL;
    $leaseLock = NULL;
    $leaseLockHeld = FALSE;
    try {
      $projectRoot = dirname(DRUPAL_ROOT);
      $workerFixture = 'web/modules/custom/aculta_portal/modules/aculta_messenger/tests/fixtures/DigestCrashWorkerKernelTest.php';
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

      $deadline = microtime(TRUE) + 45;
      while (!is_file($acceptedMarker)) {
        $status = proc_get_status($process);
        if (!$status['running']) {
          [$stdout, $stderr, $exitCode] = $this->collectWorkerProcess($process, $pipes);
          $process = NULL;
          throw new \RuntimeException('Digest worker exited before transport acceptance: ' . trim($stderr . "\n" . $stdout));
        }
        if (microtime(TRUE) >= $deadline) {
          throw new \RuntimeException('Timed out waiting for the suspended digest worker to accept transport.');
        }
        usleep(10_000);
      }

      $workerPid = (int) file_get_contents($acceptedMarker);
      self::assertGreaterThan(0, $workerPid, 'The accepted-delivery marker must identify the isolated Drupal worker.');
      self::assertTrue(posix_kill($workerPid, SIGSTOP), 'The worker must be suspended after the Mailer accepts the digest.');

      // Expire only this test recipient's lease and claim it from the parent
      // process before resuming the suspended worker.
      self::assertSame(1, $database->update('semaphore')
        ->fields(['expire' => microtime(TRUE) - 1])
        ->condition('name', 'aculta_messenger.avisos_resumo.' . $uid)
        ->execute());
      $leaseLock = $this->container->get('lock');
      $leaseLockHeld = $leaseLock->acquire('aculta_messenger.avisos_resumo.' . $uid, 180.0);
      self::assertTrue($leaseLockHeld, 'A fresh worker must be able to claim the expired recipient lease.');

      self::assertTrue(file_put_contents($resumeMarker, 'resume', LOCK_EX) !== FALSE);
      self::assertTrue(posix_kill($workerPid, SIGCONT), 'The suspended digest worker must resume.');
      $workerPid = NULL;
      [$stdout, $stderr, $exitCode] = $this->collectWorkerProcess($process, $pipes);
      $process = NULL;
      self::assertSame(0, $exitCode, 'The resumed worker must defer acknowledgement after losing its lease: ' . trim($stderr . "\n" . $stdout));
      self::assertSame(0, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
        ->fields('digest', ['acknowledged'])
        ->condition('id', 1)
        ->execute()
        ->fetchField(), 'The stale worker must not acknowledge while another worker owns the recipient lease.');
      $snapshot = $userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      self::assertIsArray($snapshot);
      self::assertArrayNotHasKey('accepted_at', $snapshot);
      self::assertSame("sent\n", file_get_contents($transportCountMarker));
      self::assertCount(1, $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')
        ->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));

      $leaseLock->release('aculta_messenger.avisos_resumo.' . $uid);
      $leaseLockHeld = FALSE;
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
        new AvisoPreferencias($userData),
        $this->container->get('aculta_mailer.mailer'),
        new EmailConfirmationPolicy($userData, $entityTypeManager, $this->container->get('module_handler')),
        $userData,
        $this->container->get('datetime.time'),
        $avisos,
        $this->container->get('settings'),
        $database,
        $leaseLock,
        [$provider],
      );

      self::assertSame(0, $summary->enviarResumos(), 'The accepted manifest is reconciled without a second transport.');
      self::assertSame(1, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
        ->fields('digest', ['acknowledged'])
        ->condition('id', 1)
        ->execute()
        ->fetchField());
      self::assertNull($userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento'));
      self::assertSame("sent\n", file_get_contents($transportCountMarker));
      self::assertCount(1, $entityTypeManager->getStorage('aculta_mail_log')
        ->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]), 'The suspended worker transported the digest only once.');
    }
    finally {
      file_put_contents($resumeMarker, 'resume', LOCK_EX);
      if ($workerPid !== NULL) {
        posix_kill($workerPid, SIGCONT);
        posix_kill($workerPid, SIGTERM);
      }
      if (is_resource($process)) {
        proc_terminate($process);
        foreach ($pipes as $pipe) {
          if (is_resource($pipe)) {
            fclose($pipe);
          }
        }
        proc_close($process);
      }
      if ($leaseLockHeld && $leaseLock !== NULL) {
        $leaseLock->release('aculta_messenger.avisos_resumo.' . $uid);
      }
      foreach ($originalEnvironment as $name => $value) {
        if ($value === FALSE) {
          putenv($name);
        }
        else {
          putenv($name . '=' . $value);
        }
      }
      foreach ([$acceptedMarker, $resumeMarker, $transportCountMarker] as $marker) {
        @unlink($marker);
      }
      @rmdir($barrierDirectory);
    }
  }

  /**
   * Recovers an accepted digest after the sending PHP process is killed.
   */
  public function testAcceptedDigestRecoversAfterAbruptWorkerTermination(): void {
    if (!function_exists('posix_kill') || !function_exists('proc_open')) {
      self::markTestSkipped('POSIX process control and proc_open are required for the crash-recovery test.');
    }

    $account = User::create([
      'name' => 'digest_worker_crash',
      'mail' => 'digest-crash@example.test',
      'status' => 1,
      'langcode' => 'pt-br',
      'preferred_langcode' => 'pt-br',
    ]);
    $account->save();
    $uid = (int) $account->id();
    $userData = $this->container->get('user.data');
    $userData->set('aculta_portal', $uid, 'email_confirmed', TRUE);
    (new AvisoPreferencias($userData))->saveEmailMode($uid, 'wiki', AvisoPreferencias::EMAIL_DIGEST);

    $database = $this->container->get('database');
    $provider = new DigestRecoveryProvider($uid, $database);
    $barrierDirectory = sys_get_temp_dir() . '/aculta-digest-crash-' . bin2hex(random_bytes(8));
    if (!mkdir($barrierDirectory, 0700) && !is_dir($barrierDirectory)) {
      throw new \RuntimeException('Could not create the digest crash marker directory.');
    }

    $originalEnvironment = [];
    $transportCountMarker = $barrierDirectory . '/transport-count';
    foreach ([
      'ACULTA_DIGEST_RECIPIENT_UID',
      'ACULTA_DIGEST_SHARED_TEST_PREFIX',
      'ACULTA_DIGEST_CRASH_MARKER',
      'ACULTA_DIGEST_CRASH_COUNT_MARKER',
    ] as $name) {
      $originalEnvironment[$name] = getenv($name);
    }
    putenv('ACULTA_DIGEST_RECIPIENT_UID=' . $uid);
    putenv('ACULTA_DIGEST_SHARED_TEST_PREFIX=' . $database->getPrefix());
    putenv('ACULTA_DIGEST_CRASH_MARKER=' . $barrierDirectory . '/accepted');
    putenv('ACULTA_DIGEST_CRASH_COUNT_MARKER=' . $transportCountMarker);

    $pipes = [];
    $process = NULL;
    try {
      $projectRoot = dirname(DRUPAL_ROOT);
      $workerFixture = 'web/modules/custom/aculta_portal/modules/aculta_messenger/tests/fixtures/DigestCrashWorkerKernelTest.php';
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
      [$stdout, $stderr, $exitCode] = $this->collectWorkerProcess($process, $pipes);
      $process = NULL;

      self::assertFileExists($barrierDirectory . '/accepted', 'The independent worker must die after Mailer acceptance.');
      self::assertNotSame(0, $exitCode, 'The worker process is expected to terminate before digest acknowledgement.');

      $mailLogStorage = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log');
      $acceptedLogs = $mailLogStorage->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]);
      self::assertCount(1, $acceptedLogs, 'The real Mailer acceptance must be durable before the worker is killed. ' . trim($stderr . "\n" . $stdout));
      self::assertSame('sent', reset($acceptedLogs)->get('status')->value);
      $snapshot = $userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      self::assertIsArray($snapshot);
      self::assertArrayNotHasKey('accepted_at', $snapshot, 'The worker died before writing its acceptance marker.');
      self::assertSame(0, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
        ->fields('digest', ['acknowledged'])
        ->condition('id', 1)
        ->execute()
        ->fetchField());
      self::assertSame("sent\n", file_get_contents($transportCountMarker));

      $GLOBALS['config']['system.mail']['interface']['default'] = 'aculta_digest_crash_test';
      $this->container->get('config.factory')->reset('system.mail');

      // SIGKILL leaves the persistent recipient lease behind. Expire only this
      // isolated test lock so a fresh worker can exercise Mail Log recovery.
      $database->update('semaphore')
        ->fields(['expire' => microtime(TRUE) - 1])
        ->condition('name', 'aculta_messenger.avisos_resumo.' . $uid)
        ->execute();

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
        new AvisoPreferencias($userData),
        $this->container->get('aculta_mailer.mailer'),
        new EmailConfirmationPolicy($userData, $entityTypeManager, $this->container->get('module_handler')),
        $userData,
        $this->container->get('datetime.time'),
        $avisos,
        $this->container->get('settings'),
        $database,
        $this->container->get('lock'),
        [$provider],
      );

      self::assertSame(0, $summary->enviarResumos(), 'Recovery acknowledges the accepted manifest without sending a new email.');
      self::assertSame(1, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
        ->fields('digest', ['acknowledged'])
        ->condition('id', 1)
        ->execute()
        ->fetchField());
      self::assertNull($userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento'));
      self::assertSame("sent\n", file_get_contents($transportCountMarker), 'Recovery must not call the transport twice.');
      self::assertCount(1, $mailLogStorage->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));
    }
    finally {
      if (is_resource($process)) {
        proc_terminate($process);
        foreach ($pipes as $pipe) {
          if (is_resource($pipe)) {
            fclose($pipe);
          }
        }
        proc_close($process);
      }
      foreach ($originalEnvironment as $name => $value) {
        if ($value === FALSE) {
          putenv($name);
        }
        else {
          putenv($name . '=' . $value);
        }
      }
      @unlink($barrierDirectory . '/accepted');
      @unlink($transportCountMarker);
      @rmdir($barrierDirectory);
    }
  }

  /**
   * Proves independent Drupal boots serialize one recipient's digest.
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
    $preferences->saveEmailMode($uid, 'wiki', AvisoPreferencias::EMAIL_DIGEST);

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
      $this->container->get('lock'),
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

    $workerCount = 8;
    $workers = [];
    try {
      $workerFixture = 'web/modules/custom/aculta_portal/modules/aculta_messenger/tests/fixtures/DigestCompetingWorkerKernelTest.php';
      $workerCommand = [
        PHP_BINARY,
        $projectRoot . '/vendor/bin/phpunit',
        '-c',
        $projectRoot . '/web/core/phpunit.xml.dist',
        $projectRoot . '/' . $workerFixture,
      ];
      putenv('ACULTA_DIGEST_COMPETITOR_COUNT=' . $workerCount);
      for ($workerId = 1; $workerId <= $workerCount; $workerId++) {
        $pipes = [];
        $environment = getenv();
        $environment['ACULTA_DIGEST_COMPETITOR_ID'] = (string) $workerId;
        $process = proc_open($workerCommand, [
          0 => ['pipe', 'r'],
          1 => ['pipe', 'w'],
          2 => ['pipe', 'w'],
        ], $pipes, $projectRoot, $environment);
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], FALSE);
        stream_set_blocking($pipes[2], FALSE);
        $workers[$workerId] = ['process' => $process, 'pipes' => $pipes];

        // KernelTestBase runs an installer probe with a fixed table name. All
        // workers share the parent fixture prefix for lock contention, so
        // serialize only their bootstrap and let each worker reach the barrier
        // before starting the next one. Delivery attempts remain concurrent.
        $readyPath = $barrierDirectory . '/competitor-ready-' . $workerId;
        $readyDeadline = microtime(TRUE) + 30;
        while (!is_file($readyPath)) {
          if (microtime(TRUE) >= $readyDeadline) {
            throw new \RuntimeException(sprintf(
              'Digest worker %d did not finish its isolated Drupal bootstrap.',
              $workerId,
            ));
          }
          usleep(10_000);
        }
      }

      $sentCount = $summary->enviarResumos();
      foreach ($workers as $workerId => &$worker) {
        [$stdout, $stderr, $exitCode] = $this->collectWorkerProcess($worker['process'], $worker['pipes']);
        $worker['process'] = NULL;
        self::assertSame(0, $exitCode, sprintf('Competing Drupal worker %d failed: %s', $workerId, trim($stderr . "\n" . $stdout)));
        self::assertSame('locked', trim((string) file_get_contents($barrierDirectory . '/competitor-done-' . $workerId)));
      }
      unset($worker);
      self::assertSame(1, $sentCount, 'The first worker did not complete delivery.');
      self::assertSame([[502]], $provider->acknowledged);
      self::assertCount(1, $this->collectedMails());
      self::assertCount(1, $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')
        ->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]));
    }
    finally {
      foreach ($workers as $workerId => $worker) {
        if (is_resource($worker['process'])) {
          file_put_contents($barrierDirectory . '/competitor-done-' . $workerId, 'abort', LOCK_EX);
          proc_terminate($worker['process']);
          foreach ($worker['pipes'] as $pipe) {
            if (is_resource($pipe)) {
              fclose($pipe);
            }
          }
          proc_close($worker['process']);
        }
      }
      if ($originalBarrier === FALSE) {
        putenv('ACULTA_DIGEST_BARRIER_DIR');
      }
      else {
        putenv('ACULTA_DIGEST_BARRIER_DIR=' . $originalBarrier);
      }
      putenv('ACULTA_DIGEST_RECIPIENT_UID');
      putenv('ACULTA_DIGEST_SHARED_TEST_PREFIX');
      putenv('ACULTA_DIGEST_COMPETITOR_COUNT');
      putenv('ACULTA_DIGEST_COMPETITOR_ID');
      $markerFiles = ['transport-started'];
      foreach (range(1, $workerCount) as $workerId) {
        $markerFiles[] = 'competitor-ready-' . $workerId;
        $markerFiles[] = 'competitor-done-' . $workerId;
      }
      foreach ($markerFiles as $filename) {
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
