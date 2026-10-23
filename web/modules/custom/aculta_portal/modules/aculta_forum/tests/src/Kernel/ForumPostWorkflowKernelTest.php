<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Hook\ForumPublicationTimeHooks;
use Drupal\aculta_user\ParticipationEditWindow;
use Drupal\aculta_user\ParticipationRules;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises the revisionable public Forum post and Core workflow integration.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostWorkflowKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'entity_usage',
    'filter',
    'text',
    'node',
    'comment',
    'workflows',
    'content_moderation',
    'aculta_forum_post_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'system',
      'user',
      'field',
      'filter',
      'text',
      'node',
      'comment',
      'workflows',
      'content_moderation',
    ]);
    $this->container->get('config.storage')->write('workflows.workflow.aculta_forum_post', [
      'langcode' => 'pt-br',
      'status' => TRUE,
      'id' => 'aculta_forum_post',
      'label' => 'Forum post moderation test workflow',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
          'review' => ['label' => 'Review', 'weight' => 1, 'published' => FALSE, 'default_revision' => FALSE],
          'published' => ['label' => 'Published', 'weight' => 2, 'published' => TRUE, 'default_revision' => TRUE],
          'rejected' => ['label' => 'Rejected', 'weight' => 3, 'published' => FALSE, 'default_revision' => TRUE],
        ],
        'transitions' => [
          'approve' => ['label' => 'Approve', 'from' => ['review'], 'to' => 'published', 'weight' => 0],
          'edit_published' => ['label' => 'Edit published', 'from' => ['published'], 'to' => 'published', 'weight' => 1],
          'reject' => ['label' => 'Reject', 'from' => ['review'], 'to' => 'rejected', 'weight' => 2],
        ],
        'entity_types' => ['aculta_forum_post' => ['aculta_forum_post']],
        'default_moderation_state' => 'draft',
      ],
    ]);
    $this->container->get('config.factory')->reset('workflows.workflow.aculta_forum_post');
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
  }

  /**
   * The Core workflow moderates revisionable posts and preserves revisions.
   */
  public function testPostEntityUsesRevisionStorageAndWorkflow(): void {
    $definition = $this->container->get('entity_type.manager')->getDefinition('aculta_forum_post');
    self::assertTrue($definition->isRevisionable());
    self::assertSame('aculta_forum_post_revision', $definition->getRevisionTable());
    self::assertArrayHasKey('aculta_forum_post', $this->container->get('entity_type.bundle.info')->getBundleInfo('aculta_forum_post'));
    self::assertSame('aculta_forum_post', $this->container->get('entity_type.bundle.info')->getBundleInfo('aculta_forum_post')['aculta_forum_post']['workflow'] ?? NULL);
    self::assertArrayHasKey('moderation_state', $this->container->get('entity_field.manager')->getBaseFieldDefinitions('aculta_forum_post'));

    $workflow = $this->container->get('entity_type.manager')->getStorage('workflow')->load('aculta_forum_post');
    self::assertNotNull($workflow);
    self::assertTrue($workflow->getTypePlugin()->appliesToEntityTypeAndBundle('aculta_forum_post', 'aculta_forum_post'));

    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('primeiro_comentario_discussao_moderado', TRUE)
      ->set('papeis_isentos', [])
      ->save();
    User::create(['name' => 'forum workflow admin', 'status' => 1])->save();
    $author = User::create(['name' => 'forum workflow author', 'status' => 1]);
    $author->save();
    $topic = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Tópico de teste', 'status' => 1]);
    $topic->save();

    $post = ForumPost::create([
      'uid' => $author->id(),
      'topic' => $topic->id(),
      'body' => ['value' => 'Primeiro texto', 'format' => 'plain_text'],
    ]);
    $publication_hooks = new ForumPublicationTimeHooks(
      $this->container->get('datetime.time'),
      new ParticipationRules($this->container->get('config.factory'), $this->container->get('entity_type.manager')),
    );
    $publication_hooks->entityPresave($post);
    $post->save();

    self::assertSame('review', $post->get('moderation_state')->value);
    self::assertFalse($post->isPublished());
    self::assertTrue($post->get('first_published')->isEmpty());

    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('primeiro_comentario_discussao_moderado', FALSE)
      ->save();
    $published = ForumPost::create([
      'uid' => $author->id(),
      'topic' => $topic->id(),
      'body' => ['value' => 'Texto publicado', 'format' => 'plain_text'],
    ]);
    $publication_hooks->entityPresave($published);
    $published->save();
    self::assertSame('published', $published->get('moderation_state')->value);
    self::assertTrue($published->isPublished());
    self::assertGreaterThan(0, (int) $published->get('first_published')->value);

    $first_published = (int) $published->get('first_published')->value;
    $publication_hooks->entityPresave($published);
    self::assertSame($first_published, (int) $published->get('first_published')->value, 'Editing must not reset the first-publication timestamp.');
    $edit_window = new ParticipationEditWindow($this->container->get('config.factory'), $this->container->get('datetime.time'));
    self::assertTrue($edit_window->access($published, 'update', $author)->isNeutral(), 'The window policy must not grant access denied by the entity access handler.');
    $published->set('first_published', NULL);
    self::assertTrue($edit_window->access($published, 'update', $author)->isNeutral(), 'An entity without its migration marker must retain base access behavior.');
    $published->set('first_published', $this->container->get('datetime.time')->getRequestTime() - 601);
    self::assertTrue($edit_window->access($published, 'update', $author)->isForbidden(), 'The author edit window must expire from the immutable timestamp.');

    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_forum_post');
    self::assertInstanceOf(RevisionableStorageInterface::class, $storage);
    $previous_revision_id = $published->getRevisionId();
    $published->setNewRevision(TRUE);
    $published->set('body', ['value' => 'Texto corrigido dentro do prazo', 'format' => 'plain_text']);
    $published->save();

    self::assertNotSame($previous_revision_id, $published->getRevisionId());
    self::assertSame('Texto publicado', $storage->loadRevision($previous_revision_id)->get('body')->value);
    self::assertSame('Texto corrigido dentro do prazo', $storage->load($published->id())->get('body')->value);
  }

}
