<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_legacy\Kernel;

use Drupal\aculta_forum_legacy\Migration\LegacyArchiveTopicMapper;
use Drupal\aculta_forum_legacy\Migration\LegacyArchiveTopicWriter;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the archive topic writer is idempotent: re-running updates the same entity.
 */
#[Group('aculta_forum_legacy')]
#[RunTestsInSeparateProcesses]
final class LegacyArchiveTopicWriterKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'text', 'filter', 'aculta_forum_legacy'];

  /**
   * Re-running the same source row updates one entity and never duplicates it.
   */
  public function testRerunUpdatesSameEntityWithoutDuplicates(): void {
    $this->installEntitySchema('aculta_forum_legacy_user');
    $this->installEntitySchema('aculta_forum_legacy_topics');
    $writer = new LegacyArchiveTopicWriter($this->container->get('entity_type.manager'));
    $row = ['nid' => 42, 'title' => 'Tópico histórico', 'body' => 'Texto', 'created' => 1700000000];

    $first = $writer->upsert(LegacyArchiveTopicMapper::map($row) ?? []);
    $revised = ['nid' => 42, 'title' => 'Título revisado', 'body' => 'Texto'] + $row;
    $second = $writer->upsert(LegacyArchiveTopicMapper::map($revised) ?? []);

    self::assertSame((int) $first->id(), (int) $second->id());
    self::assertSame('Título revisado', $second->label());
    $ids = $this->container->get('entity_type.manager')->getStorage('aculta_forum_legacy_topics')->getQuery()->accessCheck(FALSE)->execute();
    self::assertCount(1, $ids);
  }

}
