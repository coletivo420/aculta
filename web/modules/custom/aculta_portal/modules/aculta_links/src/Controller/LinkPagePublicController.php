<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\aculta_links\Entity\LinkPage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a published microsite on its links-purpose Domain. */
final class LinkPagePublicController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Builds the neutral presentation contract for a public link page. */
  public function view(string $slug): array {
    $page_storage = $this->entityTypeManager->getStorage('aculta_link_page');
    $page_ids = $page_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('slug', $slug)
      ->condition('status', TRUE)
      ->range(0, 2)
      ->execute();
    if (count($page_ids) !== 1) {
      throw new NotFoundHttpException();
    }
    $page = $page_storage->load(reset($page_ids));
    if (!$page instanceof LinkPage || !$page->access('view', $this->currentUser)) {
      throw new NotFoundHttpException();
    }

    $item_storage = $this->entityTypeManager->getStorage('aculta_link_item');
    $item_ids = $item_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('page', $page->id())
      ->condition('status', TRUE)
      ->sort('weight')
      ->sort('id')
      ->execute();
    $items = $item_storage->loadMultiple($item_ids);
    $link_items = [];
    $cache_tags = array_merge(
      $page->getCacheTags(),
      $page_storage->getEntityType()->getListCacheTags(),
      $item_storage->getEntityType()->getListCacheTags(),
    );
    $cache_contexts = ['url.site', 'languages:language_interface', 'user', 'user.permissions'];

    foreach ($items as $item) {
      $access = $item->access('view', $this->currentUser, TRUE);
      $cache_tags = array_merge($cache_tags, $item->getCacheTags(), $access->getCacheTags());
      if (!$access->isAllowed()) {
        continue;
      }
      $link = $item->get('link')->entity;
      if (!$link || !$link->isPublished()) {
        continue;
      }
      $link_access = $link->access('view', $this->currentUser, TRUE);
      $cache_tags = array_merge($cache_tags, $link->getCacheTags(), $link_access->getCacheTags());
      if (!$link_access->isAllowed()) {
        continue;
      }
      try {
        $url = $link->get('uri')->first()?->getUrl();
      }
      catch (\InvalidArgumentException) {
        continue;
      }
      if (!$url instanceof Url) {
        continue;
      }
      $link_items[] = [
        '#type' => 'link',
        '#title' => $item->get('title')->value,
        '#url' => $url,
        '#attributes' => $url->isExternal()
          ? ['rel' => 'noopener noreferrer', 'target' => '_blank']
          : [],
      ];
      $cache_contexts = array_merge($cache_contexts, $link_access->getCacheContexts(), $access->getCacheContexts());
    }

    $links = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $link_items,
    ];

    return [
      '#theme' => 'aculta_links_page',
      '#title' => $page->label(),
      '#description' => (string) $page->get('description')->value,
      '#links' => $links,
      '#has_links' => $link_items !== [],
      '#cache' => [
        'contexts' => array_values(array_unique($cache_contexts)),
        'tags' => array_values(array_unique($cache_tags)),
      ],
    ];
  }

}
