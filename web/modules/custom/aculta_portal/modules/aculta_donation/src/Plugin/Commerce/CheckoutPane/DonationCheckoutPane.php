<?php

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A custom pane for creating Donation OrderItems.
 */
#[CommerceCheckoutPane(
  id: 'commerce_donation_pane',
  label: new TranslatableMarkup('Doação'),
  display_label: new TranslatableMarkup('Sua doação'),
  default_step: 'donation',
  wrapper_element: 'fieldset',
)]
class DonationCheckoutPane extends DonationItemPaneBase implements CheckoutPaneInterface, ContainerFactoryPluginInterface {

  /**
   * Is commerce_recurring available?
   *
   * Flag for monthly donation elements.
   *
   * @var bool
   */
  protected $hasRecurring;

  /**
   * The form mode used by this pane for donation items.
   *
   * @var string
   */
  protected $formMode;

  /**
   * The current user, injected to check whether the visitor is anonymous.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?CheckoutFlowInterface $checkout_flow = NULL,
  ) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow
    );
    $instance->formMode = 'donation';
    $instance->currentUser = $container->get('current_user');
    $instance->hasRecurring = $instance->moduleHandler->moduleExists('commerce_recurring');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['message'] = $this->hasRecurring ? 'Your first donation will be charged today and then monthly starting on' : '';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = [];
    if (!empty($this->configuration['message'])) {
      $summary[] = $this->t(
        'Monthly giving message: @message',
        ['@message' => $this->configuration['message']]
      );
    }

    return implode(', ', $summary);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);
    if ($this->hasRecurring) {
      $message = $this->configuration['message'];
      $form['message'] = [
        '#type' => 'textfield',
        '#title' => $this
          ->t('Monthly giving message'),
        '#default_value' => empty($message) ? '' : $message,
        '#description' => $this->t(
          'The date one month ahead will be appended to this string'
        ),
        '#size' => 128,
        '#maxlength' => 128,
        '#required' => TRUE,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['message'] = $values['message'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(
    array $pane_form,
    FormStateInterface $form_state,
    array &$complete_form,
  ) {
    $this->setFormDisplay($this->formMode);
    $class = get_class($this);
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    // Add ajax to the gift type.
    if (isset($pane_form['field_gift_type']['widget'])) {
      $pane_form['field_gift_type']['widget']['#ajax'] = [
        'callback' => [$class, 'ajaxAmountUpdate'],
        'progress' => 'none',
      ];
    }
    // Block progress if there is no javascript.
    $complete_form["actions"]['#attributes']['class'] = $complete_form["actions"]['#attributes']['class'] ?? [];
    $pane_form['#attributes']['class'] = $pane_form['#attributes']['class'] ?? [];
    $complete_form["actions"]['#attributes']['class'][] = 'js-show';
    $pane_form['#attributes']['class'][] = 'js-show';
    $pane_form['conta_convite'] = $this->contaConvite();
    return $pane_form;
  }

  /**
   * Convite para criar conta, para o visitante (o checkout de doação aceita visitante, mas a conta acompanha a doação).
   *
   * O link abre o cadastro em modal (aculta_portal.register_popup) e volta para a etapa atual depois do cadastro.
   *
   * @return array<string, mixed>
   */
  private function contaConvite(): array {
    if (!$this->order || $this->currentUser->isAuthenticated()) {
      return [];
    }
    $destino = Url::fromRoute('aculta_donation.donation_controller_formPage', [
      'commerce_order' => $this->order->id(),
      'step' => $this->getStepId(),
    ])->toString();
    $link = Url::fromRoute('aculta_portal.register_popup', [], [
      'query' => ['destination' => $destino],
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'title' => (string) $this->t('Criar conta'),
          'width' => 480,
        ]),
      ],
    ]);
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'donation-account-invite'],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      '#cache' => ['contexts' => ['user.roles:anonymous']],
      'texto' => [
        '#markup' => '<p>' . $this->t('Crie uma conta para acompanhar suas doações.') . '</p>',
      ],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Criar conta'),
        '#url' => $link,
        '#attributes' => ['data-portal-ui' => 'donation-account-invite-link'],
      ],
    ];
  }

  /**
   * An ajax responder for gift type change.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public static function ajaxAmountUpdate(
    array $form,
    FormStateInterface $formState,
  ) {
    $response = new AjaxResponse();
    $response->addCommand(
      new ReplaceCommand(
        'fieldset[data-donation-level="fieldset"]',
        $form['commerce_donation_pane']['field_donation_amount']['widget'][0]['donation_level']
      )
    );
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    if (!$this->isVisible()) {
      return [];
    }
    $viewMode = 'donation';
    return $this->doSummaryBuild($viewMode);
  }

}
