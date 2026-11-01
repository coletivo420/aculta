<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\aculta_user\Hook\ParticipationHooks;
use Drupal\aculta_user\ParticipationRules;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the LMS comment propagates approval to the shared moderation policy.
 *
 * LMS comments never enter the first-comment moderation: they are published on
 * creation. A published LMS comment then counts as approved participation in
 * the non-LMS contexts and releases the temporary topic-creation block. A
 * pending LMS comment proves nothing and keeps the block in place.
 *
 * The policy service and its hook are instantiated from the test container
 * because enabling aculta_user pulls its full runtime dependency chain; the
 * aculta_user_test fixture supplies only the settings schema.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsModerationPropagationKernelTest extends KernelTestBase {

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
      'label' => 'Discussion post',
      'target_entity_type_id' => 'node',
    ])->save();
    foreach (['aculta_forum_topic', 'blog', 'aculta_lms_topic'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
      $this->addDefaultCommentField(
        'node',
        $bundle,
        'field_aculta_discussion',
        CommentingStatus::Open,
        ParticipationRules::DISCUSSION_COMMENT_TYPE,
      );
    }

    // Only the temporary block is under test: the published-comment minimum
    // would forbid every fresh account for an unrelated reason.
    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('primeiro_comentario_discussao_moderado', TRUE)
      ->set('papeis_isentos', [])
      ->set('topico_limite_ativo', FALSE)
      ->set('bloqueio_ate_aprovacao', TRUE)
      ->save();
  }

  /**
   * A published LMS comment releases the temporary topic-creation block.
   */
  public function testPublishedLmsCommentReleasesTheTemporaryBlock(): void {
    $account = $this->createAccount();
    $rules = $this->createRules();
    $hooks = new ParticipationHooks($rules);
    $uid = (int) $account->id();

    $pendingPublic = $this->newComment('blog', $uid);
    $hooks->commentPresave($pendingPublic);
    self::assertFalse(
      $pendingPublic->isPublished(),
      'The first non-LMS comment still enters moderation.',
    );
    $pendingPublic->save();

    self::assertFalse($rules->hasPublishedDiscussionComment($uid));
    self::assertTrue($rules->firstDiscussionCommentPending($uid));
    $this->assertTopicCreationBlocked($hooks, $account);

    $lms = $this->newComment('aculta_lms_topic', $uid);
    $hooks->commentPresave($lms);
    self::assertTrue(
      $lms->isPublished(),
      'LMS comments never enter the first-comment moderation.',
    );
    $lms->save();

    self::assertTrue(
      $rules->hasPublishedDiscussionComment($uid),
      'A published LMS comment counts as approved participation.',
    );
    self::assertFalse(
      $rules->firstDiscussionCommentPending($uid),
      'The published LMS comment clears the temporary block.',
    );

    $access = $hooks->entityCreateAccess(
      $account,
      ['entity_type_id' => 'node'],
      'aculta_forum_topic',
    );
    self::assertNotNull($access);
    self::assertTrue(
      $access->isNeutral(),
      'The released block no longer forbids topic creation.',
    );
    self::assertContains('comment_list', $access->getCacheTags());
    self::assertContains('config:aculta_user.settings', $access->getCacheTags());
    self::assertContains('user', $access->getCacheContexts());
    self::assertContains('user.roles', $access->getCacheContexts());

    $nextPublic = $this->newComment('aculta_forum_topic', $uid);
    $hooks->commentPresave($nextPublic);
    self::assertTrue(
      $nextPublic->isPublished(),
      'The approved participation releases later non-LMS comments.',
    );
  }

  /**
   * A pending LMS comment is not approval and does not release the block.
   */
  public function testPendingLmsCommentDoesNotReleaseTheTemporaryBlock(): void {
    $account = $this->createAccount();
    $rules = $this->createRules();
    $hooks = new ParticipationHooks($rules);
    $uid = (int) $account->id();

    $pendingPublic = $this->newComment('blog', $uid);
    $hooks->commentPresave($pendingPublic);
    $pendingPublic->save();
    $this->assertTopicCreationBlocked($hooks, $account);

    // A Comment stored unpublished in the LMS bundle never becomes approval;
    // the policy sees it neither as published nor as a non-LMS pending entry.
    $pendingLms = $this->newComment('aculta_lms_topic', $uid);
    $pendingLms->setUnpublished()->save();
    self::assertFalse($rules->hasPublishedDiscussionComment($uid));

    self::assertTrue(
      $rules->firstDiscussionCommentPending($uid),
      'Only the pending non-LMS comment keeps the block, and the LMS comment does not clear it.',
    );
    $this->assertTopicCreationBlocked($hooks, $account);

    $lmsOnly = $this->createAccount();
    $this->newComment('aculta_lms_topic', (int) $lmsOnly->id())
      ->setUnpublished()
      ->save();
    self::assertFalse(
      $rules->hasPendingDiscussionComment((int) $lmsOnly->id()),
      'A pending LMS comment is invisible to the non-LMS pending rule.',
    );
  }

  /**
   * Asserts the temporary block forbids topic creation with cacheability.
   */
  private function assertTopicCreationBlocked(ParticipationHooks $hooks, User $account): void {
    $access = $hooks->entityCreateAccess(
      $account,
      ['entity_type_id' => 'node'],
      'aculta_forum_topic',
    );
    self::assertNotNull($access);
    self::assertTrue(
      $access->isForbidden(),
      'The temporary block forbids topic creation until approval.',
    );
    self::assertContains('config:aculta_user.settings', $access->getCacheTags());
    self::assertContains('user.roles', $access->getCacheContexts());
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
   * Creates a regular account without exempt roles.
   */
  private function createAccount(): User {
    $account = User::create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);
    $account->save();
    return $account;
  }

  /**
   * Creates a host Node and a new published Comment for its shared field.
   */
  private function newComment(string $hostBundle, int $uid): Comment {
    $host = Node::create([
      'type' => $hostBundle,
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
