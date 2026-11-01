<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioClient;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioConfig;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioTokenStoreInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cliente OAuth2 do Melhor Envio, sem rede: respostas simuladas pelo MockHandler do Guzzle.
 */
#[CoversClass(MelhorEnvioClient::class)]
#[Group('aculta_commerce_melhorenvio')]
class MelhorEnvioClientTest extends UnitTestCase {

  /**
   * Valores fictícios. Nenhum é credencial real. */
  private const SETTINGS = [
    'environment' => 'sandbox',
    'redirect_uri' => 'https://aculta.example.test/melhorenvio/callback',
    'scopes' => '',
    'contact_email' => 'suporte@example.test',
    'keys' => [
      'sandbox' => ['client_id' => 'me_sb_id', 'client_secret' => 'me_sb_secret'],
      'production' => ['client_id' => 'me_prod_id', 'client_secret' => 'me_prod_secret'],
    ],
  ];

  private const KEY_VALUES = [
    'me_sb_id' => 'cid-sb',
    'me_sb_secret' => 'SECRET-SB',
    'me_prod_id' => 'cid-prod',
    'me_prod_secret' => 'SECRET-PROD',
  ];

  /**
   * Histórico de requisições enviadas ao MockHandler, na ordem de envio.
   *
   * @var array<int, array{request: \Psr\Http\Message\RequestInterface}>
   */
  private array $history = [];

  /**
   * Armazenamento de tokens em memória, usado pelo cliente de teste.
   */
  private object $store;

  /**
   * Monta o cliente com as respostas em fila. O histórico fica em $this->history.
   *
   * @param array<int, mixed> $responses
   *   Respostas ou exceções devolvidas pelo MockHandler, em ordem.
   * @param array<string, mixed> $settingsOverride
   *   Valores de configuração que sobrescrevem SETTINGS.
   * @param array<string, mixed>|null $tokens
   *   Tokens já gravados no ambiente sandbox, ou NULL para nenhum.
   */
  private function build(array $responses, array $settingsOverride = [], ?array $tokens = NULL): MelhorEnvioClient {
    $this->history = [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($this->history));
    $http = new Client(['handler' => $stack]);

    $settings = array_replace_recursive(self::SETTINGS, $settingsOverride);
    $immutable = $this->createMock(ImmutableConfig::class);
    $immutable->method('get')->willReturnCallback(fn (string $key) => $settings[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($immutable);
    $config = new MelhorEnvioConfig($factory);

    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->method('getKey')->willReturnCallback(function (string $id): ?KeyInterface {
      if (!isset(self::KEY_VALUES[$id])) {
        return NULL;
      }
      $key = $this->createMock(KeyInterface::class);
      $key->method('getKeyValue')->willReturn(self::KEY_VALUES[$id]);
      return $key;
    });

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $this->store = new class($tokens === NULL ? [] : ['sandbox' => $tokens]) implements MelhorEnvioTokenStoreInterface {

      /** @var array<string, array{access_token: string, refresh_token: string, expires_at: int}> */
      public array $data;

      public function __construct(array $data) {
        $this->data = $data;
      }

      /**
       * Tokens do ambiente informado, ou NULL quando não há.
       *
       * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
       *   Ambiente.
       * @return array{access_token:
       *   string, refresh_token: string, expires_at: int}|null
       */
      public function load(MelhorEnvioEnvironment $environment): ?array {
        return $this->data[$environment->value] ?? NULL;
      }

      /**
       * Grava os tokens do ambiente informado.
       *
       * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
       *   Ambiente.
       * @param array{access_token: string, refresh_token: string, expires_at: int} $tokens
       *   Tokens a gravar.
       */
      public function save(MelhorEnvioEnvironment $environment, array $tokens): void {
        $this->data[$environment->value] = $tokens;
      }

      /**
       * Remove os tokens do ambiente informado.
       *
       * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
       *   Ambiente.
       */
      public function clear(MelhorEnvioEnvironment $environment): void {
        unset($this->data[$environment->value]);
      }

    };

    return new MelhorEnvioClient($http, $config, $this->store, $keys, $loggerFactory, 0, fn (int $micros) => NULL);
  }

  /**
   * Tokens válidos por padrão, expirando em $expiresIn segundos.
   *
   * @param int $expiresIn
   *   Segundos até expirar.
   *
   * @return array{access_token:
   *   string, refresh_token: string, expires_at: int}
   */
  private function validTokens(int $expiresIn = 3600): array {
    return ['access_token' => 'AT-1', 'refresh_token' => 'RT-1', 'expires_at' => time() + $expiresIn];
  }

  /**
   * Resposta JSON simulada.
   *
   * @param array<string, mixed> $data
   *   Corpo da resposta.
   * @param int $status
   *   Status HTTP.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Resultado da operação.
   */
  private function json(array $data, int $status = 200): Response {
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
  }

  /**
   * Executa a chamada e confirma a categoria do erro lançado.
   *
   * @param callable $call
   *   Chamada que deve lançar MelhorEnvioException.
   * @param string $category
   *   Categoria esperada.
   *
   * @return \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException
   *   Exceção a ser lançada.
   */
  private function expectCategory(callable $call, string $category): MelhorEnvioException {
    try {
      $call();
    }
    catch (MelhorEnvioException $e) {
      $this->assertSame($category, $e->category());
      return $e;
    }
    $this->fail("Esperada MelhorEnvioException com categoria $category.");
  }

  /**
   * Requisição registrada no histórico, na posição informada.
   *
   * @param int $index
   *   Índice no histórico.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   Resultado da operação.
   */
  private function sent(int $index): Request {
    return $this->history[$index]['request'];
  }

  /**
   * Sandbox e produção usam as URLs base confirmadas.
   */
  public function testEnvironmentsUseConfirmedBaseUrls(): void {
    $this->assertSame('https://sandbox.melhorenvio.com.br', MelhorEnvioEnvironment::Sandbox->baseUrl());
    $this->assertSame('https://melhorenvio.com.br', MelhorEnvioEnvironment::Production->baseUrl());
  }

  /**
   * Ambiente inválido cai no sandbox.
   */
  public function testInvalidEnvironmentFallsBackToSandbox(): void {
    $client = $this->build([], ['environment' => 'prod']);
    $this->assertStringStartsWith('https://sandbox.melhorenvio.com.br/', $client->authorizationUrl('s1'));
  }

  /**
   * A URL de autorização leva client_id e state, sem o secret.
   */
  public function testAuthorizationUrlCarriesClientIdAndStateButNoSecret(): void {
    $url = $this->build([])->authorizationUrl('estado-aleatorio');
    $this->assertStringContainsString('client_id=cid-sb', $url);
    $this->assertStringContainsString('state=estado-aleatorio', $url);
    $this->assertStringContainsString('response_type=code', $url);
    $this->assertStringNotContainsString('SECRET-SB', $url);
  }

  /**
   * A troca de código grava os tokens e envia o secret só no corpo do formulário.
   */
  public function testExchangeStoresTokensAndSendsSecretOnlyInForm(): void {
    $client = $this->build([
      $this->json(['access_token' => 'AT-NEW', 'refresh_token' => 'RT-NEW', 'expires_in' => 2592000]),
    ]);
    $client->exchangeAuthorizationCode('CODIGO-1');

    $this->assertSame('AT-NEW', $this->store->data['sandbox']['access_token']);
    $this->assertSame('RT-NEW', $this->store->data['sandbox']['refresh_token']);
    $request = $this->sent(0);
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame('/oauth/token', $request->getUri()->getPath());
    $this->assertFalse($request->hasHeader('Authorization'));
    $body = (string) $request->getBody();
    $this->assertStringContainsString('grant_type=authorization_code', $body);
    $this->assertStringContainsString('client_secret=SECRET-SB', $body);
  }

  /**
   * Resposta de token sem os campos esperados é recusada e nada é gravado.
   */
  public function testInvalidTokenResponseIsRejected(): void {
    $client = $this->build([$this->json(['access_token' => 'AT-NEW'])]);
    $this->expectCategory(fn () => $client->exchangeAuthorizationCode('CODIGO-1'), 'invalid_response');
    $this->assertSame([], $this->store->data);
  }

  /**
   * Requisição autenticada envia Bearer, Accept e User-Agent com contato.
   */
  public function testRequestSendsBearerAndRequiredHeaders(): void {
    $client = $this->build([$this->json(['ok' => TRUE])], [], $this->validTokens());
    $this->assertSame(['ok' => TRUE], $client->request('GET', '/api/v2/teste', NULL, TRUE));

    $request = $this->sent(0);
    $this->assertSame(['Bearer AT-1'], $request->getHeader('Authorization'));
    $this->assertSame(['application/json'], $request->getHeader('Accept'));
    $this->assertStringContainsString('suporte@example.test', $request->getHeaderLine('User-Agent'));
  }

  /**
   * Token perto de expirar é renovado antes da requisição.
   */
  public function testTokenNearExpiryIsRefreshedBeforeRequest(): void {
    $client = $this->build([
      $this->json(['access_token' => 'AT-NEW', 'refresh_token' => 'RT-NEW', 'expires_in' => 3600]),
      $this->json(['ok' => TRUE]),
    ], [], $this->validTokens(10));
    $client->request('GET', '/api/v2/teste', NULL, TRUE);

    $this->assertStringContainsString('grant_type=refresh_token', (string) $this->sent(0)->getBody());
    $this->assertSame(['Bearer AT-NEW'], $this->sent(1)->getHeader('Authorization'));
  }

  /**
   * Um 401 provoca uma renovação e uma nova tentativa.
   */
  public function testUnauthorizedRefreshesOnceAndRetries(): void {
    $client = $this->build([
      $this->json(['message' => 'expirado'], 401),
      $this->json(['access_token' => 'AT-NEW', 'refresh_token' => 'RT-NEW', 'expires_in' => 3600]),
      $this->json(['ok' => TRUE]),
    ], [], $this->validTokens());

    $this->assertSame(['ok' => TRUE], $client->request('GET', '/api/v2/teste', NULL, TRUE));
    $this->assertSame(['Bearer AT-NEW'], $this->sent(2)->getHeader('Authorization'));
    $this->assertCount(3, $this->history);
  }

  /**
   * Um segundo 401 após a renovação falha sem novo laço.
   */
  public function testSecondUnauthorizedAfterRefreshFails(): void {
    $client = $this->build([
      $this->json([], 401),
      $this->json(['access_token' => 'AT-NEW', 'refresh_token' => 'RT-NEW', 'expires_in' => 3600]),
      $this->json([], 401),
    ], [], $this->validTokens());

    $this->expectCategory(fn () => $client->request('GET', '/api/v2/teste', NULL, TRUE), 'unauthorized');
    $this->assertCount(3, $this->history, 'Não pode haver segundo refresh nem laço infinito.');
  }

  /**
   * Leitura idempotente repete após erro 503.
   */
  public function testIdempotentReadRetriesServerError(): void {
    $client = $this->build([$this->json([], 503), $this->json(['ok' => TRUE])], [], $this->validTokens());
    $this->assertSame(['ok' => TRUE], $client->request('GET', '/api/v2/teste', NULL, TRUE));
    $this->assertCount(2, $this->history);
  }

  /**
   * POST nunca é repetido, mesmo com erro 503.
   */
  public function testNonIdempotentPostIsNotRetried(): void {
    $client = $this->build([$this->json([], 503)], [], $this->validTokens());
    $this->expectCategory(fn () => $client->request('POST', '/api/v2/teste', ['x' => 1]), 'server');
    $this->assertCount(1, $this->history);
  }

  /**
   * Limite de requisições é repetido e, esgotado, reportado como rate_limited.
   */
  public function testRateLimitIsRetriedThenReported(): void {
    $client = $this->build([$this->json([], 429), $this->json([], 429), $this->json([], 429)], [], $this->validTokens());
    $exception = $this->expectCategory(fn () => $client->request('GET', '/api/v2/teste', NULL, TRUE), 'rate_limited');
    $this->assertSame(429, $exception->statusCode());
    $this->assertCount(3, $this->history);
  }

  /**
   * Falha de rede não expõe token nem secret na mensagem.
   */
  public function testNetworkErrorIsSanitized(): void {
    $error = fn () => new ConnectException('falhou com AT-1', new Request('GET', 'https://sandbox.melhorenvio.com.br/x'));
    $client = $this->build([$error(), $error(), $error()], [], $this->validTokens());
    $exception = $this->expectCategory(fn () => $client->request('GET', '/api/v2/teste', NULL, TRUE), 'network');
    $this->assertStringNotContainsString('AT-1', $exception->getMessage());
    $this->assertStringNotContainsString('SECRET', $exception->getMessage());
  }

  /**
   * Cotação recusada com 403 vira `forbidden` e não é repetida, mesmo sendo idempotente.
   */
  public function testQuoteForbiddenIsNotRetried(): void {
    $client = $this->build([$this->json(['message' => 'negado'], 403)], [], $this->validTokens());
    $this->expectCategory(fn () => $client->request('POST', '/api/v2/me/shipment/calculate', ['from' => ['postal_code' => '01001000']], TRUE), 'forbidden');
    $this->assertCount(1, $this->history);
  }

  /**
   * Cotação sem nenhuma tarifa (resposta `[]`) é sucesso sem opções, não erro.
   */
  public function testQuoteEmptyResponseIsEmptyList(): void {
    $client = $this->build([$this->json([])], [], $this->validTokens());
    $this->assertSame([], $client->request('POST', '/api/v2/me/shipment/calculate', ['from' => ['postal_code' => '01001000']], TRUE));
  }

  /**
   * Cotação com 503 persistente é repetida o limite de tentativas e depois informada como erro de servidor.
   */
  public function testQuoteServerErrorIsRetriedThenReported(): void {
    $client = $this->build([
      $this->json(['message' => 'indisponível'], 503),
      $this->json(['message' => 'indisponível'], 503),
      $this->json(['message' => 'indisponível'], 503),
    ], [], $this->validTokens());
    $this->expectCategory(fn () => $client->request('POST', '/api/v2/me/shipment/calculate', ['from' => ['postal_code' => '01001000']], TRUE), 'server');
    $this->assertCount(3, $this->history);
  }

  /**
   * Timeout de rede na cotação é repetido como operação idempotente e informado como `network`, sem vazar o token.
   */
  public function testQuoteTimeoutIsNetworkAndSanitized(): void {
    $error = fn () => new ConnectException('timeout com AT-1', new Request('POST', 'https://sandbox.melhorenvio.com.br/api/v2/me/shipment/calculate'));
    $client = $this->build([$error(), $error(), $error()], [], $this->validTokens());
    $exception = $this->expectCategory(fn () => $client->request('POST', '/api/v2/me/shipment/calculate', ['from' => ['postal_code' => '01001000']], TRUE), 'network');
    $this->assertStringNotContainsString('AT-1', $exception->getMessage());
  }

  /**
   * Sem token, a requisição falha como unauthenticated sem chamar a rede.
   */
  public function testMissingTokenIsUnauthenticatedWithoutHttpCall(): void {
    $client = $this->build([]);
    $this->expectCategory(fn () => $client->request('GET', '/api/v2/teste', NULL, TRUE), 'unauthenticated');
    $this->assertCount(0, $this->history);
  }

  /**
   * Renovação recusada limpa os tokens e exige nova autorização.
   */
  public function testRejectedRefreshClearsTokensAndRequiresAuthorization(): void {
    $client = $this->build([$this->json([], 401)], [], $this->validTokens(10));
    $this->expectCategory(fn () => $client->request('GET', '/api/v2/teste', NULL, TRUE), 'unauthenticated');
    $this->assertSame([], $this->store->data);
  }

  /**
   * Credenciais ausentes geram not_configured.
   */
  public function testMissingCredentialsAreNotConfigured(): void {
    $client = $this->build([], ['keys' => ['sandbox' => ['client_id' => '', 'client_secret' => '']]]);
    $this->expectCategory(fn () => $client->authorizationUrl('s1'), 'not_configured');
  }

  /**
   * Contato ausente bloqueia as requisições antes da rede.
   */
  public function testMissingContactEmailBlocksRequests(): void {
    $client = $this->build([], ['contact_email' => ''], $this->validTokens());
    $this->expectCategory(fn () => $client->request('GET', '/api/v2/teste', NULL, TRUE), 'not_configured');
    $this->assertCount(0, $this->history);
  }

  /**
   * Corpo que não é JSON vira invalid_response.
   */
  public function testInvalidJsonIsInvalidResponse(): void {
    $client = $this->build([new Response(200, [], 'não é json')], [], $this->validTokens());
    $this->expectCategory(fn () => $client->request('GET', '/api/v2/teste', NULL, TRUE), 'invalid_response');
  }

  /**
   * Erro 422 vira validation e não é repetido.
   */
  public function testNotFoundAndValidationAreMappedWithoutRetry(): void {
    $client = $this->build([$this->json(['erro' => 'x'], 422)], [], $this->validTokens());
    $this->expectCategory(fn () => $client->request('GET', '/api/v2/teste', NULL, TRUE), 'validation');
    $this->assertCount(1, $this->history);
  }

}
