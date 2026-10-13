<?php

declare(strict_types=1);

/**
 * Executes the domain services without Drupal, Composer or a database.
 */
use Drupal\aculta_associado_id\Enum\DocumentType;
use Drupal\aculta_associado_id\Service\CnpjValidator;
use Drupal\aculta_associado_id\Service\CpfValidator;
use Drupal\aculta_associado_id\Service\DocumentFormatter;
use Drupal\aculta_associado_id\Service\DocumentNormalizer;
use Drupal\aculta_associado_id\Service\DocumentValidator;

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
  $prefix = 'Drupal\\aculta_associado_id\\';
  if (str_starts_with($class, $prefix)) {
    require $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
  }
});

$validator = new DocumentValidator(new DocumentNormalizer(), new CpfValidator(), new CnpjValidator());
$formatter = new DocumentFormatter($validator);
$fixtures = require __DIR__ . '/fixtures/documents.php';
$checks = 0;
$failures = [];
$check = static function (bool $success, string $label) use (&$checks, &$failures): void {
  $checks++;
  if (!$success) {
    $failures[] = $label;
  }
};
foreach ($fixtures['valid'] as $i => [$type, $input, $canonical, $formatted]) {
  $documentType = DocumentType::from($type);
  $check($validator->canonicalize($input, $documentType) === $canonical, "canonical vector $i");
  $check($formatter->format($input, $documentType) === $formatted, "formatted vector $i");
  $redacted = $formatter->redact($input, $documentType);
  $check(preg_replace('/[^0-9]/', '', $redacted) === substr($canonical, -2), "redaction vector $i");
  // Every single-digit change to either DV must invalidate these known vectors.
  foreach ([strlen($canonical) - 2, strlen($canonical) - 1] as $position) {
    foreach (range(0, 9) as $digit) {
      if ($canonical[$position] === (string) $digit) {
        continue;
      }
      $mutated = substr_replace($canonical, (string) $digit, $position, 1);
      $check(!$validator->isValid($mutated, $documentType), "DV mutation $i/$position/$digit");
    }
  }
}
foreach ($fixtures['invalid'] as $i => [$type, $input]) {
  $check(!$validator->isValid($input, DocumentType::from($type)), "invalid vector $i");
}
foreach (range(0, 9) as $digit) {
  $check(!$validator->isValid(str_repeat((string) $digit, 11), DocumentType::CPF), "repeated CPF $digit");
}
foreach ([[], new stdClass(), NULL, 52998224725] as $i => $input) {
  try {
    $validator->canonicalize($input, DocumentType::CPF);
    $check(FALSE, "non-string input $i");
  }
  catch (TypeError) {
    $check(TRUE, "non-string input $i");
  }
}
try {
  $validator->canonicalize('52998224724', DocumentType::CPF);
  $check(FALSE, 'invalid input throws');
}
catch (InvalidArgumentException $exception) {
  $check(!str_contains($exception->getMessage(), '52998224724'), 'exception does not disclose input');
}
foreach ($failures as $failure) {
  fwrite(STDERR, "FAIL $failure\n");
}
echo count($failures) === 0 ? "PASS: $checks domain checks\n" : 'FAIL: ' . count($failures) . "/$checks checks\n";
exit(count($failures) === 0 ? 0 : 1);
