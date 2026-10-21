<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Kernel;

use Drupal\aculta_magazine\Migration\PublicationBundleMigrator;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\path_alias\Entity\PathAlias;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies supported Entity API identity preservation for the bundle mover.
 *
 * @group aculta_magazine
 */
#[RunTestsInSeparateProcesses]
final class PublicationBundleMigratorKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'token',
    'field',
    'text',
    'filter',
    'node',
    'path_alias',
    'path',
    'pathauto',
    'comment',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('comment');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'user', 'filter', 'node', 'pathauto', 'comment']);

    foreach (['article' => 'Article', 'blog' => 'Blog', 'revista' => 'Revista'] as $id => $label) {
      NodeType::create(['type' => $id, 'name' => $label])->save();
    }
  }

  /**
   * Ensures the mover recreates the Node and alias with their original IDs.
   */
  public function testMigratesNodeAndAliasWithIdentityAndRevisionIds(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node = $storage->create(['type' => 'article', 'title' => 'Preserve identity', 'status' => 1]);
    $node->set('path', [['pathauto' => 0]]);
    $node->save();
    $nid = (int) $node->id();
    $uuid = $node->uuid();
    $vid = (int) $node->getRevisionId();
    $alias = PathAlias::create([
      'path' => '/node/' . $nid,
      'alias' => '/legacy/publicacao-de-teste',
      'langcode' => 'en',
      'status' => 1,
    ]);
    $alias->save();
    $alias_id = (int) $alias->id();
    $alias_uuid = $alias->uuid();
    $alias_revision_id = (int) $alias->getRevisionId();

    require_once DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_magazine/src/Migration/PublicationBundleMigrator.php';
    $migrator = new PublicationBundleMigrator(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('database'),
    );
    $plan = $migrator->migrate();

    $migrated = $storage->load($nid);
    self::assertSame([$nid], $plan['nids']);
    self::assertSame('revista', $migrated->bundle());
    self::assertSame($nid, (int) $migrated->id());
    self::assertSame($uuid, $migrated->uuid());
    self::assertSame($vid, (int) $migrated->getRevisionId());
    self::assertSame('Preserve identity', $migrated->getTitle());

    $restored_alias = $this->container->get('entity_type.manager')->getStorage('path_alias')->load($alias_id);
    self::assertSame($alias_uuid, $restored_alias->uuid());
    self::assertSame($alias_revision_id, (int) $restored_alias->getRevisionId());
    self::assertSame('/legacy/publicacao-de-teste', $restored_alias->getAlias());
  }

  /**
   * Refuses revisions beyond the shape inventoried for the development site.
   */
  public function testRefusesMultipleNodeRevisionsWithoutDeletingSource(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node = $storage->create(['type' => 'article', 'title' => 'Keep revision history', 'status' => 1]);
    $node->save();
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Second revision');
    $node->save();
    $nid = (int) $node->id();

    require_once DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_magazine/src/Migration/PublicationBundleMigrator.php';
    $migrator = new PublicationBundleMigrator(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('database'),
    );

    try {
      $migrator->migrate();
      self::fail('Expected migration to refuse multiple revisions.');
    }
    catch (\RuntimeException $exception) {
      self::assertStringContainsString('revisões', $exception->getMessage());
    }
    $storage->resetCache([$nid]);
    self::assertSame('article', $storage->load($nid)->bundle());
    self::assertSame(2, $storage->getQuery()->allRevisions()->accessCheck(FALSE)->condition('nid', $nid)->count()->execute());
  }

}
