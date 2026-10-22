<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\aculta_user\ParticipationRules;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Characterizes the ParticipationRules policy against real entities.
 *
 * PublicDiscussionModerationKernelTest covers the hook integration. This test
 * pins the observable state of the shared policy itself: what counts as
 * published, pending, public Forum activity and historic material.
 */
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class ParticipationRulesCharacterizationKernelTest extends KernelTestBase {

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
    $this->installConfig(['system', 'filter', 'node', 'comment']);

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
   * Missing keys resolve to the documented defaults.
   */
  public function testSettingsDefaultsAndCacheTags(): void {
    $this->container->get('config.factory')->getEditable('aculta_user.settings')->delete();

    $rules = $this->createRules();
    $settings = $rules->settings();

    self::assertTrue($settings['topico_limite_ativo']);
    self::assertSame(10, $settings['topico_comentarios_minimos']);
    self::assertTrue($settings['primeiro_comentario_discussao_moderado']);
    self::assertTrue($settings['bloqueio_ate_aprovacao']);
    self::assertSame(['content_editor', 'administrator'], $settings['papeis_isentos']);
    self::assertSame(['config:aculta_user.settings'], $rules->settingsCacheTags());
  }

  /**
   * State moves from none to pending to published without shortcuts.
   */
  public function testParticipationStateProgressesFromNoneToPendingToPublished(): void {
    $account = $this->createAccount();
    $uid = (int) $account->id();
    $rules = $this->createRules();

    // No participation at all.
    self::assertFalse($rules->hasPublishedDiscussionComment($uid));
    self::assertFalse($rules->hasPendingDiscussionComment($uid));
    self::assertFalse($rules->firstDiscussionCommentPending($uid));
    self::assertSame(0, $rules->publishedPublicForumCommentCount($uid));
    self::assertTrue($rules->requiresFirstDiscussionModeration($account));

    // Pending public comment: awaiting review, not published.
    $pending = $this->newComment('blog', $uid);
    $pending->setUnpublished();
    $pending->save();

    self::assertFalse($rules->hasPublishedDiscussionComment($uid));
    self::assertTrue($rules->hasPendingDiscussionComment($uid));
    self::assertTrue($rules->firstDiscussionCommentPending($uid));
    self::assertTrue($rules->requiresFirstDiscussionModeration($account), 'Pending review does not release first-comment moderation.');
    self::assertSame(0, $rules->publishedPublicForumCommentCount($uid));

    // Approval publishes the same comment and releases moderation.
    $pending->setPublished();
    $pending->save();

    self::assertTrue($rules->hasPublishedDiscussionComment($uid));
    self::assertFalse($rules->hasPendingDiscussionComment($uid));
    self::assertFalse($rules->firstDiscussionCommentPending($uid));
    self::assertFalse($rules->requiresFirstDiscussionModeration($account));
    // The blog comment is public participation, but not Forum activity.
    self::assertSame(0, $rules->publishedPublicForumCommentCount($uid));

    $first_reply = $this->newComment('aculta_forum_topic', $uid);
    $first_reply->save();
    $second_reply = $this->newComment('aculta_forum_topic', $uid);
    $second_reply->save();

    self::assertSame(2, $rules->publishedPublicForumCommentCount($uid));
  }

  /**
   * Thresholds are computed per account, never across accounts.
   */
  public function testParticipationIsIsolatedPerAccount(): void {
    $account_a = $this->createAccount();
    $account_b = $this->createAccount();
    $uid_a = (int) $account_a->id();
    $uid_b = (int) $account_b->id();
    $rules = $this->createRules();

    $this->newComment('aculta_forum_topic', $uid_a)->save();

    self::assertTrue($rules->hasPublishedDiscussionComment($uid_a));
    self::assertFalse($rules->hasPublishedDiscussionComment($uid_b));
    self::assertTrue($rules->requiresFirstDiscussionModeration($account_b));
    self::assertSame(0, $rules->publishedPublicForumCommentCount($uid_b));

    // A publication from A does not exempt B.
    $this->newComment('article', $uid_a)->save();
    self::assertSame(1, $rules->publishedPublicForumCommentCount($uid_a));
    self::assertSame(0, $rules->publishedPublicForumCommentCount($uid_b));
  }

  /**
   * LMS contribution counts for approval but never as public Forum activity.
   */
  public function testLmsPublishedCommentCountsForApprovalOnly(): void {
    $account = $this->createAccount();
    $uid = (int) $account->id();
    $rules = $this->createRules();

    $this->newComment('aculta_lms_topic', $uid)->save();

    self::assertTrue($rules->hasPublishedDiscussionComment($uid), 'LMS publication counts as accepted participation.');
    self::assertFalse($rules->requiresFirstDiscussionModeration($account));
    self::assertSame(0, $rules->publishedPublicForumCommentCount($uid), 'LMS comments are not public Forum activity.');
    self::assertFalse($rules->hasPendingDiscussionComment($uid));
  }

  /**
   * Pending LMS comments never block or signal the first public comment.
   */
  public function testPendingLmsCommentIsExcludedFromPendingLookup(): void {
    $account = $this->createAccount();
    $uid = (int) $account->id();
    $rules = $this->createRules();

    $pending_lms = $this->newComment('aculta_lms_topic', $uid);
    $pending_lms->setUnpublished();
    $pending_lms->save();

    self::assertFalse($rules->hasPendingDiscussionComment($uid));
    self::assertFalse($rules->firstDiscussionCommentPending($uid));
    self::assertFalse($rules->hasPublishedDiscussionComment($uid));
    self::assertTrue($rules->requiresFirstDiscussionModeration($account));
  }

  /**
   * Public Forum activity only counts published replies on Forum topics.
   */
  public function testPublicForumActivityExcludesOtherContextsAndPending(): void {
    $account = $this->createAccount();
    $uid = (int) $account->id();
    $rules = $this->createRules();

    $this->newComment('aculta_forum_topic', $uid)->save();
    $this->newComment('article', $uid)->save();
    $this->newComment('blog', $uid)->save();
    $this->newComment('wiki_entry', $uid)->save();
    $this->newComment('aculta_lms_topic', $uid)->save();

    $pending_forum = $this->newComment('aculta_forum_topic', $uid);
    $pending_forum->setUnpublished();
    $pending_forum->save();

    self::assertSame(1, $rules->publishedPublicForumCommentCount($uid));
  }

  /**
   * Historic author markers exempt imported material from the shared policy.
   */
  public function testHistoricAuthorMarkersExemptImportedDiscussionComments(): void {
    $account = $this->createAccount();
    $uid = (int) $account->id();
    $rules = $this->createRules();

    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'field_autor_legado' => ['value' => 'historic-author'],
    ]);
    $topic->save();
    $comment_on_historic_topic = $this->newComment('aculta_forum_topic', $uid, $topic);
    self::assertFalse($rules->isNonLmsDiscussionComment($comment_on_historic_topic));

    $marked_comment = $this->newComment('aculta_forum_topic', $uid);
    $marked_comment->set('field_autor_legado', ['value' => 'historic-author']);
    self::assertFalse($rules->isNonLmsDiscussionComment($marked_comment));

    $contemporary = $this->newComment('aculta_forum_topic', $uid);
    self::assertTrue($rules->isNonLmsDiscussionComment($contemporary));
  }

  /**
   * Public discussion classification is decided by the host bundle.
   */
  public function testIsNonLmsDiscussionCommentByHostBundle(): void {
    $account = $this->createAccount();
    $uid = (int) $account->id();
    $rules = $this->createRules();

    $expected = [
      'aculta_forum_topic' => TRUE,
      'article' => TRUE,
      'blog' => TRUE,
      'wiki_entry' => TRUE,
      'document' => TRUE,
      'aculta_lms_topic' => FALSE,
    ];
    foreach ($expected as $bundle => $is_public) {
      $comment = $this->newComment($bundle, $uid);
      self::assertSame($is_public, $rules->isNonLmsDiscussionComment($comment), $bundle);
    }
  }

  /**
   * Exempt roles bypass moderation without any participation history.
   */
  public function testExemptRoleBypassesModeration(): void {
    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('papeis_isentos', ['content_editor'])
      ->save();
    Role::create(['id' => 'content_editor', 'label' => 'Content editor'])->save();
    $account = $this->createAccount();
    $account->addRole('content_editor');
    $account->save();

    $rules = $this->createRules();
    self::assertTrue($rules->isExempt($account));
    self::assertFalse($rules->requiresFirstDiscussionModeration($account));
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
  private function createAccount(): UserInterface {
    $account = $this->container->get('entity_type.manager')->getStorage('user')->create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);
    $account->save();
    return $account;
  }

  /**
   * Creates a node host and a Comment entity for its shared field.
   */
  private function newComment(string $host_bundle, int $uid, ?Node $host = NULL): Comment {
    if ($host === NULL) {
      $host = Node::create([
        'type' => $host_bundle,
        'title' => $this->randomMachineName(),
        'status' => 1,
      ]);
      $host->save();
    }

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
