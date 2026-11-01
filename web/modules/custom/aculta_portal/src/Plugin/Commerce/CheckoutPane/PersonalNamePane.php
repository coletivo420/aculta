<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\aculta_portal\Checkout\PersonalNameStore;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Nome e sobrenome do checkout (LMS e doação).
 *
 * Preenchido com o perfil quando a pessoa tem conta; caso contrário, com o que ela já informou no pedido.
 * Salvo no perfil da conta ao concluir, ou no pedido quando não há conta ainda.
 */
#[CommerceCheckoutPane(
  id: 'aculta_personal_name',
  label: new TranslatableMarkup('Nome e sobrenome'),
  default_step: 'order_information',
  wrapper_element: 'fieldset',
)]
final class PersonalNamePane extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * Armazenamento do nome.
   *
   * @var \Drupal\aculta_portal\Checkout\PersonalNameStore
   */
  private PersonalNameStore $names;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->names = $container->get('aculta_portal.personal_name_store');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $valores = $this->valoresAtuais();
    $pane_form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome'),
      '#default_value' => $valores['first'],
      '#required' => TRUE,
      '#maxlength' => PersonalNameStore::MAX_LENGTH,
      '#size' => 30,
      '#attributes' => ['autocomplete' => 'given-name'],
    ];
    $pane_form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sobrenome'),
      '#default_value' => $valores['last'],
      '#required' => TRUE,
      '#maxlength' => PersonalNameStore::MAX_LENGTH,
      '#size' => 30,
      '#attributes' => ['autocomplete' => 'family-name'],
    ];
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    foreach (['first_name' => $this->t('Informe seu nome.'), 'last_name' => $this->t('Informe seu sobrenome.')] as $campo => $mensagem) {
      $valor = trim((string) $form_state->getValue(array_merge($pane_form['#parents'], [$campo])));
      if ($valor === '') {
        $form_state->setError($pane_form[$campo], $mensagem);
      }
      elseif (mb_strlen($valor) > PersonalNameStore::MAX_LENGTH) {
        $form_state->setError($pane_form[$campo], $this->t('Use até @max caracteres.', ['@max' => PersonalNameStore::MAX_LENGTH]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $nome = ['first' => trim((string) $values['first_name']), 'last' => trim((string) $values['last_name'])];
    $conta = $this->order->getCustomer();
    if ($conta->isAuthenticated()) {
      $this->names->saveForAccount($conta, $nome['first'], $nome['last']);
    }
    $this->order->setData(PersonalNameStore::ORDER_DATA_KEY, $nome);
    $this->order->save();
  }

  /**
   * Valores iniciais: perfil da conta, ou o que a pessoa já informou neste pedido.
   *
   * @return array{first: string, last: string}
   *   Partes do nome.
   */
  private function valoresAtuais(): array {
    $conta = $this->order->getCustomer();
    if ($conta->isAuthenticated()) {
      $perfil = $this->names->forAccount($conta);
      if ($perfil['first'] !== '' || $perfil['last'] !== '') {
        return $perfil;
      }
    }
    $pedido = $this->order->getData(PersonalNameStore::ORDER_DATA_KEY, []);
    return ['first' => (string) ($pedido['first'] ?? ''), 'last' => (string) ($pedido['last'] ?? '')];
  }

}
