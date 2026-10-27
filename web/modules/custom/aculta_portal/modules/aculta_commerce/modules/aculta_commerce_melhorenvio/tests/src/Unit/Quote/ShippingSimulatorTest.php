<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioClientInterface;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException;
use Drupal\aculta_commerce_melhorenvio\Quote\MelhorEnvioQuoter;
use Drupal\aculta_commerce_melhorenvio\Quote\QuoteOption;
use Drupal\aculta_commerce_melhorenvio\Quote\QuotePackage;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulator;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Estados do simulador de frete: a ordem das verificações, o cache e a falha que não chega ao visitante.
 */
#[CoversClass(ShippingSimulator::class)]
#[CoversClass(ShippingSimulation::class)]
#[Group('aculta_commerce_melhorenvio')]
final class ShippingSimulatorTest extends UnitTestCase {

  private const ORIGIN = ['postal_code' => '01310100', 'country_code' => 'BR'];

  /**
   * Monta o serviço com origem, credencial e tempo controlados.
   *
   * @param array<string, mixed>|null $origin
   *   Origem da configuração.
   * @param bool $credentials
   *   Se o cliente tem as chaves do ambiente.
   */
  private function simulator(?array $origin, bool $credentials, MelhorEnvioQuoter $quoter, ?array $package = NULL): ShippingSimulator {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([['origin', $origin], ['package', $package]]);
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->with('aculta_commerce_melhorenvio.settings')->willReturn($config);

    $client = $this->createMock(MelhorEnvioClientInterface::class);
    $client->method('hasCredentials')->willReturn($credentials);

    $cep = $this->createMock(CepLookupInterface::class);
    $cep->method('normalize')->willReturnCallback(static function (string $value): ?string {
      $digits = preg_replace('/\D/', '', $value) ?? '';
      return strlen($digits) === 8 ? $digits : NULL;
    });

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_000_000);

    $logger = $this->createMock(LoggerInterface::class);
    $loggers = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggers->method('get')->willReturn($logger);

    return new ShippingSimulator($client, $quoter, $configs, $cep, new MemoryBackend($time), $time, $loggers);
  }

  private function package(): QuotePackage {
    return new QuotePackage(widthCm: 11.0, heightCm: 2.0, lengthCm: 16.0, weightKg: 0.3, insuranceValue: 0.0);
  }

  private function quoter(): MelhorEnvioQuoter {
    return $this->createMock(MelhorEnvioQuoter::class);
  }

  /**
   * CEP inválido não chega à API e não depende da configuração.
   */
  public function testInvalidPostalCodeIsUnavailable(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->never())->method('attempt');
    $result = $this->simulator(self::ORIGIN, TRUE, $quoter)->simulate('123', $this->package());
    $this->assertSame(ShippingSimulation::STATE_UNAVAILABLE, $result->state);
    $this->assertSame(ShippingSimulation::REASON_INVALID_POSTAL_CODE, $result->reason);
  }

  /**
   * Sem origem configurada, nenhuma chamada é feita: a origem não é inventada.
   */
  public function testMissingOriginBlocksBeforeApi(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->never())->method('attempt');
    $result = $this->simulator(['postal_code' => ''], TRUE, $quoter)->simulate('01310-100', $this->package());
    $this->assertSame(ShippingSimulation::REASON_NO_ORIGIN, $result->reason);
  }

  /**
   * Origem com CEP inválido também conta como sem origem.
   */
  public function testMalformedOriginIsTreatedAsMissing(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->never())->method('attempt');
    $result = $this->simulator(['postal_code' => '1234'], TRUE, $quoter)->simulate('01310100', $this->package());
    $this->assertSame(ShippingSimulation::REASON_NO_ORIGIN, $result->reason);
  }

  /**
   * Sem credencial do ambiente ativo, nenhuma chamada real.
   */
  public function testMissingCredentialsBlocksBeforeApi(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->never())->method('attempt');
    $result = $this->simulator(self::ORIGIN, FALSE, $quoter)->simulate('01310100', $this->package());
    $this->assertSame(ShippingSimulation::REASON_NO_CREDENTIALS, $result->reason);
  }

  /**
   * Sem pacote real, a cotação não é feita sobre dimensões inventadas.
   */
  public function testMissingPackageBlocksBeforeApi(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->never())->method('attempt');
    $result = $this->simulator(self::ORIGIN, TRUE, $quoter)->simulate('01310100', NULL);
    $this->assertSame(ShippingSimulation::REASON_NO_PACKAGE, $result->reason);
  }

  /**
   * Opções da API viram estado disponível, com preço em reais formatado a partir do texto.
   */
  public function testAvailableFormatsOptions(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->once())->method('attempt')->willReturn([
      new QuoteOption('1', 'PAC', 'Correios', '1234.50', 9),
    ]);
    $result = $this->simulator(self::ORIGIN, TRUE, $quoter)->simulate('01310-100', $this->package());
    $this->assertSame(ShippingSimulation::STATE_AVAILABLE, $result->state);
    $this->assertNull($result->reason);
    $data = $result->toArray();
    $this->assertSame('PAC (Correios)', $data['options'][0]['name']);
    $this->assertSame('R$ 1.234,50', $data['options'][0]['price']);
    $this->assertSame(9, $data['options'][0]['days']);
  }

  /**
   * Resultado de API sem serviço válido é "sem opções", e não falha.
   */
  public function testEmptyWhenApiHasNoServices(): void {
    $quoter = $this->quoter();
    $quoter->method('attempt')->willReturn([]);
    $result = $this->simulator(self::ORIGIN, TRUE, $quoter)->simulate('01310100', $this->package());
    $this->assertSame(ShippingSimulation::STATE_EMPTY, $result->state);
  }

  /**
   * Um resultado bem-sucedido fica em cache: a segunda consulta não chega à API.
   */
  public function testSuccessIsCachedByPostalCodeAndPackage(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->once())->method('attempt')->willReturn([
      new QuoteOption('1', 'PAC', 'Correios', '37.79', 9),
    ]);
    $simulator = $this->simulator(self::ORIGIN, TRUE, $quoter);
    $first = $simulator->simulate('01310100', $this->package());
    $second = $simulator->simulate('01310-100', $this->package());
    $this->assertSame($first->toArray(), $second->toArray());
  }

  /**
   * Falha da API vira estado indisponível e não é guardada: a próxima consulta tenta de novo.
   */
  public function testFailureIsReportedAndNotCached(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->exactly(2))->method('attempt')
      ->willThrowException(new MelhorEnvioException('server', 'falha'));
    $simulator = $this->simulator(self::ORIGIN, TRUE, $quoter);
    $first = $simulator->simulate('01310100', $this->package());
    $second = $simulator->simulate('01310100', $this->package());
    $this->assertSame(ShippingSimulation::REASON_FAILURE, $first->reason);
    $this->assertSame(ShippingSimulation::STATE_UNAVAILABLE, $second->state);
  }

  /**
   * Formatação de preço trabalha sobre o texto: milhar com ponto e centavos sempre com duas casas.
   */
  public function testBrlFormatting(): void {
    $this->assertSame('R$ 0,10', ShippingSimulation::brl('0.10'));
    $this->assertSame('R$ 37,79', ShippingSimulation::brl('37.79'));
    $this->assertSame('R$ 1.234.567,00', ShippingSimulation::brl('1234567.00'));
    $this->assertSame('R$ 100,00', ShippingSimulation::brl('100'));
  }

  /**
   * Mensagem ao visitante: genérica para motivos internos, específica para CEP e falha, vazia quando há opções.
   */
  public function testVisitorMessages(): void {
    $this->assertSame('', (new ShippingSimulation(ShippingSimulation::STATE_AVAILABLE, NULL, [new QuoteOption('1', 'PAC', 'Correios', '1.00', 1)]))->message());
    $this->assertSame('Nenhuma opção de entrega para este CEP.', (new ShippingSimulation(ShippingSimulation::STATE_EMPTY))->message());
    $this->assertSame('Informe um CEP válido, com oito dígitos.', ShippingSimulation::unavailable(ShippingSimulation::REASON_INVALID_POSTAL_CODE)->message());
    $this->assertSame('Não foi possível calcular o frete agora. Tente novamente.', ShippingSimulation::unavailable(ShippingSimulation::REASON_FAILURE)->message());
    $this->assertSame('Cálculo de frete indisponível no momento.', ShippingSimulation::unavailable(ShippingSimulation::REASON_NO_ORIGIN)->message());
    $this->assertSame('Cálculo de frete indisponível no momento.', ShippingSimulation::unavailable(ShippingSimulation::REASON_NO_PACKAGE)->message());
    $this->assertSame('Cálculo de frete indisponível no momento.', ShippingSimulation::unavailable(ShippingSimulation::REASON_NO_CREDENTIALS)->message());
  }

  /**
   * Pacote padrão só existe com as quatro medidas maiores que zero. Zero ou ausência não vira medida presumida.
   */
  public function testPackageForRequiresAllMeasures(): void {
    $quoter = $this->quoter();
    $complete = ['width' => 11, 'height' => 2, 'length' => 16, 'weight' => 0.3];
    $package = $this->simulator(self::ORIGIN, TRUE, $quoter, $complete)->packageFor();
    $this->assertNotNull($package);
    $this->assertSame(11.0, $package->widthCm);
    $this->assertSame(0.3, $package->weightKg);
    $this->assertSame(0.0, $package->insuranceValue);

    $zeroed = ['width' => 0, 'height' => 2, 'length' => 16, 'weight' => 0.3];
    $this->assertNull($this->simulator(self::ORIGIN, TRUE, $quoter, $zeroed)->packageFor());
    $this->assertNull($this->simulator(self::ORIGIN, TRUE, $quoter, NULL)->packageFor());
  }

  /**
   * Sem pacote cadastrado, a disponibilidade é indisponível mesmo com origem e credencial.
   */
  public function testAvailabilityNeedsConfiguredPackage(): void {
    $quoter = $this->quoter();
    $this->assertSame(ShippingSimulation::REASON_NO_PACKAGE, $this->simulator(self::ORIGIN, TRUE, $quoter)->availabilityReason());
    $complete = ['width' => 11, 'height' => 2, 'length' => 16, 'weight' => 0.3];
    $this->assertNull($this->simulator(self::ORIGIN, TRUE, $quoter, $complete)->availabilityReason());
  }

  /**
   * O destino enviado ao cotador leva o país BR: sem ele, o mapeador recusa o endereço antes da API.
   */
  public function testDestinationCarriesCountryForQuote(): void {
    $quoter = $this->quoter();
    $quoter->expects($this->once())->method('attempt')
      ->with(['country_code' => 'BR', 'postal_code' => '01310100'], $this->anything())
      ->willReturn([]);
    $result = $this->simulator(self::ORIGIN, TRUE, $quoter)->simulate('01310-100', $this->package());
    $this->assertSame(ShippingSimulation::STATE_EMPTY, $result->state);
  }

}
