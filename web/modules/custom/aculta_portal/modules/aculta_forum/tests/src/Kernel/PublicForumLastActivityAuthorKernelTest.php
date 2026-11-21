<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Hook\PublicForumLastActivityAuthorHooks;
use Drupal\aculta_forum\Service\ForumPublicPostStatistics;
use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\aculta_user\Social\SocialNetworks;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\profile\ProfileStorageInterface;
use Drupal\user\Entity\User;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies historical authors are used for the latest public Forum activity.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class PublicForumLastActivityAuthorKernelTest extends KernelTestBase {

  use CommentTestTrait;

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
    'comment',
    'views',
    'aculta_forum_post_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'comment']);

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    $this->addDefaultCommentField('node', 'aculta_forum_topic', 'field_aculta_discussion', CommentingStatus::Open, 'aculta_discussion_post');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Uses only the Drupal account owner for current ForumPost activity.
   */
  public function testLatestPostUsesCurrentAuthorWhenNoPublicCardExists(): void {
    $account = User::create(['name' => 'forum-owner', 'status' => 1]);
    $account->save();
    $topicId = 7001;
    $timestamp = 1700000300;
    $post = ForumPost::create([
      'uid' => $account->id(),
      'topic' => $topicId,
      'body' => ['value' => 'Resposta corrente', 'format' => 'plain_text'],
      'status' => 1,
      'created' => $timestamp,
      'changed' => $timestamp,
    ]);
    $post->save();

    $postStorage = $this->createMock(EntityStorageInterface::class);
    $postStorage->expects(self::once())
      ->method('loadMultiple')
      ->with([(int) $post->id()])
      ->willReturn([(int) $post->id() => $post]);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())
      ->method('loadMultiple')
      ->with([(int) $account->id()])
      ->willReturn([(int) $account->id() => $account]);
    $profileStorage = $this->createMock(ProfileStorageInterface::class);
    $profileStorage->method('loadByUser')->willReturn(NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['aculta_forum_post', $postStorage],
      ['user', $userStorage],
      ['profile', $profileStorage],
    ]);

    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $entityTypeManager),
      new PublicProfilePolicy(),
      new SocialNetworks(),
      $this->createMock(DateFormatterInterface::class),
    );
    $builder = new AuthorCardBuilder($resolver);
    $statistics = new ForumPublicPostStatistics($this->container->get('database'));
    $hooks = new PublicForumLastActivityAuthorHooks($entityTypeManager, $builder, $statistics);

    $row = new ResultRow(['topic_id' => $topicId]);
    $view = $this->getMockBuilder(ViewExecutable::class)->disableOriginalConstructor()->getMock();
    $view->method('id')->willReturn('aculta_forum_public_topics');
    $view->result = [$row];
    $view->field = [
      'nid' => $this->fieldValue('topic_id'),
      'last_comment_uid' => $this->fieldValue('comment_uid', 0),
      'last_comment_timestamp' => $this->fieldValue('comment_timestamp', 0),
      'last_comment_count' => $this->fieldValue('comment_count', 0),
      'last_updated' => $this->fieldValue('last_updated', $timestamp),
    ];
    $variables = ['view' => $view, 'row' => $row];

    $hooks->preprocessViewsViewFields($variables);

    self::assertArrayNotHasKey('last_comment_author', $variables);
    self::assertIsArray($variables['last_comment_author_cache'] ?? NULL);

    $invalidViewVariables = ['view' => new \stdClass()];
    $hooks->preprocessViewsViewFields($invalidViewVariables);
    self::assertArrayNotHasKey('last_comment_author', $invalidViewVariables);
  }

  /**
   * Creates a Views field returning a named fixture value.
   */
  private function fieldValue(string $property, int $default = 0): FieldPluginBase {
    $field = $this->getMockBuilder(FieldPluginBase::class)->disableOriginalConstructor()->getMock();
    $field->method('getValue')->willReturnCallback(
      static function (ResultRow $row) use ($property, $default): int {
        $value = $row->{$property} ?? NULL;
        if (is_int($value)) {
          return $value;
        }
        if ((is_float($value) || is_string($value)) && is_numeric($value)) {
          return (int) $value;
        }

        return $default;
      },
    );
    return $field;
  }

}
