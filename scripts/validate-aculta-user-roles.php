<?php

/**
 * Gate: contrato de papéis editoriais (aculta_user).
 *
 * Decisão do responsável (2026-10-14): todas as funções editoriais são uma só, o papel content_editor
 * (Editor de conteúdo). Autor, editor e revisor da Wiki, e editor, autor e moderador do Fórum, são o mesmo
 * papel. A única exceção é wiki_contributor (Colaborador da Wiki420), concedido a quem tem e-mail confirmado.
 *
 * Verifica no config/sync (sem Runtime):
 * - wiki_editor não existe mais (papel, ações de papel);
 * - content_editor concentra as permissões editoriais: moderação de comentários e de fórum, revisões,
 *   Wiki e Revista;
 * - wiki_contributor continua existindo como única exceção;
 * - a política de perfil público obriga somente content_editor.
 *
 * Só lê config/sync e o código. Não importa configuração nem consulta o Runtime.
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
$contributor = $load('wiki_contributor');

if (is_file($root . '/config/sync/user.role.wiki_editor.yml')) {
  $errors[] = 'user.role.wiki_editor.yml existe: o papel editorial é único (content_editor)';
}
if ($content_editor === []) {
  $errors[] = 'user.role.content_editor.yml ausente ou sem permissões';
}
if ($contributor === []) {
  $errors[] = 'user.role.wiki_contributor.yml ausente: é a única exceção e deve existir';
}

foreach (['administer comments', 'edit any forum content', 'revert all revisions', 'create wiki_entry content', 'edit any wiki_entry content', 'use wiki_editorial transition review_to_published'] as $permission) {
  if (!in_array($permission, $content_editor, TRUE)) {
    $errors[] = "content_editor sem a permissão editorial '$permission'";
  }
}
if (in_array('administer forums', $content_editor, TRUE)) {
  $errors[] = "content_editor não pode ter 'administer forums' (fica com o administrador)";
}

$policy = $root . '/web/modules/custom/aculta_portal/modules/aculta_user/src/PublicProfilePolicy.php';
$code = is_file($policy) ? file_get_contents($policy) : '';
if (!preg_match("/PAPEIS_PUBLICO_OBRIGATORIO = \['content_editor'\];/", $code)) {
  $errors[] = "PublicProfilePolicy deve obrigar perfil público somente para 'content_editor'";
}

if ($errors !== []) {
  echo "ACULTA USER ROLES: FAIL\n";
  foreach ($errors as $e) {
    echo " - $e\n";
  }
  exit(1);
}
echo 'ACULTA USER ROLES: PASS (papel editorial único content_editor com ' . count($content_editor) . " permissões; exceção wiki_contributor)\n";
