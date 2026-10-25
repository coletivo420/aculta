<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Appearance\AppearanceForm;
use Drupal\aculta_portal\Controller\PortalController;
use Drupal\aculta_portal\Support\Controller\SupportController;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Displays Portal-owned account sections without introducing a second shell.
 *
 * Address normalization, security and OAuth first-password
 * handling are delegated to the unchanged original Portal controller.
 * The basics form is scoped by PortalHooks; the legacy /dados form stays intact.
 * Studio owns only the route and the contextual navigation presentation.
 */
final class StudioAccountSectionController {

  public function __construct(
    private readonly ClassResolverInterface $resolver,
    private readonly AccountInterface $account,
    private readonly ?AccountDocumentInterface $documents = NULL,
    private readonly ?FormBuilderInterface $forms = NULL,
  ) {}

  /**
   * Portal-owned private account data through the existing Profile Form API.
   *
   * The Portal form-alter removes only the nickname from this Studio route.
   *
   * @return array<string, mixed>
   *   Account data form in the Studio shell.
   */
  public function data(): array {
    return $this->build('myData', 'dados');
  }

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
   * Owner-filtered Commerce orders, including the existing payment notice.
   */
  public function orders(): array {
    return $this->build('orders', 'pedidos');
  }

  /**
   * Existing Portal support history and subscription action links.
   */
  public function support(): array {
    return $this->buildOwner(SupportController::class, 'mySupport', 'apoio');
  }

  /**
   * Reuses the original account appearance Form API and its owner tabs.
   *
   * The form is built on this route, so a successful POST stays in Studio.
   * Its owner-specific settings tabs remain available; they are not an
   * embedded AccountShellBuilder or a duplicate of the Studio navigation.
   */
  public function preferences(): array {
    $this->requireAccountAccess();
    if ($this->forms === NULL) {
      throw new \LogicException('The account form builder is unavailable.');
    }
    return $this->compose($this->forms->getForm(AppearanceForm::class), 'preferencias');
  }

  /**
   * Delegates the existing Portal account content without an extra shell.
   */
  private function build(string $method, string $section): array {
    return $this->buildOwner(PortalController::class, $method, $section);
  }

  /**
   * Invokes only explicitly selected owner methods; never accepts user input.
   *
   * @return array<string, mixed>
   *   Owner render array in the Studio shell.
   */
  private function buildOwner(string $ownerClass, string $method, string $section): array {
    $this->requireAccountAccess();
    // Optional document capability must not be resolved when absent.
    // An installed owner can still report its safe read-only state.
    if ($method === 'otherData' && $this->documents === NULL) {
      throw new NotFoundHttpException();
    }

    $owner = $this->resolver->getInstanceFromDefinition($ownerClass);
    $content = $owner->{$method}();
    // Remove legacy account-section tabs only; preserve owner Form API,
    // Commerce payment links, subscription actions and render metadata.
    unset($content['tabs'], $content['content']['tabs']);
    if (isset($content['content']['section']['tabs'])) {
      unset($content['content']['section']['tabs']);
    }

    return $this->compose($content, $section);
  }

  /**
   * Both the route and the controller enforce an authenticated account.
   */
  private function requireAccountAccess(): void {
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission('access aculta portal')) {
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Adds Studio navigation while retaining all owner render/cache metadata.
   *
   * @param array<string, mixed> $content
   *   The existing owner render array or built Form API.
   *
   * @return array<string, mixed>
   *   A private render array.
   */
  private function compose(array $content, string $section): array {
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
      'dados' => ['Dados cadastrais', 'aculta_studio.account_data'],
      'endereco' => ['Endereço', 'aculta_studio.account_address'],
      'documentos' => ['Documentos', 'aculta_studio.account_documents'],
      'seguranca' => ['E-mail e segurança', 'aculta_studio.account_security'],
      'senha' => ['Senha', 'aculta_studio.account_password'],
      'conexoes' => ['Contas conectadas', 'aculta_studio.account_connections'],
      'pedidos' => ['Meus pedidos', 'aculta_studio.account_orders'],
      'apoio' => ['Meu apoio', 'aculta_studio.account_support'],
      'preferencias' => ['Preferências da conta', 'aculta_studio.account_preferences'],
    ];

    if (!$this->documents?->isAvailable()) {
      unset($destinations['documentos']);
    }

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
