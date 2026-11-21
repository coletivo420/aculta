<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Controller\ForumPostRevisionHistoryController;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
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
    $rows = $this->historyRows($build);
    self::assertCount(2, $rows);

    $newest = $rows[0];
    $this->assertPlainTextCell($newest, 0, (string) $this->secondRevision);
    $this->assertPlainTextCell($newest, 3, 'published');
    $this->assertPlainTextCell($newest, 4, 'Segunda versão');
    $this->assertTranslatedCell($newest, 6, 'Revisão atual');
    $this->assertComparisonCell($newest, 7, $this->firstRevision, $this->secondRevision);

    $oldest = $rows[1];
    $this->assertPlainTextCell($oldest, 0, (string) $this->firstRevision);
    $this->assertPlainTextCell($oldest, 3, 'review');
    $this->assertTranslatedCell($oldest, 6, 'Histórica');
    $this->assertEmptyOperationsCell($oldest, 7);
    $back = $build['back'];
    self::assertIsArray($back);
    self::assertInstanceOf(Url::class, $back['#url']);
    self::assertSame('aculta_forum.post_moderation_queue', $back['#url']->getRouteName());
  }

  /**
   * Validates and returns the rows from the controller's history render array.
   *
   * @param array<array-key, mixed> $build
   *   The controller render array.
   *
   * @return list<array<array-key, mixed>>
   *   Validated history rows.
   */
  private function historyRows(array $build): array {
    $history = $build['history'];
    self::assertIsArray($history);
    self::assertSame('portal_component', $history['#theme']);
    self::assertSame('forum-revision-history', $history['#name']);
    $table = $history['#slots']['revisions'];
    self::assertIsArray($table);
    self::assertSame('table', $table['#type']);
    $rows = $table['#rows'];
    self::assertIsArray($rows);
    $validatedRows = [];
    foreach ($rows as $row) {
      self::assertIsArray($row);
      $validatedRows[] = $row;
    }
    return $validatedRows;
  }

  /**
   * Asserts one plain-text table cell.
   *
   * @param array<array-key, mixed> $row
   *   The table row.
   * @param int $column
   *   The column index.
   * @param string $expected
   *   The expected cell text.
   */
  private function assertPlainTextCell(array $row, int $column, string $expected): void {
    $cell = $row[$column];
    self::assertIsArray($cell);
    $data = $cell['data'];
    self::assertIsArray($data);
    self::assertSame($expected, $data['#plain_text']);
  }

  /**
   * Asserts one translated table cell.
   *
   * @param array<array-key, mixed> $row
   *   The table row.
   * @param int $column
   *   The column index.
   * @param string $expected
   *   The expected translated text.
   */
  private function assertTranslatedCell(array $row, int $column, string $expected): void {
    $cell = $row[$column];
    self::assertIsArray($cell);
    $data = $cell['data'];
    self::assertIsArray($data);
    self::assertInstanceOf(TranslatableMarkup::class, $data['#plain_text']);
    self::assertSame($expected, (string) $data['#plain_text']);
  }

  /**
   * Asserts the previous-revision comparison link in a table cell.
   *
   * @param array<array-key, mixed> $row
   *   The table row.
   * @param int $column
   *   The column index.
   * @param int $leftRevision
   *   The revision on the left side of the comparison.
   * @param int $rightRevision
   *   The revision on the right side of the comparison.
   */
  private function assertComparisonCell(array $row, int $column, int $leftRevision, int $rightRevision): void {
    $cell = $row[$column];
    self::assertIsArray($cell);
    $operations = $cell['data'];
    self::assertIsArray($operations);
    $comparison = $operations[0];
    self::assertIsArray($comparison);
    $url = $comparison['#url'];
    self::assertInstanceOf(Url::class, $url);
    self::assertSame('aculta_forum.post_revision_compare', $url->getRouteName());
    $parameters = $url->getRouteParameters();
    self::assertSame($leftRevision, $parameters['left_revision']);
    self::assertSame($rightRevision, $parameters['right_revision']);
  }

  /**
   * Asserts that a table cell has no operations.
   *
   * @param array<array-key, mixed> $row
   *   The table row.
   * @param int $column
   *   The column index.
   */
  private function assertEmptyOperationsCell(array $row, int $column): void {
    $cell = $row[$column];
    self::assertIsArray($cell);
    self::assertSame([], $cell['data']);
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
