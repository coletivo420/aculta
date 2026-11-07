<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Role;

/**
 * Cálculo puro da hierarquia de papéis (cadeia de papéis e herança).
 *
 * A cadeia vai do papel mais alto ao mais baixo; cada papel herda do papel imediatamente abaixo,
 * e a herança é transitiva.
 * Papel que não está na cadeia herda do autenticado. O administrador herda de todos os papéis do site. Mapa de herança:
 * papel => papéis dos quais herda. Não há papel lateral nem categoria: a ordem da cadeia é a única classificação.
 */
final class RoleHierarchy {

  /**
   * Papéis que não entram na cadeia editável. */
  public const FORA_DA_CADEIA = ['anonymous', 'administrator'];

  /**
   * Papéis herdados por um papel (direta ou transitivamente), sem o próprio papel.
   *
   * @param array<string, string[]> $mapa
   *   Mapa de herança no formato papel => papéis dos quais herda.
   * @param string $papel
   *   Papel cujos herdados serão calculados.
   *
   * @return string[]
   *   Papéis herdados pelo papel informado.
   */
  public static function efetivos(array $mapa, string $papel): array {
    $resultado = [];
    $pilha = $mapa[$papel] ?? [];
    while ($pilha !== []) {
      $atual = array_shift($pilha);
      if ($atual === $papel || in_array($atual, $resultado, TRUE)) {
        continue;
      }
      $resultado[] = $atual;
      array_push($pilha, ...($mapa[$atual] ?? []));
    }
    return $resultado;
  }

  /**
   * Verdadeiro se o mapa contém autorreferência ou ciclo (ex.: A herda de B e B herda de A).
   *
   * @param array<string, string[]> $mapa
   *   Mapa de herança no formato papel => papéis dos quais herda.
   *
   * @return bool
   *   VERDADEIRO se houver autorreferência ou ciclo no mapa.
   */
  public static function temCiclo(array $mapa): bool {
    foreach (array_keys($mapa) as $papel) {
      $pilha = $mapa[$papel] ?? [];
      $visitados = [];
      while ($pilha !== []) {
        $atual = array_shift($pilha);
        if ($atual === $papel) {
          return TRUE;
        }
        if (in_array($atual, $visitados, TRUE)) {
          continue;
        }
        $visitados[] = $atual;
        array_push($pilha, ...($mapa[$atual] ?? []));
      }
    }
    return FALSE;
  }

  /**
   * Mapa de herança a partir da cadeia.
   *
   * - Cada papel da cadeia herda do papel imediatamente abaixo.
   * - Papel de $todos fora da cadeia (e não anônimo nem administrador) herda do autenticado.
   * - Administrador herda de todos os demais papéis.
   *
   * @param string[] $cadeia
   *   Papéis da cadeia, do mais alto para o mais baixo (sem administrator).
   * @param string[] $todos
   *   Papéis existentes no site (inclui anonymous e administrator).
   *
   * @return array<string, string[]>
   *   Mapa de herança derivado da cadeia.
   */
  public static function derivarMapa(array $cadeia, array $todos): array {
    $mapa = [];
    $total = count($cadeia);
    for ($i = 0; $i < $total - 1; $i++) {
      $mapa[$cadeia[$i]] = [$cadeia[$i + 1]];
    }
    foreach ($todos as $papel) {
      if (!in_array($papel, $cadeia, TRUE) && !in_array($papel, self::FORA_DA_CADEIA, TRUE)) {
        $mapa[$papel] = ['authenticated'];
      }
    }
    $mapa['administrator'] = array_values(array_diff($todos, ['administrator']));
    return $mapa;
  }

  /**
   * Papéis herdados pelos papéis informados (sem os próprios).
   *
   * @param string[] $papeis
   *   Papéis cujos herdados serão reunidos.
   * @param array<string, string[]> $mapa
   *   Mapa de herança no formato papel => papéis dos quais herda.
   *
   * @return string[]
   *   Papéis herdados pelos papéis informados, sem os próprios.
   */
  public static function herdados(array $papeis, array $mapa): array {
    $herdados = [];
    foreach ($papeis as $papel) {
      foreach (self::efetivos($mapa, $papel) as $herdado) {
        if (!in_array($herdado, $papeis, TRUE) && !in_array($herdado, $herdados, TRUE)) {
          $herdados[] = $herdado;
        }
      }
    }
    return $herdados;
  }

  /**
   * Cadeia coerente com os papéis existentes: só papéis que existem, sem repetição, na ordem informada.
   *
   * Papel excluído sai da cadeia. Papel novo não entra aqui: ele herda do autenticado por derivarMapa().
   *
   * @param string[] $cadeia
   *   Papéis da cadeia na ordem informada.
   * @param string[] $existentes
   *   Papéis que existem atualmente no site.
   *
   * @return string[]
   *   Cadeia contendo apenas papéis existentes, sem repetição.
   */
  public static function completar(array $cadeia, array $existentes): array {
    $saida = [];
    foreach ($cadeia as $papel) {
      if (in_array($papel, $existentes, TRUE) && !in_array($papel, $saida, TRUE)) {
        $saida[] = $papel;
      }
    }
    return $saida;
  }

}
