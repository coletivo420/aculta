<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\aculta_calendar\Entity\CalendarImportedItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;

/**
 * Creates imported items idempotently by source and external UID.
 */
final class CalendarImportRepository {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Loads or creates one draft item for this source and external UID.
   */
  public function loadOrCreate(CalendarImportSource $source, string $externalUid): CalendarImportedItem {
    $existing = $this->findCurrent($source, $externalUid);
    if ($existing !== NULL) {
      return $existing;
    }

    $storage = $this->entityTypeManager->getStorage('aculta_calendar_imported_item');
    /** @var \Drupal\aculta_calendar\Entity\CalendarImportedItem $item */
    $item = $storage->create([
      'source' => $source->id(),
      'external_uid' => $externalUid,
      'title' => $externalUid,
      'start' => 0,
      'end' => 0,
      'import_state' => 'rascunho',
      'status' => FALSE,
    ]);
    $item->save();
    return $item;
  }

  /** Loads an item's current default revision for a source and UID. */
  public function findCurrent(CalendarImportSource $source, string $externalUid): ?CalendarImportedItem {
    $items = $this->entityTypeManager->getStorage('aculta_calendar_imported_item')
      ->loadByProperties([
        'source' => $source->id(),
        'external_uid' => $externalUid,
      ]);
    if ($items === []) {
      return NULL;
    }
    return reset($items);
  }

  /** Loads the latest revision, including a non-default review revision. */
  public function findLatest(CalendarImportSource $source, string $externalUid): ?CalendarImportedItem {
    $current = $this->findCurrent($source, $externalUid);
    if ($current === NULL) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_calendar_imported_item');
    if (!$storage instanceof RevisionableStorageInterface) {
      return $current;
    }
    $latestRevisionId = $storage->getLatestRevisionId($current->id());
    if ($latestRevisionId === NULL) {
      return $current;
    }
    $latest = $storage->loadRevision($latestRevisionId);
    return $latest instanceof CalendarImportedItem ? $latest : $current;
  }

  /** Loads all current items from a source, keyed by entity ID. */
  public function itemsForSource(CalendarImportSource $source): array {
    return $this->entityTypeManager->getStorage('aculta_calendar_imported_item')
      ->loadByProperties(['source' => $source->id()]);
  }

  /** Creates a draft from one normalized external event. */
  public function createFromEvent(CalendarImportSource $source, NormalizedCalendarEvent $event): CalendarImportedItem {
    $storage = $this->entityTypeManager->getStorage('aculta_calendar_imported_item');
    /** @var \Drupal\aculta_calendar\Entity\CalendarImportedItem $item */
    $item = $storage->create([
      'source' => $source->id(),
      'external_uid' => $event->uid,
      'title' => $event->title !== '' ? $event->title : $event->uid,
      'description' => $event->description,
      'location' => $event->location,
      'start' => $event->start->getTimestamp(),
      'end' => $event->end->getTimestamp(),
      'all_day' => $event->allDay,
      'timezone' => $event->timezone,
      'timezone_assumed' => $event->timezoneAssumed,
      'source_presence' => 'presente',
      'status' => FALSE,
    ]);
    $item->setImportState($event->importState());
    $item->save();
    return $item;
  }

  /** Applies normalized content and a local workflow state to a revision. */
  public function applyEvent(CalendarImportedItem $item, NormalizedCalendarEvent $event, string $state): void {
    $item->set('external_uid', $event->uid);
    $item->set('title', $event->title !== '' ? $event->title : $event->uid);
    $item->set('description', $event->description);
    $item->set('location', $event->location);
    $item->set('start', $event->start->getTimestamp());
    $item->set('end', $event->end->getTimestamp());
    $item->set('all_day', $event->allDay);
    $item->set('timezone', $event->timezone);
    $item->set('timezone_assumed', $event->timezoneAssumed);
    $item->set('source_presence', 'presente');
    $item->setImportState($state);
  }

}
