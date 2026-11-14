<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cart;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Event\CartUpdatedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adicionar ao carrinho sem recarregar a página (produto e listagens).
 *
 * O formulário nativo do Commerce (commerce_order_item_add_to_cart_form) não tem AJAX: o Portal aplica HTMX no
 * botão e um contêiner de status acima do formulário. A caixa é o template do módulo
 * (aculta_portal_cart_status): mostra
 * "Item adicionado com sucesso." com o link "Ver carrinho", ou o erro de validação, no mesmo lugar. O módulo só envia
 * dados (contagem, URL, textos). O badge e o corpo do carrinho são atualizados por `hx-swap-oob`; o contador e o
 * rótulo do gatilho vêm no HX-Trigger `aculta:cart-updated`, tratado pelo JS do tema. Sem JavaScript o envio segue o
 * fluxo normal do Commerce.
 */
class AddToCartAjax {

  public const FORM_ID_PREFIX = 'commerce_order_item_add_to_cart_form';

  public function __construct(
    private readonly CartUtility $cart,
    private readonly TranslationInterface $translation,
    private readonly RendererInterface $renderer,
    private readonly MessengerInterface $messenger,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
    private readonly ?EventDispatcherInterface $eventDispatcher = NULL,
  ) {}

  /**
   * Aplica HTMX ao botão de adicionar e cria o contêiner de status acima do formulário.
   */
  public function alter(array &$form, string $formId, FormStateInterface $formState): void {
    if (!str_starts_with($formId, self::FORM_ID_PREFIX) || !isset($form['actions']['submit'])) {
      return;
    }
    $statusId = $this->statusId($formId);
    // Na reconstrução por erro de validação o status já vai preenchido; o HTMX
    // recorta este id do formulário devolvido.
    $statusHtml = $formState->hasAnyErrors()
      ? (string) $this->renderer->renderInIsolation($this->statusBox($formState, ''))
      : '';
    $form['#prefix'] = ($form['#prefix'] ?? '') . '<div id="' . $statusId . '">' . $statusHtml . '</div>';
    $form['#attached']['library'][] = 'aculta_portal/cart-status';

    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return;
    }
    // O formulário não tem #action: o POST nativo vai para a própria página.
    $url = Url::fromUserInput($request->getRequestUri());
    (new Htmx())
      ->post($url)
      ->select('#' . $statusId)
      ->target('#' . $statusId)
      ->swap('innerHTML')
      ->onlyMainContent()
      ->applyTo($form['actions']['submit']);

    $submit = &$form['actions']['submit'];
    if (!empty($submit['#submit'])) {
      $submit['#submit'][] = 'aculta_portal.cart_ajax:htmxResponse';
    }
    else {
      $form['#submit'][] = 'aculta_portal.cart_ajax:htmxResponse';
    }
  }

  /**
   * Resposta HTMX do botão: caixa de status, painel do carrinho e contador.
   */
  public function htmxResponse(array &$form, FormStateInterface $formState): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL || !$request->headers->has('HX-Request')) {
      return;
    }
    $formId = (string) $formState->getFormObject()->getFormId();
    $statusId = $this->statusId($formId);
    $cart = $this->cart->build();
    $this->eventDispatcher?->dispatch(new CartUpdatedEvent());
    // A caixa "Item adicionado com sucesso." substitui a mensagem padrão do
    // Commerce, que não pode ficar para a próxima página.
    $this->messenger->deleteAll();

    $box = $this->statusBox($formState, (string) $cart['url']);
    $painel = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:cart-offcanvas-body',
      '#props' => [
        'sites' => $cart['sites'],
        'total' => $cart['total'],
        'url' => $cart['url'],
        'empty' => $cart['empty'],
        'checkout_url' => $cart['checkout_url'],
        'htmx_oob' => TRUE,
      ],
    ];
    $body = '<div id="' . $statusId . '">'
      . $this->renderer->renderInIsolation($box)
      . '</div>'
      . $this->renderer->renderInIsolation($painel);

    $count = (string) $cart['count'];
    $headers = [
      'Content-Type' => 'text/html; charset=UTF-8',
      'HX-Trigger' => json_encode([
        'aculta:cart-updated' => [
          'count' => $count,
          'label' => (string) $this->translation->translate('Abrir carrinho (@count itens)', ['@count' => $count]),
        ],
      ]),
    ];
    $formState->setResponse(new Response($body, Response::HTTP_OK, $headers));
  }

  /**
   * Caixa de status do carrinho (sucesso ou erro de validação).
   */
  private function statusBox(FormStateInterface $formState, string $url): array {
    $errors = array_map(static fn($error): string => strip_tags((string) $error), array_values($formState->getErrors()));
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:cart-feedback',
      '#props' => [
        'status_type' => $errors === [] ? 'success' : 'error',
        'status_role' => $errors === [] ? 'status' : 'alert',
        'message' => $errors === []
          ? (string) $this->translation->translate('Item adicionado com sucesso.')
          : implode(' ', $errors),
        'url' => $errors === [] ? $url : '',
        'link_label' => (string) $this->translation->translate('Ver carrinho'),
        'close_label' => (string) $this->translation->translate('Fechar'),
      ],
    ];
  }

  /**
   * Id estável a partir do form_id (o #id do formulário ganha sufixo na reconstrução).
   */
  private function statusId(string $formId): string {
    return Html::cleanCssIdentifier('portal-cart-status-' . $formId);
  }

}
