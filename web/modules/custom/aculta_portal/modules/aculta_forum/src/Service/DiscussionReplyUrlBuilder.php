<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Builds host URLs and fragment destinations for discussion replies.
 */
class DiscussionReplyUrlBuilder {

  /**
   * Builds the canonical reply or fallback URL on the host entity page.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host
   *   The host entity.
   * @param int|null $parentId
   *   Optional parent comment or post ID.
   * @param string|null $mode
   *   Optional mode ('responder', 'citar').
   * @param array $extraQuery
   *   Additional query parameters to preserve (e.g. pager).
   *
   * @return \Drupal\Core\Url
   *   The destination URL on the host.
   */
  public function buildHostReplyUrl(EntityInterface $host, ?int $parentId = NULL, ?string $mode = NULL, array $extraQuery = []): Url {
    try {
      $url = $host->toUrl('canonical');
    }
    catch (\Throwable) {
      $url = NULL;
    }
    if (!$url instanceof Url) {
      $url = Url::fromRoute('<current>');
    }
    $query = $extraQuery;
    if ($parentId !== NULL && $parentId > 0) {
      $query['resposta'] = $parentId;
    }
    if ($mode !== NULL && $mode !== '') {
      $query['modo'] = $mode;
    }
    if (!empty($query)) {
      $url->setOption('query', $query);
    }
    $fragment = ($parentId !== NULL && $parentId > 0)
      ? 'comentario-resposta-' . $parentId
      : 'compositor-discussao';
    $url->setOption('fragment', $fragment);

    return $url;
  }

}
