<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Cleanup;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\aculta_portal\Cleanup\FileReferenceInspector;
use Drupal\aculta_portal\Cleanup\FileReferenceStatus;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests global structured and revision-aware reference inspection.
 */
#[Group('aculta_portal')]
final class FileReferenceInspectorTest extends UnitTestCase {

  /**
   * Finds structured references in all revisions.
   */
  public function testFindsStructuredReferenceInAllRevisions(): void {
    $target = $this->target('file', '50', 'file-uuid');
    $query = $this->query([101]);
    $query->expects(self::once())->method('allRevisions');
    $query->expects(self::once())
      ->method('condition')
      ->with('field_attachment.target_id', '50')
      ->willReturnSelf();

    $inspector = $this->inspector('entity_reference', 'file', $query);

    self::assertSame(FileReferenceStatus::Referenced, $inspector->inspect($target));
  }

  /**
   * Finds formatted-text embeds and includes historical revisions.
   */
  public function testFindsFormattedTextReferenceInAllRevisions(): void {
    $target = $this->target('file', '50', 'file-uuid');
    $query = $this->query([101]);
    $query->expects(self::once())->method('allRevisions');
    $query->expects(self::once())
      ->method('condition')
      ->with('field_attachment.value', '%file-uuid%', 'LIKE')
      ->willReturnSelf();

    $inspector = $this->inspector('text_long', NULL, $query);

    self::assertSame(FileReferenceStatus::Referenced, $inspector->inspect($target));
  }

  /**
   * Fails closed when a field reference query cannot be completed.
   */
  public function testUnverifiableQueryFailure(): void {
    $target = $this->target('file', '50', 'file-uuid');
    $query = $this->query([]);
    $query->method('execute')->willThrowException(new \RuntimeException('query failed'));

    $inspector = $this->inspector('entity_reference', 'file', $query);

    self::assertSame(FileReferenceStatus::Unverifiable, $inspector->inspect($target));
  }

  /**
   * Creates the inspector with one persisted, revisionable entity type.
   */
  private function inspector(string $fieldType, ?string $targetType, QueryInterface $query): FileReferenceInspector {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('entityClassImplements')
      ->with(FieldableEntityInterface::class)
      ->willReturn(TRUE);
    $entityType->method('getBaseTable')->willReturn('node_field_data');
    $entityType->method('isRevisionable')->willReturn(TRUE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getDefinitions')->willReturn(['node' => $entityType]);
    $manager->method('getDefinition')->with('node')->willReturn($entityType);
    $manager->method('getStorage')->with('node')->willReturn($storage);

    $field = $this->createMock(FieldStorageDefinitionInterface::class);
    $field->method('getType')->willReturn($fieldType);
    $field->method('getSetting')->with('target_type')->willReturn($targetType);
    $fieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $fieldManager->method('getFieldStorageDefinitions')
      ->with('node')
      ->willReturn(['field_attachment' => $field]);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('node_field_data')->willReturn(TRUE);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('escapeLike')->willReturnArgument(0);

    return new FileReferenceInspector($manager, $fieldManager, $database);
  }

  /**
   * Creates an Entity Query double with predictable result rows.
   *
   * @param array $result
   *   The query results.
   */
  private function query(array $result): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('allRevisions')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->with(0, 1)->willReturnSelf();
    $query->method('execute')->willReturn($result);
    return $query;
  }

  /**
   * Creates a target entity for the requested type.
   */
  private function target(string $type, string $id, string $uuid): EntityInterface {
    $target = $this->createMock(EntityInterface::class);
    $target->method('getEntityTypeId')->willReturn($type);
    $target->method('id')->willReturn($id);
    $target->method('uuid')->willReturn($uuid);
    return $target;
  }

}
