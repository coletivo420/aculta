<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Hook;

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Discovers source entity changes for the derived Content Index.
 */
final class ContentIndexEntityHooks {

  public function __construct(
    #[Autowire(service: 'aculta_index.provider_registry')]
    private readonly ContentIndexProviderRegistry $providers,
    #[Autowire(service: 'aculta_index.work_manager')]
    private readonly ContentIndexWorkManager $workManager,
  ) {}

  /**
   * Schedules a projection after an entity is inserted.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->scheduleEntity($entity, 'project');
  }

  /**
   * Schedules a projection after an entity is updated.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->scheduleEntity($entity, 'project');
  }

  /**
   * Schedules removal after an entity is deleted.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->scheduleEntity($entity, 'delete');
  }

  /**
   * Schedules removal of one deleted translation.
   */
  #[Hook('entity_translation_delete')]
  public function entityTranslationDelete(EntityInterface $translation): void {
    $this->scheduleTranslation($translation, 'delete');
  }

  /**
   * Schedules every extant translation when a content entity changes.
   */
  private function scheduleEntity(EntityInterface $entity, string $operation): void {
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    foreach ($entity->getTranslationLanguages() as $langcode => $language) {
      if ($entity->hasTranslation($langcode)) {
        $this->scheduleTranslation($entity->getTranslation($langcode), $operation);
      }
    }
  }

  /**
   * Schedules one translation only when an enabled provider owns its bundle.
   */
  private function scheduleTranslation(EntityInterface $translation, string $operation): void {
    if (!$translation instanceof ContentEntityInterface) {
      return;
    }
    $providerId = $this->providers->findProviderIdForBundle($translation->getEntityTypeId(), $translation->bundle());
    if ($providerId === NULL) {
      return;
    }
    $this->workManager->schedule(
      $providerId,
      $translation->getEntityTypeId(),
      (string) $translation->id(),
      $translation->language()->getId(),
      $operation,
    );
  }

}
