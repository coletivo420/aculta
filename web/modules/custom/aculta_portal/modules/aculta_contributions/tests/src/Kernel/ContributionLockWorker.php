<?php

/**
 * @file
 * Holds a database lock in an independent process for Kernel concurrency tests.
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\Core\Lock\DatabaseLockBackend;

$projectRoot = $argv[1] ?? '';
$lockName = $argv[2] ?? '';
if ($projectRoot === '' || $lockName === '') {
  exit(2);
}

$classLoader = require $projectRoot . '/vendor/autoload.php';
$classLoader->addPsr4(
  'Drupal\\mysql\\Driver\\Database\\mysql\\',
  $projectRoot . '/web/core/modules/mysql/src/Driver/Database/mysql',
);

$encodedConnectionInfo = fgets(STDIN);
$connectionInfo = is_string($encodedConnectionInfo)
  ? base64_decode(trim($encodedConnectionInfo), TRUE)
  : FALSE;
if (!is_string($connectionInfo)) {
  exit(3);
}

$databaseInfo = unserialize($connectionInfo, ['allowed_classes' => FALSE]);
if (!is_array($databaseInfo)) {
  exit(4);
}

Database::addConnectionInfo('default', 'default', $databaseInfo);
$lock = new DatabaseLockBackend(Database::getConnection('default', 'default'));
if (!$lock->acquire($lockName, 30.0)) {
  fwrite(STDOUT, 'N');
  fflush(STDOUT);
  exit(5);
}

fwrite(STDOUT, 'L');
fflush(STDOUT);
$release = fgets(STDIN);
$lock->releaseAll();
exit($release === "R\n" ? 0 : 6);
