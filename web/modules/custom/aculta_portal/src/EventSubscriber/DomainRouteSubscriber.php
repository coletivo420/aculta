<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\aculta_portal\Domain\DomainRoutePolicy;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\Routing\RouteCollection;

/** Applies canonical paths and purpose metadata to known route families. */
final class DomainRouteSubscriber extends RouteSubscriberBase {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    // Rename Admin Paths runs at -2048; translate its resulting paths after it.
    return [RoutingEvents::ALTER => [['onAlterRoutes', -2049]]];
  }

  /** {@inheritdoc} */
  protected function alterRoutes(RouteCollection $collection): void {
    // Política de confirmação de e-mail: cursos e checkout exigem e-mail confirmado, somado à regra original do dono
    // do recurso (ProtectedRouteAccessCheck). Rota sem recurso (/checkout) só recebe a checagem de e-mail; mantém _permission.
    $protected = [
      'lms.course.start' => 'aculta_portal.access.protected_route:course',
      'lms.group.answer_form' => 'aculta_portal.access.protected_route:course',
      'commerce_checkout.form' => 'aculta_portal.access.protected_route:checkout',
    ];
    foreach ($protected as $name => $access) {
      if ($route = $collection->get($name)) {
        $route->setRequirement('_custom_access', $access);
      }
    }
    if ($route = $collection->get('commerce_checkout.checkout')) {
      $route->setRequirement('_custom_access', 'aculta_portal.access.protected_route:account');
    }
    // Consulta de CEP: controller do Portal (só AJAX e sem a resposta bruta do ViaCEP).
    if ($route = $collection->get('cep_autocomplete.viacep_lookup')) {
      $route->setDefault('_controller', '\\Drupal\\aculta_portal\\Controller\\CepLookupController::lookup');
    }
    // Página do acordo de termos: substituída pelo popup de aceite (AccountTermsHooks). A rota continua existindo
    // porque o módulo agreement a consulta por nome; o caminho /aceite-termos responde 404.
    if ($route = $collection->get('agreement.aculta_account_terms')) {
      $route->setDefault('_controller', '\\Drupal\\aculta_portal\\Controller\\PortalController::retiredNotFound');
      $route->setDefault('_form', NULL);
      $route->setRequirements(['_access' => 'TRUE']);
    }

    $pathChanges = [
      'user.login' => '/entrar',
      'user.register' => '/criar-conta',
      'user.pass' => '/recuperar-senha',
      'user.reset' => '/recuperar-acesso/{uid}/{timestamp}/{hash}',
      'user.reset.form' => '/recuperar-acesso/{uid}',
      'user.reset.login' => '/recuperar-acesso/{uid}/{timestamp}/{hash}/entrar',
      'user.logout' => '/sair',
      'user.logout.confirm' => '/sair/confirmar',
      'user.logout.http' => '/sair',
      'user.edit' => '/identidade/editar',
      'entity.user.canonical' => '/identidade/{user}',
      'entity.user.edit_form' => '/painel-administrativo/pessoas/{user}/editar',
      'entity.email_confirmer_confirmation.response_form' => '/confirmar-email/{email_confirmer_confirmation}/{hash}',
      'entity.email_confirmer_confirmation.resend' => '/confirmar-email/reenviar/{confirmation}',
      'entity.user.cancel_email_change' => '/seguranca/email/cancelar/{user}',
      'change_mail_page.change_mail' => '/seguranca/email',
      'change_mail_page.change_mail_form' => '/seguranca/email/{user}',
      'social_auth.network.redirect' => '/oauth/{network}',
      'social_auth.network.callback' => '/oauth/{network}/retorno',
      'commerce_payment.notify' => '/integracoes/pagamentos/{commerce_payment_gateway}/notificacao',
    ];
    $accountRoutes = array_fill_keys(array_keys($pathChanges), TRUE);
    unset(
      $accountRoutes['commerce_payment.notify'],
      $accountRoutes['entity.user.edit_form'],
    );

    // Browser Form API is the supported password-authentication surface.
    // Core's JSON login/password endpoints bypass Form API and therefore the
    // Turnstile policy, so this site does not publish them.
    foreach (['user.login.http', 'user.pass.http'] as $httpRoute) {
      $collection->remove($httpRoute);
    }

    // Keep user.page because Core Navigation and other upstream flows still
    // generate it. It is compatibility-only and never renders Core's generic
    // profile UI. Do not bind it to a purpose: admin Navigation may generate
    // the route while the current host is MAIN.
    if ($route = $collection->get('user.page')) {
      $route->setDefault('_controller', '\\Drupal\\aculta_portal\\Controller\\PortalController::legacyUserPageRedirect');
    }

    foreach ($pathChanges as $name => $path) {
      if ($route = $collection->get($name)) {
        $route->setPath($path);
        if (isset($accountRoutes[$name])) {
          $route->setOption('_aculta_domain_purpose', 'account');
        }
        elseif ($name === 'commerce_payment.notify') {
          $route->setOption('_aculta_domain_purpose', 'main');
          // Provider notifications are machine-to-machine POST requests.
          // Do not expose a GET variant through Commerce's generic route.
          $route->setMethods(['POST']);
        }
        elseif ($name === 'entity.user.edit_form') {
          // Core's one-time password reset uses this same route. The request
          // policy permits it on ACCOUNT only with Core's session token.
          $route->setOption('_aculta_domain_purpose', 'main');
        }
      }
    }

    // Keep the canonical registration URL available while public sign-up is
    // closed. When Core registration is enabled later, its normal entity form
    // remains at the same route and path.
    if ($route = $collection->get('user.register')) {
      if ($this->configFactory->get('user.settings')->get('register') !== 'visitors') {
        $route->setDefault('_title', 'Cadastro temporariamente indisponível');
        $route->setDefault('_controller', '\\Drupal\\aculta_portal\\Controller\\RegistrationController::closed');
        $defaults = $route->getDefaults();
        unset($defaults['_entity_form']);
        $route->setDefaults($defaults);
        $requirements = $route->getRequirements();
        unset($requirements['_access_user_register']);
        $requirements['_access'] = 'TRUE';
        $route->setRequirements($requirements);
      }
    }

    // Endereços, pagamentos e pedido: o ID do usuário sai da URL; AccountRouteSubscriber põe o usuário da sessão
    // no parâmetro antes das verificações de acesso do Commerce. Rotas antigas /user/{uid}/... deixam de existir.
    $account_paths = [
      'commerce_order.address_book.overview' => ['/enderecos', []],
      'commerce_order.address_book.add_form' => ['/enderecos/novo/{profile_type}', []],
      'commerce_order.address_book.edit_form' => ['/enderecos/{profile}/editar', []],
      'commerce_order.address_book.delete_form' => ['/enderecos/{profile}/excluir', []],
      'commerce_order.address_book.set_default' => ['/enderecos/{profile}/padrao', []],
      'entity.commerce_order.user_view' => ['/pedidos/{commerce_order}', []],
    ];
    foreach ($account_paths as $name => [$path, $unused]) {
      if ($route = $collection->get($name)) {
        $route->setPath($path);
        $route->setOption('parameters', array_diff_key((array) $route->getOption('parameters'), ['user' => TRUE]));
        $route->setOption('_aculta_domain_purpose', 'account');
      }
    }
    // Meios de pagamento não são guardados nem exibidos pelo portal: o pagamento é do Mercado Pago. As rotas de
    // cadastro, edição, exclusão e padrão são removidas (404). A lista antiga /pagamentos redireciona para /pedidos.
    foreach (['entity.commerce_payment_method.add_form', 'entity.commerce_payment_method.edit_form', 'entity.commerce_payment_method.delete_form', 'entity.commerce_payment_method.set_default'] as $name) {
      $collection->remove($name);
    }
    // Verificações que dependiam do usuário da URL passam a usar a sessão (login) e a verificação da entidade
    // (dono do pedido ou do perfil). Ver docs/portal/REDIRECTS.md, seção de caminhos de conta.
    // Endereços do Commerce: a tela própria foi substituída por Meus Dados (CEP). Os caminhos antigos
    // redirecionam para lá (ver PortalController::addressRedirect e docs/portal/REDIRECTS.md).
    foreach (['commerce_order.address_book.overview', 'commerce_order.address_book.add_form', 'commerce_order.address_book.edit_form', 'commerce_order.address_book.delete_form', 'commerce_order.address_book.set_default'] as $name) {
      if ($route = $collection->get($name)) {
        $route->setDefaults(['_controller' => '\\Drupal\\aculta_portal\\Controller\\PortalController::addressRedirect', '_title' => 'Meus dados']);
        $route->setRequirements(['_user_is_logged_in' => 'TRUE']);
        $route->setOption('parameters', []);
        $route->setOption('_aculta_domain_purpose', 'account');
      }
    }
    // Pedidos e pagamentos são as listas do Commerce, agora no painel da conta (sem ID na URL).
    // O controller da página é o do Portal; o acesso exige login e a lista usa o usuário da sessão.
    $account_lists = [
      'view.commerce_user_orders.order_page' => ['/pedidos', 'orders', 'Meus pedidos'],
    ];
    foreach ($account_lists as $name => [$path, $method, $title]) {
      if ($route = $collection->get($name)) {
        $route->setPath($path);
        $route->setDefaults(['_controller' => '\\Drupal\\aculta_portal\\Controller\\PortalController::' . $method, '_title' => $title]);
        $route->setRequirements(['_user_is_logged_in' => 'TRUE']);
        $route->setOption('parameters', []);
        $route->setOption('_aculta_domain_purpose', 'account');
      }
    }
    // /pagamentos: 301 para /pedidos (sem 404 para links antigos). Acesso livre para o redirecionamento acontecer.
    if ($route = $collection->get('entity.commerce_payment_method.collection')) {
      $route->setPath('/pagamentos');
      $route->setDefaults(['_controller' => '\\Drupal\\aculta_portal\\Controller\\PaymentMethodsRedirectController::redirectToOrders']);
      $route->setRequirements(['_access' => 'TRUE']);
      $route->setOption('parameters', []);
      $route->setOption('_aculta_domain_purpose', 'account');
    }
    foreach (['aculta_portal.dashboard', 'aculta_portal.my_data', 'aculta_portal.my_data_address', 'aculta_portal.my_data_other', 'aculta_portal.connections', 'aculta_portal.security', 'aculta_portal.security_password', 'aculta_portal.support_my', 'aculta_portal.account_courses', 'aculta_portal.account_settings'] as $name) {
      if ($route = $collection->get($name)) {
        $route->setOption('_aculta_domain_purpose', 'account');
      }
    }
    if ($route = $collection->get('aculta_portal.search')) {
      $route->setOption('_aculta_domain_purpose', 'main');
    }
    if ($route = $collection->get('aculta_portal.support_form')) {
      $route->setOption('_aculta_domain_purpose', 'support');
    }
    foreach (['aculta_portal.wiki_home', 'aculta_portal.wiki_search', 'view.wiki_entries.page_1'] as $name) {
      if ($route = $collection->get($name)) {
        $route->setOption('_aculta_domain_purpose', 'wiki');
      }
    }
    // Página do produto da loja pertence ao purpose SHOP (host de loja por ambiente, ver aculta_deployer).
    // Carrinho e checkout continuam no MAIN (DomainRoutePolicy).
    if ($route = $collection->get('entity.commerce_product.canonical')) {
      $route->setOption('_aculta_domain_purpose', 'shop');
    }

    // Rotas do módulo Forum (fórum, tópicos e containers) pertencem ao purpose FORUM. Admin continua em MAIN (abaixo).
    foreach ($collection as $name => $route) {
      if (str_starts_with((string) $name, 'forum.') || str_starts_with((string) $name, 'entity.taxonomy_term.forum_')) {
        $route->setOption('_aculta_domain_purpose', 'forum');
      }
    }

    foreach ($collection as $name => $route) {
      if ($route->getOption('_admin_route')) {
        $route->setOption('_aculta_domain_purpose', 'main');
      }
      if (DomainRoutePolicy::isCentralTransactionRouteName((string) $name)) {
        $route->setOption('_aculta_domain_purpose', 'main');
        if ($name !== 'commerce_payment.notify') {
          // Browser navigation may canonicalize to MAIN. Machine-to-machine
          // notification POSTs must never be redirected between hosts.
          $route->setOption('_aculta_cross_domain_canonical_purpose', 'main');
        }
      }
    }

    // Rename Admin Paths runs at -2048. Translate only first-level groups after
    // its prefix rewrite, leaving stable internal route suffixes untouched.
    $adminSegments = [
      'config' => 'configuracoes',
      'people' => 'pessoas',
      'content' => 'conteudo',
      'structure' => 'estrutura',
      'commerce' => 'comercio',
      'reports' => 'relatorios',
      'appearance' => 'aparencia',
      'modules' => 'modulos',
    ];
    foreach ($collection as $route) {
      $path = $route->getPath();
      if ($path === '/painel-administrativo' || str_starts_with($path, '/painel-administrativo/')) {
        $route->setOption('_aculta_domain_purpose', 'main');
      }
      foreach ($adminSegments as $source => $target) {
        if (preg_match('~^/painel-administrativo/' . preg_quote($source, '~') . '(?:/|$)~', $path)) {
          $route->setPath(preg_replace('~^/painel-administrativo/' . preg_quote($source, '~') . '(?=/|$)~', '/painel-administrativo/' . $target, $path, 1));
          break;
        }
      }
    }
  }

}
