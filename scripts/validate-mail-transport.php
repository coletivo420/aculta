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

// M2: o legado drupal/smtp não pode voltar (composer.json e configuração versionada).
$check(!isset($composer['require']['drupal/smtp']), 'composer.json não pode exigir drupal/smtp (legado removido na M2).');
$check(!is_file($root . '/config/sync/smtp.settings.yml'), 'config/sync/smtp.settings.yml não pode existir (legado removido na M2).');

$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);
$locked = array_column($lock['packages'] ?? [], 'version', 'name');
$check(isset($locked['drupal/symfony_mailer']) && str_starts_with($locked['drupal/symfony_mailer'], '2.'), 'composer.lock deve conter drupal/symfony_mailer 2.x.');

$system_mail = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/system.mail.yml') ?: [];
$check(($system_mail['interface']['default'] ?? NULL) === 'symfony_mailer', 'system.mail deve manter Symfony Mailer como backend declarado.');
$fallback_scheme = (string) ($system_mail['mailer_dsn']['scheme'] ?? '');
$fallback_fails_closed = FALSE;
try {
  \Symfony\Component\Mailer\Transport::fromDsn($fallback_scheme . '://default');
}
catch (\Symfony\Component\Mailer\Exception\UnsupportedSchemeException) {
  $fallback_fails_closed = TRUE;
}
$check($fallback_scheme === 'aculta-disabled' && $fallback_fails_closed, 'O backend Symfony Mailer do Core deve falhar fechado, sem fallback sendmail.');

$transports = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/mailer_transport.settings.yml') ?: [];
$check(($transports['default_transport'] ?? NULL) === 'smtp2go', 'Mailer Plus deve usar SMTP2GO como transporte padrão.');
$smtp2go = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/mailer_transport.mailer_transport.smtp2go.yml') ?: [];
$smtp_configuration = $smtp2go['configuration'] ?? [];
$check(($smtp2go['plugin'] ?? NULL) === 'smtp', 'SMTP2GO deve usar o plugin SMTP instalado.');
$check(($smtp_configuration['host'] ?? NULL) === 'mail.smtp2go.com', 'Host SMTP2GO inesperado.');
$check(($smtp_configuration['port'] ?? NULL) === 465, 'SMTP2GO deve usar a porta 465 com TLS implícito obrigatório.');
$check(($smtp_configuration['query']['verify_peer'] ?? NULL) === TRUE, 'Verificação de certificado SMTP deve permanecer habilitada.');
$tls_transport = (new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory())->create(
  new \Symfony\Component\Mailer\Transport\Dsn('smtp', 'mail.smtp2go.com', 'validation-user', 'validation-password', 465),
);
$tls_stream = $tls_transport->getStream();
$tls_options = $tls_stream->getStreamOptions();
$check($tls_stream->isTLS(), 'O transporte SMTP Symfony deve iniciar com TLS implícito na porta 465.');
$check(($tls_options['ssl']['verify_peer'] ?? TRUE) !== FALSE, 'Symfony SMTP não pode desabilitar a validação do certificado.');
$check(($tls_options['ssl']['verify_peer_name'] ?? TRUE) !== FALSE, 'Symfony SMTP não pode desabilitar a validação do nome do certificado.');

$override_settings = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/mailer_override.settings.yml') ?: [];
$extensions = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/core.extension.yml') ?: [];
$check(isset($extensions['module']['symfony_mailer']) && isset($extensions['module']['mailer_transport']), 'Mailer Plus e mailer_transport devem estar habilitados.');
$check(isset($extensions['module']['mailer_override']), 'mailer_override deve estar habilitado para substituir o MailManager do Core.');
$check(is_array($override_settings['override'] ?? NULL), 'A lista de overrides deve estar declarada.');

$key_overrides = [
  'mailer_smtp2go_username' => ['configuration.user', 'smtp2go_username'],
  'mailer_smtp2go_password' => ['configuration.pass', 'smtp2go_password'],
];
foreach ($key_overrides as $id => [$item, $key_id]) {
  $file = $root . '/config/sync/key.config_override.' . $id . '.yml';
  $override = is_file($file) ? (\Symfony\Component\Yaml\Yaml::parseFile($file) ?: []) : [];
  $check(($override['config_prefix'] ?? NULL) === 'mailer_transport.mailer_transport'
    && ($override['config_name'] ?? NULL) === 'smtp2go'
    && ($override['config_item'] ?? NULL) === $item
    && ($override['key_id'] ?? NULL) === $key_id,
  "$id deve injetar sua Key no caminho SMTP2GO correto.");
}

foreach ([
  'smtp2go_username' => 'SMTP2GO_USERNAME',
  'smtp2go_password' => 'SMTP2GO_PASSWORD',
] as $key_id => $environment_variable) {
  $file = $root . '/config/sync/key.key.' . $key_id . '.yml';
  $key = is_file($file) ? (\Symfony\Component\Yaml\Yaml::parseFile($file) ?: []) : [];
  $check(($key['key_provider'] ?? NULL) === 'env'
    && ($key['key_provider_settings']['env_variable'] ?? NULL) === $environment_variable,
  "$key_id deve resolver a variável de ambiente aprovada.");
}

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
