<?php

/**
 * Gate: papéis de Wiki e moderação do Fórum no config/sync (aculta_user, fase de papéis).
 *
 * Decisões do responsável (2026-10-13), registradas em docs/portal/ACULTA-USER-PLAN.md, seções E4 e E5:
 * - content_editor recebe o conjunto de permissões de wiki_editor (Drupal não herda papéis);
 * - content_editor recebe administer comments e edit any forum content;
 * - content_editor NÃO recebe administer forums (continua com administrador);
 * - wiki_editor recebe revert wiki_entry revisions (reversão de revisão é do wiki_editor).
 *
 * O papel wiki_contributor para quem tem email_confirmed NÃO é verificado aqui: Drupal não tem mapeamento
 * automático entre papéis, e a forma de conceder esse papel ainda depende de decisão (ver o relatório U0).
 *
 * Só lê config/sync. Não importa configuração nem consulta o Runtime.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$errors = [];

$load = static function (string $role) use ($root): array {
  $file = $root . '/config/sync/user.role.' . $role . '.yml';
  if (!is_file($file)) {
    return [];
  }
  $data = Yaml::parseFile($file);
  return is_array($data['permissions'] ?? NULL) ? $data['permissions'] : [];
};

$content_editor = $load('content_editor');
$wiki_editor = $load('wiki_editor');

if ($wiki_editor === []) {
  $errors[] = 'user.role.wiki_editor.yml ausente ou sem permissões';
}
if ($content_editor === []) {
  $errors[] = 'user.role.content_editor.yml ausente ou sem permissões';
}

foreach ($wiki_editor as $permission) {
  if (!in_array($permission, $content_editor, TRUE)) {
    $errors[] = "content_editor sem a permissão de wiki_editor: '$permission'";
  }
}

foreach (['administer comments', 'edit any forum content'] as $permission) {
  if (!in_array($permission, $content_editor, TRUE)) {
    $errors[] = "content_editor sem '$permission'";
  }
}

if (in_array('administer forums', $content_editor, TRUE)) {
  $errors[] = "content_editor não pode ter 'administer forums' (fica com o administrador)";
}

if (!in_array('revert wiki_entry revisions', $wiki_editor, TRUE)) {
  $errors[] = "wiki_editor sem 'revert wiki_entry revisions' (reversão de revisão é do wiki_editor)";
}

if ($errors !== []) {
  fwrite(STDERR, "ACULTA USER ROLES FAIL:\n- " . implode("\n- ", $errors) . "\n");
  exit(1);
}

echo 'ACULTA USER ROLES: PASS (' . count($wiki_editor) . ' permissões de wiki_editor replicadas em content_editor; reversão no wiki_editor)' . PHP_EOL;
