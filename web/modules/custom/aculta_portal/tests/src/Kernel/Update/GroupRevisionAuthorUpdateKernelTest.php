<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Update;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies recovery and idempotence of the Group revision author update.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class GroupRevisionAuthorUpdateKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'entity', 'group'];

  /**
   * Tests migrating old revision authors without Core's bundle-column path.
   */
  public function testMigratesRevisionAuthorsAndRecoversPartialInstall(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $database = $this->container->get('database');
    $schema = $database->schema();
    $table = 'groups_revision';

    // Emulate the pre-update schema after a failed attempt that registered the
    // new field but did not create its column or remove the old one.
    $new_definition = $this->container->get('entity_field.manager')
      ->getBaseFieldDefinitions('group')['revision_uid'];
    $column = $new_definition->getSchema()['columns']['target_id'];
    $schema->dropField($table, 'revision_uid');
    $schema->addField($table, 'revision_user', $column);
    $schema->addIndex($table, 'revision_user', ['revision_user'], ['fields' => ['revision_user' => $column]]);

    $database->insert($table)
      ->fields([
        'id' => 19,
        'revision_id' => 27,
        'langcode' => 'en',
        'revision_user' => 42,
      ])
      ->execute();

    $legacy_definition = clone $new_definition;
    $legacy_definition->setName('revision_user');
    $legacy_definition->setTargetBundle(NULL);
    $repository = $this->container->get('entity.last_installed_schema.repository');
    $repository->setLastInstalledFieldStorageDefinition($legacy_definition);

    $install_file = dirname(__DIR__, 4) . '/aculta_portal.install';
    require_once $install_file;
    self::assertStringContainsString(
      'migrated 1 revision author value(s)',
      aculta_portal_update_11010(),
    );

    self::assertFalse($schema->fieldExists($table, 'revision_user'));
    self::assertTrue($schema->fieldExists($table, 'revision_uid'));
    self::assertSame(42, (int) $database->select($table, 'r')
      ->fields('r', ['revision_uid'])
      ->condition('revision_id', 27)
      ->execute()
      ->fetchField());
    self::assertNull($this->container->get('entity.definition_update_manager')
      ->getFieldStorageDefinition('revision_user', 'group'));
    self::assertNotNull($this->container->get('entity.definition_update_manager')
      ->getFieldStorageDefinition('revision_uid', 'group'));

    self::assertStringContainsString(
      'migrated 0 revision author value(s)',
      aculta_portal_update_11010(),
    );
  }

}
