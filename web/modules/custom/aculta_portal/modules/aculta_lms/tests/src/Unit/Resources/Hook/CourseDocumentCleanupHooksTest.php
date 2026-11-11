<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit\Resources\Hook;

use Drupal\aculta_lms\Resources\Cleanup\CourseDocumentCleanupInterface;
use Drupal\aculta_lms\Hook\CourseDocumentCleanupHooks;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

/**

 */
#[Group('aculta_lms')]
#[CoversClass(\Drupal\aculta_lms\Resources\Hook\CourseDocumentCleanupHooks::class)]
final class CourseDocumentCleanupHooksTest extends UnitTestCase {

  /**
   * Course document relationships submit their Media ID for cleanup.
   *
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
