<?php

namespace Drupal\aculta_donation\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Attribute\CommerceCheckoutFlow;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Extends the default multistep checkout flow with donation steps.
 */
#[CommerceCheckoutFlow(
  id: 'donation_multistep_flow',
  label: new TranslatableMarkup('Multietapas com doação'),
)]
class MultistepWithDonation extends CheckoutFlowWithPanesBase {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    // Note that previous_label and next_label are not the labels
    // shown on the step itself. Instead, they are the labels shown
    // when going back to the step, or proceeding to the step.
    return [
      'login' => [
        'label' => $this->t('Login'),
        'previous_label' => $this->t('Go back'),
        'has_sidebar' => FALSE,
      ],
      'order_information' => [
        'label' => $this->t('Order information'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Go back'),
      ],
      'donation' => [
        'label' => $this->t('Your Donation'),
        'next_label' => $this->t('Continue'),
      ],
      'dedication' => [
        'label' => $this->t('Dedication Information'),
        'next_label' => $this->t('Continue'),
      ],
      'review' => [
        'label' => $this->t('Review'),
        'next_label' => $this->t('Continue to review'),
        'previous_label' => $this->t('Go back'),
        'has_sidebar' => TRUE,
      ],
    ] + parent::getSteps();
  }

}
