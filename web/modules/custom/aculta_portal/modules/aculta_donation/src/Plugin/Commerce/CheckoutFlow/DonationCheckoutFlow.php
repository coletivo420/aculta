<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutFlow;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A custom checkout flow for donations.
 */
#[CommerceCheckoutFlow(
  id: 'fluxo_doacao',
  label: new TranslatableMarkup('Fluxo de doação'),
)]
class DonationCheckoutFlow extends CheckoutFlowWithPanesBase implements CheckoutFlowWithPanesInterface {

  /**
   * Current user session proxy.
   */
  protected AccountProxyInterface $user;

  /**
   * Request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Current default currency code.
   */
  protected string $currency = 'BRL';

  /**
   * Currency formatter.
   */
  protected CurrencyFormatterInterface $currencyFormatter;

  /**
   * The current route match.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   *   Dependency injection container.
   * @param array<array-key, mixed> $configuration
   *   Plugin configuration.
   * @param string $pane_id
   *   Value used for $pane_id.
   * @param mixed $pane_definition
   *   Value used for $pane_definition.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pane_id,
    $pane_definition,
  ): static {
    $instance = parent::create(
      $container,
      $configuration,
      $pane_id,
      $pane_definition
    );
    /** @var \Drupal\Core\Session\AccountProxyInterface $currentUser */
    $currentUser = $container->get('current_user');
    $instance->user = $currentUser;
    /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
    $requestStack = $container->get('request_stack');
    $instance->requestStack = $requestStack;
    /** @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currencyFormatter */
    $currencyFormatter = $container->get('commerce_price.currency_formatter');
    $instance->currencyFormatter = $currencyFormatter;
    /** @var \Drupal\Core\Routing\RouteMatchInterface $routeMatch */
    $routeMatch = $container->get('current_route_match');
    $instance->routeMatch = $routeMatch;

    $store = $instance->order->getStore();
    if ($store !== NULL) {
      $instance->currency = $store->getDefaultCurrencyCode();
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function defaultConfiguration(): array {
    /** @var array<string, mixed> $parentConfig */
    $parentConfig = parent::defaultConfiguration();
    $parentConfig['offline_link_title'] = '';
    $parentConfig['offline_link_url'] = '';

    return $parentConfig;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   *
   * @return array<mixed>
   *   Render array returned by the method.
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $rawTitle = $this->configuration['offline_link_title'] ?? '';
    $link_title = is_scalar($rawTitle) ? (string) $rawTitle : '';
    $rawUrl = $this->configuration['offline_link_url'] ?? '';
    $link_url = is_scalar($rawUrl) ? (string) $rawUrl : '';
    $form['offline_link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Offline donation link title'),
      '#default_value' => $link_title,
    ];

    $form['offline_link_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Offline donation link URL'),
      '#default_value' => $link_url,
      '#description' => 'You may provide a link to a PDF or other file type that your users may download and use to send donations by mail.',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      /** @var array<array-key, string> $parents */
      $parents = is_array($form['#parents'] ?? NULL) ? $form['#parents'] : [];
      $values = $form_state->getValue($parents);
      $title = is_array($values) && isset($values['offline_link_title']) && is_scalar($values['offline_link_title'])
        ? (string) $values['offline_link_title']
        : '';
      $url = is_array($values) && isset($values['offline_link_url']) && is_scalar($values['offline_link_url'])
        ? (string) $values['offline_link_url']
        : '';
      $this->configuration['offline_link_title'] = $title;
      $this->configuration['offline_link_url'] = $url;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param string $step_id
   *   Value used for $step_id.
   */
  public function redirectToStep($step_id): void {
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
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function getSteps(): array {
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
    if (isset($steps['payment']) && is_array($steps['payment'])) {
      $steps['payment']['next_label'] = $this->t('Donate');
    }

    if ($this->order->hasItems()) {
      $items = $this->order->getItems();
      // Only one donation OrderItem supported.
      $donations = array_filter(
        $items,
        function ($item) {
          return $item->bundle() === 'donation';
        }
      );
      $donation = reset($donations);
      if ($donation !== FALSE
          && $donation->hasField('field_donation_amount')
          && !$donation->get('field_donation_amount')->isEmpty()) {
        $firstAmount = $donation->get('field_donation_amount')->first();
        if ($firstAmount instanceof PriceItem) {
          // Append the amount on the payment button text.
          $amountFormatOptions = [
            'currency_display' => 'symbol',
            'minimum_fraction_digits' => 0,
          ];
          $donationAmount = $firstAmount->toPrice();
          $displayAmount = $this->currencyFormatter->format(
            $donationAmount->getNumber(),
            $this->currency,
            $amountFormatOptions
          );
          // Check for monthly.
          if ($donation->hasField('field_gift_type') && !$donation->get('field_gift_type')->isEmpty()) {
            $giftItem = $donation->get('field_gift_type')->first();
            $giftValue = $giftItem !== NULL && isset($giftItem->value) && is_scalar($giftItem->value)
              ? (string) $giftItem->value
              : '';
            if ($giftValue === 'recurring') {
              $displayAmount .= '/mo';
            }
          }
          if (isset($steps['payment']) && is_array($steps['payment'])) {
            $steps['payment']['next_label'] = $this->t(
              'Donate @amount',
              ['@amount' => $displayAmount]
            );
          }
        }
      }
    }
    // All steps in the donation flow have no sidebar.
    foreach ($steps as &$step) {
      if (is_array($step)) {
        $step['has_sidebar'] = FALSE;
      }
    }

    return $steps;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   * @param string|null $step_id
   *   Value used for $step_id.
   *
   * @return array<mixed>
   *   Render array returned by the method.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $step_id = NULL,
  ): array {
    $this->order->recalculateTotalPrice();
    // Get the basic form.
    $form = parent::buildForm($form, $form_state, $step_id);
    if (!isset($form['#attributes']) || !is_array($form['#attributes'])) {
      $form['#attributes'] = [];
    }
    $form['#attributes']['autocomplete'] = 'off';
    $currentStep = isset($form['#step_id']) && is_scalar($form['#step_id']) ? (string) $form['#step_id'] : '';
    // Now build the summaries, if there are any.
    if ($currentStep !== 'complete' && $currentStep !== '') {
      $summarySteps = $this->panesToSummarize($currentStep, $form_state);
      $this->buildSummaries($form, $summarySteps);
    }
    // Identify the step in a class for analytics.
    $stepClass = Html::cleanCssIdentifier('donation-flow-step--' . (is_string($step_id) ? $step_id : ''));
    if (!isset($form['actions']) || !is_array($form['actions'])) {
      $form['actions'] = [];
    }
    if (!isset($form['actions']['next']) || !is_array($form['actions']['next'])) {
      $form['actions']['next'] = [];
    }
    if (!isset($form['actions']['next']['#attributes']) || !is_array($form['actions']['next']['#attributes'])) {
      $form['actions']['next']['#attributes'] = [];
    }
    if (!isset($form['actions']['next']['#attributes']['class']) || !is_array($form['actions']['next']['#attributes']['class'])) {
      $form['actions']['next']['#attributes']['class'] = [];
    }
    $form['actions']['next']['#attributes']['class'][] = $stepClass;

    /** @var array<string, mixed> $formAttributes */
    $formAttributes = isset($form['#attributes']) && is_array($form['#attributes']) ? $form['#attributes'] : [];
    $classAttr = isset($formAttributes['class']) && is_array($formAttributes['class']) ? $formAttributes['class'] : [];
    $classAttr[] = $stepClass;
    $formAttributes['class'] = $classAttr;
    $form['#attributes'] = $formAttributes;

    $rawUrl = $this->configuration['offline_link_url'] ?? NULL;
    $link_url = is_scalar($rawUrl) ? (string) $rawUrl : '';
    if ($link_url !== '' && $step_id === 'donation') {
      $rawTitle = $this->configuration['offline_link_title'] ?? '';
      $link_title = is_scalar($rawTitle) ? (string) $rawTitle : '';
      $form['pdf_link'] = [
        '#title' => $link_title,
        '#type' => 'link',
        '#url' => Url::fromUri($link_url),
        '#weight' => 200,
        '#attributes' => [
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
          'data-portal-ui' => 'donation-presentation-document-link',
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
  public function getStepUrl($step): Url {
    $options = [];
    $request = $this->requestStack->getCurrentRequest();
    if ($request !== NULL && $request->query->has('donate_return')) {
      $returnPath = ['donate_return' => (string) $request->query->get('donate_return')];
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
   *
   * @param array<mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Steps are dynamic based on the submission, so clear to recalculate.
    $this->visibleSteps = [];
    parent::submitForm($form, $form_state);
    // Override the parent redirect data.
    $stepId = isset($form['#step_id']) && is_scalar($form['#step_id']) ? (string) $form['#step_id'] : '';
    $nextStepId = $this->getNextStepId($stepId);
    if ($nextStepId !== NULL && $nextStepId !== '') {
      $form_state->setRedirectUrl($this->getStepUrl($nextStepId));
    }
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
  ): array {
    /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface[] $possiblePanes */
    $possiblePanes = array_filter(
      $this->getPanes(),
      function ($pane) {
        return !in_array($pane->getStepId(), ['_sidebar', '_disabled'], TRUE);
      }
    );
    /** @var array<string, mixed> $visible */
    $visible = $this->getVisibleSteps();
    $stepIds = array_keys($visible);
    $currentIndex = array_search($current_step, $stepIds, TRUE);
    $indices = array_flip($stepIds);
    $stepsCompleted = [];
    foreach ($indices as $step => $index) {
      if ($currentIndex !== FALSE && $index < $currentIndex) {
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
        $stepData = $visible[$step] ?? [];
        $rawLabel = is_array($stepData) && isset($stepData['label']) ? $stepData['label'] : $step;
        $label = is_scalar($rawLabel) || $rawLabel instanceof \Stringable ? (string) $rawLabel : (string) $step;
        $summaries[$step] = new PaneSummaryData(
          $step_number,
          $label,
          $this->getStepUrl((string) $step),
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
   * @param array<mixed> $form
   *   The form array being built.
   * @param \Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow\PaneSummaryData[] $summarySteps
   *   Array with summary data.
   */
  protected function buildSummaries(array &$form, array $summarySteps): void {
    $summariesComponent = [
      '#theme' => 'portal_component',
      '#name' => 'donation-presentation',
      '#props' => ['variant' => 'summaries'],
      '#slots' => ['content' => []],
      '#weight' => -80,
    ];
    // Adapted from Review::buildPaneForm.
    /** @var array<string, mixed> $contentSlots */
    $contentSlots = [];
    foreach ($summarySteps as $summaryStep => $data) {
      $summaryItems = [];
      if ($data->hasPanes()) {
        foreach ($data->panes as $pane) {
          $summaryItems[] = $pane->buildPaneSummary();
        }
      }
      $contentSlots[$summaryStep] = [
        '#theme' => 'portal_component',
        '#name' => 'donation-presentation',
        '#props' => [
          'variant' => 'summary',
          'attributes' => new Attribute(['data-donation-step' => $summaryStep]),
        ],
        '#slots' => [
          'content' => [
            'summary' => $summaryItems,
            'edit_link' => [
              '#title' => $this->t('Edit'),
              '#type' => 'link',
              '#url' => $data->url,
              '#attributes' => [
                'data-portal-ui' => 'donation-summary-edit',
                'data-donation-step' => $summaryStep,
              ],
            ],
          ],
        ],
      ];
    }
    $summariesComponent['#slots']['content'] = $contentSlots;
    $form['summaries'] = $summariesComponent;
  }

  /**
   * Utility method used to insure an order is set in this class.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function conditionalOrderValue(OrderInterface $order): void {
    $this->order = $order;
  }

}
