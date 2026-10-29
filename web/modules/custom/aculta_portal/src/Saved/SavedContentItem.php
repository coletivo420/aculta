<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Saved;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Immutable value object representing a single authorized saved or followed item.
 */
final class SavedContentItem {

  public function __construct(
    private readonly string $entityTypeId,
    private readonly string|int $entityId,
    private readonly EntityInterface $entity,
    private readonly string $flagId,
    private readonly int $createdTime,
    private readonly string $category,
    private readonly ?string $purpose = NULL,
  ) {}

  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  public function getEntityId(): string|int {
    return $this->entityId;
  }

  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  public function getFlagId(): string {
    return $this->flagId;
  }

  public function getCreatedTime(): int {
    return $this->createdTime;
  }

  public function getCategory(): string {
    return $this->category;
  }

  public function getPurpose(): ?string {
    return $this->purpose;
  }

  public function getLabel(): string {
    return (string) ($this->entity->label() ?? '');
  }

  public function getUrl(): ?Url {
    try {
      return $this->entity->toUrl();
    }
    catch (\Throwable) {
      return NULL;
    }
  }

}
