<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\aculta_user\Hook\ParticipationHooks;
use Drupal\aculta_user\ParticipationRules;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests first-comment moderation in public discussion contexts only.
 */
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class PublicDiscussionModerationKernelTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'comment',
    'aculta_user_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'filter', 'node', 'comment', 'aculta_user_test']);

    CommentType::create([
      'id' => ParticipationRules::DISCUSSION_COMMENT_TYPE,
      'label' => 'Public discussion post',
      'target_entity_type_id' => 'node',
    ])->save();
    foreach (['aculta_forum_topic', 'article', 'blog', 'wiki_entry', 'document', 'aculta_lms_topic'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
      $this->addDefaultCommentField(
        'node',
        $bundle,
        'field_aculta_discussion',
        CommentingStatus::Open,
        ParticipationRules::DISCUSSION_COMMENT_TYPE,
      );
    }
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_autor_legado',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_autor_legado',
      'label' => 'Historical author marker',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'comment',
      'field_name' => 'field_autor_legado',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'comment',
      'bundle' => ParticipationRules::DISCUSSION_COMMENT_TYPE,
      'field_name' => 'field_autor_legado',
      'label' => 'Historical author marker',
    ])->save();

    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('primeiro_comentario_discussao_moderado', TRUE)
      ->set('papeis_isentos', [])
      ->save();
  }

  /**
   * LMS comments are published and count as approval in other contexts.
   */
  public function testPublishedLmsCommentCountsAsApprovalAcrossOtherContexts(): void {
    $account = $this->createAccount();
    $rules = $this->createRules();
    $hooks = new ParticipationHooks($rules);

    $lms_comment = $this->newComment('aculta_lms_topic', (int) $account->id());
    $hooks->commentPresave($lms_comment);
    self::assertTrue($lms_comment->isPublished(), 'LMS comments do not use public moderation.');
    $lms_comment->save();

    $magazine_comment = $this->newComment('article', (int) $account->id());
    $hooks->commentPresave($magazine_comment);
    self::assertTrue($magazine_comment->isPublished(), 'Published LMS participation counts as approval.');
    $magazine_comment->save();

    $forum_comment = $this->newComment('aculta_forum_topic', (int) $account->id());
    $hooks->commentPresave($forum_comment);
    self::assertTrue($forum_comment->isPublished(), 'An approved public comment covers both public contexts.');
  }

  /**
   * LMS approval releases moderation and the temporary topic-creation block.
   */
  public function testPublishedLmsCommentCountsAsApprovedParticipation(): void {
    $account = $this->createAccount();
    $rules = $this->createRules();
    $hooks = new ParticipationHooks($rules);

    $pending_public_comment = $this->newComment('article', (int) $account->id());
    $hooks->commentPresave($pending_public_comment);
    self::assertFalse($pending_public_comment->isPublished());
    $pending_public_comment->save();
    self::assertTrue($rules->firstDiscussionCommentPending((int) $account->id()));

    $lms_comment = $this->newComment('aculta_lms_topic', (int) $account->id());
    $hooks->commentPresave($lms_comment);
    self::assertTrue($lms_comment->isPublished(), 'LMS comments remain outside moderation.');
    $lms_comment->save();

    $forum_comment = $this->newComment('aculta_forum_topic', (int) $account->id());
    $hooks->commentPresave($forum_comment);

    self::assertTrue($forum_comment->isPublished(), 'Published LMS participation counts as approval in public contexts.');
    self::assertTrue($rules->hasPublishedDiscussionComment((int) $account->id()));
    self::assertFalse($rules->firstDiscussionCommentPending((int) $account->id()), 'A published LMS comment clears the temporary topic-creation block.');

    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('topico_limite_ativo', FALSE)
      ->set('bloqueio_ate_aprovacao', TRUE)
      ->save();
    $access = $hooks->entityCreateAccess($account, ['entity_type_id' => 'node'], 'aculta_forum_topic');
    self::assertNotNull($access);
    self::assertTrue($access->isNeutral());
  }

  /**
   * The administration setting can disable moderation in both public contexts.
   */
  public function testAdministrativeSettingDisablesPublicDiscussionModeration(): void {
    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('primeiro_comentario_discussao_moderado', FALSE)
      ->save();

    $account = $this->createAccount();
    $comment = $this->newComment('blog', (int) $account->id());
    (new ParticipationHooks($this->createRules()))->commentPresave($comment);

    self::assertTrue($comment->isPublished());
  }

  /**
   * Imported Growroom comments bypass first-comment moderation.
   */
  public function testImportedHistoricalCommentsBypassFirstCommentModeration(): void {
    $account = $this->createAccount();
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Restored Growroom topic',
      'status' => 1,
      'field_autor_legado' => ['value' => 'historical-author'],
    ]);
    $topic->save();
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => ParticipationRules::DISCUSSION_COMMENT_TYPE,
      'uid' => $account->id(),
      'subject' => 'Restored reply',
      'comment_body' => ['value' => 'Restored reply', 'format' => 'plain_text'],
      'status' => 1,
    ]);

    (new ParticipationHooks($this->createRules()))->commentPresave($comment);
    self::assertTrue($comment->isPublished(), 'Restored comments do not enter the contemporary first-comment moderation policy.');

    $commentAuthorMarked = $this->newComment('aculta_forum_topic', (int) $account->id());
    $commentAuthorMarked->set('field_autor_legado', ['value' => 'historical-author']);
    (new ParticipationHooks($this->createRules()))->commentPresave($commentAuthorMarked);
    self::assertTrue($commentAuthorMarked->isPublished(), 'A historical author marker on a Comment bypasses first-comment moderation.');
  }

  /**
   * Wiki and any other host using the shared field follow the same policy.
   */
  public function testWikiAndOtherSharedDiscussionContextsUseModeration(): void {
    $account = $this->createAccount();
    $hooks = new ParticipationHooks($this->createRules());

    $wiki_comment = $this->newComment('wiki_entry', (int) $account->id());
    $hooks->commentPresave($wiki_comment);
    self::assertFalse($wiki_comment->isPublished());
    $wiki_comment->setPublished();
    $wiki_comment->save();

    $other_comment = $this->newComment('document', (int) $account->id());
    $hooks->commentPresave($other_comment);
    self::assertTrue($other_comment->isPublished());
  }

  /**
   * The account activity query only selects published public Forum replies.
   */
  public function testForumActivityQueryExcludesOtherContextsAndPendingComments(): void {
    $account = $this->createAccount();
    $forum_comment = $this->newComment('aculta_forum_topic', (int) $account->id());
    $forum_comment->save();

    $publication_comment = $this->newComment('article', (int) $account->id());
    $publication_comment->save();

    $pending_comment = $this->newComment('aculta_forum_topic', (int) $account->id());
    $pending_comment->setUnpublished();
    $pending_comment->save();

    $ids = $this->container->get('entity_type.manager')
      ->getStorage('comment')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', ParticipationRules::DISCUSSION_COMMENT_TYPE)
      ->condition('entity_type', 'node')
      ->condition('field_name', 'field_aculta_discussion')
      ->condition('entity_id.entity:node.type', 'aculta_forum_topic')
      ->condition('uid', $account->id())
      ->condition('status', 1)
      ->execute();

    self::assertSame([(int) $forum_comment->id()], array_map('intval', array_values($ids)));
  }

  /**
   * Pending public comments can block new Forum topics when configured.
   */
  public function testPendingPublicCommentBlocksTopicCreationWhenConfigured(): void {
    $account = $this->createAccount();
    $config = $this->container->get('config.factory')->getEditable('aculta_user.settings');
    $config->set('topico_limite_ativo', FALSE)->set('bloqueio_ate_aprovacao', TRUE)->save();
    $rules = $this->createRules();
    $hooks = new ParticipationHooks($rules);

    $pending = $this->newComment('blog', (int) $account->id());
    $hooks->commentPresave($pending);
    $pending->save();

    $result = $hooks->entityCreateAccess($account, ['entity_type_id' => 'node'], 'aculta_forum_topic');

    self::assertNotNull($result);
    self::assertTrue($result->isForbidden());
    self::assertContains('config:aculta_user.settings', $result->getCacheTags());
    self::assertContains('user.roles', $result->getCacheContexts());
  }

  /**
   * Creates the participation service with the test container.
   */
  private function createRules(): ParticipationRules {
    return new ParticipationRules(
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
    );
  }

  /**
   * Creates and saves a regular account without exempt roles.
   */
  private function createAccount() {
    $account = $this->container->get('entity_type.manager')->getStorage('user')->create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);
    $account->save();
    return $account;
  }

  /**
   * Creates a node host and a new Comment entity for its shared field.
   */
  private function newComment(string $host_bundle, int $uid): Comment {
    $host = Node::create([
      'type' => $host_bundle,
      'title' => $this->randomMachineName(),
      'status' => 1,
    ]);
    $host->save();

    return Comment::create([
      'entity_type' => 'node',
      'entity_id' => $host->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => ParticipationRules::DISCUSSION_COMMENT_TYPE,
      'uid' => $uid,
      'subject' => $this->randomMachineName(),
      'comment_body' => ['value' => 'Conteúdo de teste.', 'format' => 'plain_text'],
      'status' => 1,
    ]);
  }

}
