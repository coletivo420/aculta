<?php

/**
 * Gate: perfil participante é criado automaticamente na criação da conta (aculta_user).
 *
 * Regra do responsável (2026-10-14): ao criar o usuário, o perfil participante nasce no hook
 * user_insert, com apelido inicial. Nenhum outro caminho pode criar perfil participante para uma
 * conta sem antes verificar se ele já existe, senão uma conta acaba com perfis duplicados.
 *
 * Verifica no código-fonte (sem Runtime):
 * 1. AccountCreationHooks::userInsert() é #[Hook('user_insert')] e chama createProfile();
 * 2. createProfile() faz a guarda loadByUser($account, 'participante') antes de criar;
 * 3. todo outro arquivo de src/ que cria perfil participante faz get-or-create
 *    (loadByUser antes do create), e não cria às cegas.
 *
 * Duplicidade no banco é coberta pelo teste de Kernel ProfileAutocreateKernelTest.
 * Só lê arquivos. Não importa configuração nem consulta o Runtime.
 */

declare(strict_types=1);

$root = dirname(__DIR__) . '/web/modules/custom/aculta_portal';
$hookFile = $root . '/modules/aculta_user/src/Hook/AccountCreationHooks.php';
$errors = [];

if (!is_file($hookFile)) {
  $errors[] = 'AccountCreationHooks.php ausente: a criação automática de perfil não existe';
}
else {
  $src = file_get_contents($hookFile);

  if (!preg_match("/#\\[Hook\\('user_insert'\\)\\]\\s*public function userInsert\\(/", $src)) {
    $errors[] = "userInsert() sem #[Hook('user_insert')]";
  }
  if (!preg_match('/public function userInsert\\(.*?\\{(.*?)\\n  \\}/s', $src, $m) || !str_contains($m[1], 'createProfile(')) {
    $errors[] = 'userInsert() não chama createProfile()';
  }
  if (!preg_match('/private function createProfile\\(.*?\\{(.*?)\\n  \\}/s', $src, $c)) {
    $errors[] = 'createProfile() ausente';
  }
  else {
    $body = $c[1];
    // Um tipo de perfil por função (PerfilDoPapel): a guarda procura o perfil do papel antes de criar.
    $guard = strpos($body, 'PerfilDoPapel::principal(');
    $create = strpos($body, '->create(');
    if ($guard === FALSE) {
      $errors[] = 'createProfile() sem a guarda PerfilDoPapel::principal()';
    }
    elseif ($create === FALSE || $create < $guard) {
      $errors[] = 'createProfile() cria o perfil antes de verificar se já existe';
    }
    if (!str_contains($body, 'PerfilDoPapel::tipoDaConta(')) {
      $errors[] = 'createProfile() não escolhe o tipo pelo papel (PerfilDoPapel::tipoDaConta)';
    }
  }
}

// Regra 3: outros criadores de perfil participante precisam ser get-or-create.
$srcRoot = $root . '/src';
$modulesRoot = $root . '/modules';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
  $path = $file->getPathname();
  if ($file->getExtension() !== 'php' || str_contains($path, '/tests/') || str_contains($path, '/config/')) {
    continue;
  }
  if (!str_starts_with($path, $srcRoot . '/') && !str_starts_with($path, $modulesRoot . '/')) {
    continue;
  }
  if ($path === $hookFile) {
    continue;
  }
  $text = file_get_contents($path);
  if (!preg_match("/create\\(\\s*\\[\\s*['\"]type['\"]\\s*=>\\s*(?:['\"]participante['\"]|PerfilDoPapel::tipoDaConta\\()/s", $text, $creation, PREG_OFFSET_CAPTURE)) {
    continue;
  }
  // Busca de existência aceita: loadByUser() ou consulta por tipo participante, sempre antes do create.
  $before = substr($text, 0, $creation[0][1]);
  $hasLookup = str_contains($before, 'loadByUser(')
    || str_contains($before, 'PerfilDoPapel::principal(')
    || preg_match("/->condition\\(\\s*['\"]type['\"]\\s*,\\s*['\"]participante['\"]/", $before)
    || str_contains($before, 'participantProfile(');
  if (!$hasLookup) {
    $rel = substr($path, strlen($root) + 1);
    $errors[] = "$rel cria perfil participante sem get-or-create (nenhuma busca de existência antes do create)";
  }
}

if ($errors !== []) {
  echo "ACCOUNT PROFILE AUTOCREATE GATE: FAIL\n";
  foreach ($errors as $e) {
    echo " - $e\n";
  }
  exit(1);
}
echo "ACCOUNT PROFILE AUTOCREATE GATE: PASS (criação automática no user_insert, com guarda; criadores get-or-create)\n";
