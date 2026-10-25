<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\domain\Entity\Domain;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies Magazine provider access in the canonical Domain through the Index.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexMagazineAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'domain',
    'aculta_index',
    'aculta_index_magazine_access_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setDefinition('aculta_portal.domain_purpose', (new Definition(DomainPurposeManager::class, [
      new Reference('entity_type.manager'),
      new Reference('domain.negotiator'),
      new Reference('config.factory'),
      new Reference('request_stack'),
    ]))->setPublic(TRUE));

    // Compile exact owner declarations while keeping their full dependency
    // chains out of this access-focused Kernel harness.
    foreach ([
      'aculta_magazine' => 'aculta_magazine.content_index_provider',
      'aculta_activity' => 'aculta_activity.content_index_provider',
    ] as $owner => $serviceId) {
      $serviceMap = Yaml::parseFile(
        DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/' . $owner . '/' . $owner . '.services.yml',
        Yaml::PARSE_CUSTOM_TAGS,
      )['services'];
      $service = $serviceMap[$serviceId];
      $definition = (new Definition($service['class'], $service['arguments'] ?? []))->setPublic(TRUE);
      foreach ($service['tags'] as $tag) {
        $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
      }
      $container->setDefinition($serviceId, $definition);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
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
    $this->installConfig(['system', 'user', 'node', 'domain']);
    aculta_index_install();
    NodeType::create(['type' => 'revista', 'name' => 'Revista'])->save();
    NodeType::create(['type' => 'activity', 'name' => 'Activity'])->save();
    $this->installDomainField();
    $this->installRelatedActivityField();
    $this->createDomains();

    $authenticated = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->save();
  }

  /**
   * Tests publication, draft and missing canonical Domain access.
   *
   * Published access is current, drafts stay hidden and missing Domain
   * fails closed.
   */
  public function testFilterRevalidatesMagazineEntityAccessAndRestoresDomain(): void {
    // Reserve UID 1 so fixture readers exercise ordinary Entity Access.
    User::create(['name' => 'magazine-index-root-fixture', 'status' => TRUE])->save();
    $reader = User::create([
      'name' => 'magazine-index-reader',
      'status' => TRUE,
      'roles' => ['authenticated'],
    ]);
    $reader->save();

    $published = Node::create([
      'type' => 'revista',
      'title' => 'Published Magazine item',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'revista420_aculta_org'],
    ]);
    $published->save();
    $draft = Node::create([
      'type' => 'revista',
      'title' => 'Private Magazine draft',
      'status' => FALSE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'revista420_aculta_org'],
    ]);
    $draft->save();

    $providers = $this->container->get('aculta_index.provider_registry');
    self::assertSame('magazine', $providers->findProviderIdForBundle('node', 'revista'));
    self::assertSame('magazine', $providers->get('magazine')->getAccessPurpose($published));
    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);

    $candidates = [
      [
        'entity_type' => 'node',
        'entity_id' => (string) $published->id(),
        'langcode' => $published->language()->getId(),
      ],
      [
        'entity_type' => 'node',
        'entity_id' => (string) $draft->id(),
        'langcode' => $draft->language()->getId(),
      ],
    ];
    $filter = $this->container->get('aculta_index.access_filter');
    $result = $filter->filter($candidates, $reader);
    self::assertCount(1, $result->entities);
    self::assertSame((string) $published->id(), (string) $result->entities[0]->id());
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());

    $magazineDomain = Domain::load('revista420_aculta_org');
    self::assertInstanceOf(Domain::class, $magazineDomain);
    $magazineDomain->delete();
    self::assertCount(0, $filter->filter([$candidates[0]], $reader)->entities);
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());
  }

  /**
   * Queries Magazine's explicit Activity relation across canonical purposes.
   */
  public function testOutgoingActivityRelationRechecksBothCanonicalPurposes(): void {
    User::create(['name' => 'magazine-relation-root-fixture', 'status' => TRUE])->save();
    $reader = User::create([
      'name' => 'magazine-relation-reader',
      'status' => TRUE,
      'roles' => ['authenticated'],
    ]);
    $reader->save();
    $activity = Node::create([
      'type' => 'activity',
      'title' => 'Public activity',
      'status' => TRUE,
      'uid' => $reader->id(),
    ]);
    $activity->save();
    $publication = Node::create([
      'type' => 'revista',
      'title' => 'Magazine item with activity',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'revista420_aculta_org'],
      'field_related_activity' => [['target_id' => $activity->id()]],
    ]);
    $publication->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'Magazine and Activity providers should backfill the generation.');
    $this->processIndexWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $providers = $this->container->get('aculta_index.provider_registry');
    self::assertSame('magazine', $providers->findProviderIdForBundle('node', 'revista'));
    self::assertSame('activity', $providers->findProviderIdForBundle('node', 'activity'));
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain(Domain::load('aculta_org'));
    $result = $this->container->get('aculta_index.relation_query')->queryOutgoing(
      'node',
      (string) $publication->id(),
      $publication->language()->getId(),
      'related_activity',
      $reader,
    );

    self::assertSame([(string) $activity->id()], array_map(
      static fn (Node $entity): string => (string) $entity->id(),
      $result->entities,
    ));
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());
    self::assertSame(0, $result->cacheability->getCacheMaxAge());
  }

  /**
   * Processes queued work through the production Content Index worker.
   */
  private function processIndexWork(): void {
    $database = $this->container->get('database');
    $workIds = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('status', 'queued')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($workIds as $workId) {
      $worker->processItem(['work_id' => (int) $workId]);
    }
  }

  /**
   * Installs the Revista Domain reference used by the production projection.
   */
  private function installDomainField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'revista',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
      'settings' => [
        'handler' => 'default:domain',
        'handler_settings' => [],
      ],
    ])->save();
  }

  /**
   * Installs the explicit Magazine-to-Activity reference field.
   */
  private function installRelatedActivityField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_related_activity',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'revista',
      'field_name' => 'field_related_activity',
      'label' => 'Related activity',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['activity' => 'activity']],
      ],
    ])->save();
  }

  /**
   * Creates the active and canonical Magazine Domains.
   */
  private function createDomains(): void {
    foreach (['aculta_org' => 'main', 'revista420_aculta_org' => 'magazine'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
        'weight' => 0,
      ])->save();
    }
  }

}
