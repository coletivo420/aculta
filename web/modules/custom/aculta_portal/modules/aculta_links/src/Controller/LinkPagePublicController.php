<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\aculta_links\Entity\Link;
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

    return $this->buildPage($page, TRUE);
  }

  /**
   * Builds an owner-only preview, including unpublished items and drafts.
   */
  public function preview(LinkPage $aculta_link_page): array {
    if (!$aculta_link_page->access('update', $this->currentUser)) {
      throw new NotFoundHttpException();
    }

    $build = $this->buildPage($aculta_link_page, FALSE);
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /**
   * Builds a private preview addressed by its public slug.
   */
  public function previewBySlug(string $slug): array {
    $page_storage = $this->entityTypeManager->getStorage('aculta_link_page');
    $ids = $page_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('slug', $slug)
      ->range(0, 2)
      ->execute();
    if (count($ids) !== 1) {
      throw new NotFoundHttpException();
    }
    $page = $page_storage->load(reset($ids));
    if (!$page instanceof LinkPage || !$page->access('update', $this->currentUser)) {
      throw new NotFoundHttpException();
    }

    $build = $this->buildPage($page, FALSE);
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /**
   * Resolves a published reusable destination by its opaque short code.
   */
  public function redirectByCode(string $codigo): TrustedRedirectResponse {
    $storage = $this->entityTypeManager->getStorage('aculta_link');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('short_code', $codigo)
      ->condition('status', TRUE)
      ->range(0, 2)
      ->execute();
    if (count($ids) !== 1) {
      throw new NotFoundHttpException();
    }

    $link = $storage->load(reset($ids));
    if (!$link instanceof Link || !$link->isPublished()) {
      throw new NotFoundHttpException();
    }
    $access = $link->access('view', $this->currentUser, TRUE);
    if (!$access->isAllowed() || $link->validate()->count() > 0) {
      throw new NotFoundHttpException();
    }

    try {
      $url = $link->get('uri')->first()?->getUrl();
      if (!$url instanceof Url) {
        throw new NotFoundHttpException();
      }
      $response = new TrustedRedirectResponse($url->setAbsolute()->toString(), 302);
    }
    catch (\InvalidArgumentException) {
      throw new NotFoundHttpException();
    }

    return $response
      ->addCacheableDependency($link)
      ->addCacheableDependency($access);
  }

  /**
   * Builds page output, filtering unpublished items for public requests.
   */
  private function buildPage(LinkPage $page, bool $published_only): array {
    if ($published_only && !$page->isPublished()) {
      throw new NotFoundHttpException();
    }
    $page_storage = $this->entityTypeManager->getStorage('aculta_link_page');
    $item_storage = $this->entityTypeManager->getStorage('aculta_link_item');
    $item_query = $item_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('page', $page->id())
      ->sort('weight')
      ->sort('id');
    if ($published_only) {
      $item_query->condition('status', TRUE);
    }
    $item_ids = $item_query->execute();
    $items = $item_storage->loadMultiple($item_ids);
    $link_items = [];
    $cache_tags = array_merge(
      $page->getCacheTags(),
      $page_storage->getEntityType()->getListCacheTags(),
      $item_storage->getEntityType()->getListCacheTags(),
    );
    $cache_contexts = ['url.site', 'languages:language_interface', 'user', 'user.permissions'];
    $cache_max_age = Cache::PERMANENT;

    // This render output also contains route-specific Metatag output, so
    // invalidate it when the global or entity-type SEO defaults change.
    if ($published_only) {
      $metatag_storage = $this->entityTypeManager->getStorage('metatag_defaults');
      $cache_tags = array_merge($cache_tags, $metatag_storage->getEntityType()->getListCacheTags());
      foreach ([
        'global',
        'aculta_link_page',
        'aculta_link_page__aculta_link_page',
      ] as $default_id) {
        $defaults = $metatag_storage->load($default_id);
        if ($defaults) {
          $cache_tags = array_merge($cache_tags, $defaults->getCacheTags());
        }
      }
    }

    foreach ($items as $item) {
      // Preview is authorized by update access to the page. Requiring view
      // access here would hide scheduled items from their owner before publish.
      $access = $item->access($published_only ? 'view' : 'update', $this->currentUser, TRUE);
      $cache_tags = array_merge($cache_tags, $item->getCacheTags(), $access->getCacheTags());
      $cache_contexts = array_merge($cache_contexts, $access->getCacheContexts());
      $cache_max_age = Cache::mergeMaxAges($cache_max_age, $access->getCacheMaxAge());
      if (!$access->isAllowed()) {
        continue;
      }
      $link = $item->get('link')->entity;
      if (!$link || ($published_only && !$link->isPublished())) {
        continue;
      }
      $link_access = $link->access('view', $this->currentUser, TRUE);
      $cache_tags = array_merge($cache_tags, $link->getCacheTags(), $link_access->getCacheTags());
      $cache_contexts = array_merge($cache_contexts, $link_access->getCacheContexts());
      $cache_max_age = Cache::mergeMaxAges($cache_max_age, $link_access->getCacheMaxAge());
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
      '#theme_preset' => in_array((string) $page->get('theme_preset')->value, ['classic', 'soft', 'outline'], TRUE)
        ? (string) $page->get('theme_preset')->value
        : 'classic',
      '#links' => $links,
      '#has_links' => $link_items !== [],
      '#cache' => [
        'contexts' => array_values(array_unique($cache_contexts)),
        'tags' => array_values(array_unique($cache_tags)),
        'max-age' => $cache_max_age,
      ],
    ];
  }

}
