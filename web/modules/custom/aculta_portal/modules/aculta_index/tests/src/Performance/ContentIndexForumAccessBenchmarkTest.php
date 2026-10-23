<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Performance;

use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\node\Entity\Node;
use Drupal\Tests\aculta_index\Kernel\ForumAccessKernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Measures Forum Entity Access with real Group grants and canonical Domain.
 *
 * @group aculta_index
 * @group performance
 */
#[Group('aculta_index')]
#[Group('performance')]
#[RunTestsInSeparateProcesses]
final class ContentIndexForumAccessBenchmarkTest extends ForumAccessKernelTestBase {

  /**
   * Measures authorized and denied Forum pages at fixed candidate sizes.
   *
   * Set ACULTA_INDEX_FORUM_ACCESS_BENCHMARK=1 to run this opt-in measurement.
   */
  public function testForumGrantsAndDomainAccessAtProgressivePageSizes(): void {
    if (getenv('ACULTA_INDEX_FORUM_ACCESS_BENCHMARK') !== '1') {
      self::markTestSkipped('Set ACULTA_INDEX_FORUM_ACCESS_BENCHMARK=1 to measure Forum Entity Access.');
    }

    $member = $this->createUser('forum-access-benchmark-member');
    $this->setAssociateActive($member);
    $outsider = $this->createUser('forum-access-benchmark-outsider');
    $group = $this->container->get('entity_type.manager')->getStorage('group')->create([
      'type' => self::GROUP_TYPE,
      'label' => 'Forum access benchmark group',
      'uid' => $member->id(),
    ]);
    $group->save();
    $group->addMember($member);

    $candidates = [];
    for ($index = 0; $index < 100; $index++) {
      $topic = Node::create([
        'type' => 'aculta_forum_topic',
        'title' => 'Benchmark restricted topic ' . $index,
        'status' => TRUE,
        'uid' => $member->id(),
        'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      ]);
      $topic->save();
      $group->addRelationship($topic, 'group_node:aculta_forum_topic')->save();
      // Persist the production grant record generated from the relationship.
      $topic->save();
      $candidates[] = [
        'entity_type' => 'node',
        'entity_id' => (string) $topic->id(),
        'langcode' => $topic->language()->getId(),
      ];
    }

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    $filter = new ContentIndexAccessFilter(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_index.provider_registry'),
      $this->container->get('aculta_portal.domain_purpose'),
    );
    $outputDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $outputFile = getenv('ACULTA_INDEX_FORUM_ACCESS_BENCHMARK_OUTPUT')
      ?: $outputDirectory . 'aculta-index-forum-access-' . getmypid() . '.json';
    if (!str_starts_with($outputFile, $outputDirectory)) {
      throw new \RuntimeException('The Forum access benchmark output must be inside sys_get_temp_dir().');
    }

    $pageResults = [];
    foreach ([1, 12, 25, 50, 100] as $pageSize) {
      $page = array_slice($candidates, 0, $pageSize);
      $pageResults[] = [
        'page_size' => $pageSize,
        'member' => $this->measureAccessPage($filter, $page, $member, $pageSize),
        'outsider' => $this->measureAccessPage($filter, $page, $outsider, 0),
      ];
    }

    $payload = [
      'benchmark' => 'forum_group_grants_and_domain_entity_access',
      'date' => gmdate('Y-m-d'),
      'drupal' => \Drupal::VERSION,
      'php' => PHP_VERSION,
      'database' => $this->container->get('database')->version(),
      'warmup_runs_per_account_page' => 5,
      'measured_runs_per_account_page' => 30,
      'page_sizes' => $pageResults,
      'scope' => 'Production Forum grant hooks, real Group membership, Forum provider purpose and ContentIndexAccessFilter; excludes catalog candidate SQL.',
      'slo' => FALSE,
    ];
    if (file_put_contents($outputFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX) === FALSE) {
      throw new \RuntimeException('Could not write the Forum access benchmark result.');
    }

    self::assertFileExists($outputFile);
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose());
  }

  /**
   * Measures Entity Access for one candidate page and checks its result.
   *
   * @param \Drupal\aculta_index\Query\ContentIndexAccessFilter $filter
   *   Filter under measurement.
   * @param list<array{entity_type: string, entity_id: string, langcode: string}> $candidates
   *   Candidate identities to authorize.
   * @param \Drupal\user\Entity\User $account
   *   User whose access is evaluated.
   * @param int $expectedAuthorized
   *   Expected number of accessible topics.
   *
   * @return array{authorized_per_run: int, p50_ms: float, p95_ms: float, samples_ms: list<float>}
   *   Measured outcome, percentiles and individual latencies.
   */
  private function measureAccessPage(ContentIndexAccessFilter $filter, array $candidates, User $account, int $expectedAuthorized): array {
    for ($warmup = 0; $warmup < 5; $warmup++) {
      $filter->filter($candidates, $account);
    }
    $samples = [];
    $authorizedPerRun = 0;
    for ($sample = 0; $sample < 30; $sample++) {
      $started = hrtime(TRUE);
      $authorizedPerRun = count($filter->filter($candidates, $account)->entities);
      $samples[] = (hrtime(TRUE) - $started) / 1_000_000;
    }
    $rawSamples = array_map(static fn (float $sample): float => round($sample, 3), $samples);
    sort($samples, SORT_NUMERIC);
    self::assertSame($expectedAuthorized, $authorizedPerRun);
    return [
      'authorized_per_run' => $authorizedPerRun,
      'p50_ms' => round($samples[(int) ceil((count($samples) - 1) * 0.50)], 3),
      'p95_ms' => round($samples[(int) ceil((count($samples) - 1) * 0.95)], 3),
      'samples_ms' => $rawSamples,
    ];
  }

}
