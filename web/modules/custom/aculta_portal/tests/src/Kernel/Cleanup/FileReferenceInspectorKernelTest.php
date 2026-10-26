<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel\Cleanup;

use Drupal\file\Entity\File;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\aculta_portal\Cleanup\FileReferenceInspector;
use Drupal\aculta_portal\Cleanup\FileReferenceStatus;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies File reference detection against Drupal's revision and text storage.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class FileReferenceInspectorKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'filter']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->installField('field_attachment', 'entity_reference', ['target_type' => 'file']);
    $this->installField('field_body', 'text_long');
  }

  /**
   * A file referenced only by an older revision remains in use.
   */
  public function testFindsFileReferenceInHistoricalNodeRevision(): void {
    $file = $this->file('historical.txt');
    $node = Node::create([
      'type' => 'article',
      'title' => 'Revision reference fixture',
      'field_attachment' => ['target_id' => $file->id()],
    ]);
    $node->save();
    $node->setNewRevision(TRUE);
    $node->set('field_attachment', []);
    $node->save();

    $this->assertSame([], $node->get('field_attachment')->getValue());
    $this->assertSame(FileReferenceStatus::Referenced, $this->inspector()->inspect($file));
  }

  /**
   * A file UUID embedded in a formatted text field remains in use.
   */
  public function testFindsFileUuidEmbeddedInTextField(): void {
    $file = $this->file('embedded.txt');
    $node = Node::create([
      'type' => 'article',
      'title' => 'Text reference fixture',
      'field_body' => [
        'value' => '<drupal-entity data-entity-type="file" data-entity-uuid="' . $file->uuid() . '"></drupal-entity>',
        'format' => 'plain_text',
      ],
    ]);
    $node->save();

    $this->assertSame(FileReferenceStatus::Referenced, $this->inspector()->inspect($file));
  }

  /**
   * Installs one field on the Article fixture bundle.
   */
  private function installField(string $name, string $type, array $settings = []): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => $name,
      'bundle' => 'article',
      'label' => $name,
    ])->save();
  }

  /**
   * Creates the inspector with Drupal's real entity and field APIs.
   */
  private function inspector(): FileReferenceInspector {
    return new FileReferenceInspector(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('database'),
    );
  }

  /**
   * Creates a managed file entity fixture without writing a physical file.
   */
  private function file(string $name): File {
    $file = File::create([
      'uid' => 0,
      'filename' => $name,
      'uri' => 'public://' . $name,
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

}
