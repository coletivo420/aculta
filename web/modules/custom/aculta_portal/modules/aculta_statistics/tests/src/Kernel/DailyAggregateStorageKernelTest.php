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
   * Totais por dimensão somam o período, ordenam e isolam a métrica.
   */
  public function testDimensionTotalsSumThePeriodAndOrderByTotal(): void {
    $storage = $this->storage();
    $this->insertRow('wiki.entries.read', '10', 100, 3);
    $this->insertRow('wiki.entries.read', '10', 102, 2);
    $this->insertRow('wiki.entries.read', '11', 101, 4);
    $this->insertRow('wiki.entries.read', '12', 100, 1);
    $this->insertRow('wiki.entries.read', '13', 100, 3);
    $this->insertRow('wiki.entries.read', '11', 99, 50);
    $this->insertRow('links.link.clicks', '10', 100, 7);

    // Fora do período: o dia 99 não entra.
    $this->assertSame([10 => 5, 11 => 4, 13 => 3, 12 => 1], $storage->dimensionTotals('wiki.entries.read', 100, 102));
    // Empate desempata pela dimensão.
    $this->assertSame([10 => 3, 13 => 3, 12 => 1], $storage->dimensionTotals('wiki.entries.read', 100, 100));
    $this->assertSame([], $storage->dimensionTotals('wiki.entries.read', 200, 210));
  }

  /**
   * Totals for a supplied entity page query only those dimensions in SQL.
   */
  public function testDimensionTotalsCanBeRestrictedToSelectedDimensions(): void {
    $storage = $this->storage();
    $this->insertRow('wiki.entries.read', '10', 100, 3);
    $this->insertRow('wiki.entries.read', '10', 102, 2);
    $this->insertRow('wiki.entries.read', '11', 101, 4);
    $this->insertRow('wiki.entries.read', '12', 100, 1);
    $this->insertRow('wiki.entries.read', '11', 99, 50);
    $this->insertRow('links.link.clicks', '10', 100, 7);

    $this->assertSame(
      [10 => 5, 11 => 4],
      $storage->dimensionTotalsForDimensions('wiki.entries.read', 100, 102, ['10', 11, '13']),
    );
    $this->assertSame([], $storage->dimensionTotalsForDimensions('wiki.entries.read', 100, 102, []));
  }

  /**
   * The selected-dimension aggregate has a fixed parameter bound.
   */
  public function testDimensionTotalsRejectMoreThanOneHundredDimensions(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->storage()->dimensionTotalsForDimensions('wiki.entries.read', 100, 102, range(1, 101));
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
   * Substituir um dia troca os totais, zera dimensões ausentes e não toca outros dias.
   */
  public function testReplaceDaySubstitutesOnlyThatDay(): void {
    $storage = $this->storage();
    $this->insertRow('email.messages.accepted', 'smtp', 100, 9);
    $this->insertRow('email.messages.accepted', 'brevo', 100, 4);
    $this->insertRow('email.messages.accepted', 'smtp', 101, 7);

    $storage->replaceDay('email.messages.accepted', 100, ['smtp' => 2, 'brevo' => 0]);

    $this->assertSame(2, $storage->total('email.messages.accepted', 100, 100, 'smtp'));
    $this->assertSame(0, $storage->total('email.messages.accepted', 100, 100, 'brevo'));
    $this->assertSame(7, $storage->total('email.messages.accepted', 101, 101));
  }

  /**
   * Substituir com totais vazios apaga o dia (uma conciliação que zera o contador).
   */
  public function testReplaceDayWithEmptyTotalsClearsDay(): void {
    $storage = $this->storage();
    $this->insertRow('email.messages.failed', 'smtp|timeout', 100, 3);

    $storage->replaceDay('email.messages.failed', 100, []);

    $this->assertSame([], $storage->dimensions('email.messages.failed', 100));
  }

  /**
   * Apagar um aluno remove as linhas dele em todos os dias, e só as dele.
   */
  public function testPurgeStudentRemovesOnlyThatStudent(): void {
    $storage = $this->storage();
    $this->insertRow('lms.student.status', '5/42|passed', 100, 1);
    $this->insertRow('lms.student.status', '6/42|failed', 101, 1);
    $this->insertRow('lms.student.status', '5/4|passed', 100, 1);
    $this->insertRow('lms.student.status', '5/142|passed', 100, 1);

    $this->assertSame(2, $storage->purgeStudent('lms.student.status', 42));
    $this->assertSame(['5/142|passed', '5/4|passed'], $storage->dimensions('lms.student.status', 100));
    $this->assertSame(0, $storage->total('lms.student.status', 0, 1000, '5/42|passed'));
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
