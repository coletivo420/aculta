<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Kernel;

use Drupal\aculta_cgu\Adapter\ContributionAdapterRegistry;
use Drupal\aculta_cgu\Contract\ContributionAdapterInterface;
use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Tests generic proposal submission, decision, idempotency, and conflicts. */
#[RunTestsInSeparateProcesses]
final class ContributionLifecycleKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = ['system', 'user', 'options', 'views', 'aculta_contributions'];

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->container->set('lock', new DatabaseLockBackend($this->container->get('database')));
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_contribution');
    $this->installConfig(['system', 'user', 'aculta_contributions']);
  }

  /**
   * Submission replay returns one proposal; acceptance applies selected key. */
  public function testSubmissionReplayAndSelectiveApplication(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($author);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $token = str_repeat('a', 64);
    $proposal = $lifecycle->submit(
      'test_user',
      'update',
      $target->uuid(),
      'Rename profile',
      'Correct the display name.',
      ['name' => 'Suggested name'],
      $token,
      ['evidence' => ['https://example.test/source']],
    );
    $replay = $lifecycle->submit('test_user', 'update', $target->uuid(), 'Different replay body', 'Ignored replay body.', ['name' => 'Ignored'], $token);

    self::assertSame($proposal->id(), $replay->id());
    self::assertSame(['evidence' => ['https://example.test/source']], $proposal->getSourceData());
    self::assertSame('Original name', $this->loadUser($target->uuid())->getAccountName());

    $reviewer = $this->createReviewer();
    $this->container->get('current_user')->setAccount($reviewer);
    $decided = $lifecycle->decide($proposal, 'accepted', 'Verified correction.', ['name'], (int) $proposal->getRevisionId());
    self::assertSame('accepted', $decided->get('state')->value);
    self::assertSame([
      'selected_fields' => ['name'],
      'adapter_data' => [],
    ], $decided->getDecisionData());
    self::assertSame('Suggested name', $this->loadUser($target->uuid())->getAccountName());
    self::assertNotSame($proposal->getRevisionId(), $decided->getRevisionId());
  }

  /**
   * Changed source version creates conflict and leaves official data alone. */
  public function testSourceConflictBlocksApplication(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($author);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit('test_user', 'update', $target->uuid(), 'Rename profile', 'Correct the display name.', ['name' => 'Suggested name'], str_repeat('b', 64));

    $target->set('name', 'Independent update');
    $target->save();
    $reviewer = $this->createReviewer();
    $this->container->get('current_user')->setAccount($reviewer);
    try {
      $lifecycle->decide($proposal, 'accepted', 'Verified correction.', ['name'], (int) $proposal->getRevisionId());
      self::fail('Expected a stale source conflict.');
    }
    catch (ConflictHttpException) {
      $reloaded = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')->load($proposal->id());
      self::assertSame('conflict', $reloaded->get('state')->value);
    }
    self::assertSame('Independent update', $this->loadUser($target->uuid())->getAccountName());
  }

  /**
   * Requested changes create an author revision and withdrawal is final.
   */
  public function testRevisionAfterFeedbackAndWithdrawal(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $this->container->get('current_user')->setAccount($author);
    $proposal = $lifecycle->submit(
      'test_user',
      'update',
      $target->uuid(),
      'Rename profile',
      'Correct the display name.',
      ['name' => 'First suggestion'],
      str_repeat('7', 64),
    );

    $this->container->get('current_user')->setAccount($this->createReviewer());
    $returned = $lifecycle->decide(
      $proposal,
      'needs_changes',
      'Please clarify the source.',
      [],
      (int) $proposal->getRevisionId(),
    );
    self::assertSame('needs_changes', $returned->get('state')->value);

    $this->container->get('current_user')->setAccount($author);
    $revised = $lifecycle->revise(
      $returned,
      'Rename profile with source',
      'The source confirms the new display name.',
      ['name' => 'Revised suggestion'],
      (int) $returned->getRevisionId(),
    );
    self::assertSame('submitted', $revised->get('state')->value);
    self::assertSame(['name' => 'Revised suggestion'], $revised->getPayload());
    self::assertNotSame($returned->getRevisionId(), $revised->getRevisionId());

    $withdrawn = $lifecycle->withdraw($revised, (int) $revised->getRevisionId());
    self::assertSame('withdrawn', $withdrawn->get('state')->value);
    self::assertNotSame($revised->getRevisionId(), $withdrawn->getRevisionId());
    $this->expectException(ConflictHttpException::class);
    $lifecycle->withdraw($withdrawn, (int) $withdrawn->getRevisionId());
  }

  /**
   * An independent lock backend blocks a duplicate concurrent submission.
   */
  public function testIndependentBackendContentionStopsSubmission(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($author);
    $token = str_repeat('8', 64);
    $hash = hash('sha256', (int) $author->id() . ':' . $token);
    $lock_name = 'aculta_contributions.submit:' . $hash;

    $connection_info = Database::getConnectionInfo('default');
    Database::addConnectionInfo('contributions_lock_observer', 'default', $connection_info['default']);
    $observer_connection = Database::getConnection('default', 'contributions_lock_observer');
    $observer = new DatabaseLockBackend($observer_connection);
    self::assertTrue($observer->acquire($lock_name, 30.0));
    try {
      try {
        $this->container->get('aculta_contributions.lifecycle')->submit(
          'test_user',
          'update',
          $target->uuid(),
          'Contended proposal',
          'A second request must not pass the submission lock.',
          ['name' => 'Should not persist'],
          $token,
        );
        self::fail('Expected the independent lock backend to block submission.');
      }
      catch (TooManyRequestsHttpException) {
        self::assertSame([], $this->container->get('entity_type.manager')
          ->getStorage('aculta_contribution')->loadMultiple());
      }
    }
    finally {
      $observer->releaseAll();
      Database::removeConnection('contributions_lock_observer');
    }
  }

  /**
   * An OS process holding the submission lock blocks a competing request.
   */
  public function testOsProcessContentionStopsSubmission(): void {
    if (!function_exists('proc_open')) {
      self::markTestSkipped('This test requires proc_open.');
    }

    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($author);
    $token = str_repeat('9', 64);
    $hash = hash('sha256', (int) $author->id() . ':' . $token);
    $lock_name = 'aculta_contributions.submit:' . $hash;
    $project_root = dirname(DRUPAL_ROOT);
    $process = proc_open(
      [PHP_BINARY, __DIR__ . '/ContributionLockWorker.php', $project_root, $lock_name],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes,
      $project_root,
    );
    self::assertIsResource($process, 'The competing lock process must start.');

    $denied = FALSE;
    $exit_code = NULL;
    $lock_signal = '';
    try {
      fwrite($pipes[0], base64_encode(serialize(Database::getConnectionInfo('default')['default'])) . "\n");
      fflush($pipes[0]);
      stream_set_timeout($pipes[1], 15);
      $lock_signal = fread($pipes[1], 1);
      if ($lock_signal === 'L') {
        try {
          $this->container->get('aculta_contributions.lifecycle')->submit(
            'test_user',
            'update',
            $target->uuid(),
            'Competing process proposal',
            'A second OS process already owns the submission lock.',
            ['name' => 'Must not persist'],
            $token,
          );
        }
        catch (TooManyRequestsHttpException) {
          $denied = TRUE;
        }

        self::assertTrue($denied, 'The competing request must be rate-limited while the process lock is held.');
        self::assertSame([], $this->container->get('entity_type.manager')
          ->getStorage('aculta_contribution')->loadMultiple());
      }
    }
    finally {
      @fwrite($pipes[0], "R\n");
      fflush($pipes[0]);
      fclose($pipes[0]);
      fclose($pipes[1]);
      stream_get_contents($pipes[2]);
      fclose($pipes[2]);
      $exit_code = proc_close($process);
    }

    self::assertSame('L', $lock_signal, 'The separate process must acquire the shared database lock.');
    self::assertSame(0, $exit_code, 'The lock process must acquire and release the shared database lock.');
  }

  /**
   * Accounts without submit grants cannot create proposals. */
  public function testSubmissionDeniedWithoutPermission(): void {
    [, $target] = $this->createAccountsAndTarget();
    $outsider = $this->container->get('entity_type.manager')->getStorage('user')->create([
      'name' => 'unauthorized',
      'status' => 1,
    ]);
    $outsider->save();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($outsider);
    try {
      $this->container->get('aculta_contributions.lifecycle')->submit(
        'test_user',
        'update',
        $target->uuid(),
        'Unauthorized proposal',
        'No permission.',
        ['name' => 'Unauthorized'],
        str_repeat('c', 64),
      );
      self::fail('Expected submission to be denied.');
    }
    catch (AccessDeniedHttpException) {
      self::assertSame([], $this->container->get('entity_type.manager')
        ->getStorage('aculta_contribution')->loadMultiple());
    }
  }

  /**
   * Adapter allowlists reject unknown source fields before any write. */
  public function testAdapterRejectsNonAllowlistedFields(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($author);
    try {
      $this->container->get('aculta_contributions.lifecycle')->submit(
        'test_user',
        'update',
        $target->uuid(),
        'Invalid proposal',
        'Contains a forbidden field.',
        ['name' => 'Suggested name', 'status' => 0],
        str_repeat('d', 64),
      );
      self::fail('Expected the adapter to reject an unknown field.');
    }
    catch (\InvalidArgumentException) {
      self::assertSame('Original name', $this->loadUser($target->uuid())->getAccountName());
      self::assertSame([], $this->container->get('entity_type.manager')
        ->getStorage('aculta_contribution')->loadMultiple());
    }
  }

  /**
   * Source context is adapter-validated before a proposal can be saved.
   */
  public function testAdapterRejectsNonAllowlistedSourceData(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($author);

    $this->expectException(\InvalidArgumentException::class);
    try {
      $this->container->get('aculta_contributions.lifecycle')->submit(
        'test_user',
        'update',
        $target->uuid(),
        'Invalid context',
        'The adapter must reject unknown context.',
        ['name' => 'Suggested name'],
        str_repeat('9', 64),
        ['private_account_data' => 'must not be accepted'],
      );
    }
    finally {
      self::assertSame([], $this->container->get('entity_type.manager')
        ->getStorage('aculta_contribution')->loadMultiple());
    }
  }

  /**
   * Flood limits are enforced before saving a second distinct proposal. */
  public function testFloodLimitIsConfigurableAndStopsExcessSubmissions(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($author);
    $this->container->get('config.factory')->getEditable('aculta_contributions.settings')
      ->set('flood_limit', 1)
      ->set('flood_window', 3600)
      ->save();
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $lifecycle->submit('test_user', 'update', $target->uuid(), 'First proposal', 'First rationale.', ['name' => 'First'], str_repeat('e', 64));
    try {
      $lifecycle->submit('test_user', 'update', $target->uuid(), 'Second proposal', 'Second rationale.', ['name' => 'Second'], str_repeat('f', 64));
      self::fail('Expected the configured Flood threshold to reject submission.');
    }
    catch (TooManyRequestsHttpException) {
      self::assertCount(1, $this->container->get('entity_type.manager')
        ->getStorage('aculta_contribution')->loadMultiple());
    }
  }

  /**
   * An applier error rolls back its source change and proposal decision. */
  public function testApplyFailureRollsBackSourceAndProposal(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter(TRUE));
    $this->container->get('current_user')->setAccount($author);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit('test_user', 'update', $target->uuid(), 'Rename profile', 'Correct the display name.', ['name' => 'Suggested name'], str_repeat('6', 64));
    $this->container->get('current_user')->setAccount($this->createReviewer());
    try {
      $lifecycle->decide($proposal, 'accepted', 'Verified correction.', ['name'], (int) $proposal->getRevisionId());
      self::fail('Expected the fixture applier to fail.');
    }
    catch (\RuntimeException) {
      $saved_proposal = $this->container->get('entity_type.manager')->getStorage('aculta_contribution')->load($proposal->id());
      self::assertSame('submitted', $saved_proposal->get('state')->value);
    }
    self::assertSame('Original name', $this->loadUser($target->uuid())->getAccountName());
  }

  /**
   * A downstream notice failure cannot roll back a committed approval. */
  public function testNoticeFailureDoesNotRollBackDecisionOrSource(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter(FALSE, TRUE));
    $this->container->get('current_user')->setAccount($author);
    $lifecycle = $this->container->get('aculta_contributions.lifecycle');
    $proposal = $lifecycle->submit(
      'test_user',
      'update',
      $target->uuid(),
      'Rename profile',
      'Correct the display name.',
      ['name' => 'Approved name'],
      str_repeat('5', 64),
    );

    $this->container->get('current_user')->setAccount($this->createReviewer());
    $decided = $lifecycle->decide(
      $proposal,
      'accepted',
      'Verified correction.',
      ['name'],
      (int) $proposal->getRevisionId(),
    );

    self::assertSame('accepted', $decided->get('state')->value);
    self::assertSame('Approved name', $this->loadUser($target->uuid())->getAccountName());
  }

  /**
   * Adapter-specific decision choices are validated before applying fields.
   */
  public function testAdapterRejectsUnvalidatedDecisionData(): void {
    [$author, $target] = $this->createAccountsAndTarget();
    $this->registerAdapter($this->createAdapter());
    $this->container->get('current_user')->setAccount($author);
    $proposal = $this->container->get('aculta_contributions.lifecycle')->submit(
      'test_user',
      'update',
      $target->uuid(),
      'Rename profile',
      'Correct the display name.',
      ['name' => 'Suggested name'],
      str_repeat('a', 64),
    );

    $this->container->get('current_user')->setAccount($this->createReviewer());
    $this->expectException(\InvalidArgumentException::class);
    try {
      $this->container->get('aculta_contributions.lifecycle')->decide(
        $proposal,
        'accepted',
        'Verified correction.',
        ['name'],
        (int) $proposal->getRevisionId(),
        ['arbitrary_field' => 'not allowlisted'],
      );
    }
    finally {
      $reloaded = $this->container->get('entity_type.manager')
        ->getStorage('aculta_contribution')->load($proposal->id());
      self::assertSame('submitted', $reloaded->get('state')->value);
      self::assertSame('Original name', $this->loadUser($target->uuid())->getAccountName());
    }
  }

  /**
   * Creates ordinary accounts with non-superuser roles and a source profile. */
  private function createAccountsAndTarget(): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->create(['name' => 'reserved-superuser', 'status' => 1])->save();
    Role::create([
      'id' => 'contribution_submitter',
      'label' => 'Contribution submitter',
      'permissions' => ['submit aculta contributions', 'view own aculta contributions'],
    ])->save();
    $author = $storage->create([
      'name' => 'ordinary-author',
      'status' => 1,
      'roles' => ['contribution_submitter'],
    ]);
    $author->save();
    $target = $storage->create(['name' => 'Original name', 'status' => 1]);
    $target->save();
    return [$author, $target];
  }

  /**
   * Creates a reviewer with explicit review and collection permissions. */
  private function createReviewer(): object {
    Role::create([
      'id' => 'contribution_reviewer',
      'label' => 'Contribution reviewer',
      'permissions' => ['review aculta contributions', 'view any aculta contributions'],
    ])->save();
    $reviewer = $this->container->get('entity_type.manager')->getStorage('user')->create([
      'name' => 'ordinary-reviewer',
      'status' => 1,
      'roles' => ['contribution_reviewer'],
    ]);
    $reviewer->save();
    return $reviewer;
  }

  /**
   * Registers a test domain adapter through the production service registry. */
  private function registerAdapter(ContributionAdapterInterface $adapter): void {
    $registry = $this->container->get('aculta_contributions.adapter_registry');
    self::assertInstanceOf(ContributionAdapterRegistry::class, $registry);
    $registry->addAdapter($adapter);
  }

  /**
   * Creates an adapter with one fixed, server-allowlisted source field. */
  private function createAdapter(bool $failAfterApply = FALSE, bool $failNotice = FALSE): ContributionAdapterInterface {
    $entity_type_manager = $this->container->get('entity_type.manager');
    return new class($entity_type_manager, $failAfterApply, $failNotice) implements ContributionAdapterInterface {

      public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly bool $failAfterApply,
        private readonly bool $failNotice,
      ) {}

      /** Returns the fixed adapter machine name. */
      public function id(): string {
        return 'test_user';
      }

      /** Returns the owner module. */
      public function moduleId(): string {
        return 'aculta_user';
      }

      /** Returns a trusted purpose from the fixture adapter. */
      public function purpose(?EntityInterface $target): string {
        return 'account';
      }

      /** Applies the fixture's source-owned access policy. */
      public function entityAccess(AccountInterface $account, Contribution $contribution, string $operation): AccessResultInterface {
        $owner = (int) $contribution->getOwnerId() === (int) $account->id();
        $allowed = match ($operation) {
          'view', 'view revision' => ($owner && $account->hasPermission('view own aculta contributions'))
            || $account->hasPermission('view any aculta contributions'),
          'update' => $account->hasPermission('review aculta contributions')
            || ($owner && $contribution->get('state')->value === 'needs_changes'
              && $account->hasPermission('submit aculta contributions')),
          default => FALSE,
        };
        return AccessResult::allowedIf($allowed)->cachePerUser()->cachePerPermissions();
      }

      /** Allows the submitter for this fixture operation. */
      public function submitAccess(AccountInterface $account, string $intent, ?EntityInterface $target): bool {
        return $intent === 'update' && $target !== NULL && $account->hasPermission('submit aculta contributions');
      }

      /** Allows authorized reviewers for this fixture operation. */
      public function reviewAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
        return $intent === 'update' && $target !== NULL && $reviewer->hasPermission('review aculta contributions');
      }

      /** Reuses the reviewer policy at application time. */
      public function applyAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
        return $this->reviewAccess($reviewer, $intent, $target);
      }

      /** Resolves only user entities by UUID. */
      public function loadTarget(string $intent, ?string $targetUuid): ?EntityInterface {
        if ($intent !== 'update' || $targetUuid === NULL) {
          return NULL;
        }
        $targets = $this->entityTypeManager->getStorage('user')->loadByProperties(['uuid' => $targetUuid]);
        $target = reset($targets);
        return $target instanceof EntityInterface ? $target : NULL;
      }

      /** Allows only a non-empty account name payload. */
      public function validatePayload(string $intent, array $payload, ?EntityInterface $target = NULL): array {
        if ($intent !== 'update' || array_keys($payload) !== ['name'] || !is_string($payload['name']) || trim($payload['name']) === '') {
          throw new \InvalidArgumentException('Only a non-empty account name is allowed.');
        }
        return ['name' => trim($payload['name'])];
      }

      /** Keeps the fixture free of source-owned context. */
      public function validateSourceData(string $intent, array $sourceData): array {
        if ($intent !== 'update') {
          throw new \InvalidArgumentException('Unsupported source context intent.');
        }
        if ($sourceData === []) {
          return [];
        }
        $evidence = $sourceData['evidence'] ?? NULL;
        if (array_keys($sourceData) !== ['evidence'] || !is_array($evidence)) {
          throw new \InvalidArgumentException('Only evidence URLs are allowed.');
        }
        foreach ($evidence as $uri) {
          if (!is_string($uri) || !filter_var($uri, FILTER_VALIDATE_URL)
            || strtolower((string) parse_url($uri, PHP_URL_SCHEME)) !== 'https') {
            throw new \InvalidArgumentException('Evidence must use HTTPS URLs.');
          }
        }
        return ['evidence' => array_values(array_unique($evidence))];
      }

      /** Allows no domain-specific application options in this fixture. */
      public function validateDecisionData(string $intent, array $selectedFields, array $decisionData): array {
        if ($intent !== 'update' || $decisionData !== []) {
          throw new \InvalidArgumentException('The fixture accepts no decision data.');
        }
        return [];
      }

      /** Fingerprints the current target label. */
      public function currentVersion(string $intent, ?EntityInterface $target): string {
        return hash('sha256', $intent . ':' . ($target?->label() ?? 'missing'));
      }

      /** Records a notice or simulates downstream delivery failure. */
      public function recordDecision(Contribution $proposal, string $outcome): void {
        if ($this->failNotice) {
          throw new \RuntimeException('Fixture notice delivery failure.');
        }
      }

      /** Applies the selected name after checking the expected version. */
      public function apply(string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
        if (!$target || !hash_equals($expectedVersion, $this->currentVersion($intent, $target)) || array_keys($selectedPayload) !== ['name']) {
          throw new \RuntimeException('The source changed or the selected field is invalid.');
        }
        $target->set('name', $selectedPayload['name']);
        $target->save();
        if ($this->failAfterApply) {
          throw new \RuntimeException('Fixture failure after the source entity save.');
        }
        return $target;
      }

    };
  }

  /**
   * Loads a test account by UUID. */
  private function loadUser(string $uuid): object {
    $users = $this->container->get('entity_type.manager')->getStorage('user')->loadByProperties(['uuid' => $uuid]);
    return reset($users);
  }

}
