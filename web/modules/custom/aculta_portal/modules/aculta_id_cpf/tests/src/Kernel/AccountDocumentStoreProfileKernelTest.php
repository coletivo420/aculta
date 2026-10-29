<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_id_cpf\Kernel;

use Drupal\aculta_id_cpf\Service\AccountDocumentStore;
use Drupal\aculta_id_cpf\Service\DocumentCipher;
use Drupal\aculta_id_cpf\Service\DocumentFingerprint;
use Drupal\aculta_id_cpf\Service\DocumentFormatter;
use Drupal\aculta_id_cpf\Service\DocumentNormalizer;
use Drupal\aculta_id_cpf\Service\DocumentValidator;
use Drupal\aculta_id_cpf\Service\CnpjValidator;
use Drupal\aculta_id_cpf\Service\CpfValidator;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies document storage against real Profile entities and field storage.
 */
#[Group('aculta_id_cpf')]
#[RunTestsInSeparateProcesses]
final class AccountDocumentStoreProfileKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'profile',
    'key',
    'aculta_id_cpf',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installConfig(['system', 'user', 'field']);

    ProfileType::create([
      'id' => 'associado',
      'label' => 'Associado',
      'display_label' => 'Perfil de associado',
      'multiple' => TRUE,
    ])->save();
    foreach (['field_documento', 'field_documento_indice'] as $field_name) {
      FieldStorageConfig::create([
        'entity_type' => 'profile',
        'field_name' => $field_name,
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'entity_type' => 'profile',
        'bundle' => 'associado',
        'field_name' => $field_name,
        'label' => $field_name,
      ])->save();
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Persists a document once and reads its mask from an unpublished profile.
   */
  public function testSaveMasksDocumentAndRejectsDuplicateOwner(): void {
    $store = $this->store();
    self::assertTrue($store->canSave());
    $first = User::create(['name' => 'cpf-kernel-one', 'status' => 1]);
    $first->save();
    $canonical = $store->canonicalize('529.982.247-25');
    self::assertSame('52998224725', $canonical);
    $store->save($first, $canonical);

    $profiles = $this->container->get('entity_type.manager')->getStorage('profile');
    $profile = $profiles->loadUnchanged($this->profileId((int) $first->id()));
    self::assertNotSame($canonical, $profile->get('field_documento')->value);
    self::assertSame(64, strlen($profile->get('field_documento_indice')->value));
    $profile->setUnpublished();
    $profile->save();
    self::assertSame('529.98*.***-**', $store->savedDocument($first));

    $second = User::create(['name' => 'cpf-kernel-two', 'status' => 1]);
    $second->save();
    try {
      $store->save($second, $canonical);
      self::fail('A second owner must not save the same document.');
    }
    catch (\LogicException $exception) {
      self::assertSame('Document already registered.', $exception->getMessage());
    }
    self::assertNull($store->savedDocument($second));
  }

  /**
   * Builds the real store with deterministic test-only Key implementations.
   */
  private function store(): AccountDocumentStore {
    $validator = new DocumentValidator(
      new DocumentNormalizer(),
      new CpfValidator(),
      new CnpjValidator(),
    );
    $keyValues = [
      'aculta_document_encryption_key' => str_repeat('a', 64),
      DocumentFingerprint::KEY_ID => str_repeat('test-fingerprint-key-', 2),
    ];
    $keys = [];
    foreach ($keyValues as $id => $value) {
      $keys[$id] = $this->createMock(KeyInterface::class);
      $keys[$id]->method('getKeyValue')->willReturn($value);
    }
    $repository = $this->createMock(KeyRepositoryInterface::class);
    $repository->method('getKey')->willReturnCallback(
      static fn(string $id): ?KeyInterface => $keys[$id] ?? NULL,
    );
    $cipher = new DocumentCipher($repository);
    $fingerprint = new DocumentFingerprint($validator, $repository);
    return new AccountDocumentStore(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $validator,
      new DocumentFormatter($validator),
      $cipher,
      $fingerprint,
    );
  }

  /**
   * Finds the Profile ID for a user without access filtering.
   */
  private function profileId(int $uid): int {
    $ids = $this->container->get('entity_type.manager')->getStorage('profile')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('type', 'associado')
      ->execute();
    return (int) reset($ids);
  }

}
