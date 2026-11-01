<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_id_cpf\Kernel;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the database uniqueness guard for active document fingerprints.
 */
#[RunTestsInSeparateProcesses]
final class DocumentFingerprintUniqueIndexKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('database')->schema()->createTable('profile__field_documento_indice', [
      'fields' => [
        'entity_id' => ['type' => 'int', 'not null' => TRUE],
        'deleted' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'bundle' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE, 'default' => 'associado'],
        'delta' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'langcode' => ['type' => 'varchar', 'length' => 12, 'not null' => TRUE, 'default' => 'pt-br'],
        'field_documento_indice_value' => ['type' => 'varchar', 'length' => 64, 'not null' => FALSE],
      ],
      'primary key' => ['entity_id', 'deleted', 'delta', 'langcode'],
    ]);
    $this->container->get('database')->schema()->createTable('profile__field_documento', [
      'fields' => [
        'entity_id' => ['type' => 'int', 'not null' => TRUE],
        'deleted' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'bundle' => ['type' => 'varchar', 'length' => 128, 'not null' => TRUE, 'default' => 'associado'],
        'delta' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'langcode' => ['type' => 'varchar', 'length' => 12, 'not null' => TRUE, 'default' => 'pt-br'],
        'field_documento_value' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
      ],
      'primary key' => ['entity_id', 'deleted', 'delta', 'langcode'],
    ]);
    require_once dirname(__DIR__, 3) . '/aculta_id_cpf.install';
  }

  /**
   * Verifies that the constraint blocks equal fingerprints on different rows.
   */
  public function testUniqueConstraintBlocksDuplicateFingerprints(): void {
    $database = $this->container->get('database');
    foreach ([90, 91] as $entityId) {
      $database->insert('profile__field_documento_indice')
        ->fields(['entity_id' => $entityId, 'deleted' => 0, 'field_documento_indice_value' => NULL])
        ->execute();
    }
    self::assertSame(
      'Unique document fingerprint constraint created.',
      aculta_id_cpf_update_10002(),
    );
    self::assertSame(
      'Unique document fingerprint constraint created.',
      aculta_id_cpf_update_10002(),
    );
    $database->insert('profile__field_documento_indice')
      ->fields(['entity_id' => 1, 'deleted' => 0, 'field_documento_indice_value' => str_repeat('a', 64)])
      ->execute();

    try {
      $database->insert('profile__field_documento_indice')
        ->fields(['entity_id' => 2, 'deleted' => 0, 'field_documento_indice_value' => str_repeat('a', 64)])
        ->execute();
      self::fail('The database accepted a duplicate document fingerprint.');
    }
    catch (IntegrityConstraintViolationException) {
      self::assertTrue($database->schema()->indexExists(
        'profile__field_documento_indice',
        'aculta_id_cpf_documento_unique',
      ));
    }
  }

  /**
   * Verifies that existing duplicate groups stop migration without values.
   */
  public function testMigrationRefusesExistingDuplicatesWithoutFingerprint(): void {
    $database = $this->container->get('database');
    foreach ([1, 2] as $entityId) {
      $database->insert('profile__field_documento_indice')
        ->fields(['entity_id' => $entityId, 'deleted' => 0, 'field_documento_indice_value' => str_repeat('b', 64)])
        ->execute();
    }

    try {
      aculta_id_cpf_update_10002();
      self::fail('Migration accepted existing duplicate fingerprints.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('1 duplicate group', $exception->getMessage());
      self::assertStringNotContainsString(str_repeat('b', 64), $exception->getMessage());
    }
  }

  /**
   * Verifies that documents without a fingerprint stop the uniqueness update.
   */
  public function testMigrationRefusesUnindexedAssociatedDocuments(): void {
    $database = $this->container->get('database');
    $database->insert('profile__field_documento')
      ->fields([
        'entity_id' => 7,
        'deleted' => 0,
        'bundle' => 'associado',
        'field_documento_value' => 'encrypted-test-value',
      ])
      ->execute();

    try {
      aculta_id_cpf_update_10002();
      self::fail('Migration accepted an associated document without a fingerprint.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('1 associated document(s) lack a fingerprint', $exception->getMessage());
      self::assertStringNotContainsString('encrypted-test-value', $exception->getMessage());
      self::assertFalse($database->schema()->indexExists(
        'profile__field_documento_indice',
        'aculta_id_cpf_documento_unique',
      ));
    }
  }

  /**
   * The documented rollback drops only the new index and preserves rows.
   */
  public function testRollbackDropsIndexWithoutChangingFingerprintRows(): void {
    $database = $this->container->get('database');
    $database->insert('profile__field_documento_indice')
      ->fields(['entity_id' => 20, 'deleted' => 0, 'field_documento_indice_value' => str_repeat('c', 64)])
      ->execute();

    aculta_id_cpf_update_10002();
    self::assertTrue($database->schema()->indexExists(
      'profile__field_documento_indice',
      'aculta_id_cpf_documento_unique',
    ));

    $database->schema()->dropIndex('profile__field_documento_indice', 'aculta_id_cpf_documento_unique');
    self::assertFalse($database->schema()->indexExists(
      'profile__field_documento_indice',
      'aculta_id_cpf_documento_unique',
    ));
    self::assertSame(1, (int) $database->select('profile__field_documento_indice', 'i')
      ->countQuery()
      ->execute()
      ->fetchField());
  }

}
