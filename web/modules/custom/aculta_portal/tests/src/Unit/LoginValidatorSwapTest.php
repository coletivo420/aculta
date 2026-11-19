<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Cart\AddToCartAjax;
use Drupal\aculta_portal\Cart\CartUtility;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Hook\FormHooks;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Garante que o callback do Portal rode exatamente uma vez no login.
 *
 * Independentemente da lista de validadores do Core. Uma falha silenciosa
 * restauraria a revelação.
 */
#[Group('aculta_portal')]
final class LoginValidatorSwapTest extends UnitTestCase {

  private const WRAPPER = 'aculta_portal.form_callbacks:validateLoginAuthentication';

  /**
   * Executa o alter do formulário de login e devolve os validadores.
   *
   * @param array $start
   *   Lista inicial de validadores do formulário.
   *
   * @return array
   *   Lista de validadores após o alter.
   */
  private function validators(array $start): array {
    // Serviços finais (DomainSiteManager, CartUtility, AddToCartAjax) são construídos de verdade, não duplicados.
    // AddToCartAjax::alter() ignora formulários que não são "Adicionar ao carrinho", então os mocks
    // não são usados aqui.
    $purpose = new DomainSiteManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $translation = $this->createMock(TranslationInterface::class);
    $hooks = new FormHooks(
      $this->createMock(CurrentRouteMatch::class),
      $this->createMock(AccountProxyInterface::class),
      $translation,
      new EmailConfirmationPolicy(
        $this->createMock(UserDataInterface::class),
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(ModuleHandlerInterface::class),
      ),
      $purpose,
      new AddToCartAjax(
        new CartUtility($this->createMock(CartProviderInterface::class), $purpose, $translation),
        $translation,
        $this->createMock(RendererInterface::class),
        $this->createMock(MessengerInterface::class),
        new RequestStack(),
      ),
      new RequestStack(),
    );
    $form = ['#validate' => $start];
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getFormObject')->willReturn(NULL);
    $hooks->formAlter($form, $state, 'user_login_form');
    return $form['#validate'];
  }

  /**
   * Verifica que o validador do Core é substituído no mesmo lugar.
   */
  public function testCoreValidatorIsReplacedInPlace(): void {
    $this->assertSame(
      [self::WRAPPER, '::validateFinal'],
      $this->validators(['::validateAuthentication', '::validateFinal']),
    );
  }

  /**
   * Verifica que o wrapper é adicionado primeiro quando falta o validador.
   */
  public function testWrapperIsAddedFirstWhenCoreValidatorIsMissing(): void {
    $this->assertSame(
      [self::WRAPPER, '::validateFinal'],
      $this->validators(['::validateFinal']),
    );
  }

  /**
   * Verifica que o wrapper aparece exatamente uma vez na lista.
   */
  public function testWrapperAppearsExactlyOnce(): void {
    $result = $this->validators([self::WRAPPER, '::validateAuthentication', '::validateFinal']);
    $this->assertSame(1, count(array_filter($result, static fn($v) => $v === self::WRAPPER)));
    $this->assertNotContains('::validateAuthentication', $result);
  }

}
