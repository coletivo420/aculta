<?php

/**
 * @file
 * Static gate for the ACCOUNT root.
 *
 * It is a regression barrier for /conta-interna.
 *
 * The account overview is served only at the root of the ACCOUNT Domain. Its
 * internal path (/conta-interna) must never become public, and no link may be
 * generated for it.
 * This gate reads the sources and reprova:
 * - a second routing entry that uses the internal path;
 * - the request guard of the overview route being removed;
 * - a literal link to the internal path in code, templates, scripts or config;
 * - a public email confirmation route outside the Portuguese slug family;
 * - a version marker (info.yml) that disagrees with the module CHANGELOG.
 *
 * It does not bootstrap Drupal or need vendor/, so it runs in any checkout.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$internalPath = '/minha-conta';
$failures = [];
$checks = 0;

$read = static function (string $file) use ($root): string {
  $path = $root . '/' . $file;
  $contents = is_file($path) ? file_get_contents($path) : FALSE;
  if ($contents === FALSE) {
    fwrite(STDERR, "CONTA ROOT FAIL: cannot read $file\n");
    exit(1);
  }
  return $contents;
};

// 1. The retired path /minha-conta is absent; the original overview is at
// /conta-legada.
$routing = $read('web/modules/custom/aculta_portal/aculta_portal.routing.yml');
$checks++;
preg_match_all('#^(?<name>[A-Za-z0-9_.]+):\s*\n\s+path:\s*(?<path>\S+)#m', $routing, $matches, PREG_SET_ORDER);
$usingInternal = [];
foreach ($matches as $match) {
  if (str_starts_with($match['path'], $internalPath)) {
    $usingInternal[] = $match['name'];
  }
}
if ($usingInternal !== []) {
  $failures[] = 'routing: the retired path ' . $internalPath . ' must not be a route; found: ' . implode(', ', $usingInternal);
}
$checks++;
if (!preg_match('#aculta_portal\.dashboard:\s*\n\s+path:\s*/conta-legada\s*\n#', $routing)) {
  $failures[] = 'routing: aculta_portal.dashboard (Minha Conta original) must use /conta-legada';
}

// 2. Email confirmation resend is public and uses the Portuguese path.
$checks++;
if (!preg_match('#aculta_portal\.email_resend:\s*\n\s+path:\s*/confirmar-email/reenviar\s*\n#', $routing)) {
  $failures[] = 'routing: aculta_portal.email_resend must use /confirmar-email/reenviar';
}

// 3. Guards for the retired path and ACCOUNT root must stay in place.
$checks++;
$subscriber = $read('web/modules/custom/aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php');
// /minha-conta is not a route; its explicit 404 prevents redirects.
if (!str_contains($subscriber, "\$accountPath === '/minha-conta'") || !str_contains($subscriber, 'Response::HTTP_NOT_FOUND')) {
  $failures[] = 'subscriber: /minha-conta must answer 404 without redirect';
}
// The ACCOUNT root serves /conta; public /studio routes are removed.
// Its guard precedes Core access checks, sending anonymous visitors to login.
$policy = $read('web/modules/custom/aculta_portal/src/Domain/FrontPathPolicy.php');
if (!str_contains($subscriber, 'FrontPathPolicy::acaoDaContaNaRaiz(')
  || !str_contains($policy, "return \$autenticado ? NULL : self::ACAO_LOGIN;")
  || !str_contains($policy, "\$route !== 'aculta_studio.account'")) {
  $failures[] = 'guard: the ACCOUNT root entry must be decided by FrontPathPolicy::acaoDaContaNaRaiz and used by the subscriber';
}

// 4. No literal internal link in code, templates, assets or exported config.
$scanDirs = [
  'web/modules/custom/aculta_portal/src',
  'web/modules/custom/aculta_portal/templates',
  'web/modules/custom/aculta_portal/js',
  'web/modules/custom/aculta_portal/css',
  'web/modules/custom/aculta_portal/modules/aculta_deployer/src',
  'scripts',
  'config/sync',
];
$allowedConfig = [
  // The ACCOUNT Domain front page uses the canonical Conta entry route.
  'config/sync/domain/conta_aculta_org/system.site.yml' => '#^\s*front: /conta\s*$#',
];
foreach ($scanDirs as $dir) {
  // Some trees may be absent (for example, css/ in the module).
  if (!is_dir($root . '/' . $dir)) {
    continue;
  }
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !preg_match('#\.(php|module|inc|yml|twig|js|mjs|css|json)$#', $file->getFilename())) {
      continue;
    }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    // Validators quote the retired path as data, not as a link.
    if (str_starts_with($relative, 'scripts/validate-')) {
      continue;
    }
    $checks++;
    $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $number => $line) {
      // The retired /minha-conta/perfil path is not a link to the overview.
      if (!preg_match('#' . preg_quote($internalPath, '#') . '(?![/\w-])#', $line)) {
        continue;
      }
      // Comments may name the path; code, templates and links may not.
      if (preg_match('~^\s*(?://|\#|\*|/\*)~', $line)) {
        continue;
      }
      // The 404 guard reads the incoming path but does not generate a link.
      if ($relative === 'web/modules/custom/aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php' && str_contains($line, '$accountPath')) {
        continue;
      }
      if (isset($allowedConfig[$relative]) && preg_match($allowedConfig[$relative], $line)) {
        continue;
      }
      $failures[] = sprintf('%s:%d links the internal path %s', $relative, $number + 1, $internalPath);
    }
  }
}

// 5. Version marker: info.yml must match the head of the module CHANGELOG.
$checks++;
$info = $read('web/modules/custom/aculta_portal/aculta_portal.info.yml');
$changelog = $read('web/modules/custom/aculta_portal/CHANGELOG.md');
preg_match("#^version:\s*'?([^'\n]+)'?#m", $info, $infoMatch);
preg_match('~^## (\S+) —~m', $changelog, $logMatch);
if (($infoMatch[1] ?? NULL) === NULL || ($logMatch[1] ?? NULL) !== ($infoMatch[1] ?? NULL)) {
  $failures[] = sprintf('version: aculta_portal.info.yml (%s) and the CHANGELOG head (%s) disagree', $infoMatch[1] ?? '?', $logMatch[1] ?? '?');
}

if ($failures !== []) {
  foreach ($failures as $failure) {
    fwrite(STDERR, "CONTA ROOT FAIL: $failure\n");
  }
  fwrite(STDERR, sprintf("CONTA ROOT: FAIL (%d failures in %d checks)\n", count($failures), $checks));
  exit(1);
}

echo sprintf("CONTA ROOT: PASS (%d checks; the ACCOUNT root serves the Conta entry, the original Minha Conta is /conta-legada)\n", $checks);
