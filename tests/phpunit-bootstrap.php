<?php

declare(strict_types=1);

// The path is relative to the project checkout so PHPUnit does not depend on
// an operator-created directory or on the current working directory.
$browserOutput = dirname(__DIR__) . '/web/sites/simpletest/browser_output';
if (!is_dir($browserOutput) && !mkdir($browserOutput, 0775, TRUE) && !is_dir($browserOutput)) {
  throw new RuntimeException(sprintf('Não foi possível criar a saída PHPUnit: %s', $browserOutput));
}

require dirname(__DIR__) . '/web/core/tests/bootstrap.php';
