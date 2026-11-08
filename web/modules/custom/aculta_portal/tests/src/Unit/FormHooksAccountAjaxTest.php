<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Cart\AddToCartAjax;
use Drupal\aculta_portal\Hook\FormHooks;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Formulários da conta viram HTMX no botão; exclusões e rotas fora da conta ficam como estão.
 */
#[Group('aculta_portal')]
class FormHooksAccountAjaxTest extends UnitTestCase {

  /**
   * Monta o hook com a rota informada e um formulário simulado.
   */
  private function hooks(?string $purpose, string $routeName = 'aculta_portal.account'): FormHooks {
    $route = new Route('/conta');
    if ($purpose !== NULL) {
      $route->setOption('_aculta_domain_purpose', $purpose);
    }

    $routeMatch = $this->createMock(CurrentRouteMatch::class);
    $routeMatch->method('getRouteObject')->willReturn($route);
    $routeMatch->method('getRouteName')->willReturn($routeName);
    $routeMatch->method('getRawParameters')->willReturn(new InputBag([]));

    $user = $this->createMock(AccountProxyInterface::class);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn (string $t, array $a = []): string => strtr($t, $a));

    $urlGenerator = $this->createMock(\Drupal\Core\Routing\UrlGeneratorInterface::class);
    $generated = new \Drupal\Core\GeneratedUrl();
    $generated->setGeneratedUrl('/conta/fake');
    $urlGenerator->method('generateFromRoute')->willReturn($generated);
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    $stack = new RequestStack();
    $stack->push(Request::create('/conta'));

    $cart = $this->createMock(AddToCartAjax::class);

    return new FormHooks(
      $routeMatch,
      $user,
      $translation,
      // Classes final não podem ser duplicadas; os casos testados não as usam.
      (new \ReflectionClass(EmailConfirmationPolicy::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      $cart,
      $stack,
    );
  }

  /**
   * Formulário simulado com botão de envio e um id informado.
   */
  private function form(array $extra = []): array {
    return $extra + [
      '#prefix' => '',
      '#suffix' => '',
      'actions' => ['submit' => ['#type' => 'submit', '#value' => 'Salvar']],
    ];
  }

  /**
   * Formulário da conta: o botão ganha HTMX, o wrapper e o callback sem redirecionamento.
   */
  public function testFormularioDeContaGanhaHtmx(): void {
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($this->createMock(FormInterface::class));

    $form = $this->form();
    $this->hooks('account')->formAlter($form, $formState, 'aculta_user_picture_form');

    $submit = $form['actions']['submit'];
    $this->assertArrayHasKey('data-hx-post', $submit['#attributes'] ?? []);
    $this->assertStringContainsString('portal-account-aculta-user-picture-form', $form['#prefix']);
    $this->assertContains('aculta_portal.form_callbacks:disableAccountRedirect', $form['#submit']);
  }

  /**
   * Formulários excluídos (login, senha) não recebem HTMX no envio.
   */
  public function testFormularioExcluidoNaoGanhaHtmx(): void {
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($this->createMock(FormInterface::class));

    $form = $this->form();
    $this->hooks('account')->formAlter($form, $formState, 'user_login_form');

    $this->assertArrayNotHasKey('data-hx-post', $form['actions']['submit']['#attributes'] ?? []);
    $this->assertStringNotContainsString('portal-account-', (string) $form['#prefix']);
  }

  /**
   * Rota fora da conta: o formulário fica como está.
   */
  public function testRotaForaDaContaNaoGanhaHtmx(): void {
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($this->createMock(FormInterface::class));

    $form = $this->form();
    $this->hooks('shop')->formAlter($form, $formState, 'aculta_user_picture_form');

    $this->assertArrayNotHasKey('data-hx-post', $form['actions']['submit']['#attributes'] ?? []);
    $this->assertStringNotContainsString('portal-account-', (string) $form['#prefix']);
  }

}
