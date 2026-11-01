<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Migration;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Copies unambiguous Magazine gallery embeds into the structured field.
 *
 * The original body markup is intentionally preserved. Publications with more
 * than one distinct gallery are reported for editorial review, since the
 * structured field has cardinality one.
 */
final class GalleryReferenceMigrator {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Processes one keyset batch of Revista nodes.
   *
   * @param int $afterNid
   *   Resume after this node ID.
   * @param int $limit
   *   Maximum source nodes to inspect.
   * @param bool $execute
   *   Whether to save eligible structured references.
   *
   * @return array{
   *   inspected: int,
   *   candidates: int,
   *   migrated: int,
   *   already_set: int,
   *   no_embed: int,
   *   multiple: list<int>,
   *   unresolved: int,
   *   stale: int,
   *   failed: int,
   *   failed_nids: list<int>,
   *   next_nid: int,
   *   complete: bool
   *   }
   *   Bounded migration report. At most 100 multiple-reference NIDs are
   *   included; counts cover the entire batch.
   */
  public function processBatch(int $afterNid, int $limit, bool $execute): array {
    if ($afterNid < 0 || $limit < 1 || $limit > 500) {
      throw new \InvalidArgumentException('afterNid must be non-negative and limit must be between 1 and 500.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $fieldDefinition = $this->entityFieldManager->getFieldDefinitions('node', 'revista')['field_editorial_gallery'] ?? NULL;
    if ($fieldDefinition === NULL || !$storage->getEntityType()->hasKey('bundle')) {
      throw new \RuntimeException('Import field_editorial_gallery configuration before running the gallery reference migration.');
    }

    $ids = $storage->getQuery()
      ->condition('type', 'revista')
      ->condition('nid', $afterNid, '>')
      ->sort('nid')
      ->range(0, $limit)
      ->accessCheck(FALSE)
      ->execute();
    $nodes = $storage->loadMultiple($ids);
    $report = [
      'inspected' => count($nodes),
      'candidates' => 0,
      'migrated' => 0,
      'already_set' => 0,
      'no_embed' => 0,
      'multiple' => [],
      'unresolved' => 0,
      'stale' => 0,
      'failed' => 0,
      'failed_nids' => [],
      'next_nid' => $nodes === [] ? $afterNid : (int) end($nodes)->id(),
      'complete' => count($nodes) < $limit,
    ];

    $references = [];
    $uuids = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface || !$node->hasField('field_editorial_gallery')) {
        continue;
      }
      foreach (array_keys($node->getTranslationLanguages()) as $langcode) {
        $translation = $node->getTranslation($langcode);
        if (!$translation->hasField('body') || $translation->get('body')->isEmpty()) {
          $report['no_embed']++;
          continue;
        }
        if (!$translation->get('field_editorial_gallery')->isEmpty()) {
          $report['already_set']++;
          continue;
        }
        $found = self::extractGalleryUuids((string) $translation->get('body')->value);
        if ($found === []) {
          $report['no_embed']++;
          continue;
        }
        if (count($found) > 1) {
          if (count($report['multiple']) < 100) {
            $report['multiple'][] = (int) $node->id();
          }
          continue;
        }
        $uuid = $found[0];
        $references[] = [(int) $node->id(), (string) $langcode, $uuid];
        $uuids[$uuid] = TRUE;
      }
    }

    $report['candidates'] = count($references);
    if ($references === []) {
      return $report;
    }

    $galleryStorage = $this->entityTypeManager->getStorage('media_gallery');
    $galleryIds = $galleryStorage->getQuery()
      ->condition('uuid', array_keys($uuids), 'IN')
      ->accessCheck(FALSE)
      ->execute();
    $galleries = $galleryStorage->loadMultiple($galleryIds);
    $galleryByUuid = [];
    foreach ($galleries as $gallery) {
      $galleryByUuid[$gallery->uuid()] = $gallery;
    }

    $eligible = [];
    foreach ($references as [$nid, $langcode, $uuid]) {
      $gallery = $galleryByUuid[$uuid] ?? NULL;
      if ($gallery === NULL) {
        $report['unresolved']++;
        continue;
      }
      if (!$execute) {
        continue;
      }
      $eligible[$nid][$langcode] = [$uuid, (string) $gallery->id()];
    }

    if (!$execute) {
      return $report;
    }
    $storage->resetCache(array_keys($eligible));
    $freshNodes = $storage->loadMultiple(array_keys($eligible));
    foreach ($eligible as $nid => $translations) {
      $node = $freshNodes[$nid] ?? NULL;
      if (!$node instanceof NodeInterface) {
        $report['stale']++;
        continue;
      }
      $changed = FALSE;
      foreach ($translations as $langcode => [$uuid, $galleryId]) {
        if (!$node->hasTranslation($langcode)) {
          $report['stale']++;
          continue;
        }
        $translation = $node->getTranslation($langcode);
        if (!$translation->hasField('field_editorial_gallery')
          || !$translation->get('field_editorial_gallery')->isEmpty()
          || !$translation->hasField('body')
          || $translation->get('body')->isEmpty()
          || self::extractGalleryUuids((string) $translation->get('body')->value) !== [$uuid]) {
          $report['stale']++;
          continue;
        }
        $translation->set('field_editorial_gallery', ['target_id' => $galleryId]);
        $changed = TRUE;
      }
      if (!$changed) {
        continue;
      }
      try {
        $node->save();
        $report['migrated']++;
      }
      catch (\Throwable) {
        $report['failed']++;
        if (count($report['failed_nids']) < 100) {
          $report['failed_nids'][] = (int) $nid;
        }
        $report['next_nid'] = min($report['next_nid'], (int) $nid - 1);
        $report['complete'] = FALSE;
      }
    }
    return $report;
  }

  /**
   * Extracts distinct Gallery UUIDs from supported Entity Embed markup.
   *
   * @return list<string>
   *   Unique UUIDs in document order.
   */
  public static function extractGalleryUuids(string $html): array {
    if ($html === '' || !class_exists(\DOMDocument::class)) {
      return [];
    }
    $document = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    try {
      $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
        LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR,
      );
      if (!$loaded) {
        return [];
      }
      $xpath = new \DOMXPath($document);
      $elements = $xpath->query('//drupal-entity[@data-entity-type="media_gallery"][@data-entity-uuid]');
      if ($elements === FALSE) {
        return [];
      }
      $uuids = [];
      foreach ($elements as $element) {
        $uuid = trim($element->getAttribute('data-entity-uuid'));
        if ($uuid !== '' && !isset($uuids[$uuid])) {
          $uuids[$uuid] = TRUE;
        }
      }
      return array_keys($uuids);
    }
    finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
  }

}
