<?php

declare(strict_types=1);

namespace Drupal\aculta_ui_library\Controller;

use Drupal\Core\Routing\LocalRedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Keeps the legacy ACULTA library entry point pointed at UI Patterns Library.
 */
final class UiPatternsLibraryRedirectController {

  public function __construct(
    private readonly UrlGeneratorInterface $urlGenerator,
  ) {}

  /**
   * Creates the controller from Drupal's route URL generator.
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('url_generator'));
  }

  /**
   * Redirects the legacy ACULTA library route to the canonical SDC catalog.
   */
  public function redirectToLibrary(): LocalRedirectResponse {
    $path = $this->urlGenerator->generate(
      'ui_patterns_library.overview',
      [],
      UrlGeneratorInterface::ABSOLUTE_PATH,
    );

    return new LocalRedirectResponse($path);
  }

}
