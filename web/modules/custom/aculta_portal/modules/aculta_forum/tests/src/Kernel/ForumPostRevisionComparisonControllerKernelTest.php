<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Controller\ForumPostRevisionComparisonController;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Proves the revision comparison stays inside one post and shows differences.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostRevisionComparisonControllerKernelTest extends KernelTestBase {

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
    'domain',
    'diff',
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
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'diff']);
    $this->container->get('config.factory')->getEditable('diff.plugins')
      ->set('fields.aculta_forum_post.subject', ['type' => 'core_field_diff_builder', 'settings' => []])
      ->set('fields.aculta_forum_post.body', ['type' => 'text_field_diff_builder', 'settings' => []])
      ->save();
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'aculta_forum_post',
      'bundle' => 'aculta_forum_post',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('subject', ['type' => 'string']);
    $display->setComponent('body', ['type' => 'text_default']);
    $display->save();
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    Role::load(RoleInterface::AUTHENTICATED_ID)->grantPermission('access content')->save();
    User::create(['name' => 'base', 'status' => 1])->save();
    $author = User::create(['name' => 'autora', 'status' => 1]);
    $author->save();
    $this->container->get('current_user')->setAccount($author);
    $this->post = ForumPost::create([
      'uid' => $author->id(),
      'topic' => 1,
      'subject' => 'Primeiro assunto',
      'body' => ['value' => 'Primeiro texto', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $this->post->save();
    $this->firstRevision = (int) $this->post->getRevisionId();
    $this->post->setNewRevision(TRUE);
    $this->post->set('subject', 'Segundo assunto');
    $this->post->set('body', ['value' => 'Primeiro texto', 'format' => 'plain_text']);
    $this->post->save();
    $this->secondRevision = (int) $this->post->getRevisionId();
  }

  /**
   * Only differing fields appear, labeled for the reader.
   */
  public function testComparisonShowsOnlyChangedFields(): void {
    $build = $this->controller()->compare(
      $this->post,
      (string) $this->firstRevision,
      (string) $this->secondRevision,
    );

    self::assertSame(
      'Comparando revisão ' . $this->firstRevision . ' com ' . $this->secondRevision,
      (string) $build['#title'],
    );
    self::assertSame('table', $build['comparison']['#type']);
    $rows = $build['comparison']['#rows'];
    $labels = [];
    foreach ($rows as $row) {
      if (isset($row[0]['data']['#plain_text'])) {
        $labels[] = (string) $row[0]['data']['#plain_text'];
      }
    }
    self::assertContains('Assunto', $labels);
    self::assertNotContains('Texto', $labels);
    self::assertSame('aculta_forum.post_revision_history', $build['back']['#url']->getRouteName());
    self::assertContains('domain', $build['#cache']['contexts']);
  }

  /**
   * Identical, unknown or foreign revisions are refused.
   */
  public function testInvalidComparisonsAreNotFound(): void {
    self::assertTrue($this->isNotFound(fn () => $this->controller()->compare(
      $this->post,
      (string) $this->firstRevision,
      (string) $this->firstRevision,
    )));

    self::assertTrue($this->isNotFound(fn () => $this->controller()->compare(
      $this->post,
      (string) $this->firstRevision,
      '999999',
    )));

    $other = ForumPost::create([
      'uid' => $this->post->getOwnerId(),
      'topic' => 1,
      'subject' => 'Outra publicação',
      'body' => ['value' => 'Outro texto', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $other->save();
    self::assertTrue($this->isNotFound(fn () => $this->controller()->compare(
      $this->post,
      (string) $this->firstRevision,
      (string) $other->getRevisionId(),
    )));
  }

  /**
   * Runs the comparison and reports whether it raised not found.
   */
  private function isNotFound(callable $callback): bool {
    try {
      $callback();
    }
    catch (NotFoundHttpException) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Builds the comparison controller with production services.
   */
  private function controller(): ForumPostRevisionComparisonController {
    return new ForumPostRevisionComparisonController(
      $this->container->get('entity_type.manager'),
      $this->container->get('diff.entity_comparison'),
    );
  }

}
