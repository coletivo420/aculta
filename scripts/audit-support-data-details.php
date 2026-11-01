<?php
$storage = \Drupal::entityTypeManager()->getStorage('aculta_contribution');
foreach ($storage->loadMultiple() as $item) {
  print json_encode([
    'id' => $item->id(),
    'type' => $item->get('type')->value,
    'status' => $item->get('status')->value,
    'amount_cents' => $item->get('amount_cents')->value,
    'has_order_id' => !$item->get('order_id')->isEmpty(),
    'has_payment_id' => !$item->get('payment_id')->isEmpty(),
    'has_subscription_id' => !$item->get('subscription_id')->isEmpty(),
    'checkout_url_saved' => !$item->get('checkout_url')->isEmpty(),
  ], JSON_THROW_ON_ERROR) . PHP_EOL;
}
