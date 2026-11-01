<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\EventSubscriber;

use Drupal\aculta_commerce_melhorenvio\EventSubscriber\CartRefreshSubscriber;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingCartRefresher;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Qualquer mudança do carrinho (adição, quantidade ou remoção) refaz o frete guardado.
 */
#[CoversClass(CartRefreshSubscriber::class)]
#[Group('aculta_commerce_melhorenvio')]
final class CartRefreshSubscriberTest extends UnitTestCase {

  /**
   * Os três eventos de carrinho disparam o recálculo.
   */
  public function testSubscribesToAddUpdateAndRemove(): void {
    $events = CartRefreshSubscriber::getSubscribedEvents();
    $this->assertSame('refreshShipping', $events[CartEvents::CART_ORDER_ITEM_ADD]);
    $this->assertSame('refreshShipping', $events[CartEvents::CART_ORDER_ITEM_UPDATE]);
    $this->assertSame('refreshShipping', $events[CartEvents::CART_ORDER_ITEM_REMOVE]);
  }

  /**
   * Cada evento chama o recálculo uma vez.
   */
  public function testEachCartChangeRefreshesOnce(): void {
    $refresher = $this->createMock(ShippingCartRefresher::class);
    $refresher->expects($this->exactly(2))->method('refresh');
    $subscriber = new CartRefreshSubscriber($refresher);
    $subscriber->refreshShipping(new \stdClass());
    $subscriber->refreshShipping(new \stdClass());
  }

}
