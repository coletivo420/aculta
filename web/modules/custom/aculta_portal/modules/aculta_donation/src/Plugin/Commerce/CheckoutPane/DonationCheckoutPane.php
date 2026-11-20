<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Htmx\Htmx;
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
  protected bool $hasRecurring = FALSE;

  /**
   * The form mode used by this pane for donation items.
   *
   * @var string
   */
  protected string $formMode = 'donation';

  /**
   * The current user, injected to check whether the visitor is anonymous.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   O container de serviços.
   * @param array<string, mixed> $configuration
   *   A configuração do plugin.
   * @param string $plugin_id
   *   O ID do plugin.
   * @param mixed $plugin_definition
   *   A definição do plugin.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface|null $checkout_flow
   *   O fluxo de checkout.
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
    /** @var \Drupal\Core\Session\AccountProxyInterface $currentUser */
    $currentUser = $container->get('current_user');
    $instance->currentUser = $currentUser;
    $instance->hasRecurring = $instance->moduleHandler->moduleExists('commerce_recurring');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<mixed>
   *   Render array returned by the method.
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['message'] = $this->hasRecurring ? 'Your first donation will be charged today and then monthly starting on' : '';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): ?string {
    $summary = [];
    if (!empty($this->configuration['message'])) {
      $msgStr = is_scalar($this->configuration['message']) || $this->configuration['message'] instanceof \Stringable ? (string) $this->configuration['message'] : '';
      $summary[] = $this->t(
        'Monthly giving message: @message',
        ['@message' => $msgStr]
      );
    }

    return $summary === [] ? NULL : implode(', ', $summary);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   O formulário de configuração.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado do formulário.
   *
   * @return array<mixed>
   *   Render array returned by the method.
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    if ($this->hasRecurring) {
      $rawMessage = $this->configuration['message'] ?? '';
      $message = is_scalar($rawMessage) || $rawMessage instanceof \Stringable ? (string) $rawMessage : '';
      $form['message'] = [
        '#type' => 'textfield',
        '#title' => $this
          ->t('Monthly giving message'),
        '#default_value' => $message,
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
   *
   * @param array<mixed> $form
   *   O formulário de configuração.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado do formulário.
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $parents = isset($form['#parents']) && is_array($form['#parents']) ? $form['#parents'] : [];
      $values = $form_state->getValue($parents);
      if (is_array($values) && isset($values['message'])) {
        $this->configuration['message'] = is_scalar($values['message']) || $values['message'] instanceof \Stringable ? (string) $values['message'] : '';
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   *   O formulário da pane.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   O estado do formulário.
   * @param array<mixed> $complete_form
   *   O formulário completo.
   *
   * @return array<mixed>
   *   Render array returned by the method.
   */
  public function buildPaneForm(
    array $pane_form,
    FormStateInterface $form_state,
    array &$complete_form,
  ): array {
    $this->setFormDisplay($this->formMode);
    $class = get_class($this);
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    // Add ajax to the gift type.
    if (isset($pane_form['field_gift_type']) && is_array($pane_form['field_gift_type'])) {
      if (isset($pane_form['field_gift_type']['widget']) && is_array($pane_form['field_gift_type']['widget'])) {
        $pane_form['field_gift_type']['widget']['#ajax'] = [
          'callback' => [$class, 'ajaxAmountUpdate'],
          'progress' => 'none',
        ];
      }
    }
    // Block progress if there is no javascript.
    if (isset($complete_form['actions']) && is_array($complete_form['actions'])) {
      $actionsAttrs = isset($complete_form['actions']['#attributes']) && is_array($complete_form['actions']['#attributes']) ? $complete_form['actions']['#attributes'] : [];
      $actionsClasses = isset($actionsAttrs['class']) && is_array($actionsAttrs['class']) ? $actionsAttrs['class'] : [];
      $actionsClasses[] = 'js-show';
      $actionsAttrs['class'] = $actionsClasses;
      $complete_form['actions']['#attributes'] = $actionsAttrs;
    }
    $paneAttrs = isset($pane_form['#attributes']) && is_array($pane_form['#attributes']) ? $pane_form['#attributes'] : [];
    $paneClasses = isset($paneAttrs['class']) && is_array($paneAttrs['class']) ? $paneAttrs['class'] : [];
    $paneClasses[] = 'js-show';
    $paneAttrs['class'] = $paneClasses;
    $pane_form['#attributes'] = $paneAttrs;

    $pane_form['conta_convite'] = $this->contaConvite();
    return $pane_form;
  }

  /**
   * Convite para criar conta, para o visitante (o checkout de doação aceita visitante, mas a conta acompanha a doação).
   *
   * O link abre o cadastro em modal (aculta_portal.register_popup) e volta para a etapa atual depois do cadastro.
   *
   * @return array<string, mixed>
   *   Elemento de convite para criar conta, ou array vazio se já autenticado.
   */
  private function contaConvite(): array {
    if ($this->currentUser->isAuthenticated()) {
      return [];
    }
    $destino = Url::fromRoute('aculta_donation.donation_controller_formPage', [
      'commerce_order' => $this->order->id(),
      'step' => $this->getStepId(),
    ])->toString();
    $link = Url::fromRoute('aculta_portal.register_popup', [], [
      'query' => ['destination' => $destino],
    ]);
    $dialog = [
      '#type' => 'html_tag',
      '#tag' => 'dialog',
      '#attributes' => [
        'id' => 'aculta-register-dialog',
        'data-portal-ui' => 'donation-register-dialog',
        'aria-label' => (string) $this->t('Criar conta'),
        'data-aculta-utility-drawer' => '',
      ],
    ];
    // V2 opens the native dialog through utility-dialogs.js, which triggers
    // aculta:drawer:open; HTMX loads and submits the Portal fragment. The
    // link href stays valid without JavaScript.
    (new Htmx())
      ->get($link)
      ->trigger('aculta:drawer:open')
      ->target('find [data-aculta-register-content]')
      ->select('#aculta-register-popup')
      ->swap('innerHTML')
      ->sync('body:replace')
      ->onlyMainContent()
      ->applyTo($dialog);
    return [
      '#theme' => 'portal_component',
      '#name' => 'donation-presentation',
      '#props' => ['variant' => 'invite'],
      '#cache' => ['contexts' => ['user.roles:anonymous']],
      '#slots' => ['content' => [
        'texto' => [
          '#theme' => 'portal_component',
          '#name' => 'status-messages',
          '#props' => ['type' => 'info'],
          '#slots' => ['content' => [
            'text' => ['#markup' => '<p>' . $this->t('Crie uma conta para acompanhar suas doações.') . '</p>'],
            'link' => [
              '#type' => 'link',
              '#title' => $this->t('Criar conta'),
              '#url' => $link,
              '#attributes' => [
                'data-portal-ui' => 'donation-account-invite-link',
                'data-aculta-dialog-trigger' => '',
                'aria-controls' => 'aculta-register-dialog',
                'aria-haspopup' => 'dialog',
                'aria-expanded' => 'false',
              ],
            ],
          ],
          ],
        ],
        'dialog' => $dialog + [
          'header' => [
            '#type' => 'html_tag',
            '#tag' => 'header',
            '#attributes' => ['data-portal-ui' => 'donation-register-dialog-header'],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#attributes' => ['data-portal-ui' => 'donation-register-dialog-title'],
              '#value' => $this->t('Criar conta'),
            ],
            'close' => [
              '#type' => 'portal_button',
              '#props' => [
                'text' => '×',
                'aria_label' => (string) $this->t('Fechar'),
                'variant' => 'ghost',
              ],
              '#attributes' => [
                'data-portal-ui' => 'donation-register-dialog-close',
                'data-aculta-dialog-close' => '',
              ],
            ],
          ],
          'content' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'data-portal-ui' => 'donation-register-dialog-body',
              'data-aculta-register-content' => '',
              'aria-live' => 'polite',
              'aria-busy' => 'false',
            ],
            '#value' => $this->t('Carregando…'),
          ],
        ],
      ]],
    ];
  }

  /**
   * An ajax responder for gift type change.
   *
   * @param array<array-key, mixed> $form
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
  ): AjaxResponse {
    $response = new AjaxResponse();
    $fieldAmount = isset($form['commerce_donation_pane']) && is_array($form['commerce_donation_pane'])
      && isset($form['commerce_donation_pane']['field_donation_amount']) && is_array($form['commerce_donation_pane']['field_donation_amount'])
      && isset($form['commerce_donation_pane']['field_donation_amount']['widget']) && is_array($form['commerce_donation_pane']['field_donation_amount']['widget'])
      && isset($form['commerce_donation_pane']['field_donation_amount']['widget'][0]) && is_array($form['commerce_donation_pane']['field_donation_amount']['widget'][0])
      ? ($form['commerce_donation_pane']['field_donation_amount']['widget'][0]['donation_level'] ?? [])
      : [];
    $replaceContent = is_array($fieldAmount) ? $fieldAmount : [];
    $response->addCommand(
      new ReplaceCommand(
        'fieldset[data-donation-level="fieldset"]',
        $replaceContent
      )
    );
    return $response;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function buildPaneSummary(): array {
    if (!$this->isVisible()) {
      return [];
    }
    $viewMode = 'donation';
    return $this->doSummaryBuild($viewMode);
  }

}
