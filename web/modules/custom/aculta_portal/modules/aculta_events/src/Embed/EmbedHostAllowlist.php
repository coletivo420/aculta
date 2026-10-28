<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Embed;

use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Domínios que podem incorporar a agenda: os hosts de teste e de produção do mapeamento do
 * aculta_deployer (config/deploy.json, domain_transition). Nenhum host fica no código.
 *
 * Ambos os ambientes são autorizados (correção do responsável): sites *.aculta de teste e de produção.
 * O arquivo é lido pelo caminho da extensão, então funciona mesmo com o deployer desabilitado no site.
 * Sem o mapeamento, a lista é vazia (falha fechada: nenhum site externo pode incorporar).
 */
final class EmbedHostAllowlist {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly ModuleExtensionList $modulos,
  ) {}

  /**
   * Hosts autorizados, em ordem alfabética, sem repetição.
   *
   * @return string[]
   */
  public function hosts(): array {
    $caminho = $this->modulos->getPath('aculta_deployer') . '/config/deploy.json';
    if (!is_file($caminho)) {
      return [];
    }
    $dados = json_decode((string) file_get_contents($caminho), TRUE);
    return is_array($dados) ? self::hostsDoMapeamento($dados) : [];
  }

  /**
   * Coleta test_host e production_host de qualquer bloco do mapeamento que os declare.
   *
   * @param array<mixed> $mapeamento
   *   Conteúdo decodificado de deploy.json.
   *
   * @return string[]
   */
  public static function hostsDoMapeamento(array $mapeamento): array {
    $hosts = [];
    $percorrer = static function (mixed $no) use (&$percorrer, &$hosts): void {
      if (!is_array($no)) {
        return;
      }
      foreach (['test_host', 'production_host'] as $chave) {
        if (isset($no[$chave]) && is_string($no[$chave]) && $no[$chave] !== '') {
          $hosts[] = $no[$chave];
        }
      }
      foreach ($no as $filho) {
        $percorrer($filho);
      }
    };
    $percorrer($mapeamento);
    $hosts = array_values(array_unique($hosts));
    sort($hosts);
    return $hosts;
  }

}
