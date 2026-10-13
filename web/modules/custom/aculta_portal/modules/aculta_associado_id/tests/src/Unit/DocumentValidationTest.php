<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_associado_id\Unit;

use Drupal\aculta_associado_id\Enum\DocumentType;
use Drupal\aculta_associado_id\Service\CnpjValidator;
use Drupal\aculta_associado_id\Service\CpfValidator;
use Drupal\aculta_associado_id\Service\DocumentFormatter;
use Drupal\aculta_associado_id\Service\DocumentFingerprint;
use Drupal\aculta_associado_id\Service\DocumentNormalizer;
use Drupal\aculta_associado_id\Service\DocumentValidator;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Independent public vectors and malformed input cases.
 */
#[Group('aculta_associado_id')]
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

  public function testFingerprintIsDeterministicAndNamespaced(): void {
    $key = $this->createMock(KeyInterface::class);
    $key->expects($this->exactly(3))
      ->method('getKeyValue')
      ->with(TRUE)
      ->willReturn(str_repeat('k', 32));
    $repository = $this->createMock(KeyRepositoryInterface::class);
    $repository->expects($this->exactly(3))
      ->method('getKey')
      ->with(DocumentFingerprint::KEY_ID)
      ->willReturn($key);

    $fingerprint = new DocumentFingerprint($this->validator(), $repository);
    $cpf = $fingerprint->fingerprint('529.982.247-25', DocumentType::CPF);
    $this->assertSame($cpf, $fingerprint->fingerprint('52998224725', DocumentType::CPF));
    $this->assertSame(
      hash_hmac('sha256', "aculta_id_cpf:v1\0cpf\0" . '52998224725', str_repeat('k', 32)),
      $cpf,
    );
    $cnpj = $fingerprint->fingerprint('11.222.333/0001-81', DocumentType::CNPJ);
    $this->assertNotSame($cpf, $cnpj);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $cpf);
  }

  public function testFingerprintFailsClosedWithoutStrongKey(): void {
    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->with(TRUE)->willReturn(str_repeat('k', 31));
    $repository = $this->createMock(KeyRepositoryInterface::class);
    $repository->method('getKey')->with(DocumentFingerprint::KEY_ID)->willReturn($key);

    $this->expectException(\RuntimeException::class);
    (new DocumentFingerprint($this->validator(), $repository))
      ->fingerprint('52998224725', DocumentType::CPF);
  }

}
