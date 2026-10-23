<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\aculta_portal\Checkout\FiscalIdentityCheck;
use Drupal\aculta_portal\Checkout\FiscalIdentityPolicy;
use Drupal\aculta_portal\Checkout\FiscalIdentityStatus;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Identidade fiscal (CPF ou CNPJ) exigida para concluir pedidos comerciais da Loja e do LMS.
 *
 * O pane não tem campo de entrada: o documento é cadastrado somente em Outros Dados (`/dados/outros-dados`). Ele
 * mostra a versão mascarada quando existe, ou um aviso com link para o cadastro. A validação roda ao enviar a etapa
 * de revisão, no servidor, antes da conclusão. Sem documento válido, o pedido não é concluído e o carrinho é mantido.
 *
 * Doações não usam este pane: os flows `doacao` e `assinatura_flow` não o incluem.
 */
#[CommerceCheckoutPane(
  id: 'aculta_fiscal_identity',
  label: new TranslatableMarkup('Identidade fiscal (CPF ou CNPJ)'),
  default_step: 'review',
  wrapper_element: 'fieldset',
)]
final class FiscalIdentityPane extends CheckoutPaneBase implements CheckoutPaneInterface {

  private FiscalIdentityPolicy $policy;

  /**
   *
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->policy = $container->get('aculta_portal.fiscal_identity_policy');
    return $instance;
  }

  /**
   * {@inheritdoc} */
  public function isVisible() {
    return $this->policy->requiresDocument($this->order);
  }

  /**
   * {@inheritdoc} */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $check = $this->check();
    $pane_form['status'] = match ($check->status) {
      FiscalIdentityStatus::Valid => [
        '#markup' => $this->t('Documento cadastrado: @masked. Para alterá-lo, entre em contato com a administração.', ['@masked' => $check->masked]),
      ],
      FiscalIdentityStatus::Missing => [
        '#markup' => $this->t('Para concluir esta compra, informe seu CPF ou CNPJ em Meus dados > Outros dados. Seu carrinho continua salvo.'),
        'link' => Link::fromTextAndUrl($this->t('Informar CPF ou CNPJ'), Url::fromRoute('aculta_portal.my_data_other', [], [
          'query' => ['destination' => $this->checkoutUrl()],
        ]))->toRenderable(),
      ],
      FiscalIdentityStatus::Unavailable => [
        '#markup' => $this->t('Não foi possível confirmar seu CPF ou CNPJ agora. Tente novamente mais tarde.'),
      ],
    };
    return $pane_form;
  }

  /**
   * {@inheritdoc} */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    if (!$this->check()->isSatisfied()) {
      $form_state->setError($pane_form, $this->t('Não é possível concluir a compra sem um CPF ou CNPJ válido cadastrado.'));
    }
  }

  /**
   * Consulta a situação do dono do pedido, nunca do usuário da URL. Visitante é tratado como sem documento.
   */
  private function check(): FiscalIdentityCheck {
    $customer = $this->order->getCustomer();
    if ($customer->isAnonymous()) {
      return new FiscalIdentityCheck(FiscalIdentityStatus::Missing);
    }
    return $this->policy->check($customer);
  }

  /**
   * Volta ao checkout deste pedido. Só o ID do pedido, nunca o documento. */
  private function checkoutUrl(): string {
    return Url::fromRoute('commerce_checkout.form', ['commerce_order' => $this->order->id()])->toString();
  }

}
