<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\Controller\ActivityController;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies that My Forum topics use Core's published-comment aggregate.
 */
#[CoversClass(ActivityController::class)]
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class ActivityControllerKernelTest extends KernelTestBase {

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
    'domain',
  ];

  /**
   * Published replies appear in a person's topic count; pending replies do not.
   */
  public function testForumTopicListShowsPublishedReplyCount(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('domain');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'user', 'filter', 'node', 'comment', 'domain']);
    $this->container->get('state')->set('comment.maintain_entity_statistics', TRUE);

    $accountDomain = Domain::create([
      'id' => 'conta_aculta_org',
      'hostname' => 'account.example.test',
      'name' => 'Account test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
    ]);
    $accountDomain->save();
    Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => 'forum.example.test',
      'name' => 'Forum test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
    ])->save();

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
      'settings' => ['handler' => 'default:domain', 'handler_settings' => []],
    ])->save();
    CommentType::create([
      'id' => 'aculta_discussion_post',
      'label' => 'Discussion post',
      'target_entity_type_id' => 'node',
    ])->save();
    $this->addDefaultCommentField(
      'node',
      'aculta_forum_topic',
      'field_aculta_discussion',
      CommentingStatus::Open,
      'aculta_discussion_post',
    );

    $role = Role::load('authenticated');
    if (!$role instanceof Role) {
      $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated']);
    }
    $role->grantPermission('access content')->save();
    $account = User::create([
      'name' => 'activity-reader',
      'mail' => 'activity-reader@example.test',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $account->save();
    $currentUser = $this->container->get('current_user');
    $currentUser->setAccount($account);

    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Topic with published replies',
      'uid' => $account->id(),
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
    ]);
    $topic->save();
    $this->createComment($topic, 1, 'First published reply');
    $this->createComment($topic, 1, 'Second published reply');
    $this->createComment($topic, 0, 'Pending reply');

    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->setActiveDomain($accountDomain);
    $entityTypeManager = $this->container->get('entity_type.manager');
    $purposeManager = new DomainPurposeManager(
      $entityTypeManager,
      $negotiator,
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
    $controller = new ActivityController(
      $entityTypeManager,
      $currentUser,
      $purposeManager,
      $this->container->get('module_handler'),
      $this->container->get('string_translation'),
    );
    $nodeList = new \ReflectionMethod($controller, 'nodeList');
    $build = $nodeList->invoke($controller, [(int) $topic->id()], 'aculta_forum_topic', '', FALSE, TRUE);

    self::assertCount(1, $build['#items']);
    self::assertSame(
      '2 comentários — Topic with published replies',
      (string) $build['#items'][0]['#title'],
    );
    self::assertSame(
      (int) $accountDomain->id(),
      (int) $negotiator->getActiveDomain()?->id(),
      'The controller restores the account purpose after evaluating Forum access.',
    );
  }

  /**
   * Creates a published or pending reply using Drupal Comment storage.
   */
  private function createComment(Node $topic, int $status, string $subject): Comment {
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'uid' => 0,
      'name' => 'Anonymous test participant',
      'subject' => $subject,
      'comment_body' => ['value' => $subject, 'format' => 'plain_text'],
      'status' => $status,
    ]);
    $comment->save();
    return $comment;
  }

}
