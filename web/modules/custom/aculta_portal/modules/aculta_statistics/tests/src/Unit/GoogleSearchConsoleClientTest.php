<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Connector\SearchConsole\GoogleSearchConsoleClient;
use Drupal\aculta_statistics\Connector\SearchConsole\SearchConsoleSettings;
use Drupal\Core\Logger\LoggerChannelInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Cliente do Search Console: desligado sem configuração, série diária sem posição, falha sem vazamento.
 */
#[CoversClass(GoogleSearchConsoleClient::class)]
#[Group('aculta_statistics')]
final class GoogleSearchConsoleClientTest extends TestCase {

  /**
   * Sem propriedade ou credencial, nenhum pedido sai.
   */
  public function testDisabledWithoutSiteOrCredential(): void {
    $called = FALSE;
    $runner = static function () use (&$called): array {
      $called = TRUE;
      return [];
    };
    $client = new GoogleSearchConsoleClient($this->settings(NULL, ['type' => 'service_account', 'private_key' => 'x']), $this->logger(), $runner);

    $this->assertFalse($client->isEnabled());
    $this->assertNull($client->report('2026-10-01', '2026-10-07'));
    $this->assertFalse($called);
  }

  /**
   * A resposta vira série por dia, em ordem, sem posição e com datas inválidas descartadas.
   */
  public function testMapsRowsToDailySeriesWithoutPosition(): void {
    $sent = [];
    $runner = static function (string $site, array $request) use (&$sent): array {
      $sent = [$site, $request];
      return [
        ['date' => '2026-10-02', 'clicks' => 7, 'impressions' => 90],
        ['date' => '2026-10-01', 'clicks' => 3, 'impressions' => 40],
        ['date' => 'nope', 'clicks' => 1, 'impressions' => 1],
      ];
    };
    $client = new GoogleSearchConsoleClient($this->settings('sc-domain:aculta.org', ['type' => 'service_account', 'private_key' => 'x']), $this->logger(), $runner);

    $series = $client->report('2026-10-01', '2026-10-02');

    $this->assertSame('sc-domain:aculta.org', $sent[0]);
    $this->assertSame(['startDate' => '2026-10-01', 'endDate' => '2026-10-02', 'dimensions' => ['date'], 'dataState' => 'all'], $sent[1]);
    $this->assertSame(
      ['2026-10-01' => ['clicks' => 3, 'impressions' => 40], '2026-10-02' => ['clicks' => 7, 'impressions' => 90]],
      $series,
    );
  }

  /**
   * Falha da API vira NULL e um aviso só com o tipo da exceção.
   */
  public function testApiFailureReturnsNullWithoutLeakingMessage(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())->method('warning')->with(
      $this->stringContains('Consulta ao Search Console falhou'),
      ['@type' => \RuntimeException::class],
    );
    $runner = static function (): array {
      throw new \RuntimeException('credencial-que-nao-pode-vazar');
    };
    $client = new GoogleSearchConsoleClient($this->settings('sc-domain:aculta.org', ['type' => 'service_account', 'private_key' => 'x']), $logger, $runner);

    $this->assertNull($client->report('2026-10-01', '2026-10-02'));
  }

  /**
   * Configuração de teste com propriedade e credencial informadas.
   *
   * @param array<string, mixed>|null $account
   */
  private function settings(?string $site, ?array $account): SearchConsoleSettings {
    return new class($site, $account) implements SearchConsoleSettings {
      public function __construct(private readonly ?string $site, private readonly ?array $account) {}
      public function siteUrl(): ?string {
        return $this->site;
      }
      public function serviceAccount(): ?array {
        return $this->account;
      }
    };
  }

  /**
   * Canal de log sem efeito.
   */
  private function logger(): LoggerChannelInterface {
    return $this->createMock(LoggerChannelInterface::class);
  }

}
