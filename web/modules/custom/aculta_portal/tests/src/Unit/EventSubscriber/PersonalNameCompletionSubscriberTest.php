<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\EventSubscriber;

use Drupal\aculta_portal\Checkout\PersonalNameStore;
use Drupal\aculta_portal\EventSubscriber\PersonalNameCompletionSubscriber;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Copia do nome do pedido para a conta criada na compra, sem sobrescrever.
 */
#[CoversClass(PersonalNameCompletionSubscriber::class)]
#[Group('aculta_portal')]
final class PersonalNameCompletionSubscriberTest extends UnitTestCase {

  /**
   * Pedido com nome, conta autenticada e perfil vazio: grava o nome.
   */
  public function testCopiesNameToAccountWithEmptyProfile(): void {
    $names = $this->createMock(PersonalNameStore::class);
    $names->method('forAccount')->willReturn(['first' => '', 'last' => '']);
    $names->expects($this->once())->method('saveForAccount')->with($this->anything(), 'Maria', 'Silva');

    $this->subscriber($names, ['first' => 'Maria', 'last' => 'Silva'], TRUE)->onCompletion($this->event());
  }

  /**
   * Perfil que já tem nome não é sobrescrito.
   */
  public function testKeepsNameAlreadySavedInAccount(): void {
    $names = $this->createMock(PersonalNameStore::class);
    $names->method('forAccount')->willReturn(['first' => 'Ana', 'last' => 'Souza']);
    $names->expects($this->never())->method('saveForAccount');

    $this->subscriber($names, ['first' => 'Maria', 'last' => 'Silva'], TRUE)->onCompletion($this->event());
  }

  /**
   * Sem nome no pedido ou com visitante, nada é gravado.
   */
  public function testSkipsWhenNoNameOrNoAccount(): void {
    $names = $this->createMock(PersonalNameStore::class);
    $names->expects($this->never())->method('saveForAccount');

    $this->subscriber($names, [], TRUE)->onCompletion($this->event());
    $this->subscriber($names, ['first' => 'Maria', 'last' => 'Silva'], FALSE)->onCompletion($this->event());
  }

  /**
   * Assina a conclusão do checkout.
   */
  public function testSubscribesToCompletion(): void {
    $this->assertArrayHasKey(CheckoutEvents::COMPLETION, PersonalNameCompletionSubscriber::getSubscribedEvents());
  }

  /**
   * Monta o assinante com um pedido de teste.
   *
   * @param array<string, string> $nome
   *   Nome guardado no pedido.
   */
  private function subscriber(PersonalNameStore $names, array $nome, bool $autenticado): PersonalNameCompletionSubscriber {
    $conta = $this->createMock(AccountInterface::class);
    $conta->method('isAuthenticated')->willReturn($autenticado);
    $pedido = $this->createMock(OrderInterface::class);
    $pedido->method('getData')->willReturn($nome);
    $pedido->method('getCustomer')->willReturn($conta);
    $this->pedido = $pedido;
    return new PersonalNameCompletionSubscriber($names);
  }

  /**
   * Evento de conclusão do último pedido montado.
   */
  private function event(): OrderEvent {
    return new OrderEvent($this->pedido);
  }

  /**
   * Último pedido montado para o evento.
   */
  private OrderInterface $pedido;

}
