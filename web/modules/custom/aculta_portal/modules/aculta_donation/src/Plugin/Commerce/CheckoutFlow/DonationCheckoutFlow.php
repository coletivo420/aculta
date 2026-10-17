<?php

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutFlow;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A custom checkout flow for donations.
 */
#[CommerceCheckoutFlow(
  id: 'donation_checkout_flow',
  label: new TranslatableMarkup('Fluxo de doação'),
)]
class DonationCheckoutFlow extends CheckoutFlowWithPanesBase implements CheckoutFlowWithPanesInterface {

  /**
   * Injected service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $user;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Current default currency code.
   *
   * @var string
   */
  protected $currency;

  /**
   * Injected service.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pane_id,
    $pane_definition,
  ) {
    $instance = parent::create(
      $container,
      $configuration,
      $pane_id,
      $pane_definition
    );
    $instance->user = $container->get('current_user');
    $instance->requestStack = $container->get('request_stack');
    $instance->currencyFormatter = $container->get(
      'commerce_price.currency_formatter'
    );
    $instance->routeMatch = $container->get('current_route_match');
    if ($instance->order instanceof OrderInterface) {
      $instance->currency = $instance->order->getStore()
        ->getDefaultCurrencyCode();
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['offline_link_title'] = '';
    $config['offline_link_url'] = '';

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $link_title = $this->configuration['offline_link_title'];
    $link_url = $this->configuration['offline_link_url'];
    $form['offline_link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Offline donation link title'),
      '#default_value' => empty($link_title) ? '' : $link_title,
    ];

    $form['offline_link_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Offline donation link URL'),
      '#default_value' => empty($link_url) ? '' : $link_url,
      '#description' => 'You may provide a link to a PDF or other file type that your users may download and use to send donations by mail.',
    ];

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
      $this->configuration['offline_link_title'] = $values['offline_link_title'];
      $this->configuration['offline_link_url'] = $values['offline_link_url'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function redirectToStep($step_id) {
    // May throw a redirect exception with settings to commerce checkout.
    try {
      parent::redirectToStep($step_id);
    }
    catch (NeedsRedirectException $e) {
      // Use our controller instead.
      throw new NeedsRedirectException(
        Url::fromRoute(
          'aculta_donation.donation_controller_formPage',
          [
            'commerce_order' => $this->order->id(),
            'step' => $step_id,
          ]
        )->toString()
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    // Note that previous_label and next_label are not the labels
    // shown on the step itself. Instead, they are the labels shown
    // when going back to the step, or proceeding to the step.
    $steps =
      [
        'donation' => [
          'label' => $this->t('Your Donation'),
          'next_label' => $this->t('Continue'),
        ],
        'dedication' => [
          'label' => $this->t('Dedication Information'),
          'next_label' => $this->t('Continue'),
        ],
        'billing' => [
          'label' => $this->t('Billing Information'),
          'next_label' => $this->t('Continue to Payment'),
        ],
      ]
      + parent::getSteps();
    // Adjust names to match our use case:
    $steps['payment']['next_label'] = $this->t('Donate');

    if ($this->order instanceof OrderInterface && $this->order->hasItems()) {
      $items = $this->order->getItems();
      // Only one donation OrderItem supported.
      $donations = array_filter(
        $items,
        function ($item) {
          return $item->bundle() == 'donation';
        }
      );
      $donation = reset($donations);
      if ($donation instanceof OrderItemInterface
          && $donation->hasField(
          'field_donation_amount'
        ) && !$donation->field_donation_amount->isEmpty()) {
        // Append the amount on the payment button text.
        $amountFormatOptions = [
          'currency_display' => 'symbol',
          'minimum_fraction_digits' => 0,
        ];
        $donationAmount = $donation->field_donation_amount->first()->toPrice();
        $displayAmount = $this->currencyFormatter->format(
          $donationAmount->getNumber(),
          $this->currency,
          $amountFormatOptions
        );
        // Check for monthly.
        if (
          $donation->hasField('field_gift_type')
          && !$donation->field_gift_type->isEmpty()
          && $donation->field_gift_type->first()->value == 'recurring') {
          $displayAmount .= '/mo';
        }
        $steps['payment']['next_label'] = $this->t(
          'Donate @amount',
          ['@amount' => $displayAmount]
        );
      }
    }
    // All steps in the donation flow have no sidebar.
    foreach ($steps as &$step) {
      $step['has_sidebar'] = FALSE;
    }

    return $steps;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $step_id = NULL,
  ) {
    $this->order->recalculateTotalPrice();
    // Get the basic form.
    $form = parent::buildForm($form, $form_state, $step_id);
    $form['#attributes']['autocomplete'] = 'off';
    // Now build the summaries, if there are any.
    if ($form['#step_id'] != 'complete') {
      $summarySteps = $this->panesToSummarize($form['#step_id'], $form_state);
      $this->buildSummaries($form, $summarySteps);
    }
    // Identify the step in a class for analytics.
    $form['actions']['next']['#attributes']['class'][] = Html::cleanCssIdentifier(
      "donation-flow-step--$step_id"
    );
    $form['#attributes']['class'][] = Html::cleanCssIdentifier(
      "donation-flow-step--$step_id"
    );
    $link_url = $this->configuration['offline_link_url'] ?? NULL;
    if ($link_url && $step_id == 'donation') {
      $link_title = $this->configuration['offline_link_title'];
      $form['pdf_link'] = [
        '#title' => $link_title,
        '#type' => 'link',
        '#url' => Url::fromUri($link_url),
        '#weight' => 200,
        '#attributes' => [
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
          'data-portal-ui' => 'donation-document-link',
        ],
        '#theme_wrappers' => ['container'],
      ];
    }

    return $form;
  }

  /**
   * Utility method to generate a step url.
   *
   * Used in this flow in and building the summary.
   *
   * @param string $step
   *   The step ID.
   *
   * @return \Drupal\Core\Url
   *   The url to the step.
   *
   * @see \Drupal\aculta_donation\Plugin\Commerce\CheckoutPane\DonationItemPaneBase::doSummaryBuild
   */
  public function getStepUrl($step) {
    $options = [];
    $query = $this->requestStack->getCurrentRequest()->query;
    if ($query->has('donate_return')) {
      $returnPath = ['donate_return' => $query->get('donate_return')];
      $options = ['query' => $returnPath];
    }

    return Url::fromRoute(
      'aculta_donation.donation_controller_formPage',
      [
        'commerce_order' => $this->order->id(),
        'step' => $step,
      ],
      $options
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Steps are dynamic based on the submission, so clear to recalculate.
    unset($this->visibleSteps);
    parent::submitForm($form, $form_state);
    // Override the parent redirect data.
    $nextStepId = $this->getNextStepId($form['#step_id']);
    $form_state->setRedirectUrl($this->getStepUrl($nextStepId));
  }

  /**
   * Sort through panes to populate an array of panes to summarize.
   *
   * Only panes in visible steps should summarize.
   *
   * @param string $current_step
   *   The current step id.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   *
   * @return \Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow\PaneSummaryData[]
   *   The summary data.
   */
  protected function panesToSummarize(
    $current_step,
    FormStateInterface $formState,
  ) {
    /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface[] $possiblePanes */
    $possiblePanes = array_filter(
      $this->getPanes(),
      function ($pane) {
        return !in_array($pane->getStepId(), ['_sidebar', '_disabled']);
      }
    );
    $visible = $this->getVisibleSteps();
    $stepIds = array_keys($visible);
    $currentIndex = array_search($current_step, $stepIds);
    $indices = array_flip($stepIds);
    $stepsCompleted = [];
    foreach ($indices as $step => $index) {
      if ($index < $currentIndex) {
        $stepsCompleted[$step] = TRUE;
      }
    }
    if (isset($indices["complete"])) {
      // Don't list the 'thank you' page.
      unset($indices["complete"]);
    }
    $summaries = [];
    $step_number = 1;
    foreach ($indices as $step => $index) {
      if (isset($stepsCompleted[$step])) {
        $panes = array_filter(
          $possiblePanes,
          function ($pane) use ($step) {
            return $pane->getStepId() === $step;
          }
        );
        $summaries[$step] = new PaneSummaryData(
          $step_number,
          $visible[$step]['label'],
          $this->getStepUrl($step),
          $panes
        );
      }
      ++$step_number;
    }

    return $summaries;
  }

  /**
   * Helper method to build the full summary display and complete overview.
   *
   * @param array $form
   *   The form array being built.
   * @param \Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow\PaneSummaryData[] $summarySteps
   *   Array with summary data.
   */
  protected function buildSummaries(array &$form, array $summarySteps) {
    $form['summaries'] = [
      '#theme' => 'aculta_donation_summary_container',
      '#weight' => -80,
      '#attributes' => [
        'data-portal-ui' => 'donation-summaries',
      ],
      '#content' => [],
    ];
    // Adapted from Review::buildPaneForm.
    foreach ($summarySteps as $summaryStep => $data) {
      $form['summaries']['#content'][$summaryStep] = [
        'summary' => [],
        'edit_link' => [
          '#title' => $this->t('Edit'),
          '#type' => 'link',
          '#url' => $data->url,
          '#attributes' => [
            'data-portal-ui' => 'donation-summary-edit',
            'data-donation-step' => $summaryStep,
          ],
        ],
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'donation-summary',
          'data-donation-step' => $summaryStep,
        ],
      ];
      if ($data->hasPanes()) {
        foreach ($data->panes as $pane) {
          $form['summaries']['#content'][$summaryStep]['summary'][] = $pane->buildPaneSummary();
        }
      }
    }
  }

  /**
   * Utility method used to insure an order is set in this class.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function conditionalOrderValue(OrderInterface $order) {
    if (!$this->order instanceof OrderInterface) {
      $this->order = $order;
    }
  }

}
