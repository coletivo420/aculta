<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Controller\ForumPostRevisionHistoryController;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the revision history lists revisions newest first with comparisons.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostRevisionHistoryControllerKernelTest extends KernelTestBase {

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
    'workflows',
    'content_moderation',
    'aculta_forum_post_test',
  ];

  /**
   * Post with two revisions.
   */
  private ForumPost $post;

  /**
   * First revision ID.
   */
  private int $firstRevision;

  /**
   * Second revision ID.
   */
  private int $secondRevision;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('config.storage')->write('workflows.workflow.aculta_forum_post', [
      'langcode' => 'pt-br',
      'status' => TRUE,
      'id' => 'aculta_forum_post',
      'label' => 'Forum post moderation workflow',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
          'review' => ['label' => 'Review', 'weight' => 1, 'published' => FALSE, 'default_revision' => FALSE],
          'published' => ['label' => 'Published', 'weight' => 2, 'published' => TRUE, 'default_revision' => TRUE],
        ],
        'transitions' => [
          'approve' => ['label' => 'Approve', 'from' => ['review'], 'to' => 'published', 'weight' => 0],
        ],
        'entity_types' => ['aculta_forum_post' => ['aculta_forum_post']],
        'default_moderation_state' => 'draft',
      ],
    ]);
    $this->container->get('config.factory')->reset('workflows.workflow.aculta_forum_post');
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('aculta_forum_post');
    $this->installConfig([
      'system',
      'user',
      'field',
      'filter',
      'text',
      'node',
      'workflows',
      'content_moderation',
    ]);
    User::create(['name' => 'base', 'status' => 1])->save();
    $author = User::create(['name' => 'autora', 'status' => 1]);
    $author->save();
    $this->post = ForumPost::create([
      'uid' => $author->id(),
      'topic' => 1,
      'subject' => 'Primeira versão',
      'body' => ['value' => 'Primeira versão', 'format' => 'plain_text'],
      'moderation_state' => 'review',
    ]);
    $this->post->save();
    $this->firstRevision = (int) $this->post->getRevisionId();
    $this->post->setNewRevision(TRUE);
    $this->post->set('subject', 'Segunda versão');
    $this->post->set('body', ['value' => 'Segunda versão', 'format' => 'plain_text']);
    $this->post->set('moderation_state', 'published');
    $this->post->save();
    $this->secondRevision = (int) $this->post->getRevisionId();
  }

  /**
   * The newest revision comes first and links to the previous one.
   */
  public function testHistoryListsRevisionsNewestFirst(): void {
    $build = $this->controller()->history($this->post);

    self::assertSame('container', $build['#type']);
    self::assertArrayNotHasKey('comparison_selection', $build);
    $rows = $build['history']['#rows'];
    self::assertCount(2, $rows);

    $newest = $rows[0];
    self::assertSame((string) $this->secondRevision, $newest[0]['data']['#plain_text']);
    self::assertSame('published', $newest[3]['data']['#plain_text']);
    self::assertSame('Segunda versão', $newest[4]['data']['#plain_text']);
    self::assertSame('Revisão atual', (string) $newest[6]['data']['#plain_text']);
    $comparison = $newest[7]['data'][0];
    self::assertSame('aculta_forum.post_revision_compare', $comparison['#url']->getRouteName());
    self::assertSame((string) $this->firstRevision, (string) $comparison['#url']->getRouteParameters()['left_revision']);
    self::assertSame((string) $this->secondRevision, (string) $comparison['#url']->getRouteParameters()['right_revision']);

    $oldest = $rows[1];
    self::assertSame((string) $this->firstRevision, $oldest[0]['data']['#plain_text']);
    self::assertSame('review', $oldest[3]['data']['#plain_text']);
    self::assertSame('Histórica', (string) $oldest[6]['data']['#plain_text']);
    self::assertSame([], $oldest[7]['data']);
    self::assertSame('aculta_forum.post_moderation_queue', $build['back']['#url']->getRouteName());
  }

  /**
   * Builds the history controller with production services.
   */
  private function controller(): ForumPostRevisionHistoryController {
    return new ForumPostRevisionHistoryController(
      $this->container->get('entity_type.manager'),
      $this->container->get('date.formatter'),
      $this->container->get('form_builder'),
    );
  }

}
