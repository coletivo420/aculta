<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Item;

/**
 * Agrega os provedores de itens registrados (tag aculta_calendar.item_provider).
 */
final class CalendarItemSource {

  /**
   * Construtor.
   *
   * @param iterable<CalendarItemProviderInterface> $provedores
   *   Provedores coletados pela tag.
   */
  public function __construct(
    private readonly iterable $provedores,
  ) {}

  /**
   * Todos os itens dos provedores para a consulta.
   *
   * @return array<int, \Drupal\aculta_calendar\Item\CalendarItem>
   */
  public function items(CalendarQuery $query): array {
    $itens = [];
    foreach ($this->provedores as $provedor) {
      foreach ($provedor->items($query) as $item) {
        $itens[] = $item;
      }
    }
    return $itens;
  }

  /**
   * Definições de filtro agregadas.
   *
   * Chaves iguais entre provedores são mescladas, com as opções de todos.
   *
   * @return array<string, array{label: string, opcoes: array<int, string>}>
   */
  public function filtros(): array {
    $filtros = [];
    foreach ($this->provedores as $provedor) {
      foreach ($provedor->filtros() as $chave => $definicao) {
        if (!isset($filtros[$chave])) {
          $filtros[$chave] = $definicao;
          continue;
        }
        $filtros[$chave]['opcoes'] = array_replace($filtros[$chave]['opcoes'], $definicao['opcoes'] ?? []);
      }
    }
    return $filtros;
  }

  /**
   * Tags de cache agregadas dos provedores.
   *
   * @return string[]
   */
  public function cacheTags(): array {
    $tags = [];
    foreach ($this->provedores as $provedor) {
      $tags = array_merge($tags, $provedor->cacheTags());
    }
    return array_values(array_unique($tags));
  }

}
