<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Saved;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Value object representing a single authorized saved or followed item.
 */
final class SavedContentItem {

  /**
   * Constructs a SavedContentItem instance.
   *
   * @param string $entityTypeId
   *   Entity type ID.
   * @param string|int $entityId
   *   Entity ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Loaded entity object.
   * @param string $flagId
   *   Flag machine name.
   * @param int $createdTime
   *   Timestamp when item was flagged.
   * @param string $category
   *   Item category ('saved' or 'following').
   * @param string|null $purpose
   *   Resolved domain purpose.
   */
  public function __construct(
    public readonly string $entityTypeId,
    public readonly string|int $entityId,
    public readonly EntityInterface $entity,
    public readonly string $flagId,
    public readonly int $createdTime,
    public readonly string $category,
    public readonly ?string $purpose = NULL,
  ) {}

  /**
   * Gets the item title.
   */
  public function getTitle(): string {
    return $this->getLabel();
  }

  /**
   * Magic getter for virtual properties.
   */
  public function __get(string $name): mixed {
    if ($name === 'title') {
      return $this->getLabel();
    }
    return NULL;
  }

  /**
   * Gets entity type ID.
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * Gets entity ID.
   */
  public function getEntityId(): string|int {
    return $this->entityId;
  }

  /**
   * Gets loaded entity.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Gets flag ID.
   */
  public function getFlagId(): string {
    return $this->flagId;
  }

  /**
   * Gets created timestamp.
   */
  public function getCreatedTime(): int {
    return $this->createdTime;
  }

  /**
   * Gets category ('saved' or 'following').
   */
  public function getCategory(): string {
    return $this->category;
  }

  /**
   * Gets resolved purpose.
   */
  public function getPurpose(): ?string {
    return $this->purpose;
  }

  /**
   * Gets label of the entity.
   */
  public function getLabel(): string {
    return (string) ($this->entity->label() ?? '');
  }

  /**
   * Gets canonical URL of the entity.
   */
  public function getUrl(): ?Url {
    try {
      return $this->entity->toUrl();
    }
    catch (\Throwable) {
      return NULL;
    }
  }

}
