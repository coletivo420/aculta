<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Hook\StatisticsCronHooks;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Cron aplica o prazo de cada métrica, sem afetar as demais.
 */
#[CoversClass(StatisticsCronHooks::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class RetentionCronKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_statistics'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('aculta_statistics', [DailyAggregateStorage::TABLE]);
    $this->installConfig(['aculta_statistics']);
  }

  /**
   * Grava o prazo de uma métrica na linha de configuração correspondente.
   */
  private function setRetention(string $metric, int $days): void {
    $rows = array_values(array_filter(
      $this->config('aculta_statistics.settings')->get('retention'),
      static fn (array $row): bool => $row['metric'] !== $metric,
    ));
    $rows[] = ['metric' => $metric, 'days' => $days];
    $this->config('aculta_statistics.settings')->set('retention', $rows)->save();
  }

  /**
   * Prazo de 30 dias apaga a Wiki antiga; prazo padrão de 90 mantém links com 40 dias.
   */
  public function testCronAppliesPerMetricRetention(): void {
    $this->setRetention('wiki.entries.read', 30);

    $database = $this->container->get('database');
    $storage = $this->container->get('aculta_statistics.storage');
    $today = $storage->currentDay();
    foreach (['wiki.entries.read', 'links.link.clicks'] as $metric) {
      $database->insert(DailyAggregateStorage::TABLE)
        ->fields(['metric' => $metric, 'dimension' => '1', 'day' => $today - 40, 'total' => 3, 'updated' => 0])
        ->execute();
    }

    $this->container->get(StatisticsCronHooks::class)->cron();

    $this->assertSame(0, $storage->total('wiki.entries.read', 0, $today + 1));
    $this->assertSame(3, $storage->total('links.link.clicks', 0, $today + 1));
  }

  /**
   * Indefinido (0) não apaga nada.
   */
  public function testIndefiniteKeepsRows(): void {
    $this->setRetention('wiki.entries.read', 0);

    $storage = $this->container->get('aculta_statistics.storage');
    $today = $storage->currentDay();
    $this->container->get('database')->insert(DailyAggregateStorage::TABLE)
      ->fields(['metric' => 'wiki.entries.read', 'dimension' => '1', 'day' => $today - 4000, 'total' => 7, 'updated' => 0])
      ->execute();

    $this->container->get(StatisticsCronHooks::class)->cron();

    $this->assertSame(7, $storage->total('wiki.entries.read', 0, $today + 1));
  }

}
