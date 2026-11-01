<?php

/**
 * Gate estático da barreira de CAPTCHA da loja. Somente leitura; não inicia o Drupal.
 *
 * Reprova se a loja voltar a ter CAPTCHA: os pontos de carrinho e checkout padrão precisam estar desativados em
 * config/sync, a lista SHOP_FORM_IDS do TurnstileToggle precisa cobri-los, e o hook que remove o script do
 * Turnstile nas rotas da loja precisa existir com as três rotas.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

$shopForms = ['commerce_order_item_add_to_cart_form', 'commerce_checkout_flow_default'];
$routes = ['entity.commerce_product.canonical', 'commerce_cart.page', 'commerce_checkout.form'];

foreach ($shopForms as $formId) {
  $file = $root . '/config/sync/captcha.captcha_point.' . $formId . '.yml';
  $assert(is_file($file), "config/sync sem ponto de CAPTCHA para $formId.");
  if (is_file($file)) {
    $text = (string) file_get_contents($file);
    $assert(preg_match('/^status:\s*false\s*$/m', $text) === 1, "Ponto de CAPTCHA de $formId não está desativado (status: false).");
    $assert(preg_match('/^formId:\s*' . preg_quote($formId, '/') . '\s*$/m', $text) === 1, "Ponto de CAPTCHA de $formId com formId divergente.");
  }
}

$toggle = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Captcha/TurnstileToggle.php');
foreach ($shopForms as $formId) {
  $assert(str_contains($toggle, "'" . $formId . "'"), "TurnstileToggle::SHOP_FORM_IDS não cobre $formId.");
}
$assert(str_contains($toggle, 'in_array($point->id(), self::EXCEPTION_FORM_IDS, true)'), 'TurnstileToggle::setEnabled() não pula as exceções (inclui a loja).');

$hook = $root . '/web/modules/custom/aculta_portal/src/Hook/ShopCaptchaHooks.php';
$assert(is_file($hook), 'Hook da barreira da loja ausente (src/Hook/ShopCaptchaHooks.php).');
if (is_file($hook)) {
  $text = (string) file_get_contents($hook);
  $assert(str_contains($text, "#[Hook('page_attachments_alter')]"), 'ShopCaptchaHooks sem o hook page_attachments_alter.');
  $assert(str_contains($text, 'challenges.cloudflare.com'), 'ShopCaptchaHooks não remove o script do Turnstile.');
  foreach ($routes as $route) {
    $assert(str_contains($text, "'" . $route . "'"), "ShopCaptchaHooks sem a rota $route.");
  }
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "SHOP CAPTCHA FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("SHOP CAPTCHA: FAIL (%d failures in %d checks)\n", count($failures), $checks));
  exit(1);
}
echo sprintf("SHOP CAPTCHA: PASS (%d checks)\n", $checks);
