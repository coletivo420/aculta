<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Unit;

use Drupal\aculta_donation\EventSubscriber\CartCheckoutRouteSubscriber;
use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\aculta_portal\EventSubscriber\CartFriendlyRouteSubscriber;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Caracteriza a decisão atual de rotas de carrinho e checkout (P-CART A).
 *
 * Dois subscribers alteram `commerce_cart.page`: o do Portal troca o caminho e o do Donation controla o acesso.
 * Este teste registra que o resultado não depende da ordem e que `donate`, `cart` e `both` se comportam como hoje.
 * Serve de baseline de equivalência para a migração do seletor (P-CART D).
 */
#[Group('aculta_donation')]
final class CartCheckoutRoutesCharacterizationTest extends UnitTestCase {

  private function colecao(): RouteCollection {
    $colecao = new RouteCollection();
    $colecao->add('commerce_cart.page', new Route('/cart', [], ['_access' => 'TRUE']));
    $colecao->add('commerce_checkout.checkout', new Route('/checkout', [], ['_permission' => 'access checkout']));
    $colecao->add('commerce_checkout.form', new Route('/checkout/{commerce_order}/{step}', [], ['_custom_access' => '\Drupal\commerce_checkout\Controller\CheckoutController::checkAccess']));
    return $colecao;
  }

  /**
   * @param array<string, mixed> $settings
   */
  private function aplicar(array $settings, bool $donationPrimeiro): RouteCollection {
    $colecao = $this->colecao();
    $evento = new RouteBuildEvent($colecao);
    $donation = new CartCheckoutRouteSubscriber($this->getConfigFactoryStub(['aculta_donation.settings' => $settings]));
    $portal = new CartFriendlyRouteSubscriber();
    if ($donationPrimeiro) {
      $donation->onAlterRoutes($evento);
      $portal->onAlterRoutes($evento);
    }
    else {
      $portal->onAlterRoutes($evento);
      $donation->onAlterRoutes($evento);
    }
    return $colecao;
  }

  /**
   * Resumo comparável das três rotas do Commerce.
   *
   * @return array<string, array<string, mixed>>
   */
  private function resumo(RouteCollection $colecao): array {
    $resumo = [];
    foreach (['commerce_cart.page', 'commerce_checkout.checkout', 'commerce_checkout.form'] as $nome) {
      $rota = $colecao->get($nome);
      $resumo[$nome] = ['path' => $rota->getPath(), 'requirements' => $rota->getRequirements()];
    }
    return $resumo;
  }

  #[DataProvider('modos')]
  public function testResultadoNaoDependeDaOrdemDosSubscribers(string $modo): void {
    $settings = ['donation_route' => $modo];
    $this->assertSame(
      $this->resumo($this->aplicar($settings, TRUE)),
      $this->resumo($this->aplicar($settings, FALSE)),
    );
  }

  public static function modos(): array {
    return [
      'donate' => [DonationSettingsForm::ROTA_DONATE],
      'cart' => [DonationSettingsForm::ROTA_CART],
      'both' => [DonationSettingsForm::ROTA_AMBAS],
    ];
  }

  public function testDonateBloqueiaCarrinhoECheckoutMasMantemCaminho(): void {
    $resumo = $this->resumo($this->aplicar(['donation_route' => DonationSettingsForm::ROTA_DONATE], TRUE));
    $this->assertSame('/carrinho', $resumo['commerce_cart.page']['path']);
    $this->assertSame('FALSE', $resumo['commerce_cart.page']['requirements']['_access']);
    foreach (['commerce_checkout.checkout', 'commerce_checkout.form'] as $nome) {
      $this->assertSame('FALSE', $resumo[$nome]['requirements']['_access']);
      $this->assertArrayNotHasKey('_permission', $resumo[$nome]['requirements']);
    }
  }

  #[DataProvider('modosComCommerce')]
  public function testCartOuBothMantemCommerceIntacto(string $modo): void {
    $resumo = $this->resumo($this->aplicar(['donation_route' => $modo], TRUE));
    $this->assertSame('/carrinho', $resumo['commerce_cart.page']['path']);
    $this->assertSame('TRUE', $resumo['commerce_cart.page']['requirements']['_access']);
    $this->assertSame('access checkout', $resumo['commerce_checkout.checkout']['requirements']['_permission']);
    $this->assertArrayNotHasKey('_access', $resumo['commerce_checkout.checkout']['requirements']);
    $this->assertArrayHasKey('_custom_access', $resumo['commerce_checkout.form']['requirements']);
    $this->assertArrayNotHasKey('_access', $resumo['commerce_checkout.form']['requirements']);
  }

  public static function modosComCommerce(): array {
    return [
      'cart' => [DonationSettingsForm::ROTA_CART],
      'both' => [DonationSettingsForm::ROTA_AMBAS],
    ];
  }

  public function testCartOuBothGeramMesmaColecao(): void {
    $this->assertSame(
      $this->resumo($this->aplicar(['donation_route' => DonationSettingsForm::ROTA_CART], TRUE)),
      $this->resumo($this->aplicar(['donation_route' => DonationSettingsForm::ROTA_AMBAS], TRUE)),
    );
  }

  public function testConfiguracaoAusenteOuDesconhecidaSeComportaComoDonate(): void {
    $donate = $this->resumo($this->aplicar(['donation_route' => DonationSettingsForm::ROTA_DONATE], TRUE));
    $this->assertSame($donate, $this->resumo($this->aplicar([], TRUE)));
    $this->assertSame($donate, $this->resumo($this->aplicar(['donation_route' => 'valor_invalido'], TRUE)));
  }

  public function testColecaoSemRotasDoCommerceNaoQuebra(): void {
    $colecao = new RouteCollection();
    $colecao->add('outra.rota', new Route('/outra'));
    (new CartCheckoutRouteSubscriber($this->getConfigFactoryStub(['aculta_donation.settings' => ['donation_route' => 'donate']])))
      ->onAlterRoutes(new RouteBuildEvent($colecao));
    $this->assertCount(1, $colecao);
  }

}
