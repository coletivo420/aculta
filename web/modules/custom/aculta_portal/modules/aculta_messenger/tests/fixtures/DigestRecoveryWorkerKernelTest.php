<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_messenger_aviso_test\Kernel\DigestRecoveryProvider;
use Drupal\flag\FlagServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Site\Settings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Boots an independent digest worker over persisted delivery state.
 *
 * This fixture is invoked explicitly by AvisosResumoMailerKernelTest with the
 * same isolated MariaDB prefix; it is excluded from normal Kernel discovery.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class DigestRecoveryWorkerKernelTest extends KernelTestBase {

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
   * Recovers an accepted digest after a separate PHP process boots Drupal.
   */
  public function testIndependentWorkerReconcilesAcceptedDigest(): void {
    $uid = (int) getenv('ACULTA_DIGEST_RECIPIENT_UID');
    if ($uid < 1) {
      throw new \RuntimeException('The digest recovery recipient is missing.');
    }

    $userData = $this->container->get('user.data');
    $entityTypeManager = $this->container->get('entity_type.manager');
    $database = $this->container->get('database');
    $provider = new DigestRecoveryProvider($uid, $database, forbidDigestRows: TRUE);
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

    self::assertSame(0, $summary->enviarResumos());
    self::assertNull($userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento'));
    self::assertSame(1, (int) $database->select(DigestRecoveryProvider::TABLE, 'digest')
      ->fields('digest', ['acknowledged'])
      ->condition('id', 1)
      ->execute()
      ->fetchField());
    $logs = $entityTypeManager->getStorage('aculta_mail_log')
      ->loadByProperties(['type' => AvisosResumo::CHAVE_MAIL]);
    self::assertCount(1, $logs);
    self::assertSame(MailStatus::Sent->value, reset($logs)->get('status')->value);
  }

  /**
   * Preserve the parent process's database prefix after this child exits.
   */
  protected function tearDown(): void {
    // This child shares the parent's tables; do not delete them.
  }

}
