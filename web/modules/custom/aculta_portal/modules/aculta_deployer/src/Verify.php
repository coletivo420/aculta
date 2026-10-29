<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Verificação pós-deploy somente leitura: uma requisição GET por entrada com
 * "probe" e "expect". Não grava nada e não segue redirecionamentos.
 */
final class Verify {

  public const TIMEOUT_SECONDS = 10;
  public const MAX_BYTES = 1048576;

  /**
   * Somente HTTPS, com host e sem credenciais na URL. */
  public static function isAllowedUrl(string $url): bool {
    if (!str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
      return FALSE;
    }
    $parts = parse_url($url);
    return isset($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
  }

  /**
   * O texto esperado precisa aparecer na resposta. */
  public static function evaluate(string $body, string $expect): bool {
    return $expect !== '' && str_contains($body, $expect);
  }

  /**
   * GET sem seguir redirecionamentos; devolve corpo e cabeçalhos ou null. */
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
   *
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
   * Valor do cabeçalho X-Robots-Tag, ou null se ausente. */
  public static function robotsHeader(array $headers): ?string {
    foreach ($headers as $line) {
      if (preg_match('/^x-robots-tag:\s*(.+)$/i', trim((string) $line), $m) === 1) {
        return trim($m[1]);
      }
    }
    return NULL;
  }

  /**
   * Verdadeiro quando o valor do cabeçalho impede indexação. */
  public static function isNoindex(?string $value): bool {
    return $value !== NULL && stripos($value, 'noindex') !== FALSE;
  }

  /**
   * Código HTTP da resposta (primeira linha de status), ou null. */
  public static function statusCode(array $headers): ?int {
    foreach ($headers as $line) {
      if (preg_match('#^HTTP/\S+\s+(\d{3})#', trim((string) $line), $m) === 1) {
        return (int) $m[1];
      }
    }
    return NULL;
  }

  /**
   * Status que não expõe conteúdo indexável: 401/403 (acesso recusado) e
   * 404/410 (rota inexistente para o anônimo). Usado nos caminhos privados.
   */
  public static function isRefusedStatus(?int $status): bool {
    return in_array($status, [401, 403, 404, 410], TRUE);
  }

  /**
   * Valores de <loc> de um sitemap ou índice de sitemaps, ou null se o XML não
   * for válido. Sem resolução de entidades externas.
   *
   * @return string[]|null
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
   * URLs das diretivas "Sitemap:" de um robots.txt (sem diferenciar caixa).
   *
   * @return string[]
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
   * Verdadeiro quando o robots.txt bloqueia o site inteiro ("Disallow: /"). */
  public static function disallowsRoot(string $robots): bool {
    foreach (preg_split('/\R/', $robots) ?: [] as $line) {
      if (preg_match('/^\s*disallow\s*:\s*\/\s*(#.*)?$/i', $line) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Equivalente de teste de um host de produção: aculta.org -> aculta.toca.net.br,
   * preservando o subdomínio (apoio.aculta.org -> apoio.aculta.toca.net.br). Só troca
   * o sufixo exato, então evil-aculta.org não vira host de teste.
   */
  public static function testEquivalent(string $host): string {
    return preg_replace('/(^|\.)aculta\.org$/i', '$1aculta.toca.net.br', strtolower($host)) ?? $host;
  }

  /**
   * Converte um texto (URL de sonda ou valor esperado) do ambiente de produção para o de teste:
   * cada host "*.aculta.org" vira o equivalente "*.aculta.toca.net.br". Texto sem host de produção não muda.
   */
  public static function toTestEnvironment(string $text): string {
    return preg_replace_callback(
      '~(https?://)((?:[a-z0-9-]+\.)*aculta\.org)(?=[/"\'\s>?\#]|$)~i',
      static fn(array $m): string => $m[1] . self::testEquivalent($m[2]),
      $text,
    ) ?? $text;
  }

  /**
   * Host (sem porta) de uma URL, em minúsculas, ou null. */
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
