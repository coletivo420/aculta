<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Block cacheability owned by the Portal.
 */
final class BlockBuildHooks {

  /**
   * Varies the account menu by the current page.
   *
   * The Log in link carries the page the visitor came from as its destination
   * (see PortalHooks::preprocessLinks()). Without these contexts the block would
   * be cached with the destination of whichever page rendered it first.
   */
  #[Hook('block_build_alter')]
  public function blockBuildAlter(array &$build, BlockPluginInterface $block): void {
    if ($block->getPluginId() !== 'system_menu_block:account') {
      return;
    }
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheContexts(['url.path', 'url.query_args'])
      ->applyTo($build);
  }

}
