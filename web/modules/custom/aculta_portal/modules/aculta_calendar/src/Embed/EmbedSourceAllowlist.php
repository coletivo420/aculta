<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Embed;

/**
 * Aceita o destino do link "mais datas" do embed somente em duas formas.
 *
 * Caminho interno iniciado por uma barra simples, ou URL https cujo host está
 * na lista configurada. Qualquer outra forma é recusada: javascript:, data:,
 * http, host fora da lista, protocolo relativo e credenciais na URL (S3).
 */
final class EmbedSourceAllowlist {

  /**
   * Hosts permitidos, em minúsculas.
   *
   * @var string[]
   */
  private array $hosts;

  /**
   * Construtor.
   *
   * @param string[] $allowedHosts
   *   Hosts aceitos para URL absoluta, por exemplo "eventos.exemplo.invalid".
   */
  public function __construct(array $allowedHosts) {
    $this->hosts = array_map(static fn (string $host): string => strtolower($host), $allowedHosts);
  }

  /**
   * Indica se o destino pode ser usado no link do embed.
   *
   * @param string $sourceUri
   *   Valor vindo do parâmetro source_uri.
   */
  public function isAllowed(string $sourceUri): bool {
    // Espaço, controle e barra invertida são recusados antes da análise.
    if ($sourceUri === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $sourceUri) === 1) {
      return FALSE;
    }

    if (str_starts_with($sourceUri, '/')) {
      // "//host" é protocolo relativo e aponta para outro host.
      return !str_starts_with($sourceUri, '//');
    }

    $partes = parse_url($sourceUri);
    if ($partes === FALSE || !isset($partes['scheme'], $partes['host'])) {
      return FALSE;
    }

    if (strtolower($partes['scheme']) !== 'https') {
      return FALSE;
    }

    if (isset($partes['user']) || isset($partes['pass'])) {
      return FALSE;
    }

    if (isset($partes['port']) && $partes['port'] !== 443) {
      return FALSE;
    }

    return in_array(strtolower($partes['host']), $this->hosts, TRUE);
  }

}
