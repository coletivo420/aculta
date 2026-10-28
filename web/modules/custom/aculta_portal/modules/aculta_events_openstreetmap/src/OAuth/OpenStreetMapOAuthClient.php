<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\OAuth;

use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapCredentialProviderInterface;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapEnvironment;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapTokenStore;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * OAuth2 client for the OpenStreetMap editing API.
 *
 * Adaptado de OpenCulturas 3.1.4 (GPL-2.0-or-later),
 * `profile/modules/custom/openculturas_openstreetmap/src/OpenStreetMap/ApiClient.php`.
 */
final class OpenStreetMapOAuthClient {

  /**
   * Canal de log, sem valores de credenciais ou token.
   */
  private readonly LoggerInterface $logger;

  /**
   * Cria o cliente OAuth2 com transporte, credenciais e armazenamento cifrado.
   */
  public function __construct(
    private readonly ClientInterface $http,
    private readonly OpenStreetMapCredentialProviderInterface $credentials,
    private readonly OpenStreetMapEnvironment $environment,
    private readonly OpenStreetMapTokenStore $tokens,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('aculta_events_openstreetmap');
  }

  /**
   * Verifica se ambiente, cliente OAuth e chave de cifra estão prontos. */
  public function isConfigured(): bool {
    $environment = $this->environment->environment();
    return $environment !== NULL
      && $this->credentials->clientId($environment) !== NULL
      && $this->credentials->clientSecret($environment) !== NULL
      && $this->credentials->tokenEncryptionKey() !== NULL;
  }

  /**
   * Monta URL de autorização somente para o endpoint fixo do ambiente ativo. */
  public function authorizationUrl(string $state, string $redirectUri): string {
    $environment = $this->environment->environment();
    $base = $this->environment->oauthBaseUrl();
    $clientId = $environment === NULL ? NULL : $this->credentials->clientId($environment);
    if ($base === NULL || $clientId === NULL || $clientId === '' || !$this->isConfigured()) {
      throw new OpenStreetMapOAuthException('OpenStreetMap OAuth is unavailable for this environment.');
    }
    return $base . '/oauth2/authorize?' . http_build_query([
      'response_type' => 'code',
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'scope' => 'write_api',
      'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986);
  }

  /**
   * Troca o código de curta duração por token e salva somente a versão cifrada. */
  public function exchangeAuthorizationCode(string $code, string $redirectUri): void {
    $environment = $this->environment->environment();
    $base = $this->environment->oauthBaseUrl();
    $clientId = $environment === NULL ? NULL : $this->credentials->clientId($environment);
    $clientSecret = $environment === NULL ? NULL : $this->credentials->clientSecret($environment);
    if (!$this->isConfigured() || $base === NULL || $clientId === NULL || $clientSecret === NULL
      || $code === '' || strlen($code) > 4096 || preg_match('/[\r\n]/', $code) === 1) {
      throw new OpenStreetMapOAuthException('OpenStreetMap authorization could not be completed.');
    }

    try {
      $response = $this->http->request('POST', $base . '/oauth2/token', [
        'form_params' => [
          'grant_type' => 'authorization_code',
          'code' => $code,
          'redirect_uri' => $redirectUri,
          'client_id' => $clientId,
          'client_secret' => $clientSecret,
        ],
        'headers' => [
          'Accept' => 'application/json',
          'User-Agent' => 'ACULTA Events OpenStreetMap/0.1.0',
        ],
        'connect_timeout' => 5,
        'timeout' => 15,
        'http_errors' => FALSE,
        'allow_redirects' => FALSE,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (\Throwable $exception) {
      $this->logger->warning('OpenStreetMap OAuth token exchange failed: @class.', ['@class' => $exception::class]);
      throw new OpenStreetMapOAuthException('OpenStreetMap authorization could not be completed.');
    }

    if ($response->getStatusCode() !== 200 || !is_array($data)
      || !is_string($data['access_token'] ?? NULL) || $data['access_token'] === ''
      || ($data['token_type'] ?? NULL) !== 'Bearer') {
      $this->logger->warning('OpenStreetMap OAuth returned an invalid response (HTTP @status).', [
        '@status' => $response->getStatusCode(),
      ]);
      throw new OpenStreetMapOAuthException('OpenStreetMap authorization could not be completed.');
    }

    try {
      $this->tokens->save($data['access_token']);
    }
    catch (\Throwable $exception) {
      $this->logger->error('OpenStreetMap OAuth token could not be stored: @class.', ['@class' => $exception::class]);
      throw new OpenStreetMapOAuthException('The OpenStreetMap connection could not be stored securely.');
    }
  }

}
