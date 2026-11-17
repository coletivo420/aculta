<?php

/**
 * @file
 * Independent database writer used by the nickname concurrency Kernel test.
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\Core\Database\IntegrityConstraintViolationException;

/** @var array{autoload: string, core: string, connection: array{driver: string, database: string, username?: string, password?: string, host?: string, port?: int|string, prefix?: string, namespace?: string, autoload?: string, pdo?: array<mixed>, init_commands?: list<string>}, slug: string, uid: int} $payload */
$payload = json_decode((string) fgets(STDIN), TRUE, 512, JSON_THROW_ON_ERROR);
/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require $payload['autoload'];
$loader->addPsr4('Drupal\\Core\\', $payload['core'] . '/core/lib/Drupal/Core', TRUE);
$loader->addPsr4('Drupal\\Component\\', $payload['core'] . '/core/lib/Drupal/Component', TRUE);
$loader->addPsr4('Drupal\\mysql\\', $payload['core'] . '/core/modules/mysql/src', TRUE);
Database::addConnectionInfo('aculta_user_nickname_race', 'default', $payload['connection']);
$database = Database::getConnection('default', 'aculta_user_nickname_race');
fwrite(STDOUT, "READY\n");
fgets(STDIN);
$query = $database->select('aculta_user_nickname', 'n')
  ->fields('n', ['uid'])
  ->condition('slug', $payload['slug'])
  ->range(0, 1)
  ->execute();
$owner = $query ? $query->fetchField() : FALSE;
if ($owner !== FALSE) {
  fwrite(STDERR, "Slug was already claimed before the concurrency barrier.\n");
  exit(2);
}
fwrite(STDOUT, "CHECKED\n");
fgets(STDIN);

try {
  $database->insert('aculta_user_nickname')
    ->fields(['uid' => $payload['uid'], 'slug' => $payload['slug']])
    ->execute();
  fwrite(STDOUT, "CLAIMED\n");
}
catch (IntegrityConstraintViolationException) {
  fwrite(STDOUT, "CONFLICT\n");
}
