<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Exposes published imported items through the public calendar API. */
final class ImportedCalendarItemProvider implements CalendarItemProviderInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function id(): string {
    return 'aculta_calendar_import';
  }

  public function items(CalendarQuery $query): iterable {
    $storage = $this->entityTypeManager->getStorage('aculta_calendar_imported_item');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('start', $query->ate->getTimestamp(), '<=')
      ->condition('end', $query->de->getTimestamp(), '>=')
      ->execute();
    foreach ($storage->loadMultiple($ids) as $entity) {
      $timezone = (string) $entity->get('timezone')->value;
      try {
        $zone = new \DateTimeZone($timezone);
      }
      catch (\Exception) {
        $zone = new \DateTimeZone('America/Sao_Paulo');
      }
      yield new CalendarItem(
        id: 'importado:' . $entity->id(),
        titulo: (string) $entity->get('title')->value,
        inicio: (new \DateTimeImmutable('@' . $entity->get('start')->value))->setTimezone($zone),
        fim: (new \DateTimeImmutable('@' . $entity->get('end')->value))->setTimezone($zone),
        diaInteiro: (bool) $entity->get('all_day')->value,
        fuso: $zone->getName(),
        local: (string) $entity->get('location')->value,
        origem: $this->id(),
      );
    }
  }

  public function filtros(): array {
    return [];
  }

  public function cacheTags(): array {
    return ['aculta_calendar_imported_item_list'];
  }

}
