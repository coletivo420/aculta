<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\ContentIndex;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\media\MediaInterface;

/**
 * Reusable minimal Media projection configured by its owning module.
 *
 * The provider stores only the media entity identity, name, language, dates,
 * publication state and bundle. It never copies source URLs, file IDs, URIs,
 * alt text or document contents. Media timestamp accessors may return numeric
 * strings after storage loads, so normalize them before building the
 * projection.
 */
final class MediaContentIndexProvider implements ContentIndexProviderInterface, ContentIndexIndexabilityProviderInterface, ContentIndexAccessContextProviderInterface {

  public function __construct(
    private readonly string $providerId,
    private readonly string $bundle,
    private readonly ?string $accessPurpose = NULL,
  ) {
    foreach ([$providerId, $bundle] as $machineName) {
      if (preg_match('/^[a-z][a-z0-9_]*$/', $machineName) !== 1) {
        throw new \InvalidArgumentException('Media Content Index provider ID and bundle must be machine names.');
      }
    }
    if ($accessPurpose !== NULL && preg_match('/^[a-z][a-z0-9_]*$/', $accessPurpose) !== 1) {
      throw new \InvalidArgumentException('Media Content Index access purpose must be a machine name.');
    }
  }

  /**
   * {@inheritdoc} */
  public function getProviderId(): string {
    return $this->providerId;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectionVersion(): int {
    return 2;
  }

  /**
   * {@inheritdoc} */
  public function getSupportedEntityTypes(): array {
    return ['media' => [$this->bundle]];
  }

  /**
   * {@inheritdoc} */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string {
    return $entity instanceof MediaInterface && $entity->bundle() === $this->bundle
      ? $this->accessPurpose
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isIndexable(ContentEntityInterface $entity): bool {
    return $entity instanceof MediaInterface && $entity->bundle() === $this->bundle;
  }

  /**
   * {@inheritdoc} */
  public function project(ContentEntityInterface $entity): ?ContentIndexProjection {
    if (!$this->isIndexable($entity)) {
      return NULL;
    }

    return new ContentIndexProjection(
      'media',
      (string) $entity->id(),
      $entity->language()->getId(),
      $this->bundle,
      (string) $entity->uuid(),
      (string) $entity->getName(),
      NULL,
      $entity->isPublished(),
      max(0, (int) $entity->getCreatedTime()),
      max(0, (int) $entity->getChangedTime()),
      ownerUid: ContentIndexOwnerResolver::resolve($entity),
    );
  }

}
