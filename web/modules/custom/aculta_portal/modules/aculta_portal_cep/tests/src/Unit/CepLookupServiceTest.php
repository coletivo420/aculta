<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal_cep\Unit;

use Drupal\aculta_portal_cep\Lookup\CepLookupService;
use Drupal\aculta_portal_cep\Local\CepLocalStore;
use Drupal\aculta_portal_cep\Settings\CepSources;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * Testa normalização, fontes, cache, fallback e falhas da consulta de CEP.
 */
#[CoversClass(CepLookupService::class)]
#[Group('aculta_portal_cep')]
class CepLookupServiceTest extends UnitTestCase {

  private const SP_LINE = "01310100\tAvenida Paulista\tBela Vista\tSão Paulo\tSP\t3550308\n";

  /**
   * Diretório temporário com um shard local de teste.
   *
   * @var string
   */
  private string $dir;

  /**
   * URLs externas chamadas pelo cliente HTTP simulado.
   *
   * @var array
   */
  private array $httpCalls = [];

  /**
   * Cria o shard usado pelos testes de consulta local.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/aculta_portal_cep_test_' . bin2hex(random_bytes(4));
    mkdir($this->dir);
    // Shard do prefixo 013 com um CEP conhecido.
    file_put_contents($this->dir . '/013.tsv', self::SP_LINE);
  }

  /**
   * Remove os arquivos temporários dos testes.
   */
  protected function tearDown(): void {
    foreach (glob($this->dir . '/*') ?: [] as $file) {
      unlink($file);
    }
    rmdir($this->dir);
    parent::tearDown();
  }

  /**
   * Aceita CEPs formatados e rejeita valores com comprimento inválido.
   */
  public function testNormalizeAcceptsMaskAndRejectsWrongLength(): void {
    $service = $this->service();
    $this->assertSame('01310100', $service->normalize('01310-100'));
    $this->assertSame('01310100', $service->normalize(' 01310 100 '));
    $this->assertNull($service->normalize('0131'));
    $this->assertNull($service->normalize(''));
    $this->assertNull($service->normalize('abcdefgh'));
    $this->assertNull($service->normalize('abc01310100xyz'));
    $this->assertNull($service->normalize('01310--100'));
  }

  /**
   * Não chama fontes quando o CEP é inválido.
   */
  public function testInvalidCepReturnsNullWithoutAnySource(): void {
    $this->assertNull($this->service()->lookup('123'));
    $this->assertSame([], $this->httpCalls);
  }

  /**
   * Usa o resultado local sem consultar fontes externas.
   */
  public function testLocalBaseHitDoesNotCallExternalSources(): void {
    $address = $this->service()->lookup('01310-100');

    $this->assertSame([
      'postal_code' => '01310100',
      'address_line1' => 'Avenida Paulista',
      'dependent_locality' => 'Bela Vista',
      'locality' => 'São Paulo',
      'administrative_area' => 'SP',
    ], $address);
    $this->assertSame([], $this->httpCalls);
  }

  /**
   * Consulta o ViaCEP quando a base local não encontra o CEP.
   */
  public function testFallsBackToViaCepWhenLocalMisses(): void {
    $cache = $this->cacheMock(expectSet: TRUE);
    $service = $this->service(
      cache: $cache,
      http: $this->httpReturning([
        'viacep.com.br' => [
          'logradouro' => 'Rua Nova',
          'bairro' => 'Centro',
          'localidade' => 'Campinas',
          'uf' => 'SP',
        ],
      ]),
    );

    $address = $service->lookup('13010999');

    $this->assertSame('Rua Nova', $address['address_line1']);
    $this->assertSame('Campinas', $address['locality']);
    $this->assertSame(['https://viacep.com.br/ws/13010999/json/'], $this->httpCalls);
  }

  /**
   * Consulta a BrasilAPI depois de um retorno de não encontrado do ViaCEP.
   */
  public function testFallsBackToBrasilApiWhenViaCepDoesNotFind(): void {
    $service = $this->service(
      cache: $this->cacheMock(expectSet: TRUE, source: 'brasilapi'),
      http: $this->httpReturning([
        'viacep.com.br' => ['erro' => TRUE],
        'brasilapi.com.br' => [
          'cep' => '13010999',
          'street' => 'Rua B',
          'neighborhood' => 'Bairro B',
          'city' => 'Campinas',
          'state' => 'SP',
        ],
      ]),
    );

    $address = $service->lookup('13010999');

    $this->assertSame('Rua B', $address['address_line1']);
    $this->assertSame(
      ['https://viacep.com.br/ws/13010999/json/', 'https://brasilapi.com.br/api/cep/v2/13010999'],
      $this->httpCalls,
    );
  }

  /**
   * Não consulta provedores externos desabilitados.
   */
  public function testDisabledExternalSourceIsNotCalled(): void {
    $service = $this->service(sources: [
      'local' => ['enabled' => TRUE, 'weight' => 0],
      'viacep' => ['enabled' => FALSE, 'weight' => 1],
      'brasilapi' => ['enabled' => FALSE, 'weight' => 2],
    ]);

    $this->assertNull($service->lookup('13010999'));
    $this->assertSame([], $this->httpCalls);
  }

  /**
   * Reutiliza um resultado armazenado para o mesmo provedor e CEP.
   */
  public function testCachedExternalResultAvoidsNewRequest(): void {
    $cached = (object) ['data' => ['postal_code' => '13010999', 'address_line1' => 'Do cache']];
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->with('aculta_portal:cep:viacep:13010999')->willReturn($cached);
    $cache->expects($this->never())->method('set');

    $address = $this->service(cache: $cache)->lookup('13010999');

    $this->assertSame('Do cache', $address['address_line1']);
    $this->assertSame([], $this->httpCalls);
  }

  /**
   * Tests that one provider cannot read another provider's cache entry.
   */
  public function testExternalCacheIsScopedToSelectedProvider(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('get')
      ->with('aculta_portal:cep:brasilapi:13010999')
      ->willReturn(FALSE);
    $cache->expects($this->once())
      ->method('set')
      ->with('aculta_portal:cep:brasilapi:13010999', $this->anything(), $this->anything());
    $service = $this->service(
      cache: $cache,
      http: $this->httpReturning([
        'brasilapi.com.br' => ['cep' => '13010999', 'city' => 'Campinas', 'state' => 'SP'],
      ]),
      sources: [
        'local' => ['enabled' => FALSE, 'weight' => 0],
        'viacep' => ['enabled' => FALSE, 'weight' => 1],
        'brasilapi' => ['enabled' => TRUE, 'weight' => 2],
      ],
    );

    $address = $service->lookup('13010999');

    $this->assertSame('Campinas', $address['locality']);
    $this->assertSame(['https://brasilapi.com.br/api/cep/v2/13010999'], $this->httpCalls);
  }

  /**
   * Trata falhas de rede como consulta sem resultado.
   */
  public function testExternalFailureIsNullNotException(): void {
    $http = $this->createMock(ClientInterface::class);
    $http->method('request')->willThrowException(new \RuntimeException('timeout'));

    $this->assertNull($this->service(http: $http)->lookup('13010999'));
  }

  /**
   * Não armazena em cache um CEP não encontrado.
   */
  public function testNotFoundIsNotCachedAsPositive(): void {
    $cache = $this->cacheMock(expectSet: FALSE);
    $service = $this->service(
      cache: $cache,
      http: $this->httpReturning(['viacep.com.br' => ['erro' => TRUE], 'brasilapi.com.br' => []]),
    );

    $this->assertNull($service->lookup('13010999'));
  }

  /**
   * Cria o serviço com dependências simuladas.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface|null $cache
   *   Backend de cache opcional.
   * @param \GuzzleHttp\ClientInterface|null $http
   *   Cliente HTTP opcional.
   * @param array<string, array<string, mixed>> $sources
   *   Configuração das fontes CEP.
   *
   * @return \Drupal\aculta_portal_cep\Lookup\CepLookupService
   *   Serviço configurado para o teste.
   */
  private function service(?CacheBackendInterface $cache = NULL, ?ClientInterface $http = NULL, array $sources = []): CepLookupService {
    $sources += [
      'local' => ['enabled' => TRUE, 'weight' => 0],
      'viacep' => ['enabled' => TRUE, 'weight' => 1],
      'brasilapi' => ['enabled' => TRUE, 'weight' => 2],
    ];

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(fn (string $uri): string => $this->dir . '/' . basename($uri));

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn (string $key) => match ($key) {
      'sources' => $sources,
      'default_source' => 'local',
      default => NULL,
    });
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->willReturn($config);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnArgument(0);

    return new CepLookupService(
      new CepLocalStore($fileSystem),
      $http ?? $this->recordingHttp(),
      $cache ?? $this->cacheMock(expectSet: FALSE),
      new CepSources($configs, $translation),
    );
  }

  /**
   * Cria mock de cache que devolve miss e valida gravações esperadas.
   *
   * @param bool $expectSet
   *   Se o teste espera gravar resultado.
   * @param string $source
   *   Provedor que deve compor a chave armazenada.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   Cache simulado.
   */
  private function cacheMock(bool $expectSet, string $source = 'viacep'): CacheBackendInterface {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn(FALSE);
    if ($expectSet) {
      $cache->expects($this->once())->method('set')->with('aculta_portal:cep:' . $source . ':13010999', $this->anything(), $this->anything());
    }
    else {
      $cache->expects($this->never())->method('set');
    }
    return $cache;
  }

  /**
   * Cria cliente HTTP simulado que responde por host e registra as URLs.
   *
   * @param array<string, array<string, mixed>> $responses
   *   Respostas JSON associadas ao host solicitado.
   *
   * @return \GuzzleHttp\ClientInterface
   *   Cliente simulado.
   */
  private function httpReturning(array $responses): ClientInterface {
    $http = $this->createMock(ClientInterface::class);
    $http->method('request')->willReturnCallback(
      function (string $method, string $url) use ($responses): ResponseInterface {
        $this->httpCalls[] = $url;
        foreach ($responses as $host => $body) {
          if (str_contains($url, $host)) {
            return new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR));
          }
        }
        return new Response(404, [], '{}');
      },
    );
    return $http;
  }

  /**
   * Cria cliente HTTP que não retorna respostas externas.
   */
  private function recordingHttp(): ClientInterface {
    return $this->httpReturning([]);
  }

}
