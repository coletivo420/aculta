<?php

/**
 * @file
 * Consolidação OCS-A4 da camada Identity do ACULTA Studio.
 *
 * Verifica que a camada Identity:
 * - não declara seletores de modo fora de tokens.css;
 * - só sobrescreve tokens consumidos pela apresentação do Studio;
 * - é carregada pela biblioteca do tema;
 * - é ativada pelo estado controlado, sem preferência de usuário.
 *
 * Uso: php scripts/validate-aculta420-studio-identity.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420';
$identityPath = $theme . '/css/components/studio-identity.css';
$studioPath = $theme . '/css/components/studio.css';
$tokensPath = $theme . '/css/tokens.css';
$hooksPath = $theme . '/src/Hook/ThemeHooks.php';
$libraryPath = $theme . '/aculta420.libraries.yml';

$errors = [];
$checks = 0;

$read = static function (string $path) use (&$errors): string {
  $contents = @file_get_contents($path);
  if (!is_string($contents)) {
    $errors[] = 'Arquivo ausente: ' . $path;
    return '';
  }
  return $contents;
};

$identity = $read($identityPath);
$studio = $read($studioPath);
$tokens = $read($tokensPath);
$hooks = $read($hooksPath);
$library = $read($libraryPath);

// 1. Nenhum seletor de modo fora de tokens.css.
$checks++;
if (preg_match('/\[data-bs-theme\s*=|prefers-color-scheme/i', $identity) === 1) {
  $errors[] = 'studio-identity.css não pode declarar seletores de modo (use tokens semânticos).';
}

// 2. Cada token sobrescrito precisa ser consumido pela apresentação.
preg_match_all('/(--aculta-studio-fidelity-[a-z0-9-]+)\s*:/i', $identity, $declared);
$declared = array_values(array_unique($declared[1] ?? []));
$consumers = $studio . "\n" . $tokens;
$dead = [];
foreach ($declared as $token) {
  $checks++;
  if (!str_contains($consumers, 'var(' . $token . ')')) {
    $dead[] = $token;
  }
}
if ($dead !== []) {
  $errors[] = 'Tokens Identity sem consumer: ' . implode(', ', $dead);
}

// 3. Biblioteca do tema carrega a camada.
$checks++;
if (!str_contains($library, 'css/components/studio-identity.css')) {
  $errors[] = 'aculta420.libraries.yml não carrega studio-identity.css.';
}

// 4. Ativação controlada por estado, sem preferência de usuário.
$checks += 2;
if (!str_contains($hooks, "aculta420.studio_identity")) {
  $errors[] = 'ThemeHooks não lê o estado aculta420.studio_identity.';
}
if (!str_contains($hooks, "addClass('studio-identity')")) {
  $errors[] = 'ThemeHooks não adiciona a classe studio-identity.';
}

// 5. A camada Identity usa apenas tokens/valores sem literais de cor.
$checks++;
if (preg_match('/(#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\()/i', $identity) === 1) {
  $errors[] = 'studio-identity.css contém literal de cor; use tokens semânticos.';
}

$count = count($declared);
if ($errors === []) {
  printf("ACULTA420 STUDIO IDENTITY: PASS (%d checks, %d tokens overridden)\n", $checks, $count);
  exit(0);
}

fwrite(STDERR, "ACULTA420 STUDIO IDENTITY: FAIL\n");
foreach ($errors as $error) {
  fwrite(STDERR, '- ' . $error . "\n");
}
exit(1);
