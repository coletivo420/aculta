<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\ContentIndex;

/**
 * Minimal, allowlisted projection of one original entity translation.
 *
 * Deliberately excludes body text, revisions, user identifiers and files.
 */
final readonly class ContentIndexProjection {

  /**
   * Creates a validated projection.
   *
   * @param string $entityTypeId
   *   Original Drupal entity type ID.
   * @param string $entityId
   *   Original entity ID.
   * @param string $langcode
   *   Translation language code.
   * @param string $bundle
   *   Original entity bundle.
   * @param string $uuid
   *   Original entity UUID.
   * @param string $label
   *   Safe, projected label.
   * @param string|null $domainId
   *   Stable domain identifier, when applicable.
   * @param bool|null $published
   *   Published state, when applicable.
   * @param int $created
   *   Entity creation timestamp.
   * @param int $changed
   *   Entity change timestamp.
   * @param int|null $publishedAt
   *   Publication timestamp, when applicable.
   * @param list<\Drupal\aculta_portal\ContentIndex\ContentIndexRelation> $relations
   *   Explicit relationships selected by the provider.
   */
  public function __construct(
    public string $entityTypeId,
    public string $entityId,
    public string $langcode,
    public string $bundle,
    public string $uuid,
    public string $label,
    public ?string $domainId,
    public ?bool $published,
    public int $created,
    public int $changed,
    public ?int $publishedAt = NULL,
    public array $relations = [],
  ) {
    if ($entityTypeId === '' || strlen($entityTypeId) > 32 || preg_match('/^[a-z][a-z0-9_]*$/', $entityTypeId) !== 1) {
      throw new \InvalidArgumentException('Content Index entity type ID is invalid.');
    }
    if ($entityId === '' || strlen($entityId) > 255) {
      throw new \InvalidArgumentException('Content Index entity ID is invalid.');
    }
    if ($langcode === '' || strlen($langcode) > 12) {
      throw new \InvalidArgumentException('Content Index language code is invalid.');
    }
    if ($bundle === '' || strlen($bundle) > 64 || preg_match('/^[a-z][a-z0-9_]*$/', $bundle) !== 1) {
      throw new \InvalidArgumentException('Content Index bundle ID is invalid.');
    }
    if (strlen($uuid) > 128 || mb_strlen($label) > 255) {
      throw new \InvalidArgumentException('Content Index UUID or label exceeds its schema limit.');
    }
    if ($domainId !== NULL && strlen($domainId) > 128) {
      throw new \InvalidArgumentException('Content Index domain ID exceeds its schema limit.');
    }
    if ($created < 0 || $changed < 0 || ($publishedAt !== NULL && $publishedAt < 0)) {
      throw new \InvalidArgumentException('Content Index timestamps must be non-negative.');
    }
    foreach ($relations as $relation) {
      if (!$relation instanceof ContentIndexRelation) {
        throw new \InvalidArgumentException('Content Index projections accept only ContentIndexRelation values.');
      }
    }
  }

}
