<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Performs read-only post-deploy checks.
 *
 * Each entry makes one GET request for its probe and expectation. The verifier
 * writes nothing and does not follow redirects.
 */
final class Verify {

  public const TIMEOUT_SECONDS = 10;
  public const MAX_BYTES = 1048576;

  /**
   * Checks whether a URL meets the verifier's HTTPS requirements.
   *
   * @param string $url
   *   URL to validate.
   *
   * @return bool
   *   TRUE when the URL uses HTTPS, has a host, and contains no credentials.
   */
  public static function isAllowedUrl(string $url): bool {
    if (!str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
      return FALSE;
    }
    $parts = parse_url($url);
    return isset($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
  }

  /**
   * Checks whether an expected value appears in a response body.
   *
   * @param string $body
   *   Response body.
   * @param string $expect
   *   Expected text.
   *
   * @return bool
   *   TRUE when the non-empty expected text appears in the body.
   */
  public static function evaluate(string $body, string $expect): bool {
    return $expect !== '' && str_contains($body, $expect);
  }

  /**
   * Fetches a URL without following redirects.
   *
   * @param string $url
   *   URL to fetch.
   *
   * @return array{body: string, headers: array<int, string>}|null
   *   Response body and headers, or NULL on failure.
   */
  public static function fetchWithHeaders(string $url): ?array {
    if (!self::isAllowedUrl($url)) {
      return NULL;
    }
    $context = self::context();
    $body = @file_get_contents($url, FALSE, $context, 0, self::MAX_BYTES);
    if ($body === FALSE) {
      return NULL;
    }
    // file_get_contents grava os cabeçalhos da resposta neste escopo.
    return ['body' => $body, 'headers' => $http_response_header ?? []];
  }

  /**
   * Creates the bounded GET request context.
   *
   * @return resource
   *   Stream context with redirect and timeout protections.
   */
  private static function context() {
    return stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => self::TIMEOUT_SECONDS,
        'follow_location' => 0,
        'ignore_errors' => TRUE,
        'user_agent' => 'aculta-deployer/' . Cli::VERSION,
        'max_redirects' => 0,
      ],
    ]);
  }

  /**
   * Reads the X-Robots-Tag response header.
   *
   * @param array<int, string> $headers
   *   HTTP response headers.
   *
   * @return string|null
   *   Header value, or NULL when absent.
   */
  public static function robotsHeader(array $headers): ?string {
    foreach ($headers as $line) {
      if (preg_match('/^x-robots-tag:\s*(.+)$/i', trim((string) $line), $m) === 1) {
        return trim($m[1]);
      }
    }
    return NULL;
  }

  /**
   * Determines whether a header value blocks indexing.
   *
   * @param string|null $value
   *   X-Robots-Tag header value.
   *
   * @return bool
   *   TRUE when the value includes noindex.
   */
  public static function isNoindex(?string $value): bool {
    return $value !== NULL && stripos($value, 'noindex') !== FALSE;
  }

  /**
   * Reads the HTTP status code from response headers.
   *
   * @param array<int, string> $headers
   *   HTTP response headers.
   *
   * @return int|null
   *   Status code, or NULL when no status line is present.
   */
  public static function statusCode(array $headers): ?int {
    foreach ($headers as $line) {
      if (preg_match('#^HTTP/\S+\s+(\d{3})#', trim((string) $line), $m) === 1) {
        return (int) $m[1];
      }
    }
    return NULL;
  }

  /**
   * Determines whether an HTTP status refuses access to a private path.
   *
   * @param int|null $status
   *   HTTP status code.
   *
   * @return bool
   *   TRUE for 401, 403, 404, or 410 responses.
   */
  public static function isRefusedStatus(?int $status): bool {
    return in_array($status, [401, 403, 404, 410], TRUE);
  }

  /**
   * Extracts sitemap location values without resolving external entities.
   *
   * @return string[]|null
   *   Location URLs, or NULL when the XML is invalid.
   */
  public static function xmlLocs(string $xml): ?array {
    $dom = new \DOMDocument();
    if (@$dom->loadXML($xml, LIBXML_NONET) !== TRUE) {
      return NULL;
    }
    $locs = [];
    foreach ($dom->getElementsByTagName('loc') as $node) {
      $locs[] = trim($node->textContent);
    }
    return $locs;
  }

  /**
   * Extracts Sitemap directive URLs from robots.txt.
   *
   * @return string[]
   *   Sitemap URLs found in the document.
   */
  public static function sitemapDirectives(string $robots): array {
    $urls = [];
    foreach (preg_split('/\R/', $robots) ?: [] as $line) {
      if (preg_match('/^\s*sitemap\s*:\s*(\S+)/i', $line, $m) === 1) {
        $urls[] = $m[1];
      }
    }
    return $urls;
  }

  /**
   * Determines whether robots.txt blocks the whole site.
   *
   * @param string $robots
   *   Robots.txt contents.
   *
   * @return bool
   *   TRUE when a root Disallow directive is present.
   */
  public static function disallowsRoot(string $robots): bool {
    foreach (preg_split('/\R/', $robots) ?: [] as $line) {
      if (preg_match('/^\s*disallow\s*:\s*\/\s*(#.*)?$/i', $line) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Converts a production host to its test host equivalent.
   *
   * Only the exact aculta.org suffix is replaced; lookalike domains remain
   * unchanged.
   *
   * @param string $host
   *   Production hostname.
   *
   * @return string
   *   Test hostname with the same subdomain.
   */
  public static function testEquivalent(string $host): string {
    return preg_replace('/(^|\.)aculta\.org$/i', '$1aculta.toca.net.br', strtolower($host)) ?? $host;
  }

  /**
   * Replaces production hosts in a probe or expected value with test hosts.
   *
   * Text without an aculta.org URL remains unchanged.
   *
   * @param string $text
   *   Probe URL or expected response value.
   *
   * @return string
   *   Text with matching production hosts replaced.
   */
  public static function toTestEnvironment(string $text): string {
    return preg_replace_callback(
      '~(https?://)((?:[a-z0-9-]+\.)*aculta\.org)(?=[/"\'\s>?\#]|$)~i',
      static fn(array $m): string => $m[1] . self::testEquivalent($m[2]),
      $text,
    ) ?? $text;
  }

  /**
   * Extracts a URL host without its port.
   *
   * @param string $url
   *   URL to inspect.
   *
   * @return string|null
   *   Lowercase host, or NULL when the URL has none.
   */
  public static function hostOf(string $url): ?string {
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) ? strtolower($host) : NULL;
  }

  /**
   * Verdadeiro quando o HTML declara <meta name="robots"> com noindex. */
  public static function metaNoindex(string $body): bool {
    if (preg_match_all('/<meta\s[^>]*>/i', $body, $tags) === 0) {
      return FALSE;
    }
    foreach ($tags[0] as $tag) {
      if (preg_match('/\sname\s*=\s*["\']robots["\']/i', $tag) === 1
        && preg_match('/\scontent\s*=\s*["\']([^"\']*)["\']/i', $tag, $c) === 1
        && self::isNoindex($c[1])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * GET sem seguir redirecionamentos, com tempo limite e tamanho máximo. */
  public static function fetch(string $url): ?string {
    if (!self::isAllowedUrl($url)) {
      return NULL;
    }
    $context = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => self::TIMEOUT_SECONDS,
        'follow_location' => 0,
        'ignore_errors' => TRUE,
        'user_agent' => 'aculta-deployer/' . Cli::VERSION,
        'max_redirects' => 0,
      ],
    ]);
    $body = @file_get_contents($url, FALSE, $context, 0, self::MAX_BYTES);
    return $body === FALSE ? NULL : $body;
  }

}
