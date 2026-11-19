<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Calendar;

use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_events\Calendar\EventCalendarItemProvider;
use Drupal\Core\Session\AccountInterface;

/**
 * Provedor de itens de calendário para eventos publicados visíveis (Fase 1).
 */
final class StudioEventsCalendarItemProvider implements CalendarItemProviderInterface {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly ?EventCalendarItemProvider $eventsProvider,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'eventos';
  }

  /**
   * {@inheritdoc}
   */
  public function items(CalendarQuery $query): iterable {
    if ($this->eventsProvider === NULL) {
      return [];
    }
    // Repassa para o EventCalendarItemProvider existente.
    foreach ($this->eventsProvider->items($query) as $item) {
      yield new CalendarItem(
        id: $item->id,
        titulo: $item->titulo,
        inicio: $item->inicio,
        fim: $item->fim,
        diaInteiro: $item->diaInteiro,
        fuso: $item->fuso,
        local: $item->local,
        link: $item->link,
        origem: $this->id(),
        rrule: $item->rrule,
        excecoes: $item->excecoes,
        reagendadas: $item->reagendadas,
        cancelado: $item->cancelado,
        limiteMeses: $item->limiteMeses,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filtros(): array {
    return $this->eventsProvider?->filtros() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheTags(): array {
    return $this->eventsProvider?->cacheTags() ?? ['aculta_event_date_list', 'aculta_event_list'];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheContexts(): array {
    return ['user'];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheMaxAge(): int {
    return 900;
  }

}
