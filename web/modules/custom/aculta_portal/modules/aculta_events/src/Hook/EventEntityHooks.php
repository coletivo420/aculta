<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Hook;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Slug\EventSlugGenerator;
use Drupal\aculta_maps\Geocoding\GeocodingService;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Applies event and location normalization before content entities are saved.
 */
final class EventEntityHooks {

  public function __construct(
    private readonly EventSlugGenerator $slugGenerator,
    private readonly ?GeocodingService $geocoding = NULL,
  ) {}

  /**
   * Normalizes event slugs, address countries, and location coordinates.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity instanceof Event && $entity->get('field_slug')->isEmpty()) {
      $entity->set('field_slug', $this->slugGenerator->uniqueFor((string) $entity->label(), $entity->id() ? (int) $entity->id() : NULL));
    }
    if (!$entity instanceof EventLocation) {
      return;
    }

    if ($entity->hasField('field_endereco') && !$entity->get('field_endereco')->isEmpty()) {
      foreach ($entity->get('field_endereco') as $item) {
        $address = $item->getValue();
        if (($address['country_code'] ?? NULL) !== 'BR') {
          $item->setValue(['country_code' => 'BR'] + $address);
        }
      }
    }

    $original = $entity->getOriginal();
    $addressChanged = !$entity->isNew() && $original !== NULL
      && $original->get('field_endereco')->getValue() !== $entity->get('field_endereco')->getValue();
    $needsGeocoding = $addressChanged || $entity->get('field_coordenadas')->isEmpty();
    if ($this->geocoding === NULL || !$needsGeocoding) {
      return;
    }

    $address = $this->addressText($entity);
    $coordinates = $address !== '' ? $this->geocoding->geocode($address) : NULL;
    if ($coordinates !== NULL) {
      $entity->set('field_coordenadas', [
        'lat' => $coordinates['lat'],
        'lon' => $coordinates['lng'],
        'value' => sprintf('POINT (%F %F)', $coordinates['lng'], $coordinates['lat']),
        'geo_type' => 'Point',
      ]);
    }
    elseif ($addressChanged) {
      $entity->set('field_coordenadas', NULL);
    }
  }

  /**
   * Returns the location address as a geocoding query.
   */
  private function addressText(EventLocation $location): string {
    $item = $location->get('field_endereco')->first();
    if ($item === NULL) {
      return '';
    }
    $address = $item->getValue();
    return trim(implode(', ', array_filter([
      (string) ($address['address_line1'] ?? ''),
      (string) ($address['locality'] ?? ''),
      (string) ($address['administrative_area'] ?? ''),
      (string) ($address['postal_code'] ?? ''),
      'Brasil',
    ])));
  }

}
