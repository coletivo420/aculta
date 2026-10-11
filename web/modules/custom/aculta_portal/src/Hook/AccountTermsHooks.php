<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\AccountTermsPolicy;
use Drupal\aculta_portal\Form\AccountTermsAcceptForm;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Popup persistente de aceite dos termos: o bloqueio das áreas restritas a usuário logado.
 *
 * Regra para usuário logado sem aceite da versão atual: o popup aparece onde o anônimo não tem acesso (conta,
 * edição, criação de conteúdo, comentários de fórum, área de membro). Páginas que o anônimo acessa não recebem o
 * popup. A página não é negada: o popup (dialog modal, sem fechar sem aceitar) é o bloqueio. Ações protegidas
 * (checkout, cursos) seguem negadas pelo servidor (ProtectedRouteAccessCheck). Ver docs/integrations/AUTHENTICATION.md.
 */
final class AccountTermsHooks {

  /** Rotas de ação que só fazem sentido para usuário logado (criar, editar, responder). */
  private const LOGGED_ONLY_ROUTES = [
    'node.add',
    'entity.node.edit_form',
    'comment.reply',
    'entity.comment.edit_form',
    'entity.comment.delete_form',
  ];

  /** Rotas em que o popup nunca aparece: login, cadastro, recuperação e o próprio aceite. */
  private const EXEMPT_ROUTES = [
    'user.login', 'user.logout', 'user.register', 'user.pass', 'user.reset', 'user.reset.form', 'user.reset.login',
    'aculta_portal.account_terms.accept', 'social_auth.network.redirect', 'social_auth.network.callback',
  ];

  public function __construct(
    #[Autowire(service: 'aculta_portal.account_terms_policy')]
    private readonly AccountTermsPolicy $policy,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $formBuilder,
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'access_manager')]
    private readonly AccessManagerInterface $accessManager,
  ) {}

  /** Adiciona o popup de aceite à região page_bottom quando o usuário logado ainda não aceitou a versão atual. */
  #[Hook('page_bottom')]
  public function pageBottom(array &$page_bottom): void {
    $routeName = (string) $this->routeMatch->getRouteName();
    if (in_array($routeName, self::EXEMPT_ROUTES, TRUE) || !$this->policy->needsAcceptance($this->currentUser)) {
      return;
    }
    if (!$this->isRestrictedToLoggedUsers($routeName)) {
      return;
    }
    $form = $this->formBuilder->getForm(AccountTermsAcceptForm::class);
    $page_bottom['aculta_account_terms'] = [
      '#theme' => 'aculta_portal_account_terms_modal',
      '#title' => $this->translation->translate('Aceite dos termos'),
      '#form' => $form,
      '#attached' => ['library' => ['aculta_portal/account-terms-modal']],
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
  }

  /**
   * Página restrita a usuário logado: o anônimo não teria acesso, ou a rota é de ação para logados, ou é nó com comentários
   * (fórum). A página de acesso negado (system.403) também recebe o popup quando o usuário está sem aceite.
   */
  private function isRestrictedToLoggedUsers(string $routeName): bool {
    if ($routeName === 'system.403') {
      return TRUE;
    }
    if ($routeName === '' || str_starts_with($routeName, 'system.')) {
      return FALSE;
    }
    if (in_array($routeName, self::LOGGED_ONLY_ROUTES, TRUE)) {
      return TRUE;
    }
    if ($routeName === 'entity.node.canonical') {
      $node = $this->routeMatch->getParameter('node');
      if ($node instanceof \Drupal\node\NodeInterface && $node->hasField('comment_forum')) {
        return TRUE;
      }
    }
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return FALSE;
    }
    // Acesso negado para o anônimo nesta mesma rota: a página é restrita a logados.
    return !$this->accessManager->check(RouteMatch::createFromRequest($request), new AnonymousUserSession());
  }

}
