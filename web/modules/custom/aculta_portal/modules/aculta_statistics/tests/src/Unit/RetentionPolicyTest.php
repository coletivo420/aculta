<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Retention\RetentionPolicy;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Prazo de retenção por métrica.
 */
#[CoversClass(RetentionPolicy::class)]
#[Group('aculta_statistics')]
final class RetentionPolicyTest extends TestCase {

  /**
   * Prazo salvo prevalece sobre o padrão do catálogo.
   */
  public function testSavedValueOverridesCatalogDefault(): void {
    $policy = $this->policy(['wiki.entries.read' => 30]);

    $this->assertSame(30, $policy->daysFor('wiki.entries.read'));
  }

  /**
   * Sem valor salvo, usa o padrão do catálogo.
   */
  public function testMissingValueFallsBackToCatalog(): void {
    $policy = $this->policy([]);

    $this->assertSame(90, $policy->daysFor('wiki.entries.read'));
  }

  /**
   * Indefinido não tem linha de corte.
   */
  public function testIndefiniteHasNoCutoff(): void {
    $this->assertNull($this->policy([])->cutoffDay(RetentionPolicy::INDEFINITE, 20000));
  }

  /**
   * O corte é o dia atual menos o prazo, exclusivo para linhas mais antigas.
   */
  public function testCutoffSubtractsRetentionDays(): void {
    $this->assertSame(19910, $this->policy([])->cutoffDay(90, 20000));
  }

  /**
   * Só as opções do painel são aceitas.
   */
  public function testOnlyPanelOptionsAreValid(): void {
    $policy = $this->policy([]);

    $this->assertTrue($policy->isValidOption(30));
    $this->assertTrue($policy->isValidOption(RetentionPolicy::INDEFINITE));
    $this->assertFalse($policy->isValidOption(45));
  }

  /**
   * Cria a política com a configuração informada.
   *
   * @param array<string, int> $retention
   *   Prazos salvos.
   */
  private function policy(array $retention): RetentionPolicy {
    $rows = [];
    foreach ($retention as $metric => $days) {
      $rows[] = ['metric' => $metric, 'days' => $days];
    }
    $config = $this->createMock(Config::class);
    $config->method('get')->with('retention')->willReturn($rows);

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_statistics.settings')->willReturn($config);

    return new RetentionPolicy($factory, new MetricCatalog());
  }

}
