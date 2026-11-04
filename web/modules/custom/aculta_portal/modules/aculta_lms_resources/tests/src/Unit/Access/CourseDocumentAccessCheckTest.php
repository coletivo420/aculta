<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit\Access;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms_resources\Access\CourseDocumentAccessCheck;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Group-scoped create and update access for private course documents.
 */
#[CoversClass(CourseDocumentAccessCheck::class)]
#[Group('aculta_lms_resources')]
final class CourseDocumentAccessCheckTest extends UnitTestCase {

  /**
   * A course educator must also hold Group's document-creation permission.
   */
  public function testCreateRequiresCourseAndGroupDocumentPermissions(): void {
    [$check, $account, $course, $manager] = $this->fixtures();
    $accessHandler = $this->createMock(AccessControlInterface::class);
    $accessHandler->expects(self::once())
      ->method('entityCreateAccess')
      ->with($course, $account, TRUE)
      ->willReturn(AccessResult::allowed());
    $manager->expects(self::once())->method('getAccessControlHandler')->with('aculta_document_viewer:course_document')->willReturn($accessHandler);
    $course->expects(self::once())->method('hasPermission')->with('grade students', $account)->willReturn(TRUE);

    self::assertTrue($check->createAccess($course)->isAllowed());
  }

  /**
   * Only an update-authorized educator can edit a document attached to course.
   */
  public function testUpdateRequiresSameCourseAndEntityUpdateAccess(): void {
    [$check, $account, $course, $manager] = $this->fixtures();
    $document = $this->createMock(MediaInterface::class);
    $document->method('bundle')->willReturn('course_document');
    $document->method('id')->willReturn('55');
    $document->method('getCacheTags')->willReturn(['media:55']);
    $document->method('getCacheContexts')->willReturn([]);
    $document->method('getCacheMaxAge')->willReturn(-1);
    $document->expects(self::once())->method('access')->with('update', $account, TRUE)->willReturn(AccessResult::allowed());
    $course->method('getRelatedEntities')->with('aculta_document_viewer:course_document')->willReturn([$document]);
    $course->expects(self::once())->method('hasPermission')->with('grade students', $account)->willReturn(TRUE);
    $manager->expects(self::never())->method('getAccessControlHandler');

    self::assertTrue($check->updateAccess($course, $document)->isAllowed());
  }

  /**
   * A document related to another course cannot be edited from this route.
   */
  public function testUpdateRejectsDocumentOutsideRequestedCourse(): void {
    [$check, , $course] = $this->fixtures();
    $document = $this->createMock(MediaInterface::class);
    $document->method('bundle')->willReturn('course_document');
    $document->method('id')->willReturn('55');
    $document->method('getCacheTags')->willReturn(['media:55']);
    $document->method('getCacheContexts')->willReturn([]);
    $document->method('getCacheMaxAge')->willReturn(-1);
    $document->expects(self::never())->method('access');
    $course->method('getRelatedEntities')->with('aculta_document_viewer:course_document')->willReturn([]);

    self::assertTrue($check->updateAccess($course, $document)->isForbidden());
  }

  /**
   * Creates a real policy checker with isolated Group and account doubles.
   *
   * @return array{\Drupal\aculta_lms_resources\Access\CourseDocumentAccessCheck, \Drupal\Core\Session\AccountProxyInterface, \Drupal\group\Entity\GroupInterface, \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface}
   *   The checker, account, course and relation manager.
   */
  private function fixtures(): array {
    $account = $this->createMock(AccountProxyInterface::class);
    $groupType = $this->createMock(GroupTypeInterface::class);
    $groupType->method('hasPlugin')->with('aculta_document_viewer:course_document')->willReturn(TRUE);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('12');
    $course->method('getGroupType')->willReturn($groupType);
    $course->method('getCacheTags')->willReturn(['group:12']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $manager = $this->createMock(GroupRelationTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturnCallback(static fn(string $pluginId): bool => $pluginId === 'aculta_document_viewer:course_document');
    $authoringAccess = new CourseAuthoringAccessCheck($account, $this->createMock(EntityTypeManagerInterface::class));
    return [new CourseDocumentAccessCheck($authoringAccess, $manager, $account), $account, $course, $manager];
  }

}
