<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Plugin\migrate\destination\ForumCommentPost;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the migration destination rejects invalid Comment rows before saving.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumCommentMigrationDestinationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'file',
    'image',
    'media',
    'migrate',
    'workflows',
    'content_moderation',
    'language',
    'aculta_forum_post_test',
  ];

  /**
   * Destination under test.
   */
  private ForumCommentPost $destination;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The workflow must exist before the ForumPost schema is installed so the
    // moderation_state field is part of the bundle.
    $this->container->get('config.storage')->write('workflows.workflow.aculta_forum_post', [
      'langcode' => 'pt-br',
      'status' => TRUE,
      'id' => 'aculta_forum_post',
      'label' => 'Forum post migration test workflow',
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
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'system', 'user', 'field', 'filter', 'text', 'node', 'file', 'image',
      'media', 'workflows', 'content_moderation', 'language',
    ]);

    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->destination = new ForumCommentPost(
      [],
      'aculta_forum_comment_post',
      [],
      $this->createMock(MigrationInterface::class),
      $entity_type_manager->getStorage('aculta_forum_post'),
      ['aculta_forum_post'],
      $this->container->get('entity_field.manager'),
      $this->container->get('plugin.manager.field.field_type'),
      $this->container->get('account_switcher'),
      $this->container->get('entity_type.bundle.info'),
      $entity_type_manager,
    );
  }

  /**
   * A valid row creates the post with all translations and publication state.
   */
  public function testValidRowCreatesPublishedPostWithTranslations(): void {
    [$author, $topic] = $this->prepareForumFixture();
    $row = $this->destinationRow($author, $topic, [], [
      'en' => [
        'subject' => 'First reply',
        'body_value' => 'Body in English',
        'body_format' => 'forum',
        'published' => 1,
      ],
      'pt-br' => [
        'subject' => 'Primeira resposta',
        'body_value' => 'Corpo em português',
        'body_format' => 'forum',
        'published' => 1,
      ],
    ]);

    $ids = $this->destination->import($row);
    self::assertGreaterThan(0, (int) $ids[0]);

    $post = $this->loadPost((int) reset($ids));
    self::assertSame('en', $post->language()->getId());
    self::assertTrue($post->isPublished());
    self::assertSame('First reply', $post->get('subject')->value);
    self::assertSame((int) $topic->id(), (int) $post->get('topic')->target_id);
    self::assertSame(10, (int) $post->get('thread_position')->value);
    self::assertTrue($post->getEntityType()->hasHandlerClass('moderation'), 'Entity type has moderation handler.');
    $bundles = $this->container->get('entity_type.bundle.info')->getBundleInfo('aculta_forum_post');
    self::assertSame('aculta_forum_post', $bundles['aculta_forum_post']['workflow'] ?? NULL, 'Bundle workflow exists.');
    self::assertTrue($this->container->get('content_moderation.moderation_information')->isModeratedEntity($post), 'Entity is moderated.');
    self::assertSame('published', $post->get('moderation_state')->value);
    self::assertTrue($post->hasTranslation('pt-br'));
    $translation = $post->getTranslation('pt-br');
    self::assertSame('Primeira resposta', $translation->get('subject')->value);
    self::assertSame('Corpo em português', $translation->get('body')->value);
    self::assertTrue($translation->isPublished());
  }

  /**
   * Reimporting the same Comment updates the post instead of duplicating it.
   */
  public function testExistingPostIsUpdatedInPlace(): void {
    [$author, $topic] = $this->prepareForumFixture();
    $original = $this->destinationRow($author, $topic, [], $this->bilingualTranslations('First reply'));
    $ids = $this->destination->import($original);
    $post_id = (int) reset($ids);

    $updated = $this->destinationRow($author, $topic, [], $this->bilingualTranslations('Edited reply'));
    $ids = $this->destination->import($updated, [$post_id]);

    self::assertSame($post_id, (int) reset($ids));
    self::assertSame(1, (int) $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post')->getQuery()->accessCheck(FALSE)->count()->execute());
    $post = $this->loadPost($post_id);
    self::assertSame('Edited reply', $post->get('subject')->value);
    self::assertSame('Resposta editada', $post->getTranslation('pt-br')->get('subject')->value);
  }

  /**
   * Changing the default language of an existing post is refused.
   */
  public function testDefaultLanguageChangeOnUpdateIsRejected(): void {
    [$author, $topic] = $this->prepareForumFixture();
    $original = $this->destinationRow($author, $topic, [], $this->bilingualTranslations('First reply'));
    $ids = $this->destination->import($original);
    $post_id = (int) reset($ids);

    $changed = $this->destinationRow($author, $topic, ['langcode' => 'pt-br'], [
      'pt-br' => [
        'subject' => 'Resposta',
        'body_value' => 'Corpo',
        'body_format' => 'forum',
        'published' => 1,
      ],
    ]);
    try {
      $this->destination->import($changed, [$post_id]);
      self::fail('A troca de idioma padrão foi aceita.');
    }
    catch (MigrateException $exception) {
      self::assertStringContainsString('mudou o idioma padrão', $exception->getMessage());
    }
    self::assertSame('First reply', $this->loadPost($post_id)->get('subject')->value);
  }

  /**
   * The parents migration overwrites the parent relationship on update.
   */
  public function testParentRelationshipIsStoredOnUpdate(): void {
    [$author, $topic] = $this->prepareForumFixture();
    $root_ids = $this->destination->import(
      $this->destinationRow($author, $topic, [], $this->bilingualTranslations('Root')),
    );
    $root_id = (int) reset($root_ids);
    $reply_ids = $this->destination->import(
      $this->destinationRow($author, $topic, [], $this->bilingualTranslations('Reply')),
    );
    $reply_id = (int) reset($reply_ids);
    self::assertTrue($this->loadPost($reply_id)->get('parent')->isEmpty());

    $parent_update = $this->destinationRow($author, $topic, ['cid' => 999], $this->bilingualTranslations('Reply'));
    $parent_update->setDestinationProperty('parent', ['target_id' => $root_id]);
    $this->destination->import($parent_update, [$reply_id]);

    self::assertSame($root_id, (int) $this->loadPost($reply_id)->get('parent')->target_id);
    self::assertSame('Root', $this->loadPost($root_id)->get('subject')->value);
  }

  /**
   * A row without account or valid historical author fails closed.
   */
  public function testRowWithoutAuthorIsRejected(): void {
    $this->assertRejected($this->row(['uid' => 0, 'legacy_author_id' => 0]), 'autoria histórica');
  }

  /**
   * The source must include its default translation.
   */
  public function testMissingDefaultTranslationIsRejected(): void {
    $this->assertRejected($this->row(['langcode' => 'en', 'translations' => []]), 'tradução padrão');
  }

  /**
   * A rejected row may not also be published.
   */
  public function testRejectedAndPublishedIsRejected(): void {
    $this->assertRejected($this->row(['rejected' => 1]), 'publicação com rejeição');
  }

  /**
   * Empty bodies and unsupported formats are rejected.
   */
  public function testBodyContractIsEnforced(): void {
    $this->assertRejected($this->row(['translations' => $this->translations(['body_value' => ''])]), 'corpo vazio');
    $this->assertRejected($this->row(['translations' => $this->translations(['body_format' => 'basic_html'])]), 'formato de texto incompatível');
  }

  /**
   * Embeds must have a UUID and resolve to a destination entity.
   */
  public function testEmbedsMustResolve(): void {
    $embed = '<drupal-media data-entity-type="media" data-entity-uuid=""></drupal-media>';
    $this->assertRejected($this->row(['translations' => $this->translations(['body_value' => $embed])]), 'embed media sem UUID');

    $missing = '<drupal-media data-entity-type="media" data-entity-uuid="00000000-0000-0000-0000-000000000000"></drupal-media>';
    $this->assertRejected($this->row(['translations' => $this->translations(['body_value' => $missing])]), 'embed(s) media sem entidade');
  }

  /**
   * Creates the language, topic type, author and topic used by the imports.
   *
   * @return array{0: \Drupal\user\Entity\User, 1: \Drupal\node\Entity\Node}
   *   Author and topic.
   */
  private function prepareForumFixture(): array {
    ConfigurableLanguage::createFromLangcode('pt-br')->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    $author = User::create(['name' => 'autora', 'status' => 1]);
    $author->save();
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico',
      'status' => 1,
      'uid' => $author->id(),
    ]);
    $topic->save();
    return [$author, $topic];
  }

  /**
   * Builds the destination row the process pipeline would produce.
   */
  private function destinationRow(User $author, Node $topic, array $source, array $translations): Row {
    $row = $this->row($source + [
      'uid' => (int) $author->id(),
      'translations' => $translations,
    ]);
    foreach ([
      'uid' => (int) $author->id(),
      'topic' => ['target_id' => (int) $topic->id()],
      'thread_position' => 10,
      'created' => 1700000000,
      'changed' => 1700000100,
      'first_published' => 1700000000,
      'revision_uid' => (int) $author->id(),
      'revision_timestamp' => 1700000100,
    ] as $name => $value) {
      $row->setDestinationProperty($name, $value);
    }
    return $row;
  }

  /**
   * Builds English and Portuguese translations with the given subject.
   */
  private function bilingualTranslations(string $subject): array {
    return [
      'en' => [
        'subject' => $subject,
        'body_value' => 'Body',
        'body_format' => 'forum',
        'published' => 1,
      ],
      'pt-br' => [
        'subject' => $subject === 'Edited reply' ? 'Resposta editada' : 'Resposta',
        'body_value' => 'Corpo',
        'body_format' => 'forum',
        'published' => 1,
      ],
    ];
  }

  /**
   * Loads a stored ForumPost.
   */
  private function loadPost(int $id): ForumPost {
    $post = $this->container->get('entity_type.manager')
      ->getStorage('aculta_forum_post')
      ->load($id);
    self::assertInstanceOf(ForumPost::class, $post);
    return $post;
  }

  /**
   * Asserts one invalid row throws with the expected message fragment.
   */
  private function assertRejected(Row $row, string $fragment): void {
    try {
      $this->destination->import($row);
      self::fail('A linha inválida foi aceita pela migration.');
    }
    catch (MigrateException $exception) {
      self::assertStringContainsString($fragment, $exception->getMessage());
    }
  }

  /**
   * Builds a source row with a valid base contract.
   *
   * @param array<string, mixed> $overrides
   *   Source values that replace the defaults.
   */
  private function row(array $overrides = []): Row {
    $source = $overrides + [
      'cid' => 10,
      'uid' => 7,
      'legacy_author_id' => 0,
      'langcode' => 'en',
      'rejected' => 0,
      'translations' => $this->translations(),
    ];
    return new Row($source, ['cid' => 10], TRUE);
  }

  /**
   * Builds one valid source translation.
   *
   * @param array<string, mixed> $overrides
   *   Translation values that replace the defaults.
   */
  private function translations(array $overrides = []): array {
    return [
      'en' => $overrides + [
        'subject' => 'Assunto',
        'body_value' => 'Corpo da resposta',
        'body_format' => 'forum',
        'published' => 1,
      ],
    ];
  }

}
