<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Catalog\MetricDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo de métricas e definição de cada uma.
 */
#[CoversClass(MetricCatalog::class)]
#[CoversClass(MetricDefinition::class)]
#[Group('aculta_statistics')]
final class MetricCatalogTest extends TestCase {

  /**
   * Métrica conhecida é encontrada e tem os dados do catálogo.
   */
  public function testKnownMetricIsReturned(): void {
    $catalog = new MetricCatalog();

    $this->assertTrue($catalog->has('wiki.entries.read'));
    $this->assertSame('leituras', $catalog->get('wiki.entries.read')->unit);
  }

  /**
   * Identificador fora do catálogo é recusado.
   */
  public function testUnknownMetricIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new MetricCatalog())->get('wiki.page.views');
  }

  /**
   * Cada métrica do catálogo tem prazo padrão e nenhum identificador repetido.
   */
  public function testEveryMetricHasDefaultRetentionAndUniqueId(): void {
    $all = (new MetricCatalog())->all();

    foreach ($all as $id => $definition) {
      $this->assertSame($id, $definition->id);
      $this->assertIsInt($definition->defaultRetentionDays);
    }
  }

  /**
   * A configuração instalada cobre exatamente as métricas do catálogo.
   */
  public function testInstalledRetentionMatchesCatalog(): void {
    $file = dirname(__DIR__, 3) . '/config/install/aculta_statistics.settings.yml';
    $installed = array_column(Yaml::parseFile($file)['retention'], 'metric');

    $this->assertSame(
      array_keys((new MetricCatalog())->all()),
      $installed,
    );
  }

  /**
   * Sensibilidade fora de P, I e R é recusada.
   */
  public function testInvalidSensitivityIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MetricDefinition('wiki.entries.read', 'Leituras', 'leituras', 'X', 90);
  }

}
