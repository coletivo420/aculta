<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Cart\AddToCartAjax;
use Drupal\aculta_portal\Cart\CartUtility;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Hook\FormHooks;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 * The login form always runs the Portal callback exactly once, whatever Core's
 * validator list looks like. A silent miss would restore the disclosure.
 */
#[Group('aculta_portal')]
final class LoginValidatorSwapTest extends UnitTestCase {

  private const WRAPPER = 'aculta_portal.form_callbacks:validateLoginAuthentication';

  private function validators(array $start): array {
    // Serviços finais (DomainPurposeManager, CartUtility, AddToCartAjax) são construídos de verdade, não duplicados.
    // AddToCartAjax::alter() ignora formulários que não são "Adicionar ao carrinho", então os mocks não são usados aqui.
    $purpose = new DomainPurposeManager(
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
      new EmailConfirmationPolicy($this->createMock(UserDataInterface::class), $this->createMock(EntityTypeManagerInterface::class)),
      $purpose,
      new AddToCartAjax(
        new CartUtility($this->createMock(CartProviderInterface::class), $purpose, $translation),
        $translation,
        $this->createMock(RendererInterface::class),
        $this->createMock(MessengerInterface::class),
      ),
    );
    $form = ['#validate' => $start];
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getFormObject')->willReturn(NULL);
    $hooks->formAlter($form, $state, 'user_login_form');
    return $form['#validate'];
  }

  public function testCoreValidatorIsReplacedInPlace(): void {
    $this->assertSame(
      [self::WRAPPER, '::validateFinal'],
      $this->validators(['::validateAuthentication', '::validateFinal']),
    );
  }

  public function testWrapperIsAddedFirstWhenCoreValidatorIsMissing(): void {
    $this->assertSame(
      [self::WRAPPER, '::validateFinal'],
      $this->validators(['::validateFinal']),
    );
  }

  public function testWrapperAppearsExactlyOnce(): void {
    $result = $this->validators([self::WRAPPER, '::validateAuthentication', '::validateFinal']);
    $this->assertSame(1, count(array_filter($result, static fn($v) => $v === self::WRAPPER)));
    $this->assertNotContains('::validateAuthentication', $result);
  }

}
