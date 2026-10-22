<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the initial schema contract without installing tables.
 *
 * @group aculta_index
 */
final class ContentIndexSchemaTest extends UnitTestCase {

  /**
   * Loads the module schema definition.
   */
  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 3) . '/aculta_index.install';
  }

  /**
   * Tests generation and active pointer tables are declared.
   */
  public function testSchemaContainsGenerationAndPointerTables(): void {
    $schema = aculta_index_schema();

    self::assertArrayHasKey('aculta_index_generation', $schema);
    self::assertArrayHasKey('aculta_index_state', $schema);
    self::assertArrayHasKey('active_generation', $schema['aculta_index_state']['fields']);
    self::assertArrayHasKey('cursor_entity_id', $schema['aculta_index_generation']['fields']);
  }

  /**
   * Tests entry identity includes language and excludes revision.
   */
  public function testEntryIdentityUsesEntityAndLanguageWithinGeneration(): void {
    $schema = aculta_index_schema();

    self::assertSame(
      ['generation_id', 'entity_type', 'entity_id', 'langcode'],
      $schema['aculta_index_entry']['unique keys']['gen_identity'],
    );
    self::assertArrayNotHasKey('revision_id', $schema['aculta_index_entry']['fields']);
  }

  /**
   * Tests relationship direction and generation deduplication.
   */
  public function testRelationshipsAreDirectionalAndDeduplicatedByGeneration(): void {
    $schema = aculta_index_schema();
    $fields = $schema['aculta_index_relation']['fields'];

    self::assertArrayHasKey('source_entity_type', $fields);
    self::assertArrayHasKey('target_entity_type', $fields);
    self::assertSame(
      ['generation_id', 'relation_hash'],
      $schema['aculta_index_relation']['unique keys']['gen_relation_hash'],
    );
  }

}
