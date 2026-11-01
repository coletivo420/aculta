<?php

declare(strict_types=1);

namespace Drupal\aculta_content_admin\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\aculta_content_admin\Moderation\ModeratableCollectionRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists only editorial collections the current user can access.
 */
final class ContentModerationOverviewController extends ControllerBase {

  public function __construct(
    private readonly ModeratableCollectionRegistry $collectionRegistry,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_content_admin.moderation_registry'));
  }

  /**
   * Builds the moderated-area links with access-aware cache metadata.
   */
  public function overview(): array {
    $result = $this->collectionRegistry->forAccount($this->currentUser());
    $items = [];
    foreach ($result['collections'] as $collection) {
      $items[] = Link::fromTextAndUrl(
        $collection['label'],
        Url::fromRoute($collection['route'], $collection['parameters']),
      )->toRenderable();
    }

    $build = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#cache' => [],
    ];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($result['cacheability'])
      ->applyTo($build);
    return $build;
  }

}
