<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\Geocoding\GeocodingService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Interpretação da resposta da Geocoding API (sem rede).
 */
#[Group('aculta_events')]
class GeocodingServiceTest extends UnitTestCase {

  /**
   * Serviço com dependências falsas: a interpretação não usa rede nem chave.
   */
  private function servico(): GeocodingService {
    $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
    $logs = $this->createMock(LoggerChannelFactoryInterface::class);
    $logs->method('get')->willReturn($logger);
    return new GeocodingService(
      $this->createMock(ClientInterface::class),
      $this->createMock(KeyRepositoryInterface::class),
      $logs,
    );
  }

  /**
   * Resposta OK devolve latitude e longitude como número.
   */
  public function testRespostaOkDevolveCoordenadas(): void {
    $dados = ['status' => 'OK', 'results' => [['geometry' => ['location' => ['lat' => -16.68, 'lng' => -49.26]]]]];
    $this->assertSame(['lat' => -16.68, 'lng' => -49.26], $this->servico()->interpretar($dados));
  }

  /**
   * Sem resultado, status de erro ou resposta malformada devolvem NULL.
   *
   * @return array<string, array{array}>
   */
  public static function semResultado(): array {
    return [
      'zero resultados' => [['status' => 'ZERO_RESULTS', 'results' => []]],
      'chave recusada' => [['status' => 'REQUEST_DENIED']],
      'vazio' => [[]],
      'sem localização' => [['status' => 'OK', 'results' => [['geometry' => []]]]],
    ];
  }

  /**
   * Respostas sem coordenadas não geram coordenadas.
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('semResultado')]
  public function testSemCoordenadasDevolveNulo(array $dados): void {
    $this->assertNull($this->servico()->interpretar($dados));
  }

}
