<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_portal\Controller\PortalController;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Displays Portal-owned account sections without introducing a second shell.
 *
 * Address normalization, security and OAuth first-password
 * handling are delegated to the unchanged original Portal controller.
 * The mixed basics form remains on the legacy account until nickname is
 * separated from private fields; this composer does not render it.
 * Studio owns only the route and the contextual navigation presentation.
 */
final class StudioAccountSectionController {

  public function __construct(
    private readonly ClassResolverInterface $resolver,
    private readonly AccountInterface $account,
  ) {}

  /**
   * Owner's existing canonical Commerce address Form API.
   *
   * @return array<string, mixed>
   *   The existing address form with Studio-owned contextual navigation.
   */
  public function address(): array {
    return $this->build('address', 'endereco');
  }

  /**
   * Portal-owned CPF/CNPJ registration, never part of the public profile.
   *
   * Returns 404 when the optional account-document owner is not installed.
   *
   * @return array<string, mixed>
   *   The original document form composed with Studio navigation.
   */
  public function documents(): array {
    return $this->build('otherData', 'documentos');
  }

  /**
   * Owner's existing e-mail confirmation and change controls.
   *
   * @return array<string, mixed>
   *   The Portal security controls in the Studio account context.
   */
  public function security(): array {
    return $this->build('security', 'seguranca');
  }

  /**
   * Owner's password, including the first-password OAuth flow.
   *
   * @return array<string, mixed>
   *   The original password management controls.
   */
  public function password(): array {
    return $this->build('securityPassword', 'senha');
  }

  /**
   * Owner's connections and account protection controls.
   *
   * @return array<string, mixed>
   *   The existing connections page.
   */
  public function connections(): array {
    return $this->build('connections', 'conexoes');
  }

  /**
   * Composes the owner content without the legacy account navigation.
   *
   * @return array<string, mixed>
   *   A private render array.
   */
  private function build(string $method, string $section): array {
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission('access aculta portal')) {
      throw new AccessDeniedHttpException();
    }

    $portal = $this->resolver->getInstanceFromDefinition(PortalController::class);
    // These five methods are explicit owner entry points. Do not
    // dynamically route user input to an arbitrary Portal method.
    $content = $portal->{$method}();
    // Their legacy inner tabs point at original account routes. Remove only
    // this presentation, not forms/handlers, to avoid duplicate navigation.
    unset($content['tabs'], $content['content']['tabs']);
    if (isset($content['content']['section']['tabs'])) {
      unset($content['content']['section']['tabs']);
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-account-section'],
      'navigation' => $this->navigation($section),
      'content' => $content,
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site', 'route'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Provides canonical Studio-only contextual navigation with normal links.
   *
   * @return array<string, mixed>
   *   Simple, no-JavaScript-friendly navigation.
   */
  private function navigation(string $section): array {
    $destinations = [
      'dados' => ['Dados cadastrais (Conta original)', 'aculta_portal.my_data'],
      'endereco' => ['Endereço', 'aculta_studio.account_address'],
      'documentos' => ['Documentos', 'aculta_studio.account_documents'],
      'seguranca' => ['E-mail e segurança', 'aculta_studio.account_security'],
      'senha' => ['Senha', 'aculta_studio.account_password'],
      'conexoes' => ['Contas conectadas', 'aculta_studio.account_connections'],
    ];

    $items = [];
    foreach ($destinations as $key => [$label, $route]) {
      $attributes = ['data-portal-ui' => 'studio-account-nav-link'];
      if ($key === $section) {
        $attributes['aria-current'] = 'page';
      }
      $items[] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup($label),
        '#url' => Url::fromRoute($route),
        '#attributes' => $attributes,
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => [
        'data-portal-ui' => 'studio-account-contextual-nav',
        'aria-label' => (string) new TranslatableMarkup('Seções de Minha Conta Studio'),
      ],
    ];
  }

}
