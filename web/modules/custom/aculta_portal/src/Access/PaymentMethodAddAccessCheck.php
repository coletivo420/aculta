<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Access;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Cadastro de método de pagamento só quando algum gateway ativo guarda cartões.
 *
 * O Commerce libera "Adicionar método de pagamento" para qualquer usuário logado. Com gateway que não
 * armazena métodos (ex.: Checkout Pro), o formulário não tem o que salvar: a ação e a rota ficam ocultas.
 * A ação local herda o acesso da rota, então um único controle esconde o botão e bloqueia a URL.
 */
final class PaymentMethodAddAccessCheck {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function access(AccountInterface $account): AccessResult {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden('Sem sessão.')->cachePerUser();
    }
    // A lista de gateways é invalidada ao salvar ou excluir um gateway.
    $result = AccessResult::forbidden('Nenhum gateway ativo guarda métodos de pagamento.')
      ->addCacheTags(['commerce_payment_gateway_list'])
      ->cachePerUser();
    foreach ($this->entityTypeManager->getStorage('commerce_payment_gateway')->loadMultiple() as $gateway) {
      if ($gateway->status() && $gateway->getPlugin() instanceof SupportsStoredPaymentMethodsInterface) {
        return AccessResult::allowed()
          ->addCacheTags(['commerce_payment_gateway_list'])
          ->cachePerUser();
      }
    }
    return $result;
  }

}
