<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies Activity provider discovery and projection in the real container.
 */
#[Group('aculta_activity')]
#[RunTestsInSeparateProcesses]
final class ActivityContentIndexProviderKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Domain IDs activated during the current access check.
   *
   * @var list<string>
   */
  private array $domainSwitches = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'domain',
    'aculta_index',
    'aculta_activity',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturnCallback(static fn (string $id): ?Domain => Domain::load($id));
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($domainStorage);
    $domainNegotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domainNegotiator->method('getActiveDomain')->willReturnCallback(
      static fn (bool $reset = FALSE): ?DomainInterface => \Drupal::service('domain.negotiator')->getActiveDomain($reset),
    );
    $domainNegotiator->method('setActiveDomain')->willReturnCallback(
      function (DomainInterface $domain): void {
        \Drupal::service('domain.negotiator')->setActiveDomain($domain);
        $this->domainSwitches[] = $domain->id();
      },
    );
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $entityTypeManager,
      $domainNegotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('domain');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['user', 'node', 'system', 'domain']);
    aculta_index_install();
    foreach (['aculta_org' => 'main', 'cursos_aculta_org' => 'courses'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'weight' => 0,
        'status' => TRUE,
      ])->save();
    }
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain(Domain::load('cursos_aculta_org'));
    $this->domainSwitches = [];
    NodeType::create(['type' => 'activity', 'name' => 'Activity'])->save();
  }

  /**
   * Tests the tagged provider is discovered and projects an Activity node.
   */
  public function testTaggedProviderProjectsActivityNode(): void {
    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertTrue($registry->has('activity'));
    $provider = $registry->get('activity');
    self::assertInstanceOf(ContentIndexProviderInterface::class, $provider);

    $node = Node::create([
      'type' => 'activity',
      'title' => 'Encontro de teste',
      'status' => TRUE,
      'created' => 100,
      'changed' => 200,
    ]);
    $node->save();
    $work = $this->container->get('aculta_index.work_manager')
      ->getStatus('activity', 'node', (string) $node->id(), 'en');
    self::assertSame('project', $work['operation']);

    $projection = $provider->project($node);
    self::assertNotNull($projection);
    self::assertSame('node', $projection->entityTypeId);
    self::assertSame('activity', $projection->bundle);
    self::assertSame((string) $node->id(), $projection->entityId);
    self::assertSame('en', $projection->langcode);
    self::assertSame('Encontro de teste', $projection->label);
    self::assertSame([], $projection->relations);
    self::assertSame('main', $provider->getAccessPurpose($node));
  }

  /**
   * Tests backfill, incremental update and deletion through the real worker.
   */
  public function testActivityProjectionFollowsEntityCrud(): void {
    $activity = Node::create([
      'type' => 'activity',
      'title' => 'Atividade inicial',
      'status' => TRUE,
      'created' => 100,
      'changed' => 200,
    ]);
    $activity->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The Activity provider backfill should complete.');
    $this->processActivityWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    self::assertSame('Atividade inicial', $this->entryLabel($generationId, (string) $activity->id()));

    $activity->setTitle('Atividade atualizada');
    $activity->save();
    $this->processActivityWork();
    self::assertSame('Atividade atualizada', $this->entryLabel($generationId, (string) $activity->id()));

    $activity->delete();
    $this->processActivityWork();
    self::assertSame(0, (int) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'activity')
      ->condition('entity_id', (string) $activity->id())
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Rechecks Activity access in the canonical main Domain and restores caller.
   */
  public function testIndexFilterUsesMainDomainAndFailsClosedWhenMissing(): void {
    User::create([
      'name' => 'activity-index-root-fixture',
      'mail' => 'activity-index-root@example.invalid',
      'status' => 1,
    ])->save();
    $reader = $this->createUser();
    $authenticated = Role::load(Role::AUTHENTICATED_ID);
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->save();
    $activity = Node::create([
      'type' => 'activity',
      'title' => 'Atividade de acesso contextual',
      'status' => TRUE,
    ]);
    $activity->save();
    $candidate = [[
      'entity_type' => 'node',
      'entity_id' => (string) $activity->id(),
      'langcode' => $activity->language()->getId(),
    ],
    ];
    $filter = $this->container->get('aculta_index.access_filter');
    self::assertInstanceOf(ContentIndexAccessFilter::class, $filter);

    $allowed = $filter->filter($candidate, $reader);
    self::assertCount(1, $allowed->entities, 'Published Activity remains visible under its owner purpose.');
    self::assertSame(['aculta_org', 'cursos_aculta_org'], $this->domainSwitches);
    self::assertSame('courses', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());

    $activity->setUnpublished()->save();
    $this->domainSwitches = [];
    self::assertCount(0, $filter->filter($candidate, $reader)->entities, 'The current unpublished state is revalidated.');
    self::assertSame(['aculta_org', 'cursos_aculta_org'], $this->domainSwitches);

    Domain::load('aculta_org')->delete();
    $this->domainSwitches = [];
    self::assertCount(0, $filter->filter($candidate, $reader)->entities, 'A missing canonical purpose denies exposure.');
    self::assertSame([], $this->domainSwitches, 'The negotiator is not switched when the owner Domain is missing.');
  }

  /**
   * Processes queued work belonging to the Activity provider.
   */
  private function processActivityWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'activity')
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

  /**
   * Returns the label of one Activity entry in a generation.
   */
  private function entryLabel(string $generationId, string $entityId): string {
    return (string) $this->container->get('database')->select('aculta_index_entry', 'entry')
      ->fields('entry', ['label'])
      ->condition('generation_id', $generationId)
      ->condition('provider_id', 'activity')
      ->condition('entity_id', $entityId)
      ->execute()
      ->fetchField();
  }

}
