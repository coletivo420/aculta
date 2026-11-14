<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\views\ViewExecutable;

/**
 * Supplies public category counts for the forum topic listing.
 */
final class PublicForumListViewHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds access-checked category counts to the public topics View.
   *
   * @param array<string, mixed> $variables
   *   The views_view variables.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable || $view->id() !== 'aculta_forum_public_topics') {
      return;
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $categories = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheTags([
        'node_list',
        'node_list:aculta_forum_topic',
        'taxonomy_term_list:aculta_forum_categories',
      ])
      ->addCacheContexts([
        'languages:language_content',
        'languages:language_interface',
        'user.node_grants:view',
        'user.permissions',
      ]);

    foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'aculta_forum_categories']) as $term) {
      $cacheability->addCacheableDependency($term);
      $count = (int) $nodeStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'aculta_forum_topic')
        ->condition('status', 1)
        ->condition('field_aculta_categories', $term->id())
        ->count()
        ->execute();
      $generatedUrl = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()])
        ->toString(TRUE);
      $cacheability->addCacheableDependency($generatedUrl);
      $categories[] = [
        'label' => $term->label(),
        'url' => $generatedUrl->getGeneratedUrl(),
        'count' => $count,
      ];
    }

    $total = (int) $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_forum_topic')
      ->condition('status', 1)
      ->count()
      ->execute();

    $variables['forum_categories'] = $categories;
    $variables['forum_total_topics'] = $total;
    $variables['forum_list_cache_metadata'] = [
      '#markup' => '',
      '#cache' => [
        'contexts' => $cacheability->getCacheContexts(),
        'tags' => $cacheability->getCacheTags(),
        'max-age' => $cacheability->getCacheMaxAge(),
      ],
    ];
  }

}
