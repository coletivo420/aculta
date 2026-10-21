<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Connector\Ga4\Ga4Settings;
use Drupal\aculta_statistics\Connector\Ga4\GoogleGa4ReportClient;
use Drupal\Core\Logger\LoggerChannelInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Cliente GA4 real: desligado sem propriedade e credencial, mapeia a resposta e não vaza erro.
 */
#[CoversClass(GoogleGa4ReportClient::class)]
#[Group('aculta_statistics')]
final class GoogleGa4ReportClientTest extends TestCase {

  /**
   * Sem propriedade ou sem credencial válida: desligado, nenhum pedido sai.
   */
  public function testDisabledWithoutPropertyOrCredential(): void {
    $called = FALSE;
    $runner = static function () use (&$called): array {
      $called = TRUE;
      return [];
    };

    $noProperty = new GoogleGa4ReportClient($this->settings(NULL, ['type' => 'service_account', 'private_key' => 'x']), $this->logger(), $runner);
    $this->assertFalse($noProperty->isEnabled());
    $this->assertNull($noProperty->report(19990, 20000));

    $noCredential = new GoogleGa4ReportClient($this->settings('123456789', NULL), $this->logger(), $runner);
    $this->assertFalse($noCredential->isEnabled());
    $this->assertNull($noCredential->report(19990, 20000));

    $this->assertFalse($called, 'Sem configuração nenhum pedido é feito.');
  }

  /**
   * Com propriedade e credencial, o pedido leva o intervalo e as métricas certas, e a resposta vira série por dia.
   */
  public function testMapsTheReportToDailySeries(): void {
    $sent = [];
    $runner = static function (string $property, array $request) use (&$sent): array {
      $sent = [$property, $request];
      return [
        ['date' => '20260102', 'sessions' => '12', 'activeUsers' => '9'],
        ['date' => '20260101', 'sessions' => 4, 'activeUsers' => 3],
        ['date' => 'malformada', 'sessions' => 1, 'activeUsers' => 1],
      ];
    };
    $client = new GoogleGa4ReportClient($this->settings('123456789', ['type' => 'service_account', 'private_key' => 'x']), $this->logger(), $runner);

    $this->assertTrue($client->isEnabled());
    $series = $client->report(20000, 20001);

    $this->assertSame('123456789', $sent[0]);
    $this->assertSame(['dimensions' => ['date'], 'metrics' => ['sessions', 'activeUsers']], array_diff_key($sent[1], ['dateRanges' => TRUE]));
    $this->assertSame(['startDate' => '2024-10-04', 'endDate' => '2024-10-05'], $sent[1]['dateRanges'][0]);
    $this->assertSame(
      ['2026-01-01' => ['sessions' => 4, 'activeUsers' => 3], '2026-01-02' => ['sessions' => 12, 'activeUsers' => 9]],
      $series,
    );
  }

  /**
   * Falha da API vira NULL e um aviso só com o tipo da exceção (sem mensagem, que pode conter detalhe da credencial).
   */
  public function testApiFailureReturnsNullWithoutLeakingMessage(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())->method('warning')->with(
      $this->stringContains('Consulta ao GA4 falhou'),
      ['@type' => \RuntimeException::class],
    );
    $runner = static function (): array {
      throw new \RuntimeException('segredo-que-nao-pode-vazar');
    };
    $client = new GoogleGa4ReportClient($this->settings('123456789', ['type' => 'service_account', 'private_key' => 'x']), $logger, $runner);

    $this->assertNull($client->report(20000, 20001));
  }

  /**
   * Configuração de teste que devolve propriedade e credencial informadas.
   *
   * @param array<string, mixed>|null $account
   */
  private function settings(?string $property, ?array $account): Ga4Settings {
    return new class($property, $account) implements Ga4Settings {
      public function __construct(private readonly ?string $property, private readonly ?array $account) {}
      public function propertyId(): ?string {
        return $this->property;
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
