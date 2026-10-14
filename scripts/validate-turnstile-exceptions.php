<?php

/**
 * Gate anti-regressão das exceções do Turnstile (docs/portal/TURNSTILE.md, seção "Exceções").
 *
 * Confere, sem bootstrap do Drupal:
 * - cada formulário excluído (EXCEPTION_FORM_IDS) tem captcha_point com status false em config/sync;
 * - os formulários que precisam de proteção (login, cadastro, recuperação, contato, participação) seguem com status true;
 * - a lista de exceções do código e a documentação são as mesmas;
 * - o popup "Entrar" é o único formulário novo de exceção: sem elemento de captcha e com o ID registrado;
 * - a rota do popup existe e o /entrar continua com o Turnstile (caminho e ponto ativo).
 *
 * Motivo: a exceção só é segura enquanto for explícita. Uma mudança em config ou em código que aplique o desafio
 * ao modal, ou que tire o desafio de /entrar, precisa falhar aqui e não passar despercebida.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$read = static function (string $relative) use ($root): string {
  $path = $root . '/' . $relative;
  return is_file($path) ? (string) file_get_contents($path) : '';
};

$toggle = $read('web/modules/custom/aculta_portal/src/Captcha/TurnstileToggle.php');
if (!preg_match('/EXCEPTION_FORM_IDS\s*=\s*\[(.*?)\];/s', $toggle, $m)) {
  $errors[] = 'TurnstileToggle::EXCEPTION_FORM_IDS não encontrado.';
  $exceptions = [];
}
else {
  preg_match_all("/'([a-z0-9_]+)'/", $m[1], $ids);
  $exceptions = $ids[1];
}

// Exceções obrigatórias (contrato): loja, checkout (cadastro pela compra) e popup "Entrar".
$required = ['commerce_order_item_add_to_cart_form', 'commerce_checkout_flow_default', 'aculta_login_popup_form'];
foreach ($required as $formId) {
  if (!in_array($formId, $exceptions, true)) {
    $errors[] = "Exceção obrigatória fora de EXCEPTION_FORM_IDS: {$formId}.";
  }
}

// Cada exceção precisa de ponto desligado em config/sync com o mesmo formId.
foreach ($exceptions as $formId) {
  $file = "config/sync/captcha.captcha_point.{$formId}.yml";
  $yaml = $read($file);
  if ($yaml === '') {
    $errors[] = "Exceção sem ponto de captcha em {$file}.";
    continue;
  }
  if (!preg_match('/^status:\s*false\s*$/m', $yaml)) {
    $errors[] = "{$file} deve ter status: false (exceção do Turnstile).";
  }
  if (!preg_match('/^formId:\s*' . preg_quote($formId, '/') . '\s*$/m', $yaml)) {
    $errors[] = "{$file} com formId diferente de {$formId}.";
  }
}

// Formulários protegidos continuam com o desafio ativo.
$protected = ['user_login_form', 'user_register_form', 'user_pass', 'webform_submission_aculta_contact_add_form', 'webform_submission_aculta_participation_add_form'];
foreach ($protected as $formId) {
  $file = "config/sync/captcha.captcha_point.{$formId}.yml";
  $yaml = $read($file);
  if (!preg_match('/^status:\s*true\s*$/m', $yaml)) {
    $errors[] = "{$file} deve permanecer com status: true (proteção do Turnstile).";
  }
}

// O popup não pode receber elemento de captcha, e o ID do formulário é o registrado.
$popup = $read('web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php');
if ($popup === '') {
  $errors[] = 'LoginPopupForm.php não encontrado.';
}
else {
  if (!preg_match("/FORM_ID\s*=\s*'aculta_login_popup_form'/", $popup)) {
    $errors[] = 'LoginPopupForm::FORM_ID deve ser aculta_login_popup_form.';
  }
  if (!preg_match('/extends\s+UserLoginForm\b/', $popup)) {
    $errors[] = 'LoginPopupForm deve herdar de UserLoginForm (validação e flood do Core).';
  }
  // Só código conta: comentários (que citam a exceção) são removidos antes da checagem.
  $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $popup) ?? $popup;
  if (preg_match("/['\"]#type['\"]\s*=>\s*['\"]captcha['\"]|captcha_|Turnstile/i", $code)) {
    $errors[] = 'LoginPopupForm não pode receber captcha (exceção do Turnstile).';
  }
}

// Rota do popup e /entrar.
$routing = $read('web/modules/custom/aculta_portal/aculta_portal.routing.yml');
if (!preg_match('/aculta_portal\.login_popup:\s*\n\s*path:\s*\/entrar\/janela\s*\n(?:.*\n)*?\s*_form:\s*\'\\\\Drupal\\\\aculta_portal\\\\Form\\\\LoginPopupForm\'/', $routing)) {
  $errors[] = 'Rota aculta_portal.login_popup (/entrar/janela, _form LoginPopupForm) não encontrada.';
}
$subscriber = $read('web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php');
if (!str_contains($subscriber, "'user.login' => '/entrar'")) {
  $errors[] = 'user.login deixou de ser /entrar: a página de login precisa permanecer.';
}

// Documentação lista todas as exceções.
$doc = $read('docs/portal/TURNSTILE.md');
foreach ($exceptions as $formId) {
  if (!str_contains($doc, "`{$formId}`")) {
    $errors[] = "docs/portal/TURNSTILE.md não documenta a exceção {$formId}.";
  }
}

if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, "FAIL: {$error}\n");
  }
  fwrite(STDERR, sprintf("validate-turnstile-exceptions: FAIL (%d problema(s))\n", count($errors)));
  exit(1);
}

printf("validate-turnstile-exceptions: PASS (%d exceções, %d formulários protegidos)\n", count($exceptions), count($protected));
exit(0);
