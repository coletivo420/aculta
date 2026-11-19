<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Kernel\Update;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers installation of Commerce payment method bundle fields.
 */
#[Group('aculta_pagamentos_mercadopago')]
#[RunTestsInSeparateProcesses]
class PaymentCardFieldsUpdateKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'key',
    'user',
    'entity',
    'field',
    'commerce',
    'commerce_order',
    'commerce_price',
    'commerce_payment',
    'entity_reference_revisions',
    'profile',
    'filter',
    'options',
    'aculta_pagamentos_mercadopago',
  ];

  /**
   * Tests missing bundle fields are installed and the update is idempotent.
   */
  public function testInstallsMissingCardFields(): void {
    $entity_type_id = 'commerce_payment_method';
    $bundle_handler = $this->container->get('entity_type.manager')->getHandler($entity_type_id, 'bundle_plugin');
    $repository = $this->container->get('entity.last_installed_schema.repository');
    $schema = $this->container->get('database')->schema();
    $required_fields = ['card_type', 'card_number', 'card_exp_month', 'card_exp_year'];

    $this->installEntitySchema($entity_type_id);
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type_id);
    $table_mapping = $storage->getTableMapping();
    $installed_definitions = $repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
    foreach ($required_fields as $field_name) {
      $definition = $bundle_handler->getFieldDefinitions('credit_card')[$field_name];
      if ($table_mapping->requiresDedicatedTableStorage($definition)) {
        $dedicated_tables = [
          $table_mapping->getDedicatedDataTableName($definition),
          $table_mapping->getDedicatedRevisionTableName($definition),
        ];
        foreach ($dedicated_tables as $table) {
          if ($schema->tableExists($table)) {
            $schema->dropTable($table);
          }
        }
      }
      else {
        $table = $table_mapping->getFieldTableName($field_name);
        foreach ($table_mapping->getColumnNames($field_name) as $column_name) {
          if ($schema->fieldExists($table, $column_name)) {
            $schema->dropField($table, $column_name);
          }
        }
      }
      if (isset($installed_definitions[$field_name])) {
        $repository->deleteLastInstalledFieldStorageDefinition($definition);
      }
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->container->get('module_handler')->loadInclude('aculta_pagamentos_mercadopago', 'install');
    $first_result = aculta_pagamentos_mercadopago_update_10002();
    $this->assertStringContainsString('Installed missing Mercado Pago payment method fields', $first_result);

    $installed_definitions = $repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type_id);
    $table_mapping = $storage->getTableMapping();
    foreach ($required_fields as $field_name) {
      $this->assertArrayHasKey($field_name, $installed_definitions);
      $table = $table_mapping->getFieldTableName($field_name);
      foreach ($table_mapping->getColumnNames($field_name) as $column_name) {
        $this->assertTrue($schema->fieldExists($table, $column_name));
      }
    }
    $this->assertSame([], $this->container->get('entity.definition_update_manager')
      ->getChangeSummary()[$entity_type_id] ?? []);

    $this->assertSame(
      'Mercado Pago payment method card fields were already installed.',
      aculta_pagamentos_mercadopago_update_10002(),
    );
  }

}
