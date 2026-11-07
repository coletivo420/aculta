<?php

declare(strict_types=1);

namespace Drupal\aculta_paths\Validation;

/**
 * Valida slug de diretório e endereço de subdomínio, sem depender de Drupal.
 *
 * Devolve NULL se o valor está certo, ou um motivo com código e argumentos.
 * A tela traduz o código para a mensagem exibida ao usuário. Maiúsculas devem
 * ser convertidas para minúsculas antes da chamada.
 */
final class EnderecoValidator {

  /**
   * Informa o motivo de invalidação de um slug ou endereço de subdomínio.
   *
   * @param string $valor
   *   Valor já convertido para minúsculas.
   * @param bool $host
   *   Indica se o valor é um endereço de subdomínio.
   *
   * @return array{codigo: string, args: array<string, string>}|null
   *   Código e argumentos do motivo, ou NULL quando o valor é válido.
   */
  public static function motivo(string $valor, bool $host): ?array {
    if ($valor === '') {
      return ['codigo' => 'vazio', 'args' => []];
    }

    // Tudo que é permitido sai; o que sobra são os caracteres recusados.
    $sobra = (string) preg_replace($host ? '/[a-z0-9.\-]/' : '/[a-z0-9\-]/', '', $valor);
    $proibidos = array_values(array_unique(mb_str_split($sobra)));
    if ($proibidos !== [] && $proibidos[0] !== '') {
      $lista = implode(', ', array_map(
        static fn (string $c): string => $c === ' ' ? '«espaço»' : '«' . $c . '»',
        $proibidos,
      ));
      return ['codigo' => 'caracteres', 'args' => ['lista' => $lista, 'host' => $host ? '1' : '0']];
    }

    if (!$host) {
      return preg_match('/^-|-$|--/', $valor) ? ['codigo' => 'hifen_slug', 'args' => []] : NULL;
    }

    if (strlen($valor) > 253) {
      return ['codigo' => 'tamanho', 'args' => []];
    }
    if (!str_contains($valor, '.')) {
      return ['codigo' => 'sem_ponto', 'args' => []];
    }
    $trechos = explode('.', $valor);
    foreach ($trechos as $trecho) {
      if ($trecho === '') {
        return ['codigo' => 'ponto_sobrando', 'args' => []];
      }
      if (strlen($trecho) > 63) {
        return ['codigo' => 'trecho_longo', 'args' => []];
      }
      if (str_starts_with($trecho, '-') || str_ends_with($trecho, '-')) {
        return ['codigo' => 'trecho_hifen', 'args' => []];
      }
    }
    if (!preg_match('/^[a-z]{2,}$/', end($trechos))) {
      return ['codigo' => 'final', 'args' => []];
    }
    return NULL;
  }

}
