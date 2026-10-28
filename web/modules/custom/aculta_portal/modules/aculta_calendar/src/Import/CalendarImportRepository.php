<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\aculta_calendar\Entity\CalendarImportedItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
    $storage = $this->entityTypeManager->getStorage('aculta_calendar_imported_item');
    $existing = $storage->loadByProperties([
      'source' => $source->id(),
      'external_uid' => $externalUid,
    ]);
    if ($existing !== []) {
      return reset($existing);
    }

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

}
