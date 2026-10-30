<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Event;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Builds the list of Dates managed inside an Event.
 *
 * The same structure is used by the Event edit form and by the AJAX responses
 * that refresh it after creating, editing, deleting or reordering a Date, so
 * the parent form keeps its unsaved values.
 */
final class EventDateListBuilder {

  use StringTranslationTrait;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Builds the container replaced after every Date change.
   */
  public function build(Event $event): array {
    $dates = $this->loadDates($event);
    $rows = [];
    foreach ($dates as $date) {
      $quando = $date->get('field_quando')->first();
      $local = $date->get('field_local')->entity;
      $rows[] = [
        (string) $date->label(),
        $this->formatDate($quando?->value),
        $this->formatDate($quando?->end_value),
        $local instanceof EventLocation ? (string) $local->label() : '',
        ['data' => $this->buildActions($event, $date)],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-dates-list'],
      '#cache' => [
        'tags' => ['aculta_event_date_list'],
        'contexts' => ['user.permissions'],
      ],
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Título'),
        $this->t('Início'),
        $this->t('Fim'),
        $this->t('Local'),
        $this->t('Ações'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Este evento ainda não tem datas.'),
    ];
    $build['actions'] = ['#type' => 'actions'];
    $build['actions']['add'] = [
      '#type' => 'link',
      '#title' => $this->t('Adicionar data a este evento'),
      '#url' => Url::fromRoute('aculta_events.event_date_add', ['aculta_event' => $event->id()]),
      '#attributes' => $this->dialogAttributes('button button--primary'),
    ];
    if (count($dates) > 1) {
      $build['actions']['order'] = [
        '#type' => 'link',
        '#title' => $this->t('Ordenar datas'),
        '#url' => Url::fromRoute('aculta_events.event_date_order', ['aculta_event' => $event->id()]),
        '#attributes' => $this->dialogAttributes('button'),
      ];
    }

    return $build;
  }

  /**
   * Loads the Event Dates ordered by editorial weight, then start.
   *
   * @return \Drupal\aculta_events\Entity\EventDate[]
   *   Datas ordenadas.
   */
  public function loadDates(Event $event): array {
    $storage = $this->entities->getStorage('aculta_event_date');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_evento', (int) $event->id())
      ->execute();
    $dates = $storage->loadMultiple($ids);
    usort($dates, static function (EventDate $a, EventDate $b): int {
      $pa = (int) ($a->get('field_peso')->value ?? 0);
      $pb = (int) ($b->get('field_peso')->value ?? 0);
      if ($pa !== $pb) {
        return $pa <=> $pb;
      }
      return (string) $a->get('field_quando')->value <=> (string) $b->get('field_quando')->value;
    });
    return array_values($dates);
  }

  /**
   * Attributes for a link that opens the Drupal dialog.
   */
  public function dialogAttributes(string $classes = 'button'): array {
    return [
      'class' => array_values(array_filter(explode(' ', $classes . ' use-ajax'))),
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode(['width' => 720]),
    ];
  }

  /**
   * Actions to edit or remove a Date inside the Event.
   */
  private function buildActions(Event $event, EventDate $date): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-event-date-actions']],
      'edit' => [
        '#type' => 'link',
        '#title' => $this->t('Editar'),
        '#url' => Url::fromRoute('aculta_events.event_date_edit', [
          'aculta_event' => $event->id(),
          'aculta_event_date' => $date->id(),
        ]),
        '#attributes' => $this->dialogAttributes(),
      ],
      'delete' => [
        '#type' => 'link',
        '#title' => $this->t('Remover'),
        '#url' => Url::fromRoute('aculta_events.event_date_delete', [
          'aculta_event' => $event->id(),
          'aculta_event_date' => $date->id(),
        ]),
        '#attributes' => $this->dialogAttributes(),
      ],
    ];
  }

  /**
   * Formats a stored UTC value in the site timezone.
   */
  private function formatDate(?string $value): string {
    if ($value === NULL || $value === '') {
      return '';
    }
    try {
      return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
        ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        ->format('d/m/Y H:i');
    }
    catch (\Exception) {
      return $value;
    }
  }

}
