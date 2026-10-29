<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_id_cpf\Unit;

use Drupal\aculta_id_cpf\Service\AccountDocumentStore;
use Drupal\aculta_id_cpf\Service\CnpjValidator;
use Drupal\aculta_id_cpf\Service\CpfValidator;
use Drupal\aculta_id_cpf\Service\DocumentCipher;
use Drupal\aculta_id_cpf\Service\DocumentFingerprint;
use Drupal\aculta_id_cpf\Service\DocumentFormatter;
use Drupal\aculta_id_cpf\Service\DocumentNormalizer;
use Drupal\aculta_id_cpf\Service\DocumentValidator;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Ensures account documents are unavailable until their required fields exist.
 */
#[Group('aculta_id_cpf')]
final class AccountDocumentStoreAvailabilityTest extends TestCase {

  /**
   * Allows reading without optional fingerprint infrastructure.
   */
  public function testReadableWhenFingerprintFieldAndKeyAreMissing(): void {
    $store = $this->store(['field_documento' => new \stdClass()], FALSE);

    $this->assertTrue($store->isAvailable());
    $this->assertFalse($store->canSave());
  }

  /**
   * Disables access when the document field is missing.
   */
  public function testUnavailableForReadingWhenDocumentFieldIsMissing(): void {
    $store = $this->store(['field_documento_indice' => new \stdClass()]);

    $this->assertFalse($store->isAvailable());
    $this->assertFalse($store->canSave());
  }

  /**
   * Enables reading and saving when required dependencies exist.
   */
  public function testAvailableWhenRequiredFieldsAndKeysExist(): void {
    $store = $this->store([
      'field_documento' => new \stdClass(),
      'field_documento_indice' => new \stdClass(),
    ], TRUE);

    $this->assertTrue($store->isAvailable());
    $this->assertTrue($store->canSave());
  }

  /**
   * Creates the store with the requested fields and key availability.
   *
   * @param array<string, object> $fields
   *   Configurable profile field definitions.
   * @param bool $fingerprint_key_available
   *   Whether the fingerprint key is available.
   */
  private function store(array $fields, bool $fingerprint_key_available = FALSE): AccountDocumentStore {
    $field_manager = $this->createMock(EntityFieldManagerInterface::class);
    $field_manager->method('getFieldDefinitions')
      ->with('profile', 'associado')
      ->willReturn($fields);

    $encryption_key = $this->createMock(KeyInterface::class);
    $encryption_key->method('getKeyValue')->willReturn(str_repeat('ab', 32));
    $fingerprint_key = $fingerprint_key_available ? $this->createMock(KeyInterface::class) : NULL;
    $fingerprint_key?->method('getKeyValue')->willReturn(str_repeat('cd', 32));
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->method('getKey')->willReturnCallback(static fn(string $id): ?KeyInterface => match ($id) {
      'aculta_document_encryption_key' => $encryption_key,
      'aculta_id_cpf_fingerprint' => $fingerprint_key,
      default => NULL,
    });

    $validator = new DocumentValidator(new DocumentNormalizer(), new CpfValidator(), new CnpjValidator());
    $cipher = new DocumentCipher($keys);
    $fingerprint = new DocumentFingerprint($validator, $keys);

    return new AccountDocumentStore(
      $this->createMock(EntityTypeManagerInterface::class),
      $field_manager,
      $validator,
      new DocumentFormatter($validator),
      $cipher,
      $fingerprint,
    );
  }

}
