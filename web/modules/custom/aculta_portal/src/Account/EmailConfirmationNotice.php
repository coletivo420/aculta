<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Aviso de atenção em "Minha conta" para e-mail não confirmado.
 *
 * Não há botão de fechar: o aviso só some quando o e-mail é confirmado (política de confirmação de e-mail). O título
 * pode ser trocado pelo aviso persistente do sistema de avisos (ponto aculta_portal_email_notice, do aculta_messenger).
 */
final class EmailConfirmationNotice {

  /** Rotas da área "Minha conta" onde o aviso aparece. */
  private const ACCOUNT_ROUTES = [
    'aculta_portal.dashboard',
    'aculta_portal.support_my',
    'aculta_portal.account_courses',
    'aculta_portal.my_data',
    'aculta_portal.connections',
    'aculta_portal.security',
    'aculta_portal.security_password',
  ];

  public function __construct(
    private readonly EmailConfirmationPolicy $policy,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'current_route_match')]
    private readonly CurrentRouteMatch $routeMatch,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'module_handler')]
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /** Render array do aviso, ou NULL quando não deve aparecer. */
  public function build(): ?array {
    if (!in_array($this->routeMatch->getRouteName(), self::ACCOUNT_ROUTES, TRUE)
      || $this->currentUser->isAnonymous()
      || $this->policy->isConfirmed($this->currentUser)) {
      return NULL;
    }
    $context = ['title' => (string) $this->translation->translate('Seu e-mail precisa ser confirmado.')];
    $this->moduleHandler->alter('aculta_portal_email_notice', $context, $this->currentUser);

    $url = Url::fromRoute('aculta_portal.email_resend')->toString();
    $detail = $this->routeMatch->getRouteName() === 'aculta_portal.security'
      ? (string) $this->translation->translate('Sem a confirmação do e-mail, o acesso ao site é restrito: você não pode realizar cursos, editar a wiki, comentar, comprar na loja ou apoiar. Você pode continuar entrando e navegando, e pode solicitar a troca do endereço nesta página.')
      : (string) $this->translation->translate('Enquanto isso, você não pode realizar cursos nem editar a wiki, nem comentar, comprar na loja ou apoiar.');
    return [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['data-portal-ui' => 'notice-warning email-notice', 'role' => 'alert'],
      'text' => [
        '#type' => 'inline_template',
        '#template' => '<p><strong>{{ title }}</strong> {{ detail }} <a href="{{ url }}">{{ label }}</a>.</p>',
        '#context' => [
          'title' => $context['title'],
          'detail' => $detail,
          'url' => $url,
          'label' => $this->translation->translate('Clique aqui para solicitar outro link'),
        ],
      ],
    ];
  }

}
