<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Cart;

use Drupal\aculta_portal\EventSubscriber\CartFriendlyRouteSubscriber;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Caracteriza o caminho amigável do carrinho (P-CART A).
 */
#[Group('aculta_portal')]
final class CartFriendlyRouteSubscriberTest extends UnitTestCase {

  public function testCarrinhoPassaParaCaminhoEmPortuguesSemTrocarNome(): void {
    $colecao = new RouteCollection();
    $colecao->add('commerce_cart.page', new Route('/cart', [], ['_access' => 'TRUE']));
    $colecao->add('commerce_checkout.form', new Route('/checkout/{commerce_order}/{step}'));

    (new CartFriendlyRouteSubscriber())->onAlterRoutes(new RouteBuildEvent($colecao));

    $this->assertSame('/carrinho', $colecao->get('commerce_cart.page')->getPath());
    $this->assertSame('TRUE', $colecao->get('commerce_cart.page')->getRequirement('_access'));
    $this->assertSame('/checkout/{commerce_order}/{step}', $colecao->get('commerce_checkout.form')->getPath());
  }

  public function testSemARotaDoCommerceNaoFazNada(): void {
    $colecao = new RouteCollection();
    $colecao->add('outra.rota', new Route('/outra'));

    (new CartFriendlyRouteSubscriber())->onAlterRoutes(new RouteBuildEvent($colecao));

    $this->assertCount(1, $colecao);
    $this->assertSame('/outra', $colecao->get('outra.rota')->getPath());
  }

}
