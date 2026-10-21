<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Agregados diários gravados, somados, contados e apagados no banco.
 */
#[CoversClass(DailyAggregateStorage::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class DailyAggregateStorageKernelTest extends KernelTestBase {

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
  }

  /**
   * Gravações do mesmo dia e dimensão somam; dimensões separadas não se misturam.
   */
  public function testRecordsSumPerDimension(): void {
    $storage = $this->storage();
    $storage->record('wiki.entries.read', '10');
    $storage->record('wiki.entries.read', '10', 2);
    $storage->record('wiki.entries.read', '11');

    $today = $storage->currentDay();
    $this->assertSame(3, $storage->total('wiki.entries.read', $today, $today, '10'));
    $this->assertSame(4, $storage->total('wiki.entries.read', $today, $today));
  }

  /**
   * Dimensões do dia são devolvidas distintas e ordenadas, só da métrica pedida.
   */
  public function testDimensionsListsDistinctValuesForMetricAndDay(): void {
    $storage = $this->storage();
    $storage->record('commerce.orders.paid', 'BRL');
    $storage->record('commerce.orders.paid', 'BRL');
    $storage->record('commerce.orders.paid', 'USD');
    $storage->record('wiki.entries.read', 'EUR');

    $this->assertSame(['BRL', 'USD'], $storage->dimensions('commerce.orders.paid', $storage->currentDay()));
  }

  /**
   * Identificador fora do formato é recusado.
   */
  public function testRejectsInvalidMetric(): void {
    $storage = $this->storage();

    $this->expectException(\InvalidArgumentException::class);
    $storage->record('Wiki Read', '10');
  }

  /**
   * Dimensão com e-mail é recusada.
   */
  public function testRejectsEmailDimension(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->storage()->record('wiki.entries.read', 'pessoa@example.org');
  }

  /**
   * Purge remove só a métrica pedida e só os dias anteriores ao corte.
   */
  public function testPurgeIsScopedByMetricAndDay(): void {
    $storage = $this->storage();
    $this->insertRow('wiki.entries.read', '10', 100, 5);
    $this->insertRow('wiki.entries.read', '10', 200, 5);
    $this->insertRow('links.link.clicks', '10', 100, 5);

    $this->assertSame(1, $storage->countBefore('wiki.entries.read', 150));
    $this->assertSame(1, $storage->purgeMetricBefore('wiki.entries.read', 150));
    $this->assertSame(5, $storage->total('wiki.entries.read', 0, 1000));
    $this->assertSame(5, $storage->total('links.link.clicks', 0, 1000));
  }

  /**
   * Cria o storage com o dia atual do ambiente de teste.
   */
  private function storage(): DailyAggregateStorage {
    return new DailyAggregateStorage(
      $this->container->get('database'),
      $this->container->get('datetime.time'),
    );
  }

  /**
   * Insere uma linha diretamente, para simular dias antigos.
   */
  private function insertRow(string $metric, string $dimension, int $day, int $total): void {
    $this->container->get('database')->insert(DailyAggregateStorage::TABLE)
      ->fields(['metric' => $metric, 'dimension' => $dimension, 'day' => $day, 'total' => $total, 'updated' => 0])
      ->execute();
  }

}
