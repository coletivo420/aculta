<?php

declare(strict_types=1);

/**
 * @file
 * Read-only preflight for the Private Message provider handover.
 *
 * Run in a bootstrapped clone: vendor/bin/drush scr scripts/private_message_preflight.php
 * This script never writes to the database.
 */

if (!class_exists(\Drupal::class)) {
  fwrite(STDERR, "A Drupal bootstrap is required. Run through Drush scr.\n");
  exit(2);
}

$database = \Drupal::database();
$schema = $database->schema();
$entityTypes = \Drupal::entityTypeManager();
$installed = \Drupal::service('entity.last_installed_schema.repository');
$moduleHandler = \Drupal::moduleHandler();
$configStorage = \Drupal::service('config.storage');
$ids = ['private_message', 'private_message_thread', 'private_message_ban'];
$tables = [
  'private_messages',
  'private_message_threads',
  'private_message_ban',
  'private_message_thread__members',
  'private_message_thread__private_messages',
  'pm_thread_history',
  'aculta_pm_thread_history',
];
$report = [
  'purpose' => 'READ_ONLY_PRIVATE_MESSAGE_HANDOVER_PREFLIGHT',
  'modules' => [
    'private_message' => $moduleHandler->moduleExists('private_message'),
    'aculta_messenger' => $moduleHandler->moduleExists('aculta_messenger'),
    'private_message_notify' => $moduleHandler->moduleExists('private_message_notify'),
  ],
  'entities' => [],
  'tables' => [],
  'config_dependencies' => [],
  'legacy_mail_contains_body_token' => FALSE,
  'errors' => [],
];

foreach ($ids as $id) {
  try {
    $current = $entityTypes->getDefinition($id, FALSE);
    $previous = $installed->getLastInstalledDefinition($id);
    $record = [
      'current_provider' => $current?->getProvider(),
      'installed_provider' => $previous?->getProvider(),
      'current_base_table' => $current?->getBaseTable(),
      'installed_base_table' => $previous?->getBaseTable(),
      'field_providers' => [],
    ];
    if ($previous !== NULL) {
      foreach ($installed->getLastInstalledFieldStorageDefinitions($id) as $name => $definition) {
        $record['field_providers'][$name] = $definition->getProvider();
      }
      ksort($record['field_providers']);
    }
    $report['entities'][$id] = $record;
  }
  catch (\Throwable $exception) {
    $report['errors'][] = sprintf('entity:%s:%s', $id, $exception::class);
  }
}

foreach ($tables as $table) {
  try {
    $exists = $schema->tableExists($table);
    $record = ['exists' => $exists, 'rows' => NULL, 'distinct_uuids' => NULL];
    if ($exists) {
      $statementRows = $database->select($table, 't')->countQuery()->execute();
      if ($statementRows === NULL) {
        throw new \RuntimeException('consulta de contagem sem resultado');
      }
      $rowsValue = $statementRows->fetchField();
      $record['rows'] = is_numeric($rowsValue) ? (int) $rowsValue : 0;
      if ($schema->fieldExists($table, 'uuid')) {
        $query = $database->select($table, 't');
        $query->addExpression('COUNT(DISTINCT t.uuid)', 'distinct_uuids');
        $statementUuids = $query->execute();
        if ($statementUuids === NULL) {
          throw new \RuntimeException('consulta de uuids sem resultado');
        }
        $uuidsValue = $statementUuids->fetchField();
        $record['distinct_uuids'] = is_numeric($uuidsValue) ? (int) $uuidsValue : 0;
      }
    }
    $report['tables'][$table] = $record;
  }
  catch (\Throwable $exception) {
    $report['errors'][] = sprintf('table:%s:%s', $table, $exception::class);
  }
}

try {
  foreach ($configStorage->listAll() as $name) {
    if (!is_string($name)) {
      continue;
    }
    $config = $configStorage->read($name);
    if (!is_array($config)) {
      continue;
    }
    $dependencias = $config['dependencies'] ?? [];
    $modulos = is_array($dependencias) ? ($dependencias['module'] ?? []) : [];
    if (in_array('private_message', (array) $modulos, TRUE)) {
      $report['config_dependencies'][] = $name;
    }
  }
  sort($report['config_dependencies']);
  $legacyMail = $configStorage->read('private_message.mail');
  if (is_array($legacyMail)) {
    $report['legacy_mail_contains_body_token'] = str_contains(
      json_encode($legacyMail, JSON_THROW_ON_ERROR),
      '[private_message:message]',
    );
  }
}
catch (\Throwable $exception) {
  $report['errors'][] = sprintf('config:%s', $exception::class);
}

print json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if ($report['errors'] !== []) {
  exit(1);
}
