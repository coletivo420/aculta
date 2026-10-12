<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_id_cpf\Unit;

use Drupal\aculta_id_cpf\Enum\DocumentType;
use Drupal\aculta_id_cpf\Service\CnpjValidator;
use Drupal\aculta_id_cpf\Service\CpfValidator;
use Drupal\aculta_id_cpf\Service\DocumentCipher;
use Drupal\aculta_id_cpf\Service\DocumentFormatter;
use Drupal\aculta_id_cpf\Service\DocumentNormalizer;
use Drupal\aculta_id_cpf\Service\DocumentValidator;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exibição mascarada, detecção de tipo e cifra do documento armazenado.
 */
#[Group('aculta_id_cpf')]
final class DocumentStorageTest extends TestCase {

  private function validator(): DocumentValidator {
    return new DocumentValidator(new DocumentNormalizer(), new CpfValidator(), new CnpjValidator());
  }

  private function keyRepository(?string $hex): KeyRepositoryInterface {
    $repository = $this->createMock(KeyRepositoryInterface::class);
    if ($hex === NULL) {
      $repository->method('getKey')->willReturn(NULL);
      return $repository;
    }
    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn($hex);
    $repository->method('getKey')->willReturn($key);
    return $repository;
  }

  public function testPartialShowsFiveCharactersAndKeepsPunctuation(): void {
    $formatter = new DocumentFormatter($this->validator());
    $this->assertSame('529.98*.***-**', $formatter->partial('52998224725', DocumentType::CPF));
    $this->assertSame('11.222.***/****-**', $formatter->partial('11222333000181', DocumentType::CNPJ));
  }

  public function testDetectsTypeFromPunctuatedOrPlainInput(): void {
    $validator = $this->validator();
    $this->assertSame(DocumentType::CPF, $validator->detectType('529.982.247-25'));
    $this->assertSame(DocumentType::CPF, $validator->detectType('52998224725'));
    $this->assertSame(DocumentType::CNPJ, $validator->detectType('11.222.333/0001-81'));
    $this->assertSame(DocumentType::CNPJ, $validator->detectType('11222333000181'));
  }

  public static function malformedInputs(): array {
    return [
      'pontuação parcial de CPF' => ['529.98224725'],
      'barra no CPF' => ['529.982.247/25'],
      'comprimento desconhecido' => ['1234567890'],
      'comprimento de 14 dígitos com letras' => ['5299822472500X'],
    ];
  }

  #[DataProvider('malformedInputs')]
  public function testRejectsMalformedInput(string $input): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->validator()->detectType($input);
  }

  public function testCipherRoundTripAndTamperDetection(): void {
    $hex = str_repeat('ab', 32);
    $cipher = new DocumentCipher($this->keyRepository($hex));
    $this->assertTrue($cipher->isAvailable());

    $stored = $cipher->encrypt('52998224725');
    $this->assertStringNotContainsString('52998224725', $stored);
    $this->assertSame('52998224725', $cipher->decrypt($stored));

    $tampered = substr($stored, 0, -2) . (str_ends_with($stored, 'A') ? 'B' : 'A');
    $this->expectException(\RuntimeException::class);
    $cipher->decrypt($tampered);
  }

  public function testCipherIsUnavailableWithoutAValidKey(): void {
    $this->assertFalse((new DocumentCipher($this->keyRepository(NULL)))->isAvailable());
    $this->assertFalse((new DocumentCipher($this->keyRepository('abcd')))->isAvailable());
    $this->expectException(\RuntimeException::class);
    (new DocumentCipher($this->keyRepository(NULL)))->encrypt('52998224725');
  }

}
