<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Recommendation;

use Drupal\aculta_studio\Saved\SavedContentService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Generates transparent and contextual recommendations without opaque algorithms.
 */
class RecommendationService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly SavedContentService $savedContent,
    protected readonly Connection $database,
    protected readonly ?object $relationQuery = NULL,
    protected readonly ?object $contentIndexQuery = NULL,
  ) {}

  /**
   * Generates transparent recommendations for the given account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting recommendations.
   * @param int $limit
   *   Maximum number of items to recommend.
   *
   * @return array<int, array{
   *   entity: \Drupal\Core\Entity\EntityInterface,
   *   entity_type: string,
   *   entity_id: string|int,
   *   reason: string,
   *   score: int
   * }>
   *   List of recommended items with an explicit, human-readable reason.
   */
  public function getRecommendations(AccountInterface $account, int $limit = 10): array {
    $recommendations = [];
    $seenKeys = [];

    // 1. Identify what the user already saved/follows so we don't recommend duplicates.
    $savedItems = $this->savedContent->getSavedItems($account, 'all', 30);
    foreach ($savedItems as $item) {
      $seenKeys[$item['entity_type'] . ':' . $item['entity_id']] = TRUE;
    }

    // 2. Discover related items via Content Index Relations if available.
    if ($this->relationQuery !== NULL && method_exists($this->relationQuery, 'queryOutgoing')) {
      foreach ($savedItems as $savedItem) {
        if (count($recommendations) >= $limit) {
          break;
        }

        $sourceEntity = $savedItem['entity'];
        $langcode = $sourceEntity->language()->getId();
        try {
          $result = $this->relationQuery->queryOutgoing(
            $savedItem['entity_type'],
            (string) $savedItem['entity_id'],
            $langcode,
            'related',
            $account,
            NULL,
            5
          );

          if (is_iterable($result)) {
            foreach ($result as $candidate) {
              $type = $candidate->getEntityTypeId() ?? 'node';
              $id = $candidate->id();
              $key = $type . ':' . $id;

              if (!isset($seenKeys[$key]) && $candidate->access('view', $account)) {
                $seenKeys[$key] = TRUE;
                $recommendations[] = [
                  'entity' => $candidate,
                  'entity_type' => $type,
                  'entity_id' => $id,
                  'reason' => sprintf('Relacionado ao seu item salvo "%s"', $sourceEntity->label() ?? 'Conteúdo salvo'),
                  'score' => 90,
                ];
                if (count($recommendations) >= $limit) {
                  break 2;
                }
              }
            }
          }
        }
        catch (\Throwable) {
          // If relation query throws for an unsupported entity type, continue gracefully.
          continue;
        }
      }
    }

    // 3. Discover related items via shared taxonomy terms from saved nodes.
    if (count($recommendations) < $limit && !empty($savedItems) && $this->entityTypeManager->hasDefinition('node')) {
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      foreach ($savedItems as $savedItem) {
        if (count($recommendations) >= $limit) {
          break;
        }

        $savedEntity = $savedItem['entity'];
        $termIds = $this->extractTaxonomyTermIds($savedEntity);
        if (empty($termIds)) {
          continue;
        }

        // Query up to 5 published nodes sharing any of these terms.
        foreach ($termIds as $fieldTerm) {
          [$fieldName, $termId, $termLabel] = $fieldTerm;
          $query = $nodeStorage->getQuery()
            ->accessCheck(TRUE)
            ->condition('status', 1)
            ->condition($fieldName, $termId)
            ->range(0, 5)
            ->sort('created', 'DESC');

          $candidateIds = $query->execute();
          if (!empty($candidateIds)) {
            $candidates = $nodeStorage->loadMultiple($candidateIds);
            foreach ($candidates as $candidate) {
              $key = 'node:' . $candidate->id();
              if (!isset($seenKeys[$key]) && $candidate->access('view', $account)) {
                $seenKeys[$key] = TRUE;
                $recommendations[] = [
                  'entity' => $candidate,
                  'entity_type' => 'node',
                  'entity_id' => $candidate->id(),
                  'reason' => sprintf('Compartilha o tema "%s"', $termLabel),
                  'score' => 70,
                ];
                if (count($recommendations) >= $limit) {
                  break 2;
                }
              }
            }
          }
        }
      }
    }

    // 4. Fallback recommendations if user has few or no saved items: featured/recent community topics.
    if (count($recommendations) < $limit && $this->entityTypeManager->hasDefinition('node')) {
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      $query = $nodeStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->range(0, $limit - count($recommendations))
        ->sort('created', 'DESC');

      $candidateIds = $query->execute();
      if (!empty($candidateIds)) {
        $candidates = $nodeStorage->loadMultiple($candidateIds);
        foreach ($candidates as $candidate) {
          $key = 'node:' . $candidate->id();
          if (!isset($seenKeys[$key]) && $candidate->access('view', $account)) {
            $seenKeys[$key] = TRUE;
            $recommendations[] = [
              'entity' => $candidate,
              'entity_type' => 'node',
              'entity_id' => $candidate->id(),
              'reason' => 'Conteúdo recente na comunidade',
              'score' => 50,
            ];
            if (count($recommendations) >= $limit) {
              break;
            }
          }
        }
      }
    }

    return $recommendations;
  }

  /**
   * Extracts taxonomy term IDs and labels from known term reference fields on an entity.
   *
   * @return array<int, array{0: string, 1: int|string, 2: string}>
   *   Tuples of [fieldName, termId, termLabel].
   */
  protected function extractTaxonomyTermIds(EntityInterface $entity): array {
    $results = [];
    $candidateFields = ['taxonomy_forums', 'field_tags', 'field_categoria', 'field_tema', 'field_segmento'];

    foreach ($candidateFields as $fieldName) {
      if ($entity->hasField($fieldName) && !$entity->get($fieldName)->isEmpty()) {
        foreach ($entity->get($fieldName)->referencedEntities() as $term) {
          $results[] = [$fieldName, $term->id(), $term->label() ?? 'Tema'];
        }
      }
    }

    return $results;
  }

}
