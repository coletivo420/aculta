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


  /**
   * Mapa de herança derivado da lista ordenada (cadeia) e dos papéis laterais.
   *
   * - Cadeia: de cima para baixo; cada papel herda do papel imediatamente abaixo dele.
   * - Laterais: papel que entra em uma categoria da cadeia; herda dela.
   * - Administrador: herda de todos os papéis do site.
   *
   * @param string[] $cadeia
   *   Papéis da cadeia, do mais alto para o mais baixo (sem administrator).
   * @param array<string, string> $laterais
   *   Papel lateral => papel pai.
   * @param string[] $todos
   *   Todos os papéis do site (inclui anonymous).
   *
   * @return array<string, string[]>
   */
  public static function derivarMapa(array $cadeia, array $laterais, array $todos): array {
    $mapa = [];
    $total = count($cadeia);
    for ($i = 0; $i < $total - 1; $i++) {
      $mapa[$cadeia[$i]] = [$cadeia[$i + 1]];
    }
    // Papel que entra em uma categoria (ex.: colaborador da wiki em e-mail confirmado) herda da própria categoria,
    // e por ela recebe tudo o que a categoria herda.
    foreach ($laterais as $papel => $categoria) {
      $mapa[$papel] = [$categoria];
    }
    $mapa['administrator'] = array_values(array_diff($todos, ['administrator']));
    return $mapa;
  }


  /**
   * Papéis herdados pelos papéis informados (sem os próprios), a partir do mapa de herança.
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
   * Configuração da cadeia coerente com os papéis existentes.
   *
   * - Cadeia: só papéis existentes que não são laterais.
   * - Laterais: papel existente fora da cadeia, com a categoria na cadeia; sem categoria válida, herda do autenticado.
   * - Papel existente que não está em lugar nenhum entra como lateral do autenticado (papel novo).
   * - Categoria que deixou de existir (papel excluído) volta ao autenticado.
   *
   * @param string[] $cadeia
   * @param array<string, string> $laterais
   * @param string[] $existentes
   *   Papéis que existem no site, sem anônimo e administrador.
   *
   * @return array{cadeia: string[], laterais: array<string, string>}
   */
  public static function completar(array $cadeia, array $laterais, array $existentes): array {
    $cadeia = array_values(array_filter(
      $cadeia,
      static fn (string $papel): bool => in_array($papel, $existentes, TRUE) && !array_key_exists($papel, $laterais),
    ));
    $saida = [];
    foreach ($laterais as $papel => $categoria) {
      if (!in_array($papel, $existentes, TRUE) || in_array($papel, $cadeia, TRUE)) {
        continue;
      }
      $saida[$papel] = in_array($categoria, $cadeia, TRUE) ? $categoria : 'authenticated';
    }
    foreach ($existentes as $papel) {
      if (!in_array($papel, $cadeia, TRUE) && !array_key_exists($papel, $saida)) {
        $saida[$papel] = 'authenticated';
      }
    }
    // Sem autenticado na cadeia não há onde pendurar os laterais: eles ficam sem pai.
    if (!in_array('authenticated', $cadeia, TRUE)) {
      $saida = array_filter($saida, static fn (string $categoria): bool => in_array($categoria, $cadeia, TRUE));
    }
    return ['cadeia' => $cadeia, 'laterais' => $saida];
  }

}
