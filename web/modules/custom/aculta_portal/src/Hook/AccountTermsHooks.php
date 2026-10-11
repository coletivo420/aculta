<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\AccountTermsPolicy;
use Drupal\aculta_portal\Form\AccountTermsAcceptForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Popup persistente de aceite dos termos (modal que não se fecha sem aceitar).
 *
 * Aparece em todos os hosts (principal, conta, loja) para quem ainda não aceitou a versão atual. Não há botão de
 * fechar, Esc nem clique fora: o <dialog> é aberto com showModal() e o evento cancel é cancelado (JavaScript em
 * aculta_portal/account-terms-modal). Sem JavaScript, a página não tem o popup, mas as rotas protegidas continuam
 * bloqueadas no servidor (ProtectedRouteAccessCheck). Ver docs/portal/TESTES-NAVEGADOR.md.
 */
final class AccountTermsHooks {

  /** Rotas em que o popup não aparece: login, cadastro, recuperação e o próprio aceite. */
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
  ) {}

  /** Adiciona o popup de aceite à região page_bottom quando a versão atual dos termos ainda não foi aceita. */
  #[Hook('page_bottom')]
  public function pageBottom(array &$page_bottom): void {
    // Páginas públicas não mostram o popup: só as rotas restritas, ou a negativa de conteúdo restrito sem aceite.
    $request = $this->requestStack->getCurrentRequest();
    $routeObject = $request?->attributes->get('_route_object');
    // A página de acesso negado (system.403) mostra o popup também: a ação negada é de rota restrita.
    $restricted = (bool) $request?->attributes->get('_aculta_terms_pending')
      || ($routeObject !== NULL && $routeObject->getOption('_aculta_terms_required'))
      || $this->routeMatch->getRouteName() === 'system.403';
    if (!$restricted || in_array($this->routeMatch->getRouteName(), self::EXEMPT_ROUTES, TRUE)) {
      return;
    }
    if (!$this->policy->needsAcceptance($this->currentUser)) {
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

}
