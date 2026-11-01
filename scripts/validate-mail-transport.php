<?php

declare(strict_types=1);

/**
 * Gate do transporte de e-mail (F15, docs/portal/EMAIL-TRANSPORT.md): o envio do site é a camada de envio E-Mails
 * ACULTA (aculta_mailer), com transportes aculta_mailer_transport e credenciais no arquivo de segredos.
 *
 * Confere: contrib de envio removido (composer e configuração); system.mail no módulo do projeto; transportes sem
 * senha versionada; TLS e verificação de certificado do SMTP; formulário de contato em texto simples.
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
  if (!$ok) {
    $failures[] = $message;
  }
};

// F15: o pacote de envio do contrib saiu do composer.json e do composer.lock. O Symfony Mailer continua vindo do Core.
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
$check(!isset($composer['require']['drupal/symfony_mailer']), 'composer.json não pode exigir drupal/symfony_mailer (removido na F15).');
$check(!isset($composer['require']['drupal/smtp']), 'composer.json não pode exigir drupal/smtp (legado removido na M2).');
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);
$locked = array_column($lock['packages'] ?? [], 'version', 'name');
$check(!isset($locked['drupal/symfony_mailer']) && !isset($locked['drupal/mailer_transport']), 'composer.lock não pode conter drupal/symfony_mailer nem drupal/mailer_transport.');
$check(isset($locked['symfony/mailer']), 'composer.lock deve conter symfony/mailer (biblioteca usada pela camada de envio).');

$system_mail = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/system.mail.yml') ?: [];
$check(($system_mail['interface']['default'] ?? NULL) === 'aculta_mailer', 'system.mail deve usar a camada de envio E-Mails ACULTA (aculta_mailer).');

// Formulário de contato: notificação pública em texto simples, sem Twig de submissão e sem destinatário de teste.
$contact = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/webform.webform.aculta_contact.yml') ?: [];
$contact_handler = $contact['handlers']['institutional_notification']['settings'] ?? [];
$check(($contact_handler['html'] ?? NULL) === FALSE, 'A notificação pública do contato deve permanecer em texto simples até haver sanitização HTML validada.');
$check(($contact_handler['twig'] ?? NULL) === FALSE, 'A notificação pública do contato não deve avaliar Twig fornecido por submissões.');
$check(($contact_handler['body'] ?? NULL) === '[webform_submission:values]', 'A notificação do contato deve incluir os valores usando o formato padrão do Webform.');
$check(($contact_handler['reply_to'] ?? NULL) === '[webform_submission:values:email:raw]', 'Reply-To do contato deve usar o campo de e-mail validado pelo Webform.');
$check(($contact_handler['to_mail'] ?? NULL) !== 'advfjpm@gmail.com', 'A configuração exportada não pode enviar notificações ao endereço de teste histórico.');
$check(!is_file($root . '/config/sync/webform.webform.aculta_participation.yml'), 'O formulário de participação foi removido; não há notificações de participação a validar.');

// Transportes da camada de envio: entidades sem senha versionada; credenciais só por nome no arquivo de segredos.
$hostinger = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/aculta_mailer.transport.smtp_hostinger.yml') ?: [];
$check(($hostinger['plugin'] ?? NULL) === 'smtp' && ($hostinger['host'] ?? NULL) === 'smtp.hostinger.com' && ($hostinger['port'] ?? NULL) === 465, 'Transporte Hostinger: plugin smtp, smtp.hostinger.com, porta 465.');
$check(($hostinger['password_secret'] ?? NULL) === 'SMTP_PASSWORD', 'Transporte Hostinger: a senha vem de SMTP_PASSWORD (arquivo de segredos).');
$smtp2go = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/aculta_mailer.transport.smtp2go.yml') ?: [];
$check(($smtp2go['plugin'] ?? NULL) === 'smtp' && ($smtp2go['host'] ?? NULL) === 'mail.smtp2go.com' && ($smtp2go['port'] ?? NULL) === 465, 'SMTP2GO: plugin smtp, mail.smtp2go.com, porta 465.');
$check(($smtp2go['user_secret'] ?? NULL) === 'SMTP2GO_USERNAME' && ($smtp2go['password_secret'] ?? NULL) === 'SMTP2GO_PASSWORD', 'SMTP2GO: usuário e senha vêm do arquivo de segredos.');
$brevo = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/aculta_mailer.transport.brevo.yml') ?: [];
$check(($brevo['plugin'] ?? NULL) === 'brevo', 'Transporte Brevo: plugin brevo (chave em BREVO_API_KEY).');
foreach (glob($root . '/config/sync/aculta_mailer.transport.*.yml') ?: [] as $file) {
  $data = \Symfony\Component\Yaml\Yaml::parseFile($file) ?: [];
  $check(!array_key_exists('pass', $data) && !array_key_exists('password', $data), basename($file) . ': não pode versionar senha.');
  $check(($data['user'] ?? '') === '' || basename($file) === 'aculta_mailer.transport.smtp_hostinger.yml', basename($file) . ': usuário literal só no transporte Hostinger (endereço de envio, não segredo).');
}

// TLS e verificação do certificado no SMTP (porta 465 com TLS implícito).
$tls_transport = (new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory())->create(
  new \Symfony\Component\Mailer\Transport\Dsn('smtp', 'mail.smtp2go.com', 'validation-user', 'validation-password', 465),
);
$tls_stream = $tls_transport->getStream();
$check($tls_stream->isTLS(), 'O transporte SMTP Symfony deve iniciar com TLS implícito na porta 465.');
$tls_options = $tls_stream->getStreamOptions();
$check(($tls_options['ssl']['verify_peer'] ?? TRUE) !== FALSE, 'Symfony SMTP não pode desabilitar a validação do certificado.');
$check(($tls_options['ssl']['verify_peer_name'] ?? TRUE) !== FALSE, 'Symfony SMTP não pode desabilitar a validação do nome do certificado.');

// Módulos: o projeto habilitado; o contrib de envio fora da configuração.
$extensions = \Symfony\Component\Yaml\Yaml::parseFile($root . '/config/sync/core.extension.yml') ?: [];
$check(isset($extensions['module']['aculta_mailer']), 'aculta_mailer deve estar habilitado.');
foreach (['symfony_mailer', 'mailer_override', 'mailer_transport', 'mailer_policy'] as $contrib) {
  $check(!isset($extensions['module'][$contrib]), "$contrib não pode estar habilitado (removido na F15).");
}

// Sem Keys: nenhum override de Key nem Key de SMTP2GO pode existir.
foreach (['key.config_override.mailer_smtp2go_username', 'key.config_override.mailer_smtp2go_password', 'key.key.smtp2go_username', 'key.key.smtp2go_password'] as $removed) {
  $check(!is_file($root . '/config/sync/' . $removed . '.yml'), "$removed não pode existir: o envio não usa Keys.");
}

// Prontidão de e-mail do Portal: checa o transporte novo e a senha pelo arquivo de segredos.
$controller = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Controller/PortalController.php');
preg_match('/private function transactionalMailReady\(\): bool \{(.*?)\n  \}/s', $controller, $match);
$body = $match[1] ?? '';
$check($body !== '', 'PortalController::transactionalMailReady() não encontrado.');
$check(str_contains($body, "'aculta_mailer'") && str_contains($body, 'aculta_mailer_transport') && str_contains($body, 'password_secret'), 'transactionalMailReady() deve checar a camada de envio e a senha do transporte pelo arquivo de segredos.');
$check(preg_match("/'(mailer_override|mailer_transport|mailer_transport\\.[a-z_]+)'|mailer_override|smtp\\.settings/", $body) === 0, 'transactionalMailReady() não pode depender do contrib de envio nem do legado.');
$check(!str_contains($controller, 'keyHasValue'), 'PortalController não pode usar Keys para o envio.');

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
  }
  exit(1);
}
echo "MAIL TRANSPORT GATE: PASS\n";
