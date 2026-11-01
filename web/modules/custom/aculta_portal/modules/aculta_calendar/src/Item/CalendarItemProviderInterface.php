<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Item;

/**
 * Provedor de itens de calendário.
 *
 * Registra-se com a tag `aculta_calendar.item_provider`.
 *
 * O provedor é o único que conhece o dono dos dados. Ele devolve séries ou
 * ocorrências como CalendarItem; a expansão da regra fica no calendário.
 */
interface CalendarItemProviderInterface {

  /**
   * Identificador do provedor (ex.: aculta_events).
   */
  public function id(): string;

  /**
   * Itens candidatos para a consulta.
   *
   * O provedor pode devolver itens fora da janela; o calendário descarta os que
   * não têm ocorrência nela.
   *
   * @return iterable<\Drupal\aculta_calendar\Item\CalendarItem>
   *   Calendar items for the query.
   */
  public function items(CalendarQuery $query): iterable;

  /**
   * Definições de filtro exibidas nas visões de agenda e de mês.
   *
   * @return array<string, array{label: string, opcoes: array<int, string>}>
   *   Chave do filtro (vai na URL), rótulo e opções (id => rótulo).
   */
  public function filtros(): array;

  /**
   * Tags de cache que mudam quando os itens deste provedor mudam.
   *
   * @return string[]
   *   Cache tags.
   */
  public function cacheTags(): array;

  /**
   * Cache contexts required by data returned for the current request.
   *
   * @return string[]
   *   Cache contexts such as `user` for account-scoped items.
   */
  public function cacheContexts(): array;

  /**
   * Maximum cache age for returned items.
   *
   * @return int
   *   Cache max-age in seconds or Cache::PERMANENT.
   */
  public function cacheMaxAge(): int;

}
