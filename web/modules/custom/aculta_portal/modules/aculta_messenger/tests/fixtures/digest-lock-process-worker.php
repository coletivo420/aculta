<?php

/**
 * @file
 * Exercises the persistent database lock from an independent Drupal process.
 */

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\Core\Lock\PersistentDatabaseLockBackend;

if ($argc !== 7) {
  fwrite(STDERR, "Invalid digest lock worker arguments.\n");
  exit(2);
}

[, $autoload, $configurationPath, $action, $lockName, $heldPath, $releasePath] = $argv;
$classLoader = require $autoload;
$drupalRoot = getcwd();
$classLoader->addPsr4('Drupal\\mysql\\', $drupalRoot . '/core/modules/mysql/src');

try {
  $configuration = json_decode((string) file_get_contents($configurationPath), TRUE, 512, JSON_THROW_ON_ERROR);
  Database::addConnectionInfo('digest_lock_test', 'default', $configuration['connection']);
  $database = Database::getConnection('default', 'digest_lock_test');
  $lock = new PersistentDatabaseLockBackend($database);
  $acquired = $lock->acquire($lockName, 30.0);

  if ($action === 'hold') {
    if (!$acquired) {
      echo json_encode(['acquired' => FALSE, 'released' => FALSE], JSON_THROW_ON_ERROR);
      exit(0);
    }
    if (file_put_contents($heldPath, 'held', LOCK_EX) === FALSE) {
      throw new RuntimeException('Could not signal digest lock acquisition.');
    }
    $deadline = microtime(TRUE) + 20;
    while (!is_file($releasePath)) {
      if (microtime(TRUE) >= $deadline) {
        throw new RuntimeException('Timed out waiting for digest lock release signal.');
      }
      usleep(10_000);
    }
    $lock->release($lockName);
    echo json_encode(['acquired' => TRUE, 'released' => TRUE], JSON_THROW_ON_ERROR);
    exit(0);
  }

  if ($action !== 'try') {
    throw new InvalidArgumentException('Unknown digest lock worker action.');
  }
  if ($acquired) {
    $lock->release($lockName);
  }
  echo json_encode(['acquired' => $acquired], JSON_THROW_ON_ERROR);
}
catch (Throwable $exception) {
  fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . "\n");
  exit(1);
}
