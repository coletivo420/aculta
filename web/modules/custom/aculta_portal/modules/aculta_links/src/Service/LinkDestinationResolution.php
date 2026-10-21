<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;

/**
 * A resolved destination and the access/cache data that produced it.
 */
final class LinkDestinationResolution {

  public function __construct(
    public readonly ?Url $url,
    public readonly CacheableMetadata $cacheability,
  ) {}

}
