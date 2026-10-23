<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Unit;

use Drupal\aculta_donation\Checkout\DonationCheckoutProvider;
use Drupal\aculta_donation\Form\DonationSettingsForm;
use Drupal\aculta_portal\Checkout\CheckoutConfigurationResolver;
use Drupal\aculta_portal\Checkout\CheckoutContextRegistry;
use Drupal\aculta_portal\Checkout\CheckoutPolicyManager;
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
 * Caracteriza a decisão atual de rotas de carrinho e checkout (P-CART A).
 *
 * Dois subscribers do Portal alteram `commerce_cart.page`: um troca o caminho e o outro controla o acesso conforme a
 * política de checkout. Este teste fixa que o resultado não depende da ordem e que `donate`, `cart` e `both` se
 * comportam como antes da migração do seletor (P-CART D). As asserções são as da linha de base P-CART A, escritas
 * contra o `CartCheckoutRouteSubscriber` do Donation, hoje removido.
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
   * Subscriber de disponibilidade do Portal, alimentado pelo provider do Donation.
   *
   * @param array<string, mixed> $settings
   */
  private function subscriber(array $settings, bool $donationRegistrado = TRUE): CommerceCheckoutRouteSubscriber {
    $providers = $donationRegistrado
      ? [new DonationCheckoutProvider(
        $this->getConfigFactoryStub(['aculta_donation.settings' => $settings]),
        new OrderTypeCheckoutFlows($this->createMock(EntityTypeManagerInterface::class)),
      )]
      : [];
    return new CommerceCheckoutRouteSubscriber(new CheckoutPolicyManager(new CheckoutConfigurationResolver(new CheckoutContextRegistry($providers))));
  }

  /**
   * @param array<string, mixed> $settings
   */
  private function aplicar(array $settings, bool $donationPrimeiro): RouteCollection {
    $colecao = $this->colecao();
    $evento = new RouteBuildEvent($colecao);
    $donation = $this->subscriber($settings);
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
    $this->subscriber(['donation_route' => 'donate'])->onAlterRoutes(new RouteBuildEvent($colecao));
    $this->assertCount(1, $colecao);
  }

  public function testSemDonationRegistradoAsRotasDoCommerceFicamIntactas(): void {
    $colecao = $this->colecao();
    $antes = $this->resumo($colecao);
    $this->subscriber([], FALSE)->onAlterRoutes(new RouteBuildEvent($colecao));
    $this->assertSame($antes, $this->resumo($colecao));
  }

}
