<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp_client\Remote;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Content\TextContent;
use Psr\Log\LoggerInterface;

/**
 * Executes a fixed allowlist of read-only provider documentation MCP tools.
 *
 * The HTTP client/handshake/call pattern is adapted from the GPL-2.0-or-later
 * Drupal MCP Client 1.0.0-alpha3 MCPClient implementation. The upstream
 * package cannot coexist with this project's mcp/sdk 0.9 constraint, so this
 * module uses the same official SDK already required by MCP Server and keeps
 * only the HTTP client behavior needed for these four public documentation
 * tools. Upstream: https://www.drupal.org/project/mcp_client.
 */
final class RemoteToolExecutor {

  private const PROVIDERS = [
    'mercadopago' => [
      'endpoint' => 'https://mcp.mercadopago.com/mcp',
      'tools' => ['search_documentation'],
      'limit' => 15,
      'window' => 60,
    ],
    'melhorenvio' => [
      'endpoint' => 'https://docs.melhorenvio.com.br/mcp',
      'tools' => ['search-endpoints', 'get-endpoint', 'list-endpoints'],
      'limit' => 60,
      'window' => 60,
    ],
  ];

  /**
   * Logger channel for safe provider diagnostics.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  public function __construct(
    private readonly KeyRepositoryInterface $keys,
    private readonly FloodInterface $flood,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('aculta_mcp_client');
  }

  /**
   * Calls one provider documentation tool through MCP.
   *
   * Provider IDs and tool names are fixed in code. Callers cannot choose an
   * endpoint, add headers, or invoke a provider operation outside the
   * allowlist.
   *
   * @param string $provider
   *   The allowlisted provider ID.
   * @param string $tool
   *   The allowlisted provider MCP tool name.
   * @param array<string, mixed> $arguments
   *   Validated tool arguments.
   *
   * @return string
   *   Plain documentation text, bounded to 20 KB.
   *
   * @throws \RuntimeException
   *   If the provider is unavailable, credentials are missing, or the
   *   provider rejects the request.
   */
  public function execute(string $provider, string $tool, array $arguments): string {
    $definition = self::PROVIDERS[$provider] ?? NULL;
    if ($definition === NULL || !in_array($tool, $definition['tools'], TRUE)) {
      throw new \InvalidArgumentException('The requested provider documentation operation is not allowed.');
    }

    $event = 'aculta_mcp_' . $provider . '_documentation';
    if (!$this->flood->isAllowed($event, $definition['limit'], $definition['window'], 'global')) {
      throw new \RuntimeException('The documentation query limit has been reached. Try again shortly.');
    }
    $this->flood->register($event, $definition['window'], 'global');

    $headers = $provider === 'mercadopago' ? $this->mercadoPagoHeaders() : [];
    $client = Client::builder()
      ->setClientInfo('ACULTA MCP documentation bridge', '0.1.0')
      ->setProtocolVersion(ProtocolVersion::V2025_03_26)
      ->setFallbackProtocolVersion(NULL)
      ->setMaxRetries(0)
      ->setInitTimeout(15)
      ->setRequestTimeout(20)
      ->build();

    $transport = new HttpTransport($definition['endpoint'], $headers);
    try {
      $client->connect($transport);
      $result = $client->callTool($tool, $arguments, timeoutSeconds: 20);

      $parts = [];
      foreach ($result->content as $content) {
        if ($content instanceof TextContent) {
          $parts[] = $content->text;
        }
        elseif ($content instanceof \JsonSerializable) {
          $parts[] = json_encode($content->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }
      }
      $text = trim(implode("\n\n", array_filter($parts, static fn (string $part): bool => $part !== '')));
      if ($text === '') {
        throw new \RuntimeException('The provider returned no documentation text.');
      }

      // Some documentation servers use MCP's isError flag for ordinary
      // search outcomes such as "No matches found". Preserve that useful,
      // non-sensitive text while still rejecting empty provider failures.
      if ($result->isError) {
        $this->logger->notice('Provider documentation MCP returned a textual result with its error flag set for @provider/@tool.', [
          '@provider' => $provider,
          '@tool' => $tool,
        ]);
      }

      return mb_substr($text, 0, 20000);
    }
    catch (\Throwable $exception) {
      // Never log request arguments or authorization headers.
      $this->logger->warning('Provider documentation MCP call failed for @provider/@tool (@type).', [
        '@provider' => $provider,
        '@tool' => $tool,
        '@type' => get_debug_type($exception),
      ]);
      throw new \RuntimeException('The provider documentation service is unavailable. Try again shortly.');
    }
    finally {
      if ($client->isConnected()) {
        $client->disconnect();
      }
    }
  }

  /**
   * Resolves the test Mercado Pago token from the existing environment Key.
   *
   * @return array<string, string>
   *   Authorization header for the fixed Mercado Pago endpoint.
   */
  private function mercadoPagoHeaders(): array {
    $key = $this->keys->getKey('mercadopago_test_access_token');
    if (!$key || $key->getKeyProvider()->getPluginId() !== 'env') {
      throw new \RuntimeException('The test Mercado Pago key is not available.');
    }

    $providerSettings = $key->getKeyProvider()->getConfiguration();
    if (($providerSettings['env_variable'] ?? NULL) !== 'MERCADOPAGO_TEST_ACCESS_TOKEN') {
      throw new \RuntimeException('The test Mercado Pago key is not available.');
    }

    $token = $key->getKeyValue(TRUE);
    if (!is_string($token) || trim($token) === '') {
      throw new \RuntimeException('The test Mercado Pago key is not available.');
    }

    return ['Authorization' => 'Bearer ' . trim($token)];
  }

}
