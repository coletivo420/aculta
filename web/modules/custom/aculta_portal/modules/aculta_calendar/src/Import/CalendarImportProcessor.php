<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\aculta_calendar\Entity\CalendarImportedItem;

/**
 * Applies a complete local-file import without deleting existing records.
 */
final class CalendarImportProcessor {

  public function __construct(
    private readonly CalendarImportRepository $repository,
  ) {}

  /**
   * Reconciles normalized events from one source with saved imported items.
   *
   * @param \Drupal\aculta_calendar\Entity\CalendarImportSource $source
   *   Configured source whose existing items are reconciled.
   * @param \Drupal\aculta_calendar\Import\NormalizedCalendarEvent[] $events
   *   Events parsed and sanitized from a local iCalendar file.
   *
   * @return array{created: int, updated: int, unchanged: int, absent: int}
   *   Processing counters.
   */
  public function process(CalendarImportSource $source, array $events): array {
    $incoming = [];
    foreach ($events as $event) {
      if (!$event instanceof NormalizedCalendarEvent) {
        throw new \InvalidArgumentException('Calendar import processor accepts only normalized events.');
      }
      if (isset($incoming[$event->uid])) {
        throw new \InvalidArgumentException("UID duplicado no arquivo ICS: {$event->uid}.");
      }
      $incoming[$event->uid] = $event;
    }

    $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'absent' => 0];
    foreach ($incoming as $uid => $event) {
      $current = $this->repository->findCurrent($source, $uid);
      if ($current === NULL) {
        $this->repository->createFromEvent($source, $event);
        $result['created']++;
        continue;
      }

      $latest = $this->repository->findLatest($source, $uid) ?? $current;
      if ($this->contentMatches($latest, $event)) {
        if ($current->get('source_presence')->value !== 'presente') {
          $current->set('source_presence', 'presente')->save();
        }
        $result['unchanged']++;
        continue;
      }

      // A new external version of published content is a non-default draft
      // revision. The current default revision remains visible until review.
      $keepPublishedDefault = $current->isPublished();
      $latest->setNewRevision(TRUE);
      $latest->isDefaultRevision(!$keepPublishedDefault);
      $state = $event->status === 'CANCELLED' ? 'cancelado' : 'rascunho';
      $this->repository->applyEvent($latest, $event, $state);
      $latest->isDefaultRevision(!$keepPublishedDefault);
      $latest->save();
      $result['updated']++;
    }

    // Mark removed UIDs as absent without deleting or unpublishing their items.
    foreach ($this->repository->itemsForSource($source) as $current) {
      $uid = (string) $current->get('external_uid')->value;
      if (!isset($incoming[$uid]) && $current->get('source_presence')->value !== 'ausente') {
        $current->set('source_presence', 'ausente')->save();
        $result['absent']++;
      }
    }

    $source->set('lastSync', time());
    $source->set('lastResult', 'success');
    $source->save();
    return $result;
  }

  /**
   * Compares normalized external content with the latest saved revision. */
  private function contentMatches(CalendarImportedItem $item, NormalizedCalendarEvent $event): bool {
    $cancelled = $event->status === 'CANCELLED';
    $stateCompatible = $cancelled
      ? $item->get('import_state')->value === 'cancelado'
      : $item->get('import_state')->value !== 'cancelado';

    return $stateCompatible
      && $item->get('external_uid')->value === $event->uid
      && $item->get('title')->value === ($event->title !== '' ? $event->title : $event->uid)
      && (string) ($item->get('description')->value ?? '') === $event->description
      && (string) ($item->get('location')->value ?? '') === $event->location
      && (int) $item->get('start')->value === $event->start->getTimestamp()
      && (int) $item->get('end')->value === $event->end->getTimestamp()
      && (bool) $item->get('all_day')->value === $event->allDay
      && $item->get('timezone')->value === $event->timezone
      && (bool) $item->get('timezone_assumed')->value === $event->timezoneAssumed;
  }

}
