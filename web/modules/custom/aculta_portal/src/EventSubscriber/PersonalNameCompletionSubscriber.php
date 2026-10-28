<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\aculta_portal\Checkout\PersonalNameStore;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Ao concluir o checkout, copia o nome informado no pedido para a conta criada na compra.
 *
 * Roda depois da criação da conta pelo Commerce e antes do cadastro simplificado. Só preenche perfil vazio: nunca
 * sobrescreve o nome já salvo na conta.
 */
final class PersonalNameCompletionSubscriber implements EventSubscriberInterface {

  /**
   * Cria o assinante.
   */
  public function __construct(
    private readonly PersonalNameStore $names,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [CheckoutEvents::COMPLETION => ['onCompletion', -50]];
  }

  /**
   * Copia o nome do pedido para a conta do pedido, quando o perfil ainda não tem nome.
   */
  public function onCompletion(OrderEvent $event): void {
    $order = $event->getOrder();
    $pedido = $order->getData(PersonalNameStore::ORDER_DATA_KEY, []);
    $conta = $order->getCustomer();
    if (empty($pedido['first']) || empty($pedido['last']) || !$conta->isAuthenticated()) {
      return;
    }
    $atual = $this->names->forAccount($conta);
    if ($atual['first'] !== '' || $atual['last'] !== '') {
      return;
    }
    $this->names->saveForAccount($conta, (string) $pedido['first'], (string) $pedido['last']);
  }

}
