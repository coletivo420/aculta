<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit\Controller;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms_resources\Access\CourseDocumentAccessCheck;
use Drupal\aculta_lms_resources\Controller\CourseDocumentController;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityStorageInterface;
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
 * Tests that contextual document routes reuse Group and Media entity forms.
 */
#[CoversClass(CourseDocumentController::class)]
#[Group('aculta_lms_resources')]
final class CourseDocumentControllerTest extends UnitTestCase {

  /**
   * New Media is created through Group's existing attach-to-course form.
   */
  public function testAddBuildsOwnerMediaFormWithGroupContext(): void {
    [$controller, $course, $document, $entityFormBuilder] = $this->fixtures();
    $entityFormBuilder->expects(self::once())
      ->method('getForm')
      ->with($document, 'add', [
        'group__create_form_should_enhance' => TRUE,
        'group_relation' => 'aculta_document_viewer:course_document',
        'group' => $course,
      ])
      ->willReturn(['media_form' => ['#type' => 'container']]);

    $build = $controller->add($course);

    self::assertSame(['#type' => 'container'], $build['media_form']);
    self::assertSame('aculta_lms.course_materials_edit', $build['contextual_return']['#url']->getRouteName());
  }

  /**
   * Existing documents use Media's edit operation and contextual return link.
   */
  public function testEditBuildsOwnerMediaFormAfterCourseScopedAccess(): void {
    [$controller, $course, $document, $entityFormBuilder] = $this->fixtures();
    $course->method('getRelatedEntities')->with('aculta_document_viewer:course_document')->willReturn([$document]);
    $document->method('id')->willReturn('55');
    $document->method('access')->with('update', self::anything(), TRUE)->willReturn(AccessResult::allowed());
    $entityFormBuilder->expects(self::once())
      ->method('getForm')
      ->with($document, 'edit')
      ->willReturn(['media_form' => ['#type' => 'container']]);

    $build = $controller->edit($course, $document);

    self::assertSame(['#type' => 'container'], $build['media_form']);
    self::assertSame('aculta_lms.course_materials_edit', $build['contextual_return']['#url']->getRouteName());
  }

  /**
   * Creates a controller with real access policies and isolated services.
   *
   * @return array{\Drupal\aculta_lms_resources\Controller\CourseDocumentController, \Drupal\group\Entity\GroupInterface, \Drupal\media\MediaInterface, \Drupal\Core\Entity\EntityFormBuilderInterface}
   *   The controller, course and document.
   */
  private function fixtures(): array {
    $account = $this->createMock(AccountProxyInterface::class);
    $groupType = $this->createMock(GroupTypeInterface::class);
    $groupType->method('hasPlugin')->with('aculta_document_viewer:course_document')->willReturn(TRUE);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('12');
    $course->method('getGroupType')->willReturn($groupType);
    $course->method('hasPermission')->willReturn(TRUE);
    $course->method('getCacheTags')->willReturn(['group:12']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $document = $this->createMock(MediaInterface::class);
    $document->method('bundle')->willReturn('course_document');
    $document->method('getCacheTags')->willReturn(['media:55']);
    $document->method('getCacheContexts')->willReturn([]);
    $document->method('getCacheMaxAge')->willReturn(-1);

    $handler = $this->createMock(AccessControlInterface::class);
    $handler->method('entityCreateAccess')->willReturn(AccessResult::allowed());
    $relationManager = $this->createMock(GroupRelationTypeManagerInterface::class);
    $relationManager->method('hasDefinition')->willReturnCallback(static fn(string $id): bool => $id === 'aculta_document_viewer:course_document');
    $relationManager->method('getAccessControlHandler')->with('aculta_document_viewer:course_document')->willReturn($handler);
    $accessCheck = new CourseDocumentAccessCheck(
      new CourseAuthoringAccessCheck($account, $this->createMock(EntityTypeManagerInterface::class)),
      $relationManager,
      $account,
    );

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('create')->with(['bundle' => 'course_document'])->willReturn($document);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('media')->willReturn($storage);
    $entityFormBuilder = $this->createMock(EntityFormBuilderInterface::class);
    $controller = new CourseDocumentController(
      $entityTypeManager,
      $entityFormBuilder,
      $accessCheck,
      $this->getStringTranslationStub(),
    );
    return [$controller, $course, $document, $entityFormBuilder];
  }

}
