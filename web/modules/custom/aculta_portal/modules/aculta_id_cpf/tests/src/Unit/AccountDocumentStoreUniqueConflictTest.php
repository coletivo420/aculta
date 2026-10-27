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
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ensures concurrent fingerprint conflicts never expose database details.
 */
#[Group('aculta_id_cpf')]
final class AccountDocumentStoreUniqueConflictTest extends UnitTestCase {

  /**
   * Verifies collision handling does not retain the raw database exception.
   */
  public function testUniqueIndexRaceUsesGenericExceptionWithoutDatabaseCause(): void {
    $fingerprint = str_repeat('f', 64);
    $integrityException = new IntegrityConstraintViolationException(
      "Duplicate entry '$fingerprint' for key 'aculta_id_cpf_documento_unique'",
    );
    $storageException = new EntityStorageException('The profile could not be saved.', 0, $integrityException);

    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(TRUE);
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('get')->with('field_documento')->willReturn($field);
    $profile->method('set')->willReturnSelf();
    $profile->method('save')->willThrowException($storageException);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturnOnConsecutiveCalls([], []);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('create')->willReturn($profile);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('profile')->willReturn($storage);

    $fieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $fieldManager->method('getFieldDefinitions')
      ->with('profile', 'associado')
      ->willReturn([
        'field_documento' => new \stdClass(),
        'field_documento_indice' => new \stdClass(),
      ]);

    $encryptionKey = $this->createMock(KeyInterface::class);
    $encryptionKey->method('getKeyValue')->willReturn(str_repeat('ab', 32));
    $fingerprintKey = $this->createMock(KeyInterface::class);
    $fingerprintKey->method('getKeyValue')->willReturn(str_repeat('cd', 32));
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->method('getKey')->willReturnCallback(static fn(string $id): ?KeyInterface => match ($id) {
      'aculta_document_encryption_key' => $encryptionKey,
      'aculta_id_cpf_fingerprint' => $fingerprintKey,
      default => NULL,
    });

    $validator = new DocumentValidator(new DocumentNormalizer(), new CpfValidator(), new CnpjValidator());
    $store = new AccountDocumentStore(
      $entities,
      $fieldManager,
      $validator,
      new DocumentFormatter($validator),
      new DocumentCipher($keys),
      new DocumentFingerprint($validator, $keys),
    );
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');

    try {
      $store->save($account, '52998224725');
      self::fail('Expected the unique fingerprint conflict to be translated.');
    }
    catch (\LogicException $exception) {
      self::assertSame('Document already registered.', $exception->getMessage());
      self::assertNull($exception->getPrevious());
      self::assertStringNotContainsString($fingerprint, $exception->getMessage());
    }
  }

}
