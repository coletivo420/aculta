<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_messenger_aviso_test\Kernel\DigestRecoveryProvider;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\flag\FlagServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Site\Settings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kills an independent Drupal worker after the Mailer persists acceptance.
 *
 * This fixture is invoked by AvisosResumoMailerKernelTest and shares its
 * isolated MariaDB prefix. It must not be discovered as a regular test.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class DigestCrashWorkerKernelTest extends KernelTestBase {

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
  protected function getDatabaseConnectionInfo() {
    $connectionInfo = parent::getDatabaseConnectionInfo();
    $prefix = getenv('ACULTA_DIGEST_SHARED_TEST_PREFIX');
    if (!is_string($prefix) || $prefix === '') {
      throw new \RuntimeException('The shared digest test prefix is missing.');
    }
    foreach ($connectionInfo as &$target) {
      $target['prefix'] = $prefix;
    }
    unset($target);
    return $connectionInfo;
  }

  /**
   * Stops the worker after Mailer has durably recorded a successful delivery.
   */
  public function testWorkerDiesAfterMailerAcceptance(): void {
    $uid = (int) getenv('ACULTA_DIGEST_RECIPIENT_UID');
    $marker = getenv('ACULTA_DIGEST_CRASH_MARKER');
    if ($uid < 1 || !is_string($marker) || $marker === '') {
      throw new \RuntimeException('The digest crash worker arguments are incomplete.');
    }

    $GLOBALS['config']['system.mail']['interface']['default'] = 'aculta_digest_crash_test';
    $this->container->get('config.factory')->reset('system.mail');

    $userData = $this->container->get('user.data');
    $entityTypeManager = $this->container->get('entity_type.manager');
    $database = $this->container->get('database');
    $provider = new DigestRecoveryProvider($uid, $database);
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
    $realMailer = $this->container->get('aculta_mailer.mailer');
    $mailer = new class($realMailer, $marker) implements AcultaMailerInterface {

      public function __construct(
        private readonly AcultaMailerInterface $inner,
        private readonly string $marker,
      ) {}

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
        if ($result->sent) {
          if (file_put_contents($this->marker, 'accepted', LOCK_EX) === FALSE) {
            throw new \RuntimeException('Could not write the post-acceptance marker.');
          }
          posix_kill(getmypid(), SIGKILL);
          // SIGKILL cannot be handled; keep the fixture from accidentally
          // returning normally if a platform reports success without delivery.
          while (TRUE) {
            usleep(10_000);
          }
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
      new AvisoPreferencias($userData),
      $mailer,
      new EmailConfirmationPolicy($userData, $entityTypeManager, $this->container->get('module_handler')),
      $userData,
      $this->container->get('datetime.time'),
      $avisos,
      $this->container->get('settings'),
      $database,
      $this->container->get('lock.persistent'),
      [$provider],
    );

    self::assertSame(1, $summary->enviarResumos());
  }

  /**
   * Preserve the parent's database prefix when the worker exits normally.
   */
  protected function tearDown(): void {
    // The child shares the parent Kernel's tables; do not remove them.
  }

}
