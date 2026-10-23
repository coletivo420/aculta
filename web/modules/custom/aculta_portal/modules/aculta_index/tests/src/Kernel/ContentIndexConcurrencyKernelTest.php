<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_index\Generation\ContentIndexGenerationManager;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies lifecycle locks across independent MariaDB connections.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexConcurrencyKernelTest extends KernelTestBase {

  private const COMPETITOR_KEY = 'aculta_index_competitor';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'aculta_index',
    'aculta_index_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if ($this->container->get('database')->driver() !== 'mysql') {
      $this->markTestSkipped('This lock-contention contract requires MariaDB.');
    }
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    aculta_index_install();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    Database::removeConnection(self::COMPETITOR_KEY);
    parent::tearDown();
  }

  /**
   * Scheduling waits for promotion; queued changes then prevent rollback.
   */
  public function testPromotionSerializesSourceSchedulingAcrossConnections(): void {
    $database = $this->container->get('database');
    $manager = $this->container->get('aculta_index.generation_manager');
    $first = $this->validatedGeneration($manager);
    $manager->activate($first);
    $second = $this->validatedGeneration($manager);
    $competitor = $this->openCompetitor();
    $work = $this->workManager($competitor);
    $competingManager = $this->generationManager($competitor, $work);

    // Keep the production promotion's changes and locks uncommitted while a
    // separate database session attempts to schedule an entity change.
    $promotion = $database->startTransaction();
    try {
      $manager->activate($second);
      self::assertSame($first, $competingManager->getActiveGeneration(), 'Readers keep the old pointer until the promotion commits.');
      $this->assertLockWaitTimeout(static fn () => $work->schedule('test_sample_content', 'node', '41', 'en'));
      self::assertNull($work->getStatus('test_sample_content', 'node', '41', 'en'), 'A blocked scheduler must not partially persist work.');
    }
    finally {
      unset($promotion);
    }

    self::assertSame($second, $competingManager->getActiveGeneration());
    self::assertSame(1, $work->schedule('test_sample_content', 'node', '41', 'en'));
    try {
      $manager->activate($first);
      self::fail('Rollback must wait for the source change scheduled after promotion.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('waiting for source work to drain', $exception->getMessage());
    }
    self::assertSame($second, $competingManager->getActiveGeneration());

    $status = $work->getStatus('test_sample_content', 'node', '41', 'en');
    self::assertIsArray($status);
    $claim = $work->claim($status['work_id']);
    self::assertIsArray($claim);
    self::assertTrue($work->complete($claim['work_id'], $claim['sequence']));
    $manager->activate($first);
    self::assertSame($first, $competingManager->getActiveGeneration(), 'Rollback becomes possible after the current work sequence completes.');
  }

  /**
   * Another cleanup worker waits while the final deletion is uncommitted.
   */
  public function testCleanupSerializesFinalDeletionAcrossConnections(): void {
    $database = $this->container->get('database');
    $manager = $this->container->get('aculta_index.generation_manager');
    $generationId = $manager->start();
    $database->insert('aculta_index_entry')->fields([
      'generation_id' => $generationId,
      'provider_id' => 'test_sample_content',
      'entity_type' => 'node',
      'entity_id' => '41',
      'langcode' => 'en',
      'bundle' => 'sample_content',
    ])->execute();
    $manager->discardBuilding($generationId);
    $competitor = $this->openCompetitor();
    $competingManager = $this->generationManager($competitor, $this->workManager($competitor));

    $cleanup = $database->startTransaction();
    try {
      $result = $manager->discardPendingBatch(1);
      self::assertSame($generationId, $result['generation_id']);
      self::assertSame(1, $result['entries_removed']);
      self::assertTrue($result['complete']);
      $this->assertLockWaitTimeout(static fn () => $competingManager->discardPendingBatch(1));
    }
    finally {
      unset($cleanup);
    }

    $next = $competingManager->discardPendingBatch(1);
    self::assertNull($next['generation_id'], 'The competing worker finds no pending generation after the final deletion commits.');
    self::assertSame(0, $next['entries_removed']);
    self::assertTrue($next['complete']);
    self::assertSame(0, (int) $competitor->select('aculta_index_entry')
      ->condition('generation_id', $generationId)
      ->countQuery()->execute()->fetchField());
  }

  /**
   * Opens a second physical session with the Kernel's isolated table prefix.
   */
  private function openCompetitor(): Connection {
    $database = $this->container->get('database');
    Database::addConnectionInfo(self::COMPETITOR_KEY, 'default', $database->getConnectionOptions());
    $competitor = Database::getConnection('default', self::COMPETITOR_KEY);
    self::assertNotSame(
      (int) $database->query('SELECT CONNECTION_ID()')->fetchField(),
      (int) $competitor->query('SELECT CONNECTION_ID()')->fetchField(),
    );
    $competitor->query('SET SESSION innodb_lock_wait_timeout = 1');
    return $competitor;
  }

  /**
   * Builds the production work manager against a specific database session.
   */
  private function workManager(Connection $database): ContentIndexWorkManager {
    return new ContentIndexWorkManager(
      $database,
      $this->container->get('queue'),
      $this->container->get('datetime.time'),
      $this->container->get('aculta_index.provider_registry'),
      $this->container->get('logger.channel.aculta_index'),
    );
  }

  /**
   * Builds the production generation manager against the same session.
   */
  private function generationManager(Connection $database, ContentIndexWorkManager $work): ContentIndexGenerationManager {
    return new ContentIndexGenerationManager(
      $database,
      $this->container->get('uuid'),
      $this->container->get('datetime.time'),
      $work,
      $this->container->get('aculta_index.provider_registry'),
    );
  }

  /**
   * Prepares an empty generation with completed backfill for lock tests.
   */
  private function validatedGeneration(ContentIndexGenerationManager $manager): string {
    $id = $manager->start();
    $manager->markBackfillCompleted($id, 0);
    $manager->markValidated($id);
    return $id;
  }

  /**
   * Confirms that a competing statement waits for the held InnoDB row lock.
   */
  private function assertLockWaitTimeout(callable $operation): void {
    try {
      $operation();
      self::fail('The competing operation must wait for the uncommitted lifecycle change.');
    }
    catch (DatabaseExceptionWrapper $exception) {
      $cause = $exception->getPrevious();
      self::assertInstanceOf(\PDOException::class, $cause);
      self::assertSame(1205, (int) ($cause->errorInfo[1] ?? 0), 'Only an InnoDB lock wait timeout proves the expected contention.');
    }
  }

}
