<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
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

    // Load the exact owner service declaration without enabling Magazine's
    // full dependency chain in this access-focused Kernel harness.
    $serviceMap = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_magazine/aculta_magazine.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $service = $serviceMap['aculta_magazine.content_index_provider'];
    $definition = (new Definition($service['class'], $service['arguments'] ?? []))->setPublic(TRUE);
    foreach ($service['tags'] as $tag) {
      $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_magazine.content_index_provider', $definition);
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
    $this->installDomainField();
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
