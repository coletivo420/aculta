<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Role;

/**
 * Cálculo puro da hierarquia de papéis (mapa do role_inheritance: papel => papéis dos quais herda).
 *
 * A herança é transitiva no role_inheritance (cada papel recebe tudo o que está acima dele na cadeia). Esta classe
 * só calcula a cadeia efetiva para exibir no painel e rejeita mapas com ciclo ou autorreferência antes de salvar.
 */
final class RoleHierarchy {

  /**
   * Papéis efetivos de um papel: todos os papéis dos quais ele herda, direta ou transitivamente.
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

}
