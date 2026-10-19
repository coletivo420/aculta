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

// R4: preserve the safe, text-only contact notification and keep the
// administrative transport verification message localized.
$contact = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/webform.webform.aculta_contact.yml') ?: [];
$contact_handler = $contact['handlers']['institutional_notification']['settings'] ?? [];
$check(($contact_handler['html'] ?? NULL) === FALSE, 'A notificação pública do contato deve permanecer em texto simples até haver sanitização HTML validada.');
$check(($contact_handler['twig'] ?? NULL) === FALSE, 'A notificação pública do contato não deve avaliar Twig fornecido por submissões.');
$check(($contact_handler['body'] ?? NULL) === '[webform_submission:values]', 'A notificação do contato deve incluir os valores usando o formato padrão do Webform.');
$check(($contact_handler['reply_to'] ?? NULL) === '[webform_submission:values:email:raw]', 'Reply-To do contato deve usar o campo de e-mail validado pelo Webform.');
$check(($contact_handler['to_mail'] ?? NULL) !== 'advfjpm@gmail.com', 'A configuração exportada não pode enviar notificações ao endereço de teste histórico.');
$check(!is_file($root . '/config/sync/webform.webform.aculta_participation.yml'), 'O formulário de participação foi removido; não há notificações de participação a validar.');
$verify_policy = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/mailer_policy.mailer_policy.symfony_mailer.verify.yml') ?: [];
$verify_body = (string) ($verify_policy['configuration']['email_body']['content']['value'] ?? '');
$verify_subject = (string) ($verify_policy['configuration']['email_subject']['value'] ?? '');
$check(str_contains($verify_body, 'Verificação do transporte de e-mail') && str_contains($verify_subject, 'Verificação do transporte'), 'O template administrativo de verificação deve estar em português.');
$check(!str_contains($verify_body, 'Have a great') && !str_contains($verify_subject, 'Verification email'), 'O template administrativo de verificação não deve manter texto demonstrativo em inglês.');

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
$check(($transports['default_transport'] ?? NULL) === 'smtp', 'Mailer Plus deve usar a Hostinger (transporte smtp) como padrão; SMTP2GO fica disponível para troca.');
$hostinger = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/mailer_transport.mailer_transport.smtp.yml') ?: [];
$check(($hostinger['plugin'] ?? NULL) === 'smtp' && ($hostinger['configuration']['host'] ?? NULL) === 'smtp.hostinger.com' && ($hostinger['configuration']['port'] ?? NULL) === 465, 'Transporte Hostinger: plugin smtp, smtp.hostinger.com, porta 465.');
$check(($hostinger['configuration']['user'] ?? '') === '' && ($hostinger['configuration']['pass'] ?? '') === '', 'Transporte Hostinger não pode versionar credenciais.');
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

// Sem Keys: o Mailer Plus é o único controlador de envio. Nenhum override de Key nem Key de SMTP2GO pode existir.
foreach (['key.config_override.mailer_smtp2go_username', 'key.config_override.mailer_smtp2go_password', 'key.key.smtp2go_username', 'key.key.smtp2go_password'] as $removed) {
  $check(!is_file($root . '/config/sync/' . $removed . '.yml'), "$removed não pode existir: o envio não usa Keys.");
}

$controller = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Controller/PortalController.php');
preg_match('/private function transactionalMailReady\(\): bool \{(.*?)\n  \}/s', $controller, $match);
$body = $match[1] ?? '';
$check($body !== '', 'PortalController::transactionalMailReady() não encontrado.');
$check(str_contains($body, 'mailer_override'), 'transactionalMailReady() deve checar mailer_override.');
$check(str_contains($body, "'user'") && str_contains($body, "'pass'"), 'transactionalMailReady() deve checar usuário e senha do transporte do Mailer Plus.');
$check(str_contains($controller, "default_transport") && !str_contains($controller, 'keyHasValue'), 'PortalController deve checar o transporte padrão do Mailer Plus, sem Keys.');
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
