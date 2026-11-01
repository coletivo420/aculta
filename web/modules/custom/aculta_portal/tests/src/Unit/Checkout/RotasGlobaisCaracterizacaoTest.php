<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\aculta_commerce\Checkout\ShopCheckoutProvider;
use Drupal\aculta_donation\Checkout\DonationCheckoutProvider;
use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\aculta_lms\Checkout\LmsCheckoutProvider;
use Drupal\aculta_portal\Checkout\CheckoutConfigurationResolver;
use Drupal\aculta_portal\Checkout\CheckoutContextRegistry;
use Drupal\aculta_portal\Checkout\CheckoutPolicyManager;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\aculta_portal\EventSubscriber\CartFriendlyRouteSubscriber;
use Drupal\aculta_portal\EventSubscriber\CommerceCheckoutRouteSubscriber;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Caracteriza o alcance de `donation_route` e `common_checkout_enabled` (P1 / B-01).
 *
 * Mede quais rotas globais do Commerce o `CommerceCheckoutRouteSubscriber` fecha, com os três providers reais
 * (Loja, LMS e Doações) registrados. As rotas dedicadas — `/carrinho/finalizar` e `/apoiar/{pedido}/{etapa}` — não
 * entram no alcance do subscriber. Se a política ou o conjunto de rotas mudar, o teste falha.
 */
#[Group('aculta_portal')]
final class RotasGlobaisCaracterizacaoTest extends UnitTestCase {

  /**
   * Coleção com as três rotas globais do Commerce.
   */
  private function colecao(): RouteCollection {
    $colecao = new RouteCollection();
    $colecao->add('commerce_cart.page', new Route('/cart', [], ['_access' => 'TRUE']));
    $colecao->add('commerce_checkout.checkout', new Route('/checkout', [], ['_permission' => 'access checkout']));
    $colecao->add('commerce_checkout.form', new Route('/checkout/{commerce_order}/{step}', [], ['_custom_access' => '\Drupal\commerce_checkout\Controller\CheckoutController::checkAccess']));
    $colecao->add('aculta_portal.cart_checkout', new Route('/carrinho/finalizar', [], ['_access' => 'TRUE']));
    $colecao->add('aculta_donation.donation_controller_formPage', new Route('/donate/{commerce_order}/{step}', [], ['_custom_access' => '\Drupal\aculta_donation\Controller\DonationController::checkAccess']));
    return $colecao;
  }

  /**
   * Política com os três providers reais.
   */
  private function politica(array $donationSettings, bool $commonCheckout): CheckoutPolicyManager {
    $flows = new OrderTypeCheckoutFlows($this->createMock(EntityTypeManagerInterface::class));
    $factory = $this->getConfigFactoryStub([
      CheckoutSettings::CONFIG => [
        'common_checkout_enabled' => $commonCheckout,
        'shop_order_type' => 'default',
        'lms_order_type' => 'curso',
      ],
      'aculta_donation.settings' => $donationSettings,
    ]);
    $settings = new CheckoutSettings($factory);
    $providers = [
      new ShopCheckoutProvider($flows, $settings),
      new LmsCheckoutProvider($flows, $settings),
      new DonationCheckoutProvider($factory, $flows),
    ];
    return new CheckoutPolicyManager(
      new CheckoutConfigurationResolver(new CheckoutContextRegistry($providers)),
      $settings,
    );
  }

  /**
   * Aplica o subscriber do Portal sobre a coleção.
   */
  private function aplicar(array $donationSettings, bool $commonCheckout): RouteCollection {
    $colecao = $this->colecao();
    $evento = new RouteBuildEvent($colecao);
    (new CommerceCheckoutRouteSubscriber($this->politica($donationSettings, $commonCheckout)))->onAlterRoutes($evento);
    (new CartFriendlyRouteSubscriber())->onAlterRoutes($evento);
    return $colecao;
  }

  /**
   * Combina os quatro valores de `donation_route` com a chave global ligada.
   */
  public static function donationRouteComComumLigado(): array {
    return [
      'donate' => [['donation_route' => DonationSettingsForm::ROTA_DONATE]],
      'ausente' => [[]],
      'desconhecido' => [['donation_route' => 'outro']],
    ];
  }

  /**
   * donation_route em `donate`, ausente ou desconhecido fecha as três rotas globais.
   */
  #[DataProvider('donationRouteComComumLigado')]
  public function testDonationRouteSemCarrinhoFechaAsRotasGlobais(array $settings): void {
    $colecao = $this->aplicar($settings, TRUE);

    $this->assertSame('/carrinho', $colecao->get('commerce_cart.page')->getPath());
    $this->assertSame('FALSE', $colecao->get('commerce_cart.page')->getRequirements()['_access']);
    foreach (['commerce_checkout.checkout', 'commerce_checkout.form'] as $nome) {
      $requisitos = $colecao->get($nome)->getRequirements();
      $this->assertSame('FALSE', $requisitos['_access']);
      $this->assertArrayNotHasKey('_permission', $requisitos);
    }
  }

  /**
   * donation_route em `cart` ou `both` mantém as rotas globais como o Commerce as define.
   */
  public static function donationRouteComCarrinho(): array {
    return [
      'cart' => [DonationSettingsForm::ROTA_CART],
      'both' => [DonationSettingsForm::ROTA_AMBAS],
    ];
  }

  #[DataProvider('donationRouteComCarrinho')]
  public function testCartOuBothMantemAsRotasGlobais(string $modo): void {
    $colecao = $this->aplicar(['donation_route' => $modo], TRUE);

    $this->assertSame('/carrinho', $colecao->get('commerce_cart.page')->getPath());
    $this->assertSame('TRUE', $colecao->get('commerce_cart.page')->getRequirements()['_access']);
    $this->assertSame('access checkout', $colecao->get('commerce_checkout.checkout')->getRequirements()['_permission']);
    $this->assertArrayHasKey('_custom_access', $colecao->get('commerce_checkout.form')->getRequirements());
    $this->assertArrayNotHasKey('_access', $colecao->get('commerce_checkout.checkout')->getRequirements());
  }

  /**
   * Com a chave global desligada, nenhum valor de donation_route libera as rotas.
   */
  #[DataProvider('todasAsRotas')]
  public function testChaveGlobalDesligadaFechaTodasAsRotas(string $modo): void {
    $colecao = $this->aplicar(['donation_route' => $modo], FALSE);

    foreach (['commerce_cart.page', 'commerce_checkout.checkout', 'commerce_checkout.form'] as $nome) {
      $this->assertSame('FALSE', $colecao->get($nome)->getRequirements()['_access'], $nome);
    }
    $this->assertArrayNotHasKey('_permission', $colecao->get('commerce_checkout.checkout')->getRequirements());
  }

  /**
   * Todos os valores válidos de donation_route.
   */
  public static function todasAsRotas(): array {
    return [
      'donate' => [DonationSettingsForm::ROTA_DONATE],
      'cart' => [DonationSettingsForm::ROTA_CART],
      'both' => [DonationSettingsForm::ROTA_AMBAS],
    ];
  }

  /**
   * As rotas fora do conjunto global ficam intactas em qualquer combinação.
   */
  #[DataProvider('todasAsRotas')]
  public function testRotasDedicadasNaoSaoTocadas(string $modo): void {
    foreach ([TRUE, FALSE] as $comum) {
      $colecao = $this->aplicar(['donation_route' => $modo], $comum);
      $this->assertSame('/carrinho/finalizar', $colecao->get('aculta_portal.cart_checkout')->getPath());
      $this->assertSame('TRUE', $colecao->get('aculta_portal.cart_checkout')->getRequirements()['_access']);
      $this->assertSame('/donate/{commerce_order}/{step}', $colecao->get('aculta_donation.donation_controller_formPage')->getPath());
      $this->assertArrayHasKey('_custom_access', $colecao->get('aculta_donation.donation_controller_formPage')->getRequirements());
      $this->assertArrayNotHasKey('_access', $colecao->get('aculta_donation.donation_controller_formPage')->getRequirements());
    }
  }

  /**
   * A política expõe a disponibilidade por contexto, não por rota.
   */
  public function testDisponibilidadePorContexto(): void {
    $politica = $this->politica(['donation_route' => DonationSettingsForm::ROTA_CART], TRUE);

    $this->assertTrue($politica->commonCheckoutRoutesAvailable());
    $this->assertFalse($politica->allowsDedicated('donation'));
    $this->assertTrue($politica->allowsCommon('donation'));
    $this->assertTrue($politica->allowsCommon('shop'));
    $this->assertTrue($politica->allowsCommon('lms'));
    $this->assertFalse($politica->allowsCommon('contexto_inexistente'));
    $this->assertFalse($politica->allowsDedicated('contexto_inexistente'));
  }

  /**
   * Contextos além de Doações não controlam as rotas globais.
   */
  public function testSomenteDonationControlaAsRotasGlobais(): void {
    $flows = new OrderTypeCheckoutFlows($this->createMock(EntityTypeManagerInterface::class));
    $factory = $this->getConfigFactoryStub([
      CheckoutSettings::CONFIG => ['common_checkout_enabled' => TRUE],
      'aculta_donation.settings' => ['donation_route' => DonationSettingsForm::ROTA_AMBAS],
    ]);
    $settings = new CheckoutSettings($factory);
    $politica = new CheckoutPolicyManager(
      new CheckoutConfigurationResolver(new CheckoutContextRegistry([
        new ShopCheckoutProvider($flows, $settings),
        new LmsCheckoutProvider($flows, $settings),
      ])),
      $settings,
    );

    // Sem Donation registrado, não há contexto controlador: rotas como o Commerce.
    $this->assertTrue($politica->commonCheckoutRoutesAvailable());
  }

}
