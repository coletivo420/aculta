<?php

declare(strict_types=1);

namespace Drupal\aculta_events\ContentIndex;

use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_portal\ContentIndex\ContentIndexAccessContextProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexIndexabilityProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexRelation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Projeta organizações culturais no aculta_index (ARQ-05).
 */
final class OrganizationContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getProviderId(): string {
    return 'event_organization';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    return ['aculta_event_organization' => ['aculta_event_organization']];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof EventOrganization ? 'eventos' : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof EventOrganization
      && $entity->isPublished()
      && $entity->hasField('field_status')
      && !$entity->get('field_status')->isEmpty()
      && (string) $entity->get('field_status')->value === 'publicado';
  }

  /**
   * {@inheritdoc}
   */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity) || !$entity instanceof EventOrganization) {
      return NULL;
    }

    $relations = [];

    // Localidade: só entra na projeção se a organização não optou
    // por ocultá-la. Endereço completo e CEP nunca entram no índice padrão.
    if (!$entity->shouldHideLocation()) {
      $uf = (string) $entity->get('uf')->value;
      if ($uf !== '') {
        $relations[] = new ContentIndexRelation(
          type: 'location_uf',
          targetEntityTypeId: 'uf',
          targetEntityId: mb_strtoupper($uf),
        );
      }
    }

    // Para organização, o proprietário no índice representa a titularidade
    // oficial (campo titular). Organizações não reivindicadas ou pendentes
    // possuem titular NULL e não devem vincular o criador técnico no índice.
    $titularId = $entity->get('titular')->target_id;
    $ownerUid = !empty($titularId) ? (int) $titularId : NULL;

    return new ContentIndexProjection(
      'aculta_event_organization',
      (string) $entity->id(),
      $entity->language()->getId(),
      'aculta_event_organization',
      (string) $entity->uuid(),
      (string) $entity->label(),
      'eventos_aculta_org',
      TRUE,
      0,
      0,
      relations: array_values($relations),
      ownerUid: $ownerUid,
    );
  }

}
