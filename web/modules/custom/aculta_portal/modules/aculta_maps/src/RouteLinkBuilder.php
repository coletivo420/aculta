<?php

declare(strict_types=1);

namespace Drupal\aculta_maps;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;

/**
 * Builds opt-in external directions links without loading map APIs.
 */
final class RouteLinkBuilder {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * Builds a Google Maps directions URL for a supplied destination.
   */
  public function build(string $destination): Url {
    $template = (string) ($this->configFactory->get('aculta_maps.settings')->get('route_url_template')
      ?? 'https://www.google.com/maps/dir/?api=1&destination={destino}');
    $parts = parse_url(str_replace('{destino}', 'destination', $template));
    if (($parts['scheme'] ?? '') !== 'https'
      || !in_array($parts['host'] ?? '', ['www.google.com', 'maps.google.com'], TRUE)
      || !str_starts_with($parts['path'] ?? '', '/maps/dir')
      || !str_contains($template, '{destino}')) {
      $template = 'https://www.google.com/maps/dir/?api=1&destination={destino}';
    }
    $uri = str_replace('{destino}', rawurlencode(trim($destination)), $template);
    return Url::fromUri($uri, [
      'attributes' => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
      ],
    ]);
  }

}
