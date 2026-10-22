<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\ContentIndex;

/**
 * Explicit directed relationship to an original entity identity.
 *
 * A target language of `und` means the source reference points to an entity
 * independently of a specific translation; providers use a language ID when
 * the reference is explicitly translation-specific.
 */
final readonly class ContentIndexRelation {

  public function __construct(
    public string $type,
    public string $targetEntityTypeId,
    public string $targetEntityId,
    public string $targetLangcode = 'und',
  ) {
    if ($type === '' || strlen($type) > 64 || preg_match('/^[a-z][a-z0-9_]*$/', $type) !== 1) {
      throw new \InvalidArgumentException('Content Index relation type is invalid.');
    }
    if ($targetEntityTypeId === '' || strlen($targetEntityTypeId) > 32 || preg_match('/^[a-z][a-z0-9_]*$/', $targetEntityTypeId) !== 1) {
      throw new \InvalidArgumentException('Content Index relation target type is invalid.');
    }
    if ($targetEntityId === '' || strlen($targetEntityId) > 255) {
      throw new \InvalidArgumentException('Content Index relation target ID is invalid.');
    }
    if ($targetLangcode === '' || strlen($targetLangcode) > 12) {
      throw new \InvalidArgumentException('Content Index relation target language is invalid.');
    }
  }

}
