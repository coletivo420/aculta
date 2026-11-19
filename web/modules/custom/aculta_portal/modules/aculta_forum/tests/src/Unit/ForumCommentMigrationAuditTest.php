<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Migration\ForumCommentMigrationAudit;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the read-only Forum Comment migration audit report.
 */
#[Group('aculta_forum')]
final class ForumCommentMigrationAuditTest extends UnitTestCase {

  /**
   * An empty source produces a complete aggregate-only report.
   */
  public function testInspectReturnsEmptyReportWithoutExposingEntityData(): void {
    $comment_query = $this->createMock(QueryInterface::class);
    $comment_query->method('condition')->willReturnSelf();
    $comment_query->method('accessCheck')->willReturnSelf();
    $comment_query->method('count')->willReturnSelf();
    $comment_query->method('sort')->willReturnSelf();
    $comment_query->method('range')->willReturnSelf();
    $comment_query->method('execute')->willReturn([]);

    $post_query = $this->createMock(QueryInterface::class);
    $post_query->method('accessCheck')->willReturnSelf();
    $post_query->method('count')->willReturnSelf();
    $post_query->method('execute')->willReturn(0);

    $comment_storage = $this->createMock(EntityStorageInterface::class);
    $comment_storage->method('getQuery')->willReturn($comment_query);
    $post_storage = $this->createMock(EntityStorageInterface::class);
    $post_storage->method('getQuery')->willReturn($post_query);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnMap([
      ['comment', $comment_storage],
      ['aculta_forum_post', $post_storage],
    ]);
    $entity_type_manager->method('getDefinition')->willReturn(NULL);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('moduleExists')->with('aculta_messenger')->willReturn(FALSE);
    $audit = new ForumCommentMigrationAudit(
      $entity_type_manager,
      new DomainSiteManager(
        $entity_type_manager,
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
      $module_handler,
    );

    $report = $audit->inspect();

    self::assertSame(0, $report['source_comments']);
    self::assertSame(0, $report['eligible_comments']);
    self::assertSame(0, $report['existing_revisionable_posts']);
    self::assertSame([], $report['body_formats']);
    self::assertSame([], $report['languages']);
    self::assertSame([], $report['flaggings_by_flag']);
    self::assertSame([
      'available' => FALSE,
      'records' => 0,
      'records_with_comment_marker' => 0,
      'records_with_post_marker' => 0,
      'markers_in_scope' => 0,
      'markers_outside_scope' => 0,
      'markers_missing' => 0,
      'markers_for_other_topic' => 0,
      'post_markers_in_scope' => 0,
      'post_markers_outside_scope' => 0,
      'post_markers_missing' => 0,
      'post_markers_for_other_topic' => 0,
    ], $report['response_notices']);
  }

  /**
   * A batch size below the supported range is rejected before querying.
   */
  public function testRejectsBatchSizeBelowMinimum(): void {
    $audit = $this->createAuditWithoutDatabaseDependencies();

    $this->expectException(\InvalidArgumentException::class);
    $audit->inspect(0);
  }

  /**
   * A batch size above the supported range is rejected before querying.
   */
  public function testRejectsBatchSizeAboveMaximum(): void {
    $audit = $this->createAuditWithoutDatabaseDependencies();

    $this->expectException(\InvalidArgumentException::class);
    $audit->inspect(1001);
  }

  /**
   * Creates the service with dependencies unused by batch-size validation.
   */
  private function createAuditWithoutDatabaseDependencies(): ForumCommentMigrationAudit {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    return new ForumCommentMigrationAudit(
      $entity_type_manager,
      new DomainSiteManager(
        $entity_type_manager,
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
      $this->createMock(ModuleHandlerInterface::class),
    );
  }

}
