<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_id_cpf\Unit;

use Drupal\aculta_id_cpf\Enum\DocumentType;
use Drupal\aculta_id_cpf\Service\CnpjValidator;
use Drupal\aculta_id_cpf\Service\CpfValidator;
use Drupal\aculta_id_cpf\Service\DocumentFormatter;
use Drupal\aculta_id_cpf\Service\DocumentNormalizer;
use Drupal\aculta_id_cpf\Service\DocumentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Independent public vectors and malformed input cases.
 */
#[Group('aculta_id_cpf')]
final class DocumentValidationTest extends TestCase {

  private function validator(): DocumentValidator {
    return new DocumentValidator(new DocumentNormalizer(), new CpfValidator(), new CnpjValidator());
  }

  public static function validDocuments(): array {
    return (require dirname(__DIR__, 2) . '/fixtures/documents.php')['valid'];
  }

  public static function invalidDocuments(): array {
    return (require dirname(__DIR__, 2) . '/fixtures/documents.php')['invalid'];
  }

  #[DataProvider('validDocuments')]
  public function testCanonicalizationAndFormatting(string $type, string $input, string $expected, string $formatted): void {
    $documentType = DocumentType::from($type);
    $validator = $this->validator();
    $this->assertSame($expected, $validator->canonicalize($input, $documentType));
    $formatter = new DocumentFormatter($validator);
    $this->assertSame($formatted, $formatter->format($input, $documentType));
    $this->assertSame(substr($expected, -2), preg_replace('/[^0-9]/', '', $formatter->redact($input, $documentType)));
  }

  #[DataProvider('invalidDocuments')]
  public function testMalformedOrInvalidDocuments(string $type, string $input): void {
    $this->assertFalse($this->validator()->isValid($input, DocumentType::from($type)));
  }

  public function testRepeatedCpfDigitsAreRejected(): void {
    foreach (range(0, 9) as $digit) {
      $this->assertFalse($this->validator()->isValid(str_repeat((string) $digit, 11), DocumentType::CPF));
    }
  }

}
