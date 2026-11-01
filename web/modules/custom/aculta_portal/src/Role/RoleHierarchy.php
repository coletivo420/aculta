<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Role;

/**
 * Cálculo puro da hierarquia de papéis (cadeia de papéis e herança).
 *
 * A cadeia vai do papel mais alto ao mais baixo; cada papel herda do papel imediatamente abaixo, e a herança é transitiva.
 * Papel que não está na cadeia herda do autenticado. O administrador herda de todos os papéis do site. Mapa de herança:
 * papel => papéis dos quais herda. Não há papel lateral nem categoria: a ordem da cadeia é a única classificação.
 */
final class RoleHierarchy {

  /** Papéis que não entram na cadeia editável. */
  public const FORA_DA_CADEIA = ['anonymous', 'administrator'];

  /**
   * Papéis herdados por um papel (direta ou transitivamente), sem o próprio papel.
   *
   * @param array<string, string[]> $mapa
   *
   * @return string[]
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
   *   Todos os papéis do site (inclui anonymous e administrator).
   *
   * @return array<string, string[]>
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
   * @param array<string, string[]> $mapa
   *
   * @return string[]
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
   * @param string[] $existentes
   *
   * @return string[]
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
