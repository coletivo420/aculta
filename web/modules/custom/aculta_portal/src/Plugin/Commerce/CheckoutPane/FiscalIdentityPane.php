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
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Identidade fiscal (CPF ou CNPJ) exigida para concluir pedidos comerciais da Loja e do LMS.
 *
 * Regras:
 * - Com documento cadastrado: mostra a versão mascarada, somente leitura. Nunca é alterável pelo checkout.
 * - Sem documento e com cifra disponível: aceita o primeiro cadastro na própria compra. A gravação é única e usa o
 *   mesmo serviço de Outros Dados (`/dados/outros-dados`). Depois disso, o campo vira somente leitura.
 * - Sem documento e sem cifra disponível, ou com valor ilegível: falha fechada, com mensagem genérica.
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

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->policy = $container->get('aculta_portal.fiscal_identity_policy');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return $this->policy->requiresDocument($this->order);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $check = $this->check();
    switch ($check->status) {
      case FiscalIdentityStatus::Valid:
        $pane_form['status'] = [
          '#markup' => $this->t('Documento cadastrado: @masked. Não pode ser alterado.', ['@masked' => $check->masked]),
        ];
        break;

      case FiscalIdentityStatus::Missing:
        if ($this->policy->canFill()) {
          $pane_form['document'] = [
            '#type' => 'textfield',
            '#title' => $this->t('CPF ou CNPJ'),
            '#description' => $this->t('Informe uma única vez para concluir a compra. Depois de salvo, não poderá ser alterado. Pode digitar sem pontuação.'),
            '#size' => 20,
            '#maxlength' => 64,
            '#autocomplete' => 'off',
            '#spellcheck' => 'false',
          ];
        }
        else {
          $pane_form['status'] = ['#markup' => $this->unavailableMessage()];
        }
        break;

      case FiscalIdentityStatus::Unavailable:
        $pane_form['status'] = ['#markup' => $this->unavailableMessage()];
        break;
    }
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $check = $this->check();
    if ($check->isSatisfied()) {
      return;
    }
    if ($check->status !== FiscalIdentityStatus::Missing || !$this->policy->canFill()) {
      $form_state->setError($pane_form, $this->t('Não é possível concluir a compra sem um CPF ou CNPJ válido cadastrado.'));
      return;
    }
    // Primeiro cadastro na própria compra: mesma validação e mesma gravação única de Outros Dados.
    $raw = trim((string) $form_state->getValue(array_merge($pane_form['#parents'], ['document'])));
    if ($raw === '') {
      $form_state->setError($pane_form, $this->t('Informe seu CPF ou CNPJ para concluir a compra.'));
      return;
    }
    $canonical = $this->policy->canonicalize($raw);
    if ($canonical === NULL || !$this->policy->fill($this->order->getCustomer(), $canonical)) {
      // Mensagem genérica: não revela se o documento é inválido, já existe em outra conta ou não pode ser gravado.
      $form_state->setError($pane_form, $this->t('Não foi possível salvar este CPF ou CNPJ. Verifique o número e tente novamente.'));
      return;
    }
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

  private function unavailableMessage(): TranslatableMarkup {
    return $this->t('Não foi possível confirmar seu CPF ou CNPJ agora. Tente novamente mais tarde.');
  }


}
