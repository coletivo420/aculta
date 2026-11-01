<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Http;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\aculta_portal\Secrets\SecretsManager;
use Psr\Log\LoggerInterface;

/**
 * Cliente HTTP do Melhor Envio: OAuth2 authorization_code, renovação de token, timeouts e retentativas seguras.
 *
 * Ponto único de acesso à API. Não conhece cotação, remessa nem etiqueta: essas operações chamam request().
 *
 * Regras:
 * - Sandbox e produção usam tokens e credenciais separados. Nunca se misturam.
 * - Retentativas (rede, 429, 5xx) só em operações idempotentes. POST não é repetido.
 * - Um 401 provoca uma única renovação de token e uma nova tentativa.
 * - Logs e exceções nunca contêm token, client secret, corpo de resposta ou dados pessoais.
 * - Sem rede nos testes: o transporte é o http_client, substituível por MockHandler.
 */
final class MelhorEnvioClient implements MelhorEnvioClientInterface {

  /**
   * Timeout de conexão, em segundos. */
  private const CONNECT_TIMEOUT = 5;

  /**
   * Timeout total da requisição, em segundos. */
  private const TIMEOUT = 20;

  /**
   * Tentativas máximas em operações idempotentes. */
  private const MAX_ATTEMPTS = 3;

  /**
   * Segundos antes da expiração em que o token já é renovado. */
  private const REFRESH_MARGIN = 60;

  /**
   * Status HTTP que justificam nova tentativa em operações idempotentes. */
  private const RETRY_STATUSES = [429, 500, 502, 503, 504];

  /**
   * Nome do app no User-Agent exigido pela API. */
  private const APP_NAME = 'ACULTA Commerce Melhor Envio';

  /**
   * Mensagens fixas por categoria de erro. Nunca incluem corpo de resposta. */
  private const MESSAGES = [
    'unauthenticated' => 'Conexão com o Melhor Envio não autorizada para este ambiente.',
    'unauthorized' => 'Token do Melhor Envio rejeitado.',
    'forbidden' => 'Operação não permitida pelo Melhor Envio para esta conta.',
    'not_found' => 'Recurso não encontrado no Melhor Envio.',
    'validation' => 'Dados recusados pelo Melhor Envio.',
    'rate_limited' => 'Limite de requisições do Melhor Envio atingido. Tente novamente mais tarde.',
    'server' => 'Melhor Envio indisponível no momento.',
    'network' => 'Falha de rede ao consultar o Melhor Envio.',
    'invalid_response' => 'Resposta do Melhor Envio fora do formato esperado.',
    'not_configured' => 'Integração com o Melhor Envio não configurada para este ambiente.',
    'unexpected' => 'Resposta inesperada do Melhor Envio.',
  ];

  /**
   * Canal de log do módulo. */
  private readonly LoggerInterface $logger;

  /**
   * Instante da última requisição, em segundos com fração (microtime). */
  private float $lastRequestAt = 0.0;

  /**
   * Cria o cliente com as dependências injetadas.
   *
   * @param \GuzzleHttp\ClientInterface $http
   *   Transporte HTTP (http_client do Drupal, ou MockHandler nos testes).
   * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioConfig $config
   *   Configurações do módulo: ambiente, URL de retorno, escopos e nomes das chaves.
   * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioTokenStoreInterface $tokens
   *   Armazenamento dos tokens por ambiente.
   * @param \Drupal\key\KeyRepositoryInterface $keys
   *   Repositório do Drupal Key, de onde saem client_id e client_secret.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Fábrica de canais de log.
   * @param int $minIntervalMs
   *   Intervalo mínimo entre requisições, em milissegundos. Zero desativa.
   * @param \Closure|null $sleeper
   *   Recebe microssegundos a aguardar. NULL usa usleep(); os testes passam uma função vazia.
   * @param \Drupal\aculta_portal\Secrets\SecretsManager|null $secrets
   *   Credenciais resolvidas pelo contrato central de segredos. NULL desativa o fallback.
   */
  public function __construct(
    private readonly ClientInterface $http,
    private readonly MelhorEnvioConfig $config,
    private readonly MelhorEnvioTokenStoreInterface $tokens,
    private readonly KeyRepositoryInterface $keys,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly int $minIntervalMs = 200,
    private readonly ?\Closure $sleeper = NULL,
    private readonly ?SecretsManager $secrets = NULL,
  ) {
    $this->logger = $loggerFactory->get('aculta_commerce_melhorenvio');
  }

  /**
   * URL de consentimento para o administrador autorizar o app.
   *
   * O $state deve ser aleatório e validado no retorno.
   *
   * @param string $state
   *   Valor anti-CSRF devolvido pelo Melhor Envio no retorno.
   *
   * @return string
   *   URL completa de autorização no ambiente ativo.
   */
  public function authorizationUrl(string $state): string {
    $environment = $this->config->environment();
    $credentials = $this->credentials($environment);
    $query = http_build_query([
      'client_id' => $credentials['client_id'],
      'redirect_uri' => $this->redirectUri(),
      'response_type' => 'code',
      'scope' => $this->config->scopes(),
      'state' => $state,
    ]);
    return $environment->baseUrl() . MelhorEnvioEndpoints::AUTHORIZE . '?' . $query;
  }

  /**
   * Troca o código de autorização por tokens e os guarda no ambiente ativo.
   *
   * Não é repetido: o código é de uso único.
   *
   * @param string $code
   *   Código devolvido pelo Melhor Envio no retorno da autorização.
   */
  public function exchangeAuthorizationCode(string $code): void {
    $environment = $this->config->environment();
    $credentials = $this->credentials($environment);
    $body = $this->sendToken($environment, [
      'grant_type' => 'authorization_code',
      'client_id' => $credentials['client_id'],
      'client_secret' => $credentials['client_secret'],
      'redirect_uri' => $this->redirectUri(),
      'code' => $code,
    ]);
    $this->storeTokens($environment, $body, NULL);
  }

  /**
   * Requisição autenticada à API.
   *
   * @param string $method
   *   Método HTTP, por exemplo GET ou POST.
   * @param string $path
   *   Caminho da API, começando por barra.
   * @param array<string, mixed>|null $json
   *   Corpo JSON da requisição, ou NULL quando não há corpo.
   * @param bool $idempotent
   *   TRUE somente para leituras ou operações repetíveis sem efeito colateral. Habilita retentativas.
   *
   * @return array<mixed>
   *   JSON decodificado da resposta (array vazio quando não há corpo).
   */
  public function request(string $method, string $path, ?array $json = NULL, bool $idempotent = FALSE): array {
    $environment = $this->config->environment();
    $refreshed = FALSE;
    while (TRUE) {
      $options = ['headers' => ['Authorization' => 'Bearer ' . $this->validAccessToken($environment)]];
      if ($json !== NULL) {
        $options['json'] = $json;
      }
      try {
        return $this->send($environment, $method, $path, $options, $idempotent);
      }
      catch (MelhorEnvioException $e) {
        if ($e->category() !== 'unauthorized' || $refreshed) {
          throw $e;
        }
        $this->refresh($environment);
        $refreshed = TRUE;
      }
    }
  }

  /**
   * Indica se as duas chaves do ambiente ativo existem. Não chama a API nem o endpoint de token.
   */
  public function hasCredentials(): bool {
    try {
      $this->credentials($this->config->environment());
      return TRUE;
    }
    catch (MelhorEnvioException) {
      return FALSE;
    }
  }

  /**
   * Token de acesso válido, renovado quando está perto de expirar.
   *
   * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
   *   Ambiente ativo.
   *
   * @return string
   *   Access token pronto para uso no cabeçalho Authorization.
   */
  private function validAccessToken(MelhorEnvioEnvironment $environment): string {
    $tokens = $this->tokens->load($environment)
      ?? throw new MelhorEnvioException('unauthenticated', self::MESSAGES['unauthenticated']);
    if ($tokens['expires_at'] - self::REFRESH_MARGIN <= time()) {
      $tokens = $this->refresh($environment);
    }
    return $tokens['access_token'];
  }

  /**
   * Renova o access_token com o refresh_token.
   *
   * Se o Melhor Envio recusar a renovação, limpa os tokens e exige nova autorização.
   *
   * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
   *   Ambiente ativo.
   *
   * @return array{access_token:
   *   string, refresh_token: string, expires_at: int}
   *   Tokens novos, já gravados.
   */
  private function refresh(MelhorEnvioEnvironment $environment): array {
    $current = $this->tokens->load($environment)
      ?? throw new MelhorEnvioException('unauthenticated', self::MESSAGES['unauthenticated']);
    $credentials = $this->credentials($environment);
    try {
      $body = $this->sendToken($environment, [
        'grant_type' => 'refresh_token',
        'refresh_token' => $current['refresh_token'],
        'client_id' => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
      ]);
    }
    catch (MelhorEnvioException $e) {
      if ($e->category() === 'unauthorized' || $e->category() === 'validation') {
        $this->tokens->clear($environment);
        throw new MelhorEnvioException('unauthenticated', self::MESSAGES['unauthenticated'], $e->statusCode(), $e);
      }
      throw $e;
    }
    return $this->storeTokens($environment, $body, $current['refresh_token']);
  }

  /**
   * Envio ao endpoint de token. Nunca é repetido.
   *
   * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
   *   Ambiente ativo.
   * @param array<string, string> $form
   *   Campos do formulário OAuth2.
   *
   * @return array<mixed>
   *   Resposta decodificada do token.
   */
  private function sendToken(MelhorEnvioEnvironment $environment, array $form): array {
    return $this->send($environment, 'POST', MelhorEnvioEndpoints::TOKEN, ['form_params' => $form], FALSE);
  }

  /**
   * Valida a resposta de token e grava os tokens no ambiente.
   *
   * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
   *   Ambiente ativo.
   * @param array<mixed> $body
   *   Resposta decodificada do token.
   * @param string|null $fallbackRefresh
   *   Refresh token anterior, usado quando a resposta não traz um novo.
   *
   * @return array{access_token:
   *   string, refresh_token: string, expires_at: int}
   *   Tokens gravados.
   */
  private function storeTokens(MelhorEnvioEnvironment $environment, array $body, ?string $fallbackRefresh): array {
    $access = $body['access_token'] ?? NULL;
    $refresh = $body['refresh_token'] ?? $fallbackRefresh;
    $expiresIn = (int) ($body['expires_in'] ?? 0);
    if (!is_string($access) || $access === '' || !is_string($refresh) || $refresh === '' || $expiresIn <= 0) {
      throw new MelhorEnvioException('invalid_response', self::MESSAGES['invalid_response']);
    }
    $tokens = [
      'access_token' => $access,
      'refresh_token' => $refresh,
      'expires_at' => time() + $expiresIn,
    ];
    $this->tokens->save($environment, $tokens);
    return $tokens;
  }

  /**
   * Envio único com timeouts, retentativas (só se idempotente) e tradução de erros.
   *
   * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
   *   Ambiente ativo.
   * @param string $method
   *   Método HTTP.
   * @param string $path
   *   Caminho da API.
   * @param array<string, mixed> $options
   *   Opções do Guzzle (headers, json ou form_params).
   * @param bool $idempotent
   *   Habilita retentativas em rede e nos status de RETRY_STATUSES.
   *
   * @return array<mixed>
   *   JSON decodificado da resposta de sucesso.
   */
  private function send(MelhorEnvioEnvironment $environment, string $method, string $path, array $options, bool $idempotent): array {
    $options['headers'] = ($options['headers'] ?? []) + $this->baseHeaders();
    $options += [
      'connect_timeout' => self::CONNECT_TIMEOUT,
      'timeout' => self::TIMEOUT,
      'http_errors' => FALSE,
    ];
    $url = $environment->baseUrl() . $path;

    for ($attempt = 1;; $attempt++) {
      $this->throttle();
      try {
        $response = $this->http->request($method, $url, $options);
      }
      catch (GuzzleException $e) {
        if ($idempotent && $attempt < self::MAX_ATTEMPTS) {
          $this->backoff($attempt);
          continue;
        }
        $this->logFailure($method, $path, 0, 'network');
        throw new MelhorEnvioException('network', self::MESSAGES['network'], 0, $e);
      }

      $status = $response->getStatusCode();
      if ($status >= 200 && $status < 300) {
        return $this->decode((string) $response->getBody());
      }
      if ($idempotent && in_array($status, self::RETRY_STATUSES, TRUE) && $attempt < self::MAX_ATTEMPTS) {
        $this->backoff($attempt);
        continue;
      }
      $category = $this->categoryFor($status);
      $this->logFailure($method, $path, $status, $category);
      throw new MelhorEnvioException($category, self::MESSAGES[$category], $status);
    }
  }

  /**
   * Cabeçalhos exigidos pela API: Accept e User-Agent com o contato do administrador.
   *
   * @return array<string,
   *   string>
   *   Cabeçalhos base.
   */
  private function baseHeaders(): array {
    $contact = trim($this->config->contactEmail());
    if ($contact === '') {
      throw new MelhorEnvioException('not_configured', self::MESSAGES['not_configured']);
    }
    return [
      'Accept' => 'application/json',
      'User-Agent' => self::APP_NAME . ' (' . $contact . ')',
    ];
  }

  /**
   * URL de retorno configurada. Lança erro quando ausente.
   *
   * @return string
   *   URL de retorno, sem espaços nas bordas.
   */
  private function redirectUri(): string {
    $uri = trim($this->config->redirectUri());
    if ($uri === '') {
      throw new MelhorEnvioException('not_configured', self::MESSAGES['not_configured']);
    }
    return $uri;
  }

  /**
   * Credenciais lidas do Drupal Key. Nunca ficam em código, em configuração ou em log.
   *
   * @param \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment $environment
   *   Ambiente ativo.
   *
   * @return array{client_id:
   *   string, client_secret: string}
   *   Valores das duas chaves do ambiente.
   */
  private function credentials(MelhorEnvioEnvironment $environment): array {
    $ids = $this->config->keyIds($environment);
    $values = [];
    foreach (['client_id', 'client_secret'] as $name) {
      $key = $ids[$name] !== '' ? $this->keys->getKey($ids[$name]) : NULL;
      $value = $key?->getKeyValue() ?? '';
      // Compatibilidade com settings antigos que guardavam o nome da variável.
      if ($value === '' && $this->secrets !== NULL && $ids[$name] !== '') {
        $value = (string) (
          $this->secrets->value($ids[$name])
          ?? $this->secrets->valueForKeyId($ids[$name])
          ?? ''
        );
      }
      if ($value === '') {
        throw new MelhorEnvioException('not_configured', self::MESSAGES['not_configured']);
      }
      $values[$name] = $value;
    }
    return ['client_id' => $values['client_id'], 'client_secret' => $values['client_secret']];
  }

  /**
   * Decodifica o corpo JSON da resposta.
   *
   * @param string $raw
   *   Corpo bruto.
   *
   * @return array<mixed>
   *   Dados decodificados; array vazio quando o corpo está vazio.
   */
  private function decode(string $raw): array {
    if ($raw === '') {
      return [];
    }
    $decoded = json_decode($raw, TRUE);
    if (!is_array($decoded)) {
      throw new MelhorEnvioException('invalid_response', self::MESSAGES['invalid_response']);
    }
    return $decoded;
  }

  /**
   * Categoria de erro para um status HTTP.
   *
   * @param int $status
   *   Status HTTP recebido.
   *
   * @return string
   *   Nome da categoria, como unauthorized ou rate_limited.
   */
  private function categoryFor(int $status): string {
    return match (TRUE) {
      $status === 401 => 'unauthorized',
      $status === 403 => 'forbidden',
      $status === 404 => 'not_found',
      $status === 422 => 'validation',
      $status === 429 => 'rate_limited',
      $status >= 500 => 'server',
      default => 'unexpected',
    };
  }

  /**
   * Registra a falha sem query string, sem headers e sem corpo.
   *
   * @param string $method
   *   Método HTTP.
   * @param string $path
   *   Caminho da API, sem query string.
   * @param int $status
   *   Status HTTP, ou 0 quando não houve resposta.
   * @param string $category
   *   Categoria do erro.
   */
  private function logFailure(string $method, string $path, int $status, string $category): void {
    $this->logger->warning('Chamada ao Melhor Envio falhou: {method} {path}, status {status}, categoria {category}.', [
      'method' => $method,
      'path' => $path,
      'status' => $status,
      'category' => $category,
    ]);
  }

  /**
   * Espera o intervalo mínimo entre requisições, por processo.
   */
  private function throttle(): void {
    if ($this->minIntervalMs <= 0) {
      return;
    }
    $now = microtime(TRUE);
    $elapsedMs = ($now - $this->lastRequestAt) * 1000;
    if ($this->lastRequestAt > 0 && $elapsedMs < $this->minIntervalMs) {
      $this->wait((int) (($this->minIntervalMs - $elapsedMs) * 1000));
    }
    $this->lastRequestAt = microtime(TRUE);
  }

  /**
   * Espera exponencial entre tentativas: 100 ms, 200 ms, 400 ms.
   *
   * @param int $attempt
   *   Número da tentativa que falhou, a partir de 1.
   */
  private function backoff(int $attempt): void {
    $this->wait(100000 * (2 ** ($attempt - 1)));
  }

  /**
   * Aguarda o tempo informado, ou chama o sleeper injetado (usado nos testes).
   *
   * @param int $microseconds
   *   Tempo de espera em microssegundos.
   */
  private function wait(int $microseconds): void {
    if ($this->sleeper !== NULL) {
      ($this->sleeper)($microseconds);
      return;
    }
    usleep($microseconds);
  }

}
