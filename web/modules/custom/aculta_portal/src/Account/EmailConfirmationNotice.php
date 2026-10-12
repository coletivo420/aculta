<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Aviso vermelho e não descartável em "Minha conta" para e-mail não confirmado.
 *
 * Não há botão de fechar: o aviso só some quando o e-mail é confirmado (política de confirmação de e-mail).
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
    #[Autowire(service: 'csrf_token')]
    private readonly CsrfTokenGenerator $csrf,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {}

  /** Render array do aviso, ou NULL quando não deve aparecer. */
  public function build(): ?array {
    if (!in_array($this->routeMatch->getRouteName(), self::ACCOUNT_ROUTES, TRUE)
      || $this->currentUser->isAnonymous()
      || $this->policy->isConfirmed($this->currentUser)) {
      return NULL;
    }
    $url = Url::fromRoute('aculta_portal.email_resend')->toString();
    $token = $this->csrf->get(ltrim($url, '/'));
    $link = '<a href="' . htmlspecialchars($url . '?token=' . $token, ENT_QUOTES) . '">'
      . htmlspecialchars((string) $this->translation->translate('Clique aqui para reenviar a confirmação'), ENT_QUOTES) . '</a>';
    $detail = $this->routeMatch->getRouteName() === 'aculta_portal.security'
      ? (string) $this->translation->translate('Sem a confirmação do e-mail, o acesso ao site é restrito: você não pode realizar cursos, editar a wiki, comentar, comprar na loja ou apoiar. Você pode continuar entrando e navegando, e pode solicitar a troca do endereço nesta página.')
      : (string) $this->translation->translate('Enquanto isso, você não pode realizar cursos nem editar a wiki, nem comentar, comprar na loja ou apoiar.');
    return [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['data-portal-ui' => 'notice-error email-notice', 'role' => 'alert'],
      'text' => ['#markup' => '<p><strong>' . htmlspecialchars((string) $this->translation->translate('Seu e-mail precisa ser confirmado.'), ENT_QUOTES) . '</strong> '
        . htmlspecialchars($detail, ENT_QUOTES) . ' ' . $link . '.</p>'],
    ];
  }

}
