<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Migration\ActivityLocationMigrator;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Valida a migração em lote, diagnóstico, dry-run, deduplicação e rollback de locais de Activity.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class ActivityLocationMigrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'taxonomy',
    'options',
    'link',
    'text',
    'datetime',
    'datetime_range',
    'address',
    'geofield',
    'node',
    'aculta_calendar',
    'aculta_maps',
    'aculta_events',
  ];

  private ActivityLocationMigrator $migrator;
  private User $editorUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_event_location');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);

    if (!NodeType::load('activity')) {
      NodeType::create([
        'type' => 'activity',
        'name' => 'Atividade',
      ])->save();
    }

    $this->createField('node', 'activity', 'field_place_name', 'string');
    $this->createField('node', 'activity', 'field_event_street', 'string');
    $this->createField('node', 'activity', 'field_event_postal', 'string');
    $this->createField('node', 'activity', 'field_event_city', 'string');
    $this->createField('node', 'activity', 'field_event_region', 'string');
    $this->createField('node', 'activity', 'field_event_location', 'entity_reference', [
      'target_type' => 'aculta_event_location',
    ]);

    $this->editorUser = User::create([
      'name' => 'editor_migracao',
      'mail' => 'editor@example.com',
      'status' => 1,
    ]);
    $this->editorUser->save();

    $this->migrator = $this->container->get('aculta_events.activity_location_migrator');
  }

  private function createField(string $entityType, string $bundle, string $fieldName, string $type, array $settings = []): void {
    if (!FieldStorageConfig::loadByName($entityType, $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'type' => $type,
        'settings' => $settings,
      ])->save();
    }
    if (!FieldConfig::loadByName($entityType, $bundle, $fieldName)) {
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'label' => $fieldName,
      ])->save();
    }
  }

  /**
   * Valida diagnóstico e detecção de dados parciais.
   */
  public function testInspectAndDiagnose(): void {
    $node1 = Node::create([
      'type' => 'activity',
      'title' => 'Oficina Completa',
      'uid' => $this->editorUser->id(),
      'field_place_name' => 'Centro Cultural',
      'field_event_street' => 'Rua das Flores, 100',
      'field_event_postal' => '01310-100',
      'field_event_city' => 'São Paulo',
      'field_event_region' => 'SP',
    ]);
    $node1->save();

    $node2 = Node::create([
      'type' => 'activity',
      'title' => 'Oficina Incompleta',
      'uid' => $this->editorUser->id(),
      'field_event_street' => 'Rua Sem Nome',
    ]);
    $node2->save();

    $info = $this->migrator->inspect();
    self::assertSame(2, $info['total_activities']);
    self::assertSame(0, $info['already_linked']);
    self::assertSame(2, $info['candidates_count']);
    self::assertSame(1, $info['incomplete_count']);
    self::assertSame((int) $node2->id(), $info['incomplete_list'][0]['nid']);
    self::assertContains('field_place_name', $info['incomplete_list'][0]['missing']);
    self::assertContains('field_event_postal', $info['incomplete_list'][0]['missing']);
  }

  /**
   * Valida que o dry-run não grava nada no banco.
   */
  public function testDryRunDoesNotAlterDatabase(): void {
    $node = Node::create([
      'type' => 'activity',
      'title' => 'Apresentação Musical',
      'uid' => $this->editorUser->id(),
      'field_place_name' => 'Auditório Principal',
      'field_event_street' => 'Av Central, 500',
      'field_event_postal' => '80000-000',
      'field_event_city' => 'Curitiba',
      'field_event_region' => 'PR',
    ]);
    $node->save();

    $report = $this->migrator->migrate(TRUE);
    self::assertTrue($report['dry_run']);
    self::assertSame(1, $report['candidates_total']);
    self::assertSame(1, $report['locations_created']);
    self::assertSame(1, $report['nodes_updated']);

    $locations = EventLocation::loadMultiple();
    self::assertEmpty($locations);

    $reloadedNode = Node::load($node->id());
    self::assertNotNull($reloadedNode);
    self::assertTrue($reloadedNode->get('field_event_location')->isEmpty());
  }

  /**
   * Valida migração efetiva com deduplicação de locais idênticos e posterior rollback completo.
   */
  public function testMigrationExecutionAndRollback(): void {
    $node1 = Node::create([
      'type' => 'activity',
      'title' => 'Festival Dia 1',
      'status' => 1,
      'uid' => $this->editorUser->id(),
      'field_place_name' => 'Parque das Artes',
      'field_event_street' => 'Alameda Verde, 10',
      'field_event_postal' => '04000-000',
      'field_event_city' => 'São Paulo',
      'field_event_region' => 'SP',
    ]);
    $node1->save();

    // Node 2 tem mesmo local e CEP: deve reutilizar a mesma entidade de Local.
    $node2 = Node::create([
      'type' => 'activity',
      'title' => 'Festival Dia 2',
      'status' => 0,
      'uid' => $this->editorUser->id(),
      'field_place_name' => 'Parque das Artes',
      'field_event_street' => 'Alameda Verde, 10',
      'field_event_postal' => '04000-000',
      'field_event_city' => 'São Paulo',
      'field_event_region' => 'SP',
    ]);
    $node2->save();

    // Node 3 tem local diferente.
    $node3 = Node::create([
      'type' => 'activity',
      'title' => 'Exposição de Pintura',
      'status' => 1,
      'uid' => $this->editorUser->id(),
      'field_place_name' => 'Galeria Solar',
      'field_event_street' => 'Rua do Sol, 77',
      'field_event_postal' => '30000-000',
      'field_event_city' => 'Belo Horizonte',
      'field_event_region' => 'MG',
    ]);
    $node3->save();

    // 1. Executa migração
    $report = $this->migrator->migrate(FALSE);
    self::assertFalse($report['dry_run']);
    self::assertSame(3, $report['candidates_total']);
    self::assertSame(2, $report['locations_created']);
    self::assertSame(1, $report['locations_reused']);
    self::assertSame(3, $report['nodes_updated']);

    $locations = EventLocation::loadMultiple();
    self::assertCount(2, $locations);

    $reloadedNode1 = Node::load($node1->id());
    $reloadedNode2 = Node::load($node2->id());
    $reloadedNode3 = Node::load($node3->id());

    self::assertFalse($reloadedNode1->get('field_event_location')->isEmpty());
    self::assertFalse($reloadedNode2->get('field_event_location')->isEmpty());
    self::assertFalse($reloadedNode3->get('field_event_location')->isEmpty());

    // Node 1 e 2 devem referenciar o mesmo local.
    $locId1 = (int) $reloadedNode1->get('field_event_location')->target_id;
    $locId2 = (int) $reloadedNode2->get('field_event_location')->target_id;
    $locId3 = (int) $reloadedNode3->get('field_event_location')->target_id;

    self::assertSame($locId1, $locId2);
    self::assertNotSame($locId1, $locId3);

    $loc1 = EventLocation::load($locId1);
    self::assertNotNull($loc1);
    self::assertSame('Parque das Artes', $loc1->label());
    self::assertSame('BR', $loc1->get('field_endereco')->country_code);
    self::assertSame('04000-000', $loc1->get('field_endereco')->postal_code);
    self::assertSame('publicado', $loc1->get('field_status')->value);
    self::assertSame((int) $this->editorUser->id(), (int) $loc1->get('field_organizador')->target_id);

    // Campos legados permanecem intactos nos nós
    self::assertSame('Parque das Artes', $reloadedNode1->get('field_place_name')->value);

    // 2. Executa Rollback
    $rollback = $this->migrator->rollback();
    self::assertSame(3, $rollback['nodes_unlinked']);
    self::assertSame(2, $rollback['locations_deleted']);

    // Verifica que locais foram removidos e nós desvinculados
    self::assertEmpty(EventLocation::loadMultiple());

    $cleanNode1 = Node::load($node1->id());
    self::assertTrue($cleanNode1->get('field_event_location')->isEmpty());
    // Campos legados continuam intactos mesmo após rollback!
    self::assertSame('Parque das Artes', $cleanNode1->get('field_place_name')->value);
  }

}
