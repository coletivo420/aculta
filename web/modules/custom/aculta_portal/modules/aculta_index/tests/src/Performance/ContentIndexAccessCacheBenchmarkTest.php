<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Performance;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
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

/**
 * Measures Index Entity Access checks with canonical Domain switching.
 *
 * @group aculta_index
 * @group performance
 */
#[Group('aculta_index')]
#[Group('performance')]
#[RunTestsInSeparateProcesses]
final class ContentIndexAccessCacheBenchmarkTest extends KernelTestBase {

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
  }

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    $databaseUrl = getenv('SIMPLETEST_DB') ?: '';
    $databaseParts = parse_url($databaseUrl);
    if (!is_array($databaseParts)
      || ($databaseParts['host'] ?? '') !== 'localhost'
      || trim((string) ($databaseParts['path'] ?? ''), '/') !== 'aculta_simpletest') {
      throw new \RuntimeException('The Content Index access benchmark requires the dedicated localhost aculta_simpletest database.');
    }

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

    $authenticated = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->save();
    User::create([
      'name' => 'index-access-benchmark-bootstrap',
      'mail' => 'index-access-benchmark-bootstrap@example.invalid',
      'status' => 1,
    ])->save();
    $account = User::create([
      'name' => 'index-access-benchmark-reader',
      'mail' => 'index-access-benchmark-reader@example.invalid',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $account->save();

    for ($index = 0; $index < 100; $index++) {
      $entry = Node::create([
        'type' => 'wiki_entry',
        'title' => 'Benchmark Wiki entry ' . $index,
        'status' => $index % 2 === 0,
        'uid' => $account->id(),
        'field_domain_source' => ['target_id' => 'wiki420_aculta_org'],
      ]);
      $entry->save();
    }

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    $this->container->get('state')->set('aculta_index.access_benchmark_account', (int) $account->id());
  }

  /**
   * Measures fixed-size pages after warmup, without implying an SLO. */
  public function testCanonicalDomainAccessFilterAtProgressivePageSizes(): void {
    if (getenv('ACULTA_INDEX_ACCESS_BENCHMARK') !== '1') {
      self::markTestSkipped('Set ACULTA_INDEX_ACCESS_BENCHMARK=1 to measure Domain-aware Entity Access pages.');
    }

    $temporaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $outputFile = getenv('ACULTA_INDEX_ACCESS_BENCHMARK_OUTPUT')
      ?: $temporaryDirectory . 'aculta-index-access-benchmark-' . getmypid() . '.json';
    if (!str_starts_with($outputFile, $temporaryDirectory)) {
      throw new \RuntimeException('The Content Index access benchmark output must be inside sys_get_temp_dir().');
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'wiki_entry')
      ->sort('nid')
      ->range(0, 100)
      ->execute();
    self::assertCount(100, $ids);
    $entities = $storage->loadMultiple($ids);
    $candidates = [];
    foreach ($entities as $entity) {
      $candidates[] = [
        'entity_type' => 'node',
        'entity_id' => (string) $entity->id(),
        'langcode' => $entity->language()->getId(),
      ];
    }
    $account = User::load((int) $this->container->get('state')->get('aculta_index.access_benchmark_account'));
    self::assertInstanceOf(User::class, $account);
    $filter = $this->container->get('aculta_index.access_filter');
    $contentEntityHandlerCount = 0;
    foreach ($this->container->get('entity_type.manager')->getDefinitions() as $entityType) {
      $contentEntityHandlerCount += (int) $entityType->entityClassImplements(ContentEntityInterface::class);
    }

    $results = [];
    foreach ([1, 12, 25, 50, 100] as $pageSize) {
      $page = array_slice($candidates, 0, $pageSize);
      for ($warmup = 0; $warmup < 5; $warmup++) {
        $filter->filter($page, $account);
      }
      $samples = [];
      $authorizedPerRun = NULL;
      for ($sample = 0; $sample < 30; $sample++) {
        $started = hrtime(TRUE);
        $authorized = $filter->filter($page, $account)->entities;
        $samples[] = (hrtime(TRUE) - $started) / 1_000_000;
        $authorizedPerRun = count($authorized);
      }
      sort($samples, SORT_NUMERIC);
      $results[] = [
        'page_size' => $pageSize,
        'samples' => count($samples),
        'authorized_per_run' => $authorizedPerRun,
        'p50_ms' => round($this->percentile($samples, 0.50), 3),
        'p95_ms' => round($this->percentile($samples, 0.95), 3),
      ];
    }

    $payload = [
      'benchmark' => 'canonical_domain_entity_access_filter',
      'date' => gmdate('Y-m-d'),
      'drupal' => \Drupal::VERSION,
      'php' => PHP_VERSION,
      'database' => $this->container->get('database')->version(),
      'content_entity_access_handlers_reset_per_candidate' => $contentEntityHandlerCount,
      'warmup_runs_per_page_size' => 5,
      'measured_runs_per_page_size' => 30,
      'page_sizes' => $results,
      'scope' => 'Production Wiki provider, Portal EntityHooks, Domain entities and ContentIndexAccessFilter; excludes catalog candidate SQL.',
      'slo' => FALSE,
    ];
    if (file_put_contents($outputFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX) === FALSE) {
      throw new \RuntimeException('Could not write the Content Index access benchmark result.');
    }

    self::assertFileExists($outputFile);
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

  /**
   * Calculates a nearest-rank percentile.
   *
   * @param list<float> $samples
   *   Sorted elapsed-time samples in milliseconds.
   * @param float $fraction
   *   Percentile fraction between zero and one.
   */
  private function percentile(array $samples, float $fraction): float {
    return $samples[(int) ceil((count($samples) - 1) * $fraction)];
  }

}
