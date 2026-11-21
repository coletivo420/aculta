<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Migration;

use Drupal\aculta_forum_legacy\Entity\LegacyForumTopic;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Idempotent writer for archive topics. Keyed by origem_id: re-running updates, never duplicates.
 */
final class LegacyArchiveTopicWriter {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Creates or updates the archive topic for the given mapped values.
   *
   * @param array<string, mixed> $values
   *   Output of LegacyArchiveTopicMapper::map().
   */
  public function upsert(array $values): LegacyForumTopic {
    $storage = $this->entityTypeManager->getStorage('aculta_forum_legacy_topics');
    $key = $values['origem_id'] ?? NULL;
    if (!is_string($key) || $key === '') {
      throw new \InvalidArgumentException('Archive topic values need a stable origem_id.');
    }
    $existing = $storage->loadByProperties(['origem_id' => $key]);
    $topic = $existing === [] ? LegacyForumTopic::create(['origem_id' => $key]) : reset($existing);
    if (!$topic instanceof LegacyForumTopic) {
      throw new \UnexpectedValueException('Unexpected archive topic entity type.');
    }
    foreach (['titulo', 'corpo', 'dominio_origem', 'created'] as $field) {
      if (array_key_exists($field, $values) && $values[$field] !== NULL) {
        $topic->set($field, $values[$field]);
      }
    }
    if (isset($values['autor']) && is_int($values['autor'])) {
      $topic->set('autor', $values['autor']);
    }
    $topic->save();
    return $topic;
  }

}
