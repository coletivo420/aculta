<?php

declare(strict_types=1);

/**
 * Gate do transporte de e-mail (M1 da migração para Mailer Plus, docs/portal/EMAIL-TRANSPORT.md).
 *
 * Confere: dependência Mailer Plus declarada; prontidão de e-mail sem o legado smtp.settings;
 * senha e usuário do transporte fora da configuração exportada.
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
  if (!$ok) {
    $failures[] = $message;
  }
};

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
$check(($composer['require']['drupal/symfony_mailer'] ?? '') === '^2.0', 'composer.json deve exigir drupal/symfony_mailer ^2.0.');

$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);
$locked = array_column($lock['packages'] ?? [], 'version', 'name');
$check(isset($locked['drupal/symfony_mailer']) && str_starts_with($locked['drupal/symfony_mailer'], '2.'), 'composer.lock deve conter drupal/symfony_mailer 2.x.');

$controller = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Controller/PortalController.php');
preg_match('/private function transactionalMailReady\(\): bool \{(.*?)\n  \}/s', $controller, $match);
$body = $match[1] ?? '';
$check($body !== '', 'PortalController::transactionalMailReady() não encontrado.');
$check(str_contains($body, 'mailer_override'), 'transactionalMailReady() deve checar mailer_override.');
$check(str_contains($body, 'smtp2go_password') && str_contains($body, 'smtp2go_username'), 'transactionalMailReady() deve checar as duas Keys SMTP2GO.');
$check(str_contains($controller, 'private const MAIL_TRANSPORT'), 'PortalController deve declarar a constante MAIL_TRANSPORT usada por transactionalMailReady().');
$check(!str_contains($body, 'smtp.settings') && !str_contains($body, 'SMTPMailSystem'), 'transactionalMailReady() não pode depender do legado smtp.settings/SMTPMailSystem.');

foreach (glob($root . '/config/sync/mailer_transport.mailer_transport.*.yml') ?: [] as $file) {
  $data = \Symfony\Component\Yaml\Yaml::parseFile($file) ?: [];
  $configuration = $data['configuration'] ?? [];
  $name = basename($file);
  $check(($configuration['pass'] ?? '') === '', "$name: configuration.pass deve estar vazio na configuração exportada (senha vem da Key).");
  $check(($configuration['user'] ?? '') === '', "$name: configuration.user deve estar vazio na configuração exportada (usuário vem da Key).");
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
  }
  exit(1);
}
echo "MAIL TRANSPORT GATE: PASS\n";
