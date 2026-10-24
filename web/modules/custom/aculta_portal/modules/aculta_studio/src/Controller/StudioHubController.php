<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Studio hub backed by the authorized experience registry.
 *
 */
final class StudioHubController {

  public function __construct(
    private readonly StudioExperienceManager $experiences,
    private readonly AccountInterface $account,
    private readonly RouteProviderInterface $routes,
  ) {}

  /**
   * Lists only available experiences that authorize the current account.
   *
   * @return array<string, mixed>
   *   A cache-safe, progressively navigable list of ordinary Drupal links.
   */
  public function page(): array {
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission('access aculta portal')) {
      throw new AccessDeniedHttpException();
    }

    $links = [];
    foreach ($this->experiences->all() as $provider) {
      if (!$provider->available() || !$this->experiences->access($provider->id(), 'account', $this->account)->isAllowed()) {
        continue;
      }

      $routeName = $provider->routeName();
      try {
        // Optional LMS and Wiki integrations must not produce broken links.
        $this->routes->getRouteByName($routeName);
      }
      catch (RouteNotFoundException) {
        continue;
      }

      $links[] = [
        '#type' => 'link',
        '#title' => $provider->label(),
        '#url' => Url::fromRoute($routeName),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-hub'],
      'intro' => [
        '#plain_text' => new TranslatableMarkup('Escolha uma experiência do ACULTA Studio para continuar seu trabalho ou acessar seu espaço pessoal.'),
      ],
      'experiences' => [
        '#theme' => 'item_list',
        '#items' => $links,
        '#attributes' => [
          'aria-label' => (string) new TranslatableMarkup('Experiências disponíveis'),
          'data-portal-ui' => 'studio-experiences',
        ],
      ],
      '#cache' => [
        // Marley authorization currently depends on dynamic plugin actions,
        // so cache is deliberately disabled for this early hub preview.
        'contexts' => ['user', 'user.permissions', 'url.site'],
        'max-age' => 0,
      ],
    ];
  }

}
