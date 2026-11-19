<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A custom pane for creating Donation OrderItems.
 */
#[CommerceCheckoutPane(
  id: 'commerce_donation_add_pane',
  label: new TranslatableMarkup('Adicionar doação'),
  display_label: new TranslatableMarkup('Adicione uma doação'),
)]
class AddDonationPane extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   *
   * @return array<mixed>
   */
  public function defaultConfiguration(): array {
    return [
      'call_to_donate' => '',
      'summary_when_added' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary(): ?string {
    $config = $this->getConfiguration();
    $rawCta = $config['call_to_donate'] ?? '';
    $rawAdded = $config['summary_when_added'] ?? '';

    $ctaStr = is_scalar($rawCta) || $rawCta instanceof \Stringable ? (string) $rawCta : '';
    $addedStr = is_scalar($rawAdded) || $rawAdded instanceof \Stringable ? (string) $rawAdded : '';

    $cta = $ctaStr === '' ? (string) $this->t('<em>Not configured</em>') : $ctaStr;
    $added = $addedStr === '' ? (string) $this->t('<em>Not configured</em>') : $addedStr;

    return (string) $this->t('Call to donate: @donate; Summary: @added', [
      '@donate' => $cta,
      '@added' => $added,
    ]);
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
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state);
    $cta = is_scalar($config['call_to_donate'] ?? NULL) ? (string) $config['call_to_donate'] : '';
    $summary = is_scalar($config['summary_when_added'] ?? NULL) ? (string) $config['summary_when_added'] : '';

    $form['call_to_donate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Call to donate'),
      '#default_value' => $cta,
    ];
    $form['summary_when_added'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Summary text'),
      '#description' => $this->t('This text is displayed in Review when a donation has been added. If left empty, no summary is displayed.'),
      '#default_value' => $summary,
    ];
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $config = $this->getConfiguration();
      $parents = isset($form['#parents']) && is_array($form['#parents']) ? $form['#parents'] : [];
      $values = $form_state->getValue($parents);
      if (is_array($values)) {
        $config['call_to_donate'] = is_scalar($values['call_to_donate'] ?? NULL) ? (string) $values['call_to_donate'] : '';
        $config['summary_when_added'] = is_scalar($values['summary_when_added'] ?? NULL) ? (string) $values['summary_when_added'] : '';
        $this->setConfiguration($config);
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
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $config = $this->getConfiguration();
    $cta = is_scalar($config['call_to_donate'] ?? NULL) ? (string) $config['call_to_donate'] : '';
    $pane_form['add_donation'] = [
      '#type' => 'checkbox',
      '#title' => $cta,
      '#default_value' => $this->hasDonation(),
    ];
    return $pane_form;
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
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $form_state->cleanValues();
    $values = $form_state->getValues();
    $paneValues = isset($values['commerce_donation_add_pane']) && is_array($values['commerce_donation_add_pane'])
      ? $values['commerce_donation_add_pane']
      : [];
    $add = !empty($paneValues['add_donation']);

    if ($add && !$this->hasDonation()) {
      $store = $this->order->getStore();
      $currencyCode = $store !== NULL ? $store->getDefaultCurrencyCode() : 'BRL';
      $price = new Price(
        '0',
        $currencyCode !== '' ? $currencyCode : 'BRL'
      );
      $donation = OrderItem::create(
        [
          'type' => 'donation',
          'title' => 'Donation',
        ]
      );
      $donation->setUnitPrice($price);
      $this->order->addItem($donation);
      $this->order->save();
    }
    elseif (!$add && $this->hasDonation()) {
      $items = $this->order->getItems();
      $newItems = array_filter($items, static function (OrderItemInterface $item): bool {
        return $item->bundle() !== 'donation';
      });
      $this->order->setItems($newItems);
      $this->order->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    // This pane is designed to be used outside our custom flow.
    return $this->checkoutFlow->getPluginId() !== 'fluxo_doacao';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function buildPaneSummary(): array {
    $config = $this->getConfiguration();
    $summary = is_scalar($config['summary_when_added'] ?? NULL) ? (string) $config['summary_when_added'] : '';
    if ($summary === '') {
      return [];
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $summary,
    ];
  }

  /**
   * Helper method that checks the order for donations.
   */
  protected function hasDonation(): bool {
    $items = $this->order->getItems();
    // Only one donation OrderItem supported.
    $donations = array_filter($items, static function (OrderItemInterface $item): bool {
      return $item->bundle() === 'donation';
    });
    return !empty($donations);
  }

}
