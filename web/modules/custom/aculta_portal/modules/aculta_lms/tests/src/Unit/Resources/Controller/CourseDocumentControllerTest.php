<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit\Resources\Controller;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Resources\Access\CourseDocumentAccessCheck;
use Drupal\aculta_lms\Resources\Controller\CourseDocumentController;
use Drupal\aculta_lms\Hook\CourseDocumentFormHooks;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\CurrentRouteMatch;
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
#[Group('aculta_lms')]
final class CourseDocumentControllerTest extends UnitTestCase {

  /**
   * New Media is created through Group's existing attach-to-course form.
   */
  public function testAddBuildsOwnerMediaFormWithoutCachingTheGroupEntity(): void {
    [$controller, $course, $document, $entityFormBuilder] = $this->fixtures();
    $entityFormBuilder->expects(self::once())
      ->method('getForm')
      ->with($document, 'add')
      ->willReturn([
        'actions' => [
          'submit' => ['#submit' => ['::submitForm', '::save']],
        ],
        'media_form' => ['#type' => 'container'],
      ]);

    $build = $controller->add($course);

    self::assertSame(['#type' => 'container'], $build['media_form']);
    self::assertSame(
      ['::submitForm', '::save'],
      $build['actions']['submit']['#submit'],
    );
    self::assertSame('aculta_lms.course_materials_edit', $build['contextual_return']['#url']->getRouteName());
  }

  /**
   * Adds a serializable callback before the Media form is cached.
   */
  public function testFormHookAddsPostSaveCallbackOnlyOnCourseRoute(): void {
    $route_match = $this->createMock(CurrentRouteMatch::class);
    $route_match->method('getRouteName')->willReturn('aculta_lms.course_document_add');
    $hooks = new CourseDocumentFormHooks($route_match);
    $form = [
      'actions' => [
        'submit' => ['#submit' => ['::submitForm', '::save']],
      ],
    ];

    $hooks->addCourseRelation($form, new FormState());

    self::assertSame(
      ['::submitForm', '::save', [CourseDocumentController::class, 'submitDocumentToCourse']],
      $form['actions']['submit']['#submit'],
    );
  }

  /**
   * Saved private documents are related after access is checked again.
   */
  public function testAttachDocumentToCourseRevalidatesAndRedirects(): void {
    [$controller, $course, $document] = $this->fixtures();
    $course->method('getRelatedEntities')
      ->with('aculta_document_viewer:course_document')
      ->willReturn([]);
    $course->expects(self::once())
      ->method('addRelationship')
      ->with($document, 'aculta_document_viewer:course_document');
    $route_match = $this->getMockBuilder(CurrentRouteMatch::class)
      ->disableOriginalConstructor()
      ->getMock();
    $route_match->method('getParameter')->with('course')->willReturn($course);
    $controller = $this->controllerWithRouteMatch($course, $document, $route_match);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($document);
    $form_state = (new FormState())->setFormObject($form_object);

    $form = [];
    $controller->attachDocumentToCourse($form, $form_state);

    self::assertSame('aculta_lms.course_materials_edit', $form_state->getRedirect()->getRouteName());
    self::assertSame(['course' => '12'], $form_state->getRedirect()->getRouteParameters());
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
   * @return array{\Drupal\aculta_lms\Resources\Controller\CourseDocumentController, \Drupal\group\Entity\GroupInterface, \Drupal\media\MediaInterface, \Drupal\Core\Entity\EntityFormBuilderInterface}
   *   The controller, course and document.
   */
  private function fixtures(): array {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->with('administer lms')->willReturn(TRUE);
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
      $this->createMock(CurrentRouteMatch::class),
      $this->getStringTranslationStub(),
    );
    return [$controller, $course, $document, $entityFormBuilder];
  }

  /**
   * Creates a controller bound to the current Group route parameter.
   */
  private function controllerWithRouteMatch(GroupInterface $course, MediaInterface $document, CurrentRouteMatch $route_match): CourseDocumentController {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->with('administer lms')->willReturn(TRUE);
    $groupType = $this->createMock(GroupTypeInterface::class);
    $groupType->method('hasPlugin')->with('aculta_document_viewer:course_document')->willReturn(TRUE);
    $course->method('getGroupType')->willReturn($groupType);
    $course->method('hasPermission')->willReturn(TRUE);
    $course->method('getCacheTags')->willReturn(['group:12']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $handler = $this->createMock(AccessControlInterface::class);
    $handler->method('entityCreateAccess')->willReturn(AccessResult::allowed());
    $relationManager = $this->createMock(GroupRelationTypeManagerInterface::class);
    $relationManager->method('hasDefinition')->with('aculta_document_viewer:course_document')->willReturn(TRUE);
    $relationManager->method('getAccessControlHandler')->willReturn($handler);
    $accessCheck = new CourseDocumentAccessCheck(
      new CourseAuthoringAccessCheck($account, $this->createMock(EntityTypeManagerInterface::class)),
      $relationManager,
      $account,
    );
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('create')->willReturn($document);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('media')->willReturn($storage);
    return new CourseDocumentController(
      $entityTypeManager,
      $this->createMock(EntityFormBuilderInterface::class),
      $accessCheck,
      $route_match,
      $this->getStringTranslationStub(),
    );
  }

}
