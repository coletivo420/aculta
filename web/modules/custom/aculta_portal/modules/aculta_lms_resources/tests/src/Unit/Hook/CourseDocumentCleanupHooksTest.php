<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit\Hook;

use Drupal\aculta_lms_resources\Cleanup\CourseDocumentCleanupInterface;
use Drupal\aculta_lms_resources\Hook\CourseDocumentCleanupHooks;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_lms_resources\Hook\CourseDocumentCleanupHooks
 * @group aculta_lms_resources
 */
final class CourseDocumentCleanupHooksTest extends UnitTestCase {

  /**
   * Course document relationships submit their Media ID for cleanup.
   *
   * @covers ::relationshipDeleted
   */
  public function testCourseDocumentRelationshipIsSubmitted(): void {
    $cleanup = $this->createMock(CourseDocumentCleanupInterface::class);
    $cleanup->expects(self::once())->method('removeDetachedDocument')->with(73);
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getPluginId')->willReturn('aculta_document_viewer:course_document');
    $relationship->method('getEntityId')->willReturn('73');

    (new CourseDocumentCleanupHooks($cleanup))->relationshipDeleted($relationship);
  }

  /**
   * Other Group relationships do not trigger document cleanup.
   *
   * @covers ::relationshipDeleted
   */
  public function testOtherRelationshipIsIgnored(): void {
    $cleanup = $this->createMock(CourseDocumentCleanupInterface::class);
    $cleanup->expects(self::never())->method('removeDetachedDocument');
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getPluginId')->willReturn('group_node:article');
    $relationship->method('getEntityId')->willReturn('73');

    (new CourseDocumentCleanupHooks($cleanup))->relationshipDeleted($relationship);
  }

}
