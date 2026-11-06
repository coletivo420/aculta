<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Calendar;

use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_lms_agenda\AgendaEntriesProvider;
use Drupal\Core\Session\AccountInterface;

/**
 * Provedor de itens de calendário para cursos e atividades do usuário (Fase 1).
 */
final class StudioCoursesCalendarItemProvider implements CalendarItemProviderInterface {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly ?AgendaEntriesProvider $entries,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'cursos';
  }

  /**
   * {@inheritdoc}
   */
  public function items(CalendarQuery $query): iterable {
    if (!$this->currentUser->isAuthenticated() || $this->entries === NULL) {
      return [];
    }
    foreach ($this->entries->forAccount($this->currentUser, $query->de, $query->ate) as $entry) {
      yield new CalendarItem(
        id: $entry['key'],
        titulo: $entry['titulo'],
        inicio: $entry['inicio'],
        fim: $entry['fim'],
        fuso: 'America/Sao_Paulo',
        origem: $this->id(),
        cancelado: $entry['cancelada'],
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filtros(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheTags(): array {
    return ['aculta_event_list', 'aculta_event_date_list', 'lms_class_activity_due_list', 'group_content_list'];
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
    return 0;
  }

}
