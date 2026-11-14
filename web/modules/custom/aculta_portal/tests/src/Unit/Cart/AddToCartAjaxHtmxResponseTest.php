<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Cart;

use Drupal\aculta_portal\Cart\AddToCartAjax;
use Drupal\aculta_portal\Cart\CartUtility;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta HTMX do "Adicionar ao carrinho": sucesso, erro de validação e requisição sem HX-Request.
 */
#[Group('aculta_portal')]
class AddToCartAjaxHtmxResponseTest extends UnitTestCase {

  private const FORM_ID = 'commerce_order_item_add_to_cart_form_produto_1';

  /**
   * Monta o serviço com dependências simuladas.
   */
  private function service(
    CartUtility $cart,
    MessengerInterface $messenger,
    Request $request,
    ?array &$rendered = NULL,
  ): AddToCartAjax {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(
      static fn (string $texto, array $args = []): string => strtr($texto, $args),
    );
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderInIsolation')->willReturnCallback(
      static function (array $build) use (&$rendered): string {
        $rendered[] = $build;
        return '<div>render</div>';
      },
    );

    $stack = new RequestStack();
    $stack->push($request);

    return new AddToCartAjax($cart, $translation, $renderer, $messenger, $stack);
  }

  /**
   * Carrinho simulado com a contagem informada.
   */
  private function cart(int $count): CartUtility {
    $cart = $this->createMock(CartUtility::class);
    $cart->method('build')->willReturn([
      'count' => $count,
      'sites' => [],
      'total' => '0,00',
      'url' => '/carrinho',
      'empty' => $count === 0,
      'checkout_url' => '/finalizar',
    ]);
    return $cart;
  }

  /**
   * Formulário simulado com os erros informados.
   */
  private function formState(array $errors): FormStateInterface&\PHPUnit\Framework\MockObject\MockObject {
    $form = $this->createMock(FormInterface::class);
    $form->method('getFormId')->willReturn(self::FORM_ID);
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getFormObject')->willReturn($form);
    $state->method('getErrors')->willReturn($errors);
    return $state;
  }

  /**
   * Sucesso: caixa de status, contador no HX-Trigger e limpeza das mensagens do Commerce.
   */
  public function testSucessoDevolveStatusECarrinhoEContador(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('deleteAll');

    $request = Request::create('/produto/1', 'POST');
    $request->headers->set('HX-Request', 'true');

    $state = $this->formState([]);
    $captured = NULL;
    $rendered = [];
    $state->expects($this->once())->method('setResponse')->willReturnCallback(
      static function (Response $response) use (&$captured): void {
        $captured = $response;
      },
    );

    $service = $this->service($this->cart(2), $messenger, $request, $rendered);
    $form = [];
    $service->htmxResponse($form, $state);

    $this->assertInstanceOf(Response::class, $captured);
    $this->assertStringContainsString('id="portal-cart-status-', (string) $captured->getContent());
    $this->assertSame('component', $rendered[0]['#type']);
    $this->assertSame('aculta420_v2:cart-status', $rendered[0]['#component']);
    $this->assertSame('success', $rendered[0]['#props']['status_type']);
    $this->assertSame('aculta420_v2:cart-offcanvas-body', $rendered[1]['#component']);
    $trigger = json_decode((string) $captured->headers->get('HX-Trigger'), TRUE);
    $this->assertSame('2', $trigger['aculta:cart-updated']['count']);
    $this->assertSame('Abrir carrinho (2 itens)', $trigger['aculta:cart-updated']['label']);
  }

  /**
   * Erro de validação: a resposta ainda é HTML do status, com o erro sem tags HTML.
   */
  public function testErroDeValidacaoDevolveResposta(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $request = Request::create('/produto/1', 'POST');
    $request->headers->set('HX-Request', 'true');

    $state = $this->formState(['quantity' => 'Quantidade <b>inválida</b>']);
    $captured = NULL;
    $rendered = [];
    $state->expects($this->once())->method('setResponse')->willReturnCallback(
      static function (Response $response) use (&$captured): void {
        $captured = $response;
      },
    );

    $service = $this->service($this->cart(0), $messenger, $request, $rendered);
    $form = [];
    $service->htmxResponse($form, $state);

    $this->assertInstanceOf(Response::class, $captured);
    $this->assertStringContainsString('portal-cart-status-', (string) $captured->getContent());
    $this->assertSame('error', $rendered[0]['#props']['status_type']);
    $this->assertSame('alert', $rendered[0]['#props']['status_role']);
    $this->assertSame('Quantidade inválida', $rendered[0]['#props']['message']);
    $trigger = json_decode((string) $captured->headers->get('HX-Trigger'), TRUE);
    $this->assertSame('0', $trigger['aculta:cart-updated']['count']);
  }

  /**
   * Sem HX-Request o envio segue o fluxo normal do Commerce: nada é respondido.
   */
  public function testSemHxRequestNaoRespondeNemLimpaMensagens(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->never())->method('deleteAll');
    $request = Request::create('/produto/1', 'POST');

    $state = $this->formState([]);
    $state->expects($this->never())->method('setResponse');

    $service = $this->service($this->cart(1), $messenger, $request);
    $form = [];
    $service->htmxResponse($form, $state);
  }

}
