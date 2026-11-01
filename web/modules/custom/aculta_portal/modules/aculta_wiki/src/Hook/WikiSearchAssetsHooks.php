<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Attaches the Wiki search preview behavior independently of the theme.
 */
final class WikiSearchAssetsHooks {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Loads the shared search-preview library on the Wiki search route.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if ($this->routeMatch->getRouteName() === 'aculta_portal.wiki_search') {
      $attachments['#attached']['library'][] = 'aculta_portal/search-preview';
    }
  }

}
