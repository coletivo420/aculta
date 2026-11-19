<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Prevents private Studio routes from being indexed in public environments.
 */
final class PrivatePageMetatagHooks {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Adds noindex metadata to routes owned by the private Studio module.
   *
   * @param array<string, mixed> $tags
   *   Metatag values being prepared for the current response.
   * @param array<string, mixed> $context
   *   Metatag token replacement context.
   */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$tags, array &$context): void {
    $route_name = $this->routeMatch->getRouteName();
    if (!str_starts_with((string) $route_name, 'aculta_studio.')) {
      return;
    }

    $tags['robots'] = 'noindex, nofollow';
    unset($tags['canonical_url'], $tags['og_url'], $tags['schema_web_page_url']);
  }

}
