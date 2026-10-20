<?php

/**
 * @file
 * Gate: links de acesso único valem 24 horas em todo o projeto, e reenvio não reaproveita link antigo.
 *
 * Decisão do responsável (2026-10-20). Ver docs/portal/LINKS-DE-USO-UNICO.md.
 *
 * Confere, sem Runtime:
 * - password_reset_timeout (Core user) e hash_expiration (email_confirmer) em config/sync: no máximo 86400.
 * - constante de validade do Portal (SingleUseLinkPolicy::TTL) e do aculta_mailer (SingleUseLinkMail::TTL): 86400.
 * - EmailConfirmationEntity::isExpired() usa a política; nenhum isExpired() customizado devolve FALSE fixo.
 * - cancelamento do conteúdo anterior no pedido novo (AcultaMailer::deliver) e recusa de reenvio de link de uso único
 *   (MailResend::canResend).
 * - documento normativo e índice do Portal presentes.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$errors = [];
$check = static function (bool $ok, string $message) use (&$errors): void {
  if (!$ok) {
    $errors[] = $message;
  }
};
$read = static fn(string $path): string => is_file($path) ? (string) file_get_contents($path) : '';

const MAX_SECONDS = 86400;

// Configuração versionada: nenhum prazo maior que 24 horas.
$userSettings = is_file($root . '/config/sync/user.settings.yml') ? Yaml::parseFile($root . '/config/sync/user.settings.yml') : [];
$timeout = (int) ($userSettings['password_reset_timeout'] ?? 0);
$check($timeout > 0 && $timeout <= MAX_SECONDS, "user.settings:password_reset_timeout deve ser de 1 a 86400 (é $timeout).");

$emailSettings = is_file($root . '/config/sync/email_confirmer.settings.yml') ? Yaml::parseFile($root . '/config/sync/email_confirmer.settings.yml') : [];
$hash = (int) ($emailSettings['hash_expiration'] ?? 0);
$check($hash > 0 && $hash <= MAX_SECONDS, "email_confirmer.settings:hash_expiration deve ser de 1 a 86400 (é $hash).");

// Constantes da regra, nos dois módulos, com o mesmo valor.
$portalPolicy = $read($root . '/web/modules/custom/aculta_portal/src/Email/SingleUseLinkPolicy.php');
$check(preg_match('/const TTL = 86400;/', $portalPolicy) === 1, 'SingleUseLinkPolicy::TTL deve ser 86400 (src/Email/SingleUseLinkPolicy.php).');

$mailerPolicy = $read($root . '/web/modules/custom/aculta_portal/modules/aculta_mailer/src/Log/SingleUseLinkMail.php');
$check(preg_match('/const TTL = 86400;/', $mailerPolicy) === 1, 'SingleUseLinkMail::TTL deve ser 86400 (aculta_mailer/src/Log/SingleUseLinkMail.php).');

// Confirmação de e-mail: a expiração vem da política, nunca de um valor fixo.
$entity = $read($root . '/web/modules/custom/aculta_portal/src/Email/EmailConfirmationEntity.php');
$check(str_contains($entity, 'SingleUseLinkPolicy::isExpired('), 'EmailConfirmationEntity::isExpired() deve usar SingleUseLinkPolicy.');

// Nenhum isExpired() customizado do projeto pode devolver FALSE fixo (a validade infinita foi revogada).
foreach (['web/modules/custom', 'web/themes/custom/aculta420/src'] as $dir) {
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/tests/')) {
      continue;
    }
    $text = (string) file_get_contents($file->getPathname());
    if (preg_match('/function\s+isExpired\s*\([^)]*\)[^{]*\{\s*return\s+FALSE\s*;/', $text) === 1) {
      $errors[] = 'isExpired() devolve FALSE fixo em ' . substr($file->getPathname(), strlen($root) + 1) . ' (validade de 24 horas, ver docs/portal/LINKS-DE-USO-UNICO.md).';
    }
  }
}

// Pedido novo cancela o conteúdo anterior; reenvio não reaproveita link de uso único.
$deliver = $read($root . '/web/modules/custom/aculta_portal/modules/aculta_mailer/src/Api/AcultaMailer.php');
$check(str_contains($deliver, 'cancelPayloads('), 'AcultaMailer::deliver deve cancelar o conteúdo anterior de link de uso único (cancelPayloads).');
$resend = $read($root . '/web/modules/custom/aculta_portal/modules/aculta_mailer/src/Delivery/MailResend.php');
$check(str_contains($resend, 'SingleUseLinkMail::applies(') && str_contains($resend, 'regenerate('), 'MailResend deve gerar o reenvio de link de uso único pelo dono (regenerate), sem reaproveitar conteúdo.');

// Documentação e índice.
$doc = $read($root . '/docs/portal/LINKS-DE-USO-UNICO.md');
$check(str_contains($doc, '24 horas'), 'docs/portal/LINKS-DE-USO-UNICO.md ausente ou sem a regra de 24 horas.');
$check(str_contains($read($root . '/docs/portal/README.md'), 'LINKS-DE-USO-UNICO.md'), 'docs/portal/README.md deve listar LINKS-DE-USO-UNICO.md.');

if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, '- ' . $error . "\n");
  }
  fwrite(STDERR, 'SINGLE-USE LINKS: FAIL (' . count($errors) . " falhas)\n");
  exit(1);
}
echo "SINGLE-USE LINKS: PASS (24 horas em todos os módulos; pedido novo cancela o anterior; reenvio não reaproveita link)\n";
