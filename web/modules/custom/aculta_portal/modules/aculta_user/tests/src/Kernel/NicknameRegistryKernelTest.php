<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\aculta_user\Account\NicknameClaimConflictException;
use Drupal\aculta_user\Account\NicknameRegistry;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies SQL nickname claims and the rebuildable Profile projection.
 */
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class NicknameRegistryKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'field',
    'entity',
    'views',
    'profile',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_user\\', dirname(__DIR__, 3) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_portal\\', dirname(__DIR__, 5) . '/src', TRUE);
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user', 'filter', 'profile']);

    require_once dirname(__DIR__, 3) . '/aculta_user.install';
    $this->container->get('database')->schema()->createTable(
      'aculta_user_nickname',
      aculta_user_schema()['aculta_user_nickname'],
    );

    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
      'settings' => ['max_length' => 80],
      'translatable' => TRUE,
    ])->save();
    foreach (['authenticated', 'administrator'] as $type) {
      ProfileType::create(['id' => $type, 'label' => $type])->save();
      FieldConfig::create([
        'entity_type' => 'profile',
        'bundle' => $type,
        'field_name' => 'field_nickname',
        'label' => 'Nickname',
      ])->save();
    }
  }

  /**
   * A rename frees its previous slug and a different UID can claim it.
   */
  public function testRenameReleasesOldSlugAndRejectsConcurrentDuplicate(): void {
    $registry = $this->registry();
    $registry->claim(10, 'nome-2');
    self::assertSame(10, $registry->uidForSlug('nome-2'));

    $registry->claim(10, 'nome-3');
    self::assertNull($registry->uidForSlug('nome-2'));
    self::assertSame(10, $registry->uidForSlug('nome-3'));

    $this->expectException(NicknameClaimConflictException::class);
    $registry->claim(11, 'nome-3');
  }

  /**
   * The database unique key remains authoritative after both preflight reads.
   */
  public function testSqlUniqueKeyRejectsDuplicateOwnerClaims(): void {
    $this->registry()->claim(10, 'slug-publico');

    $this->expectException(IntegrityConstraintViolationException::class);
    $this->container->get('database')->insert('aculta_user_nickname')
      ->fields(['uid' => 11, 'slug' => 'slug-publico'])
      ->execute();
  }

  /**
   * Two independent processes cannot commit the same slug for different UIDs.
   */
  public function testConcurrentClaimsCannotShareSlug(): void {
    $database = $this->container->get('database');
    if (!function_exists('proc_open') || $database->driver() !== 'mysql') {
      self::markTestSkipped('The process race test requires proc_open and the MariaDB/MySQL driver.');
    }
    $processes = [];
    for ($uid = 10; $uid <= 11; $uid++) {
      $pipes = [];
      $process = proc_open(
        [PHP_BINARY, __DIR__ . '/fixtures/nickname-claim-writer.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        NULL,
        [],
      );
      if (!is_resource($process)) {
        self::fail('Unable to start an isolated nickname claim writer.');
      }
      $payload = [
        'autoload' => dirname(DRUPAL_ROOT) . '/vendor/autoload.php',
        'core' => DRUPAL_ROOT,
        'connection' => Database::getConnectionInfo('default')['default'],
        'uid' => $uid,
        'slug' => 'concorrente',
      ];
      fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR) . "\n");
      fflush($pipes[0]);
      $processes[] = ['process' => $process, 'pipes' => $pipes];
    }

    foreach ($processes as $process) {
      stream_set_timeout($process['pipes'][1], 10);
      $ready = fgets($process['pipes'][1]);
      if ($ready !== "READY\n") {
        $error = stream_get_contents($process['pipes'][2]);
        self::fail('Nickname claim writer failed before its barrier: ' . trim((string) $error));
      }
    }
    $outcomes = [];
    foreach ($processes as $process) {
      fwrite($process['pipes'][0], "GO\n");
      fflush($process['pipes'][0]);
    }
    foreach ($processes as $process) {
      stream_set_timeout($process['pipes'][1], 10);
      self::assertSame("CHECKED\n", fgets($process['pipes'][1]));
    }
    foreach ($processes as $process) {
      fwrite($process['pipes'][0], "COMMIT\n");
      fflush($process['pipes'][0]);
    }
    foreach ($processes as $process) {
      stream_set_timeout($process['pipes'][1], 10);
      $outcomes[] = trim((string) fgets($process['pipes'][1]));
      fclose($process['pipes'][0]);
      fclose($process['pipes'][1]);
      fclose($process['pipes'][2]);
      self::assertSame(0, proc_close($process['process']));
    }
    sort($outcomes);

    self::assertSame(['CLAIMED', 'CONFLICT'], $outcomes);
    self::assertContains($this->registry()->uidForSlug('concorrente'), [10, 11]);
  }

  /**
   * Rebuild accepts matching role Profiles and remains idempotent.
   */
  public function testRebuildProjectsOneNicknameForMultipleRoleProfiles(): void {
    $account = $this->account('same');
    $this->profile((int) $account->id(), 'authenticated', 'nome#2');
    $this->profile((int) $account->id(), 'administrator', 'nome#2');
    $registry = $this->registry();

    self::assertSame(['uids' => 1, 'profiles' => 2], $registry->rebuild());
    self::assertSame((int) $account->id(), $registry->uidForSlug('nome-2'));
    self::assertSame(['uids' => 1, 'profiles' => 2], $registry->rebuild());
  }

  /**
   * Rebuild refuses conflicting owners without replacing existing claims.
   */
  public function testRebuildFailsClosedOnCrossUidDuplicate(): void {
    $registry = $this->registry();
    $registry->claim(99, 'existing');
    $first = $this->account('first');
    $second = $this->account('second');
    $this->profile((int) $first->id(), 'authenticated', 'duplicado');
    $this->profile((int) $second->id(), 'authenticated', 'duplicado');

    try {
      $registry->rebuild();
      self::fail('Rebuild must reject a slug owned by two different UIDs.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('cross-UID conflicts=1', $exception->getMessage());
      self::assertSame(99, $registry->uidForSlug('existing'));
    }
  }

  /**
   * Rebuild refuses role Profiles of one UID that disagree on display nickname.
   */
  public function testRebuildFailsClosedOnSameUidMismatch(): void {
    $account = $this->account('different');
    $this->profile((int) $account->id(), 'authenticated', 'primeiro');
    $this->profile((int) $account->id(), 'administrator', 'segundo');

    try {
      $this->registry()->rebuild();
      self::fail('Rebuild must reject divergent role Profile nicknames for one UID.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('same-UID conflicts=1', $exception->getMessage());
    }
  }

  /**
   * Updating one role Profile synchronizes its siblings within the UID.
   */
  public function testProfileRenameSynchronizesSiblingRoleProfiles(): void {
    $account = $this->account('member');
    $first = $this->profile((int) $account->id(), 'authenticated', 'antes');
    $second = $this->profile((int) $account->id(), 'administrator', 'antes');
    $registry = $this->registry();
    $registry->claim((int) $account->id(), 'antes');

    $transaction = $this->container->get('database')->startTransaction();
    $first->set('field_nickname', 'depois');
    $registry->synchronizeProfile($first);
    $first->save();
    $transaction->commitOrRelease();

    $unchanged = $this->container->get('entity_type.manager')
      ->getStorage('profile')
      ->loadUnchanged($second->id());
    self::assertSame('depois', $unchanged->get('field_nickname')->value);
    self::assertNull($registry->uidForSlug('antes'));
    self::assertSame((int) $account->id(), $registry->uidForSlug('depois'));
  }

  /**
   * Reverting a failed Profile save also reverts nickname synchronization.
   */
  public function testFailedProfileSaveRollsBackNicknameSynchronization(): void {
    $account = $this->account('rollback');
    $first = $this->profile((int) $account->id(), 'authenticated', 'antes');
    $second = $this->profile((int) $account->id(), 'administrator', 'antes');
    $registry = $this->registry();
    $registry->claim((int) $account->id(), 'antes');

    $database = $this->container->get('database');
    $transaction = $database->startTransaction();
    try {
      $first->set('field_nickname', 'depois');
      $registry->synchronizeProfile($first);
      throw new \RuntimeException('Simulated Profile storage failure.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('Simulated Profile storage failure.', $exception->getMessage());
      $transaction->rollBack();
    }

    self::assertSame((int) $account->id(), $registry->uidForSlug('antes'));
    self::assertNull($registry->uidForSlug('depois'));
    $storage = $this->container->get('entity_type.manager')->getStorage('profile');
    self::assertSame('antes', $storage->loadUnchanged($first->id())->get('field_nickname')->value);
    self::assertSame('antes', $storage->loadUnchanged($second->id())->get('field_nickname')->value);
  }

  /**
   * Creates a registry using the test database and entity manager.
   */
  private function registry(): NicknameRegistry {
    return new NicknameRegistry(
      $this->container->get('database'),
      $this->container->get('entity_type.manager'),
    );
  }

  /**
   * Creates one test account.
   */
  private function account(string $name): User {
    $account = User::create([
      'name' => $name,
      'mail' => $name . '@example.test',
      'status' => 1,
    ]);
    $account->save();
    return $account;
  }

  /**
   * Creates and saves a role Profile with its public nickname.
   */
  private function profile(int $uid, string $type, string $nickname): Profile {
    $profile = Profile::create([
      'type' => $type,
      'uid' => $uid,
      'field_nickname' => $nickname,
    ]);
    $profile->save();
    return $profile;
  }

}
