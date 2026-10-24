<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\flag\FlagServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Independent Drupal worker used by the digest concurrency Kernel test.
 *
 * This fixture is invoked explicitly by AvisosResumoMailerKernelTest and is
 * excluded from the regular Kernel test discovery path.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class DigestCompetingWorkerKernelTest extends KernelTestBase {

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
   * Confirms a complete second worker cannot acquire the in-flight recipient.
   */
  public function testCompetingWorkerSkipsLockedDigest(): void {
    $barrierDirectory = getenv('ACULTA_DIGEST_BARRIER_DIR');
    $uid = (int) getenv('ACULTA_DIGEST_RECIPIENT_UID');
    if (!is_string($barrierDirectory) || $barrierDirectory === '' || $uid < 1) {
      throw new \RuntimeException('The digest worker fixture arguments are incomplete.');
    }

    $deadline = microtime(TRUE) + 30;
    while (!is_file($barrierDirectory . '/transport-started')) {
      if (microtime(TRUE) >= $deadline) {
        throw new \RuntimeException('Timed out waiting for the first digest transport.');
      }
      usleep(10_000);
    }

    $userData = $this->container->get('user.data');
    $entityTypeManager = $this->container->get('entity_type.manager');
    $database = $this->container->get('database');
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
    $preferences = new AvisoPreferencias($userData);
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
    );

    try {
      self::assertSame(0, $summary->enviarResumos());
      if (file_put_contents($barrierDirectory . '/competitor-done', 'locked', LOCK_EX) === FALSE) {
        throw new \RuntimeException('Could not signal that the digest lock rejected this worker.');
      }
    }
    catch (\Throwable $exception) {
      file_put_contents($barrierDirectory . '/competitor-done', 'error', LOCK_EX);
      throw $exception;
    }
  }

  /**
   * Prevents this worker process from dropping the shared parent test tables.
   */
  protected function tearDown(): void {
    // The worker process exits immediately after the competing attempt. Its
    // prefix is the parent Kernel's live prefix, so KernelTestBase cleanup
    // would otherwise delete the parent's tables before it can acknowledge.
  }

}
