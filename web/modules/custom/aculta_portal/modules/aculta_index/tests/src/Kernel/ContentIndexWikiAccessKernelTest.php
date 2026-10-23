<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_portal\Hook\EntityHooks;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
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
 * Verifies Index filtering under the Portal's production Wiki access policy.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexWikiAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'domain',
    'aculta_index',
    'aculta_index_wiki_access_test',
  ];

  /**
   * {@inheritdoc} */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setDefinition('aculta_portal.domain_purpose', (new Definition(DomainPurposeManager::class, [
      new Reference('entity_type.manager'),
      new Reference('domain.negotiator'),
      new Reference('config.factory'),
      new Reference('request_stack'),
    ]))->setPublic(TRUE));

    // Register the provider declaration from the owner YAML without enabling
    // the unfinished Wiki module or its unrelated service dependency chain.
    $ownerServices = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_wiki/aculta_wiki.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $service = $ownerServices['aculta_wiki.content_index_provider'];
    $definition = (new Definition($service['class'], $service['arguments'] ?? []))->setPublic(TRUE);
    foreach ($service['tags'] as $tag) {
      $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_wiki.content_index_provider', $definition);
  }

  /**
   * {@inheritdoc} */
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
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Wiki entry'])->save();
    $this->installDomainField();
    $this->createDomains();
    $this->installRelatedEntryField();
    $authenticated = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->save();
  }

  /**
   * Canonical Wiki access works from another active Domain and restores it. */
  public function testFilterUsesWikiEntityAccessInCanonicalDomain(): void {
    // Keep the reader away from Drupal's uid 1 superuser shortcut.
    User::create([
      'name' => 'wiki-index-first-user',
      'mail' => 'wiki-index-first-user@example.invalid',
      'status' => 1,
    ])->save();
    $reader = User::create([
      'name' => 'wiki-index-reader',
      'mail' => 'wiki-index-reader@example.invalid',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $reader->save();
    $entry = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Canonical Wiki entry',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $entry->save();

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $domainNegotiator = $this->container->get(DomainNegotiatorInterface::class);
    $domainNegotiator->getActiveDomain();
    $domainNegotiator->setActiveDomain($mainDomain);
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
    self::assertTrue(
      $this->container->get(EntityHooks::class)
        ->entityAccess($entry, 'view', $reader)?->isForbidden(),
      'The production Portal access policy forbids Wiki entities outside its purpose.',
    );
    $hookResults = $this->container->get('module_handler')
      ->invokeAll('entity_access', [$entry, 'view', $reader]);
    $hasPolicyDenial = FALSE;
    foreach ($hookResults as $hookResult) {
      if ($hookResult instanceof AccessResultInterface && $hookResult->isForbidden()) {
        $hasPolicyDenial = TRUE;
        break;
      }
    }
    self::assertTrue(
      $hasPolicyDenial,
      'Drupal invokes the fixture bridge to the production Portal entity access policy.',
    );

    self::assertFalse($entry->access('view', $reader), 'The Portal policy blocks Wiki access in the main purpose.');
    $filter = $this->container->get('aculta_index.access_filter');
    $result = $filter->filter([[
      'entity_type' => 'node',
      'entity_id' => (string) $entry->id(),
      'langcode' => $entry->language()->getId(),
    ],
    ], $reader);

    self::assertCount(1, $result->entities, 'The Index rechecks the same entity under its canonical Wiki Domain.');
    self::assertSame('aculta_org', $domainNegotiator->getActiveDomain()?->id(), 'The original Domain is restored.');
    self::assertFalse($entry->access('view', $reader), 'The canonical allowed result is not reused after restoring the main Domain.');
  }

  /**
   * Checks relation source and targets in the canonical Wiki Domain.
   */
  public function testRelatedEntriesRevalidateSourceAndTargetsInWikiDomain(): void {
    User::create(['name' => 'wiki-relation-root', 'status' => TRUE])->save();
    $reader = User::create([
      'name' => 'wiki-relation-reader',
      'status' => TRUE,
      'roles' => ['authenticated'],
    ]);
    $reader->save();

    $publicTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Public related entry',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $publicTarget->save();
    $privateTarget = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Private related entry',
      'status' => FALSE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
    ]);
    $privateTarget->save();
    $source = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Related entries source',
      'status' => TRUE,
      'uid' => $reader->id(),
      'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      'field_wiki_related' => [
        ['target_id' => $publicTarget->id()],
        ['target_id' => $privateTarget->id()],
      ],
    ]);
    $source->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 20)['complete'];
    }
    self::assertTrue($complete, 'The Wiki provider backfill should complete.');
    $this->processWikiWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    self::assertFalse($source->access('view', $reader), 'Wiki source is not visible from main purpose.');

    $result = $this->container->get('aculta_index.relation_query')->queryOutgoing(
      'node',
      (string) $source->id(),
      $source->language()->getId(),
      'related_entry',
      $reader,
    );
    self::assertCount(1, $result->entities, 'Only the currently authorized Wiki target is returned.');
    self::assertSame('Public related entry', (string) $result->entities[0]->label());
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id(), 'The initiating Domain is restored.');
    self::assertFalse($source->access('view', $reader), 'The relation query does not leak cached Wiki access into main purpose.');
    self::assertSame(0, $result->cacheability->getCacheMaxAge());
  }

  /**
   * Processes incremental Wiki work after the backfill.
   */
  private function processWikiWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('provider_id', 'wiki')
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
   * Installs the Wiki entry's Domain reference field. */
  private function installDomainField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
      'settings' => ['handler' => 'default:domain', 'handler_settings' => []],
    ])->save();
  }

  /**
   * Installs the outgoing related-entry field used by the Wiki provider.
   */
  private function installRelatedEntryField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wiki_related',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => 'field_wiki_related',
      'label' => 'Related Wiki entries',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['wiki_entry' => 'wiki_entry']],
      ],
    ])->save();
  }

  /**
   * Creates main and canonical Wiki Domains. */
  private function createDomains(): void {
    foreach (['aculta_org' => 'main', 'wiki420_aculta_org' => 'wiki'] as $id => $name) {
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
