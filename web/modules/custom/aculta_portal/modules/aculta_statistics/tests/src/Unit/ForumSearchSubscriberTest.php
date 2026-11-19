<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\EventSubscriber\ForumSearchSubscriber;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Coleta de busca do Fórum desligada por padrão.
 */
#[Group('aculta_statistics')]
final class ForumSearchSubscriberTest extends TestCase {

  /**
   * Desligada, nenhuma métrica é gravada.
   */
  public function testDisabledDispatchesNothing(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects(self::never())->method('record');

    $subscriber = new ForumSearchSubscriber(
      new StatisticsRecorder(new MetricCatalog(), $storage),
      $this->config(FALSE),
    );
    $subscriber->onSearchExecuted($this->event(0));
  }

  /**
   * Habilitada, conta a busca e a busca sem resultado.
   */
  public function testEnabledRecordsZeroResultQuery(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects(self::exactly(2))
      ->method('record')
      ->willReturnCallback(function (string $metric, string $dimension = '', int $amount = 1): void {
        static $calls = 0;
        $calls++;
        self::assertSame('', $dimension);
        if ($calls === 1) {
          self::assertSame('forum.search.queries', $metric);
        }
        else {
          self::assertSame('forum.search.zero_results', $metric);
        }
      });

    $subscriber = new ForumSearchSubscriber(
      new StatisticsRecorder(new MetricCatalog(), $storage),
      $this->config(TRUE),
    );
    $subscriber->onSearchExecuted($this->event(0));
  }

  /**
   * Resultado não vazio não soma zero_results.
   */
  public function testEnabledWithResultsRecordsOnlyQuery(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects(self::once())
      ->method('record')
      ->with('forum.search.queries', '', 1);

    $subscriber = new ForumSearchSubscriber(
      new StatisticsRecorder(new MetricCatalog(), $storage),
      $this->config(TRUE),
    );
    $subscriber->onSearchExecuted($this->event(5));
  }

  /**
   * Evento mínimo com apenas a contagem de resultados.
   */
  private function event(int $resultCount): object {
    return new class($resultCount) {

      public function __construct(public int $resultCount) {}

    };
  }

  /**
   * Configuração de coleta do Fórum.
   */
  private function config(bool $enabled): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('forum_discovery.enabled')->willReturn($enabled);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_statistics.settings')->willReturn($config);
    return $factory;
  }

}
