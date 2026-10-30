<?php

declare(strict_types=1);

namespace Drupal\aculta_events\ContentIndex;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexOwnerResolver;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Projeta eventos na infraestrutura transversal aculta_index.
 */
final class EventContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'event';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 4;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['aculta_event' => ['aculta_event']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof Event ? 'eventos' : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    // Apenas eventos publicados são elegíveis à descoberta pública no índice.
    return $entity instanceof Event
      && $entity->hasField('field_status')
      && !$entity->get('field_status')->isEmpty()
      && (string) $entity->get('field_status')->value === 'publicado';
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    $relations = [];
    $this->addRelations($entity, 'field_tipo', 'event_type', 'taxonomy_term', $relations);
    // Identidade canônica de organizador: relações públicas usam a entidade canônica
    // de organização (aculta_event_organization), nunca contas de usuário (user:<uid>).
    $this->addRelations($entity, 'field_organizacao', 'organizer', 'aculta_event_organization', $relations);
    $this->addRelations($entity, 'field_coorganizadores', 'co_organizer', 'aculta_event_organization', $relations);
    $this->addLocationRelations($entity, $relations);

    $isPublished = (string) $entity->get('field_status')->value === 'publicado';

    return new ContentIndexProjection(
      'aculta_event',
      (string) $entity->id(),
      $entity->language()->getId(),
      'aculta_event',
      (string) $entity->uuid(),
      (string) $entity->label(),
      'eventos_aculta_org',
      $isPublished,
      0,
      0,
      relations: array_values($relations),
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

  /**
   * Adiciona relações Evento > Local derivadas das datas ativas do evento.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entidade do evento.
   * @param array<string, \Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Vetor de relações acumuladas.
   */
  private function addLocationRelations(ContentEntityInterface $entity, array &$relations): void {
    $dateStorage = $this->entityTypeManager->getStorage('aculta_event_date');
    $dateIds = $dateStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_evento', (int) $entity->id())
      ->execute();

    if (empty($dateIds)) {
      return;
    }

    $dates = $dateStorage->loadMultiple($dateIds);
    $locStorage = $this->entityTypeManager->getStorage('aculta_event_location');
    $anonUser = new AnonymousUserSession();

    foreach ($dates as $date) {
      if (!$date instanceof ContentEntityInterface) {
        continue;
      }
      // Datas com modalidade exclusivamente virtual/online não projetam local físico.
      if ($date->hasField('field_modo') && (string) $date->get('field_modo')->value === 'online') {
        continue;
      }

      if (!$date->hasField('field_local') || $date->get('field_local')->isEmpty()) {
        continue;
      }
      $localId = (int) ($date->get('field_local')->target_id ?? 0);
      if ($localId <= 0) {
        continue;
      }

      $key = 'event_location:aculta_event_location:' . $localId;
      if (isset($relations[$key])) {
        continue;
      }

      /** @var \Drupal\aculta_events\Entity\EventLocation|null $location */
      $location = $locStorage->load($localId);
      // Relação só deve existir para locais publicamente descobríveis (LOC-04).
      // Trata campo status explicitamente sem presumir publicação em ausência.
      if (
        $location instanceof EventLocation
        && $location->hasField('field_status')
        && !$location->get('field_status')->isEmpty()
        && (string) $location->get('field_status')->value === 'publicado'
        && $location->access('view', $anonUser)
      ) {
        $relations[$key] = new ContentIndexRelation('event_location', 'aculta_event_location', (string) $localId);
      }
    }
  }

  /**
   * Adiciona relações para campos de referência configurados e preenchidos.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entidade fonte.
   * @param string $fieldName
   *   Nome do campo de referência.
   * @param string $relationType
   *   Tipo estável da relação no índice.
   * @param string $targetEntityType
   *   Tipo da entidade de destino.
   * @param array<string, \Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Vetor de relações indexadas por chave para evitar duplicatas.
   */
  private function addRelations(
    ContentEntityInterface $entity,
    string $fieldName,
    string $relationType,
    string $targetEntityType,
    array &$relations,
  ): void {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return;
    }

    foreach ($entity->get($fieldName)->getValue() as $item) {
      $targetId = is_array($item) ? ($item['target_id'] ?? NULL) : NULL;
      if ($targetId === NULL || $targetId === '') {
        continue;
      }
      $targetId = (string) $targetId;
      $key = implode(':', [$relationType, $targetEntityType, $targetId]);
      $relations[$key] = new ContentIndexRelation($relationType, $targetEntityType, $targetId);
    }
  }

}
