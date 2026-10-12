<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cart;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Adicionar ao carrinho sem recarregar a página (produto e listagens).
 *
 * O formulário nativo do Commerce (commerce_order_item_add_to_cart_form) não tem AJAX: o Portal acrescenta o #ajax no
 * botão e um contêiner de status acima do formulário. A caixa é o template do módulo (aculta_portal_cart_status): mostra
 * "Item adicionado com sucesso." com o link "Ver carrinho", ou o erro de validação, no mesmo lugar. O módulo só envia
 * dados (contagem, URL, textos). O badge do carrinho é do tema, marcado com data-aculta-cart-count e
 * data-aculta-cart-trigger; o módulo atualiza esses atributos. Sem JavaScript o envio segue o fluxo normal do Commerce.
 */
final class AddToCartAjax {

  public const FORM_ID_PREFIX = 'commerce_order_item_add_to_cart_form';

  public function __construct(
    private readonly CartUtility $cart,
    private readonly TranslationInterface $translation,
    private readonly RendererInterface $renderer,
    private readonly MessengerInterface $messenger,
  ) {}

  /** Liga o AJAX ao botão de adicionar e cria o contêiner de status acima do formulário. */
  public function alter(array &$form, string $formId): void {
    if (!str_starts_with($formId, self::FORM_ID_PREFIX) || !isset($form['actions']['submit'])) {
      return;
    }
    $statusId = $this->statusId($formId);
    $form['#prefix'] = ($form['#prefix'] ?? '') . '<div id="' . $statusId . '"></div>';
    $form['actions']['submit']['#ajax'] = [
      'callback' => 'aculta_portal.cart_ajax:response',
      'progress' => ['type' => 'throbber'],
      // O id do contêiner vai no wrapper: o callback o lê do botão acionado (o #id do formulário muda na reconstrução).
      'wrapper' => $statusId,
    ];
  }

  /** Callback AJAX do botão: caixa de status, contador e rótulo do botão do carrinho. */
  public function response(array &$form, FormStateInterface $formState): AjaxResponse {
    $statusId = $formState->getTriggeringElement()['#ajax']['wrapper'] ?? $this->statusId((string) $formState->getFormObject()->getFormId());
    // A caixa "Item adicionado com sucesso." substitui a mensagem padrão do Commerce, que não pode ficar para a próxima página.
    $this->messenger->deleteAll();
    $cart = $this->cart->build();
    $response = new AjaxResponse();
    $errors = array_map(static fn($error): string => strip_tags((string) $error), array_values($formState->getErrors()));

    // Variáveis do template com nomes próprios: "#type" é o tipo de elemento do Drupal, não uma variável.
    $box = [
      '#theme' => 'aculta_portal_cart_status',
      '#status_type' => $errors === [] ? 'success' : 'error',
      '#status_role' => $errors === [] ? 'status' : 'alert',
      '#message' => $errors === []
        ? (string) $this->translation->translate('Item adicionado com sucesso.')
        : implode(' ', $errors),
      '#url' => $errors === [] ? $cart['url'] : '',
      '#link_label' => (string) $this->translation->translate('Ver carrinho'),
      '#close_label' => (string) $this->translation->translate('Fechar'),
    ];
    $response->addCommand(new HtmlCommand('#' . $statusId, $this->renderer->renderInIsolation($box)));
    $response->addAttachments(['library' => ['aculta_portal/cart-status']]);

    $count = (string) $cart['count'];
    $response->addCommand(new InvokeCommand('[data-aculta-cart-count]', 'text', [$count]));
    $response->addCommand(new InvokeCommand('[data-aculta-cart-count]', 'attr', ['data-count', $count]));
    $response->addCommand(new InvokeCommand('[data-aculta-cart-trigger]', 'attr', [
      'aria-label',
      (string) $this->translation->translate('Abrir carrinho (@count itens)', ['@count' => $count]),
    ]));
    return $response;
  }

  /** Id estável a partir do form_id (o #id do formulário ganha sufixo na reconstrução do AJAX). */
  private function statusId(string $formId): string {
    return Html::cleanCssIdentifier('portal-cart-status-' . $formId);
  }

}
