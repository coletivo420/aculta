<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_legacy\Kernel;

use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum_legacy\Entity\LegacyForumAttachment;
use Drupal\aculta_forum_legacy\Entity\LegacyForumComment;
use Drupal\aculta_forum_legacy\Entity\LegacyForumTopic;
use Drupal\aculta_forum_legacy\Entity\LegacyForumUser;
use Drupal\aculta_forum_legacy\Origin\LegacyDiscussionOriginClassifier;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the historic archive has its own entities, apart from current Forum content.
 */
#[Group('aculta_forum_legacy')]
#[RunTestsInSeparateProcesses]
final class LegacyForumContentEntitiesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'text', 'filter', 'aculta_forum_legacy'];

  /**
   * Archive entities are stored in their own tables, never in node or comment.
   */
  public function testArchiveTopicAndCommentAreStoredApartFromCurrentContent(): void {
    $this->installEntitySchema('aculta_forum_legacy_user');
    $this->installEntitySchema('aculta_forum_legacy_topics');
    $this->installEntitySchema('aculta_forum_legacy_comments');
    $this->installEntitySchema('aculta_forum_legacy_attachments');

    $author = LegacyForumUser::create(['origem_id' => 'autor-1', 'nome' => 'Autora histórica']);
    $author->save();

    $topic = LegacyForumTopic::create([
      'origem_id' => 'topico-1',
      'titulo' => 'Tópico do acervo',
      'corpo' => 'Texto histórico',
      'autor' => $author->id(),
    ]);
    $topic->save();

    $comment = LegacyForumComment::create([
      'origem_id' => 'resposta-1',
      'topico' => $topic->id(),
      'corpo' => 'Resposta histórica',
      'autor' => $author->id(),
    ]);
    $comment->save();

    $this->assertNotNull($topic->id());
    $this->assertSame('Tópico do acervo', $topic->label());
    $this->assertSame('topico-1', $topic->get('origem_id')->value);
    $this->assertSame($topic->id(), $comment->get('topico')->target_id);

    $attachment = LegacyForumAttachment::create([
      'origem_id' => 'anexo-1',
      'comentario' => $comment->id(),
      'nome' => 'foto.jpg',
      'arquivo_uri' => 'private://legado/foto.jpg',
    ]);
    $attachment->save();
    $this->assertSame('private://legado/foto.jpg', $attachment->get('arquivo_uri')->value);

    $classifier = new LegacyDiscussionOriginClassifier();
    $this->assertSame(DiscussionOrigin::ARCHIVE, $classifier->classify($topic)?->origin);
    $this->assertSame(DiscussionOrigin::ARCHIVE, $classifier->classify($comment)?->origin);

    $this->assertSame(1, $this->countRows('aculta_forum_legacy_topics'));
    $this->assertSame(1, $this->countRows('aculta_forum_legacy_comments'));
    $this->assertSame(1, $this->countRows('aculta_forum_legacy_attachments'));

  }

  /**
   * Counts rows in one archive table, failing loudly if the query fails.
   */
  private function countRows(string $table): int {
    $statement = $this->container->get('database')->select($table, 't')->countQuery()->execute();
    self::assertNotNull($statement);
    $count = $statement->fetchField();
    self::assertIsNumeric($count);
    return (int) $count;
  }

}
