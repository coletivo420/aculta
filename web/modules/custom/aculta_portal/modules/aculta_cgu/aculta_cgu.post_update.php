<?php

/**
 * @file
 * Post-update hooks for ACULTA CGU.
 */

/**
 * Moves flood settings to the consolidated CGU configuration owner.
 */
function aculta_cgu_post_update_move_flood_settings_to_cgu(): string {
  $config_factory = \Drupal::configFactory();
  $legacy = $config_factory->get('aculta_contributions.settings');
  if ($legacy->isNew()) {
    return 'No legacy contribution settings were present.';
  }

  $settings = $config_factory->getEditable('aculta_cgu.settings');
  foreach (['flood_limit', 'flood_window'] as $key) {
    $value = $legacy->get($key);
    if ($value !== NULL) {
      $settings->set($key, $value);
    }
  }
  $settings->save();
  $config_factory->getEditable('aculta_contributions.settings')->delete();

  return 'Moved flood settings to aculta_cgu.settings and removed the old config object.';
}

/**
 * Migra as denúncias de aculta_ugc_report para a entidade aculta_cgu_report.
 *
 * Instala a tabela nova, copia as linhas preservando os ids e remove a tabela
 * e as definições antigas. Idempotente: não copia se a tabela nova já tiver
 * linhas.
 */
function aculta_cgu_post_update_migrate_report_entity_type(): string {
  $db = \Drupal::database();
  $schema = $db->schema();
  $old = 'aculta_ugc_report';
  $new = 'aculta_cgu_report';

  if (!$schema->tableExists($old)) {
    return 'Tabela legada aculta_ugc_report inexistente; nada a migrar.';
  }

  if (!$schema->tableExists($new)) {
    \Drupal::entityDefinitionUpdateManager()
      ->installEntityType(\Drupal::entityTypeManager()->getDefinition($new));
  }

  $copied = 0;
  $newCount = (int) $db->select($new)->countQuery()->execute()->fetchField();
  $rows = $db->select($old, 'o')->fields('o')->execute()
    ->fetchAll(\Drupal\Core\Database\Statement\FetchAs::Associative);
  if ($newCount > 0 && $rows !== []) {
    return "A tabela aculta_cgu_report já tem {$newCount} linhas e aculta_ugc_report ainda tem " . count($rows) . ' linhas. Nada foi apagado; revise a duplicidade antes de migrar.';
  }
  if ($newCount === 0) {
    $columns = [];
    foreach (array_keys($rows[0] ?? []) as $column) {
      if ($schema->fieldExists($new, $column)) {
        $columns[] = $column;
      }
    }
    foreach ($rows as $row) {
      $values = array_intersect_key($row, array_flip($columns));
      $db->insert($new)->fields($values)->execute();
      $copied++;
    }
  }

  $schema->dropTable($old);
  foreach (['entity.definitions.installed', 'entity.storage_schema.sql'] as $collection) {
    $store = \Drupal::keyValue($collection);
    $stale = array_values(array_filter(
      array_keys($store->getAll()),
      static fn (string $key): bool => str_starts_with($key, $old . '.'),
    ));
    if ($stale !== []) {
      $store->deleteMultiple($stale);
    }
  }

  return "Migradas {$copied} denúncias para aculta_cgu_report e removida a tabela aculta_ugc_report.";
}

/**
 * Renomeia a permissão e o papel de moderação de denúncias para o padrão CGU.
 *
 * Concede 'moderar denuncias cgu' aos papéis que tinham 'moderar denuncias ugc',
 * move os usuários de 'moderador_ugc' para 'moderador_cgu' e remove o papel antigo.
 */
function aculta_cgu_post_update_migrate_moderator_role(): string {
  $oldPermission = 'moderar denuncias ugc';
  $newPermission = 'moderar denuncias cgu';
  $oldRole = 'moderador_ugc';
  $newRole = 'moderador_cgu';

  $roleStorage = \Drupal::entityTypeManager()->getStorage('user_role');
  $permissionsMoved = 0;
  foreach ($roleStorage->loadMultiple() as $role) {
    // Papéis de administrador têm hasPermission() verdadeiro para tudo; só
    // alteramos os que guardam a permissão antiga de fato.
    if ($role->isAdmin()) {
      continue;
    }
    if (in_array($oldPermission, $role->getPermissions(), TRUE)) {
      $role->revokePermission($oldPermission)->grantPermission($newPermission)->save();
      $permissionsMoved++;
    }
  }

  $usersMoved = 0;
  $legacy = $roleStorage->load($oldRole);
  if ($legacy !== NULL) {
    $target = $roleStorage->load($newRole) ?? $roleStorage->create(['id' => $newRole, 'label' => 'Moderador CGU']);
    foreach ($legacy->getPermissions() as $permission) {
      $target->grantPermission($permission);
    }
    $target->save();

    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['roles' => $oldRole]);
    foreach ($users as $user) {
      $user->removeRole($oldRole)->addRole($newRole)->save();
      $usersMoved++;
    }
    $legacy->delete();
  }

  return "Permissão movida em {$permissionsMoved} papéis; {$usersMoved} usuários migrados de {$oldRole} para {$newRole}.";
}
