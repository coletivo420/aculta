<?php

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_order\Entity\OrderItem;
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
   */
  public function defaultConfiguration() {
    return [
      'call_to_donate' => '',
      'summary_when_added' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $config = $this->getConfiguration();
    $cta = empty($config['call_to_donate']) ? $this->t('<em>Not configured</em>') : $config['call_to_donate'];
    $added = empty($config['summary_when_added']) ? $this->t('<em>Not configured</em>') : $config['summary_when_added'];
    $summary = $this->t('Call to donate: @donate; Summary: @added', [
      '@donate' => $cta,
      '@added' => $added,
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['call_to_donate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Call to donate'),
      '#default_value' => $config['call_to_donate'],
    ];
    $form['summary_when_added'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Summary text'),
      '#description' => $this->t('This text is displayed in Review when a donation has been added. If left empty, no summary is displayed.'),
      '#default_value' => $config['summary_when_added'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $config = $this->getConfiguration();
      $values = $form_state->getValue($form['#parents']);
      $config['call_to_donate'] = $values['call_to_donate'];
      $config['summary_when_added'] = $values['summary_when_added'];
      $this->setConfiguration($config);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $config = $this->getConfiguration();
    $pane_form['add_donation'] = [
      '#type' => 'checkbox',
      '#title' => $config['call_to_donate'],
      '#default_value' => $this->hasDonation(),
    ];
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $form_state->cleanValues();
    $values = $form_state->getValues();
    $add = $values['commerce_donation_add_pane']['add_donation'] ?? FALSE;
    if ($add & !$this->hasDonation()) {
      $price = new Price(
        0,
        $this->order->getStore()->getDefaultCurrencyCode()
      );
      $donation = OrderItem::create(
        [
          'type' => 'donation',
          'title' => 'Donation',
        ]
      );
      /** @var \Drupal\commerce_order\Entity\OrderItem $donation */
      $donation->setUnitPrice($price);
      $this->order->addItem($donation);
      $this->order->save();
    }
    elseif (!$add && $this->hasDonation()) {
      $items = $this->order->getItems();
      $newItems = array_filter($items, function ($item) {
        return $item->bundle() !== 'donation';
      });
      $this->order->setItems($newItems);
      $this->order->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    // This pane is designed to be used outside our custom flow.
    return $this->checkoutFlow->getPluginId() !== 'fluxo_doacao';
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $config = $this->getConfiguration();
    if (empty($config['summary_when_added'])) {
      return [];
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $config['summary_when_added'],
    ];
  }

  /**
   * Helper method that checks the order for donations.
   */
  protected function hasDonation(): bool {
    $items = $this->order->getItems();
    // Only one donation OrderItem supported.
    $donations = array_filter($items, function ($item) {
      return $item->bundle() == 'donation';
    });
    return !empty($donations);
  }

}
