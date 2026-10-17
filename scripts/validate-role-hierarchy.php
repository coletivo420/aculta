<?php

/**
 * @file
 * Gate: hierarquia de papéis (role_inheritance) e checagens por nome de papel.
 *
 * Decisão do responsável (2026-10-17): cadeia unilateral authenticated < email_confirmed < estudante < associado <
 * lms_teacher < content_editor < gerente_loja < administrator. A herança é transitiva; administrator herda de todos.
 *
 * Verifica no config/sync (sem Runtime):
 * - a cadeia de herança exatamente como decidida (email_confirmed, estudante, associado, lms_teacher,
 *   content_editor, gerente_loja);
 * - o mapa não tem ciclo nem autorreferência;
 * - administrator herda de todos os demais papéis de negócio;
 * - a contagem de checagens de acesso por nome de papel no código (hasRole e in_array com nome de papel) não sobe
 *   acima da linha de base. Acesso novo deve usar permissão (hasPermission), que respeita a hierarquia.
 *
 * Só lê config/sync e código. Não importa configuração nem consulta o Runtime.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
require_once $root . '/web/modules/custom/aculta_portal/src/Role/RoleHierarchy.php';

use Drupal\aculta_portal\Role\RoleHierarchy;
$errors = [];

/** Herança decidida: papel => papéis dos quais herda (conjunto exato). */
$decidida = [
  'email_confirmed' => ['authenticated'],
  'estudante' => ['email_confirmed'],
  'associado' => ['estudante'],
  'lms_teacher' => ['associado'],
  'content_editor' => ['lms_teacher'],
  'gerente_loja' => ['content_editor'],
];

/** Linha de base de checagens por nome de papel em código customizado (dívida: não pode subir). */
// 15: as duas chamadas novas (aculta_user, RoleInheritanceHooks) são da própria herança (adicionar e retirar o papel
// herdado da conta), não decisões de acesso. Acesso novo continua por permissão.
const LINHA_DE_BASE_HASROLE = 15;
const LINHA_DE_BASE_IN_ARRAY_PAPEL = 4;

$mapa = Yaml::parseFile($root . '/config/sync/role_inheritance.settings.yml')['role_map'] ?? [];

foreach ($decidida as $papel => $esperado) {
  $atual = $mapa[$papel] ?? [];
  sort($atual);
  $esperadoOrdenado = $esperado;
  sort($esperadoOrdenado);
  if ($atual !== $esperadoOrdenado) {
    $errors[] = sprintf('role_inheritance: %s herda de [%s], decidido [%s]', $papel, implode(', ', $atual), implode(', ', $esperado));
  }
}

if (RoleHierarchy::temCiclo($mapa)) {
  $errors[] = 'role_inheritance: o mapa tem ciclo ou autorreferência';
}

$herdaDoAdmin = $mapa['administrator'] ?? [];
foreach (array_keys($decidida) as $papel) {
  if (!in_array($papel, $herdaDoAdmin, TRUE)) {
    $errors[] = sprintf('role_inheritance: administrator não herda de %s', $papel);
  }
}

// Contagem em código customizado (PHP de módulos e tema), excluindo testes.
$papeis = '(administrator|gerente_loja|lms_teacher|estudante|associado|email_confirmed|wiki_contributor|content_editor|lms_admin)';
$hasRole = 0;
$inArray = 0;
$dirs = [$root . '/web/modules/custom', $root . '/web/themes/custom/aculta420/src'];
foreach ($dirs as $dir) {
  $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($iterador as $arquivo) {
    if ($arquivo->getExtension() !== 'php' || str_contains($arquivo->getPathname(), '/tests/')) {
      continue;
    }
    $texto = (string) file_get_contents($arquivo->getPathname());
    $hasRole += preg_match_all('/->hasRole\(/', $texto);
    $inArray += preg_match_all("/in_array\('" . $papeis . "'/", $texto);
  }
}
if ($hasRole > LINHA_DE_BASE_HASROLE) {
  $errors[] = sprintf('checagens ->hasRole( em código customizado subiram: %d (linha de base %d). Use permissão (hasPermission).', $hasRole, LINHA_DE_BASE_HASROLE);
}
if ($inArray > LINHA_DE_BASE_IN_ARRAY_PAPEL) {
  $errors[] = sprintf('checagens in_array com nome de papel subiram: %d (linha de base %d). Use permissão (hasPermission).', $inArray, LINHA_DE_BASE_IN_ARRAY_PAPEL);
}

if ($errors !== []) {
  foreach ($errors as $erro) {
    fwrite(STDERR, '- ' . $erro . "\n");
  }
  fwrite(STDERR, "ROLE HIERARCHY: FAIL (" . count($errors) . " falhas)\n");
  exit(1);
}
echo sprintf("ROLE HIERARCHY: PASS (cadeia de %d papéis; hasRole %d/%d; in_array de papel %d/%d)\n", count($decidida), $hasRole, LINHA_DE_BASE_HASROLE, $inArray, LINHA_DE_BASE_IN_ARRAY_PAPEL);
