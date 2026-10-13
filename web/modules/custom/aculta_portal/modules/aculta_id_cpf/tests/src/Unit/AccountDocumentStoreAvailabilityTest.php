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

  public function testUnavailableWhenFingerprintFieldIsMissingEvenWithKeys(): void {
    $store = $this->store(['field_documento' => new \stdClass()]);

    $this->assertFalse($store->isAvailable());
  }

  public function testUnavailableWhenDocumentFieldIsMissingEvenWithKeys(): void {
    $store = $this->store(['field_documento_indice' => new \stdClass()]);

    $this->assertFalse($store->isAvailable());
  }

  public function testAvailableWhenRequiredFieldsAndKeysExist(): void {
    $store = $this->store([
      'field_documento' => new \stdClass(),
      'field_documento_indice' => new \stdClass(),
    ]);

    $this->assertTrue($store->isAvailable());
  }

  /**
   * @param array<string, object> $fields
   *   Configurable profile field definitions.
   */
  private function store(array $fields): AccountDocumentStore {
    $field_manager = $this->createMock(EntityFieldManagerInterface::class);
    $field_manager->expects($this->once())
      ->method('getFieldDefinitions')
      ->with('profile', 'participante')
      ->willReturn($fields);

    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn(str_repeat('ab', 32));
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->method('getKey')->willReturn($key);

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
