<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Form\ForumTopicMoveForm;
use Drupal\aculta_forum\Plugin\Action\MoveForumTopicsAction;
use Drupal\aculta_forum\Service\ForumTopicMover;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Verifies category movement through real Node, Taxonomy and Comment storage.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumTopicMoverKernelTest extends KernelTestBase {

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
    'taxonomy',
    'comment',
    'path',
    'path_alias',
    'options',
    'domain',
  ];

  /**
   * The topic mover under test. */
  private ForumTopicMover $mover;

  /**
   * Account carrying the explicit topic-move permission. */
  private AccountInterface $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'filter', 'node', 'taxonomy', 'comment', 'domain', 'path', 'path_alias']);
    $this->container->get('state')->set('comment.maintain_entity_statistics', TRUE);

    Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => 'forum.example.test',
      'name' => 'Forum test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
    ])->save();
    Vocabulary::create(['vid' => 'aculta_forum_categories', 'name' => 'Forum categories'])->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic', 'new_revision' => TRUE])->save();

    $this->addField('entity_reference', 'field_domain_source', ['target_type' => 'domain'], 'node', 'aculta_forum_topic');
    $this->addField('string', 'field_autor_legado', [], 'node', 'aculta_forum_topic');
    $this->addField('entity_reference', 'field_aculta_categories', [
      'target_type' => 'taxonomy_term',
      'handler' => 'default:taxonomy_term',
      'handler_settings' => ['target_bundles' => ['aculta_forum_categories' => 'aculta_forum_categories']],
    ], 'node', 'aculta_forum_topic');
    $this->addDefaultCommentField('node', 'aculta_forum_topic', 'field_aculta_discussion', CommentingStatus::Open, 'aculta_discussion_post');

    $this->account = $this->createMock(AccountInterface::class);
    $this->account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, [
        'access content',
        'move aculta forum topics',
        'administer nodes',
      ], TRUE),
    );
    $this->account->method('id')->willReturn(42);
    $this->account->method('isAnonymous')->willReturn(FALSE);
    $this->account->method('isAuthenticated')->willReturn(TRUE);
    $manager = $this->container->get('entity_type.manager');
    $purposes = new DomainPurposeManager(
      $manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $this->mover = new ForumTopicMover(
      $manager,
      $purposes,
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Moving a topic keeps its identity, alias, Comment and authoring data.
   */
  public function testMovePreservesTopicIdentityAndDiscussion(): void {
    $source = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Source']);
    $source->save();
    $destination = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Destination']);
    $destination->save();
    $node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Movable test topic',
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_categories' => ['target_id' => $source->id()],
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
      'field_autor_legado' => [],
      'path' => ['alias' => '/topico-movivel-teste'],
    ]);
    $node->save();
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'subject' => 'Reply preserved',
      'comment_body' => ['value' => 'Reply body', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $comment->save();
    $nid = (int) $node->id();
    $uuid = $node->uuid();
    $original_vid = (int) $node->getRevisionId();
    $cid = (int) $comment->id();
    $statistics = $this->container->get('comment.statistics')->read([$nid => $node], 'node');
    self::assertCount(1, $statistics);
    self::assertSame(1, (int) $statistics[0]->comment_count, 'Core statistics count the published reply.');

    $pending_comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $nid,
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'subject' => 'Pending reply',
      'comment_body' => ['value' => 'Pending reply body', 'format' => 'plain_text'],
      'status' => 0,
    ]);
    $pending_comment->save();
    $statistics = $this->container->get('comment.statistics')->read([$nid => $node], 'node');
    self::assertSame(1, (int) $statistics[0]->comment_count, 'Unpublished replies do not enter public statistics.');

    $access = $this->mover->access($node, $this->account);
    self::assertTrue($access->isAllowed());

    $second_node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Second movable test topic',
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_categories' => ['target_id' => $source->id()],
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
      'field_autor_legado' => [],
    ]);
    $second_node->save();
    $selection = [
      'list' => [
        [$nid, 'en', 'node', $nid],
        [(int) $second_node->id(), 'en', 'node', (int) $second_node->id()],
      ],
      'exclude_mode' => FALSE,
    ];
    $action = new MoveForumTopicsAction(
      ['destination_category' => (int) $destination->id()],
      'aculta_forum_move_topics',
      [],
      $this->mover,
      $this->container->get('entity_type.manager'),
      $this->account,
    );

    // VBO's ConfigureAction stores this data in FormState, then creates a new
    // plugin instance for validation without restoring the action context.
    $configuration_state = new FormState();
    $configuration_state->set('views_bulk_operations', $selection + [
      'view_id' => 'aculta_forum_admin_topics',
      'display_id' => 'page_forum',
      'total_results' => 2,
    ]);
    $configuration_state->setValue('destination_category', (int) $destination->id());
    $validation_action = new MoveForumTopicsAction(
      [],
      'aculta_forum_move_topics',
      [],
      $this->mover,
      $this->container->get('entity_type.manager'),
      $this->account,
    );
    $validation_errors = [];
    $validation_action->validateConfigurationForm($validation_errors, $configuration_state);
    self::assertArrayNotHasKey('', $configuration_state->getErrors());
    self::assertArrayNotHasKey('destination_category', $configuration_state->getErrors());

    $action->setContext($selection);
    $results = $action->executeMultiple([$node, $second_node]);
    self::assertCount(2, $results, 'VBO reports one result for each selected topic.');
    self::assertArrayHasKey('message', $results[0]);
    self::assertSame('status', $results[0]['type'] ?? 'status');
    $moved = Node::load($nid);
    self::assertNotNull($moved);
    $moved_second = Node::load((int) $second_node->id());
    self::assertNotNull($moved_second);
    self::assertSame((int) $destination->id(), (int) $moved_second->get('field_aculta_categories')->target_id);

    $oversized_node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Oversized selection test topic',
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_categories' => ['target_id' => $source->id()],
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
      'field_autor_legado' => [],
    ]);
    $oversized_node->save();
    $oversized_selection = [
      'list' => array_map(static fn (int $id): array => [$id, 'en', 'node', $id], range(1, MoveForumTopicsAction::MAX_SELECTION + 1)),
      'exclude_mode' => FALSE,
    ];
    $action->setContext($oversized_selection);
    $oversized_results = $action->executeMultiple([$oversized_node]);
    self::assertSame('error', $oversized_results[0]['type']);
    self::assertSame((int) $source->id(), (int) Node::load($oversized_node->id())->get('field_aculta_categories')->target_id);

    self::assertSame($nid, (int) $moved->id());
    self::assertSame($uuid, $moved->uuid());
    self::assertNotSame($original_vid, (int) $moved->getRevisionId());
    self::assertSame((int) $destination->id(), (int) $moved->get('field_aculta_categories')->target_id);
    self::assertSame('/topico-movivel-teste', $moved->get('path')->alias);
    $reloaded_comment = Comment::load($cid);
    self::assertNotNull($reloaded_comment);
    self::assertSame($nid, (int) $reloaded_comment->getCommentedEntityId());
    self::assertSame('Reply body', $reloaded_comment->get('comment_body')->value);
    $statistics = $this->container->get('comment.statistics')->read([$nid => $moved], 'node');
    self::assertSame(1, (int) $statistics[0]->comment_count, 'Category movement preserves Core reply statistics.');
  }

  /**
   * Published reply totals follow approval, unpublishing and deletion.
   */
  public function testPublishedReplyStatisticsFollowModerationLifecycle(): void {
    $node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Moderation statistics test topic',
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_discussion' => ['status' => CommentingStatus::Open->value],
    ]);
    $node->save();

    $pending = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
      'subject' => 'Pending reply',
      'comment_body' => ['value' => 'Pending reply body', 'format' => 'plain_text'],
      'status' => 0,
    ]);
    $pending->save();
    $statistics = $this->container->get('comment.statistics')->read([(int) $node->id() => $node], 'node');
    self::assertSame(0, (int) $statistics[0]->comment_count, 'Pending replies are excluded from public totals.');

    $pending->setPublished()->save();
    $statistics = $this->container->get('comment.statistics')->read([(int) $node->id() => $node], 'node');
    self::assertSame(1, (int) $statistics[0]->comment_count, 'Approval adds a reply to public totals.');

    $pending->setUnpublished()->save();
    $statistics = $this->container->get('comment.statistics')->read([(int) $node->id() => $node], 'node');
    self::assertSame(0, (int) $statistics[0]->comment_count, 'Unpublishing removes a reply from public totals.');

    $pending->setPublished()->save();
    $pending->delete();
    $statistics = $this->container->get('comment.statistics')->read([(int) $node->id() => $node], 'node');
    self::assertSame(0, (int) $statistics[0]->comment_count, 'Deleting a published reply removes it from public totals.');
  }

  /**
   * The confirmation form offers the other categories and requires consent.
   */
  public function testMoveFormOffersOtherCategories(): void {
    $source = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Origem']);
    $source->save();
    $destination = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Destino']);
    $destination->save();
    $node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico com formulário',
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_categories' => ['target_id' => $source->id()],
      'field_autor_legado' => [],
    ]);
    $node->save();

    $form = new ForumTopicMoveForm(
      $this->mover,
      $this->container->get('entity_type.manager'),
      $this->account,
    );
    $state = new FormState();
    $build = $form->buildForm([], $state, $node);

    self::assertSame((int) $node->id(), $state->get('topic_id'));
    self::assertSame('select', $build['destination']['#type']);
    self::assertTrue($build['destination']['#required']);
    self::assertArrayNotHasKey($source->id(), $build['destination']['#options'], 'A categoria atual não é destino.');
    self::assertArrayHasKey($destination->id(), $build['destination']['#options']);
    self::assertSame('Destino', $build['destination']['#options'][$destination->id()]);
    self::assertSame('checkbox', $build['confirm_move']['#type']);
    self::assertTrue($build['confirm_move']['#required']);
    self::assertSame('aculta_forum.admin', $build['actions']['cancel']['#url']->getRouteName());
    self::assertSame('Mover tópico', (string) $build['actions']['submit']['#value']);

    // The move requires both a numeric destination and explicit consent.
    $invalid = new FormState();
    $invalid->setValue('destination', $destination->id());
    $invalid->setValue('confirm_move', FALSE);
    $form->validateForm($build, $invalid);
    self::assertArrayHasKey('destination', $invalid->getErrors());
  }

  /**
   * Historical topics cannot be moved even with the operation permission.
   */
  public function testHistoricalTopicCannotBeMoved(): void {
    $category = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Historic']);
    $category->save();
    $node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Historical test topic',
      'status' => 1,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
      'field_aculta_categories' => ['target_id' => $category->id()],
      'field_autor_legado' => ['value' => 'historical-author-reference'],
    ]);
    $node->save();
    $destination = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Another']);
    $destination->save();
    $original_category = (int) $node->get('field_aculta_categories')->target_id;

    self::assertTrue($this->mover->access($node, $this->account)->isForbidden());
    $selection = ['list' => [[(int) $node->id(), 'en', 'node', (int) $node->id()]], 'exclude_mode' => FALSE];
    $action = new MoveForumTopicsAction(
      ['destination_category' => (int) $destination->id()],
      'aculta_forum_move_topics',
      [],
      $this->mover,
      $this->container->get('entity_type.manager'),
      $this->account,
    );
    $action->setContext($selection);
    self::assertTrue($action->access($node, $this->account, TRUE)->isForbidden());
    $action_result = $action->execute($node);
    self::assertSame('warning', $action_result['type']);
    self::assertSame($original_category, (int) Node::load($node->id())->get('field_aculta_categories')->target_id);
    try {
      $this->mover->move((int) $node->id(), (int) $destination->id(), $this->account);
      self::fail('Historical topics must not be moved, including by administrators.');
    }
    catch (\InvalidArgumentException) {
      self::assertSame($original_category, (int) Node::load($node->id())->get('field_aculta_categories')->target_id);
    }

    $form = new ForumTopicMoveForm(
      $this->mover,
      $this->container->get('entity_type.manager'),
      $this->account,
    );
    try {
      $form->buildForm([], new FormState(), $node);
      self::fail('The topic move form must deny historical topics to administrators.');
    }
    catch (AccessDeniedHttpException) {
      self::assertSame($original_category, (int) Node::load($node->id())->get('field_aculta_categories')->target_id);
    }
  }

  /**
   * Creates a field storage and bundle instance for the test topic.
   */
  private function addField(string $type, string $name, array $settings, string $entityType, string $bundle): void {
    FieldStorageConfig::create([
      'entity_type' => $entityType,
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
    ])->save();
    FieldConfig::create([
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
