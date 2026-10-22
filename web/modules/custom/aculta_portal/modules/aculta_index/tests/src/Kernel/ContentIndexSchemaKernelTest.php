<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Installs and exercises the initial schema in the test database.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexSchemaKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_index'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
    ]);
  }

  /**
   * Checks an identity is unique per generation, entity and language.
   */
  public function testEntryIdentityAllowsLanguageAndGenerationVariants(): void {
    $this->insertEntry('gen-a', 'node', '123', 'pt-br');
    $this->insertEntry('gen-a', 'node', '123', 'en');
    $this->insertEntry('gen-b', 'node', '123', 'pt-br');

    $this->assertSame(3, (int) $this->container->get('database')
      ->select('aculta_index_entry')
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Checks the state pointer can refer to the active generation.
   */
  public function testActiveGenerationPointerIsStored(): void {
    $this->container->get('database')->insert('aculta_index_state')
      ->fields([
        'state_id' => 1,
        'active_generation' => 'gen-a',
        'revision' => 1,
        'updated' => 100,
      ])
      ->execute();

    $this->assertSame('gen-a', $this->container->get('database')
      ->select('aculta_index_state', 'state')
      ->fields('state', ['active_generation'])
      ->condition('state_id', 1)
      ->execute()
      ->fetchField());
  }

  /**
   * Checks the provider registry loads without source adapters.
   */
  public function testProviderRegistryLoadsWithoutSourceAdapters(): void {
    $registry = $this->container->get('aculta_index.provider_registry');

    $this->assertSame([], $registry->all());
  }

  /**
   * Checks a duplicate entity and language cannot be stored in one generation.
   */
  public function testEntryIdentityIsUniqueWithinGeneration(): void {
    $this->insertEntry('gen-a', 'node', '123', 'pt-br');

    $this->expectException(IntegrityConstraintViolationException::class);
    $this->insertEntry('gen-a', 'node', '123', 'pt-br');
  }

  /**
   * Inserts a minimal entry fixture.
   */
  private function insertEntry(string $generation, string $entityType, string $entityId, string $langcode): void {
    $this->container->get('database')->insert('aculta_index_entry')
      ->fields([
        'generation_id' => $generation,
        'provider_id' => 'fixture',
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'langcode' => $langcode,
        'bundle' => 'article',
        'uuid' => '',
        'label' => 'Fixture',
        'domain_id' => '',
        'created' => 1,
        'changed' => 1,
        'published_at' => 0,
        'projection_hash' => 'fixture-hash',
        'updated' => 1,
      ])
      ->execute();
  }

}
