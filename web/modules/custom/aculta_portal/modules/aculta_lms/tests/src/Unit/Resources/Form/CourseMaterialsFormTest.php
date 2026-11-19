<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit\Resources\Form;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Resources\Access\CourseDocumentAccessCheck;
use Drupal\aculta_lms\Resources\Form\CourseMaterialsForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests the contextual editing UI for course-owned resources and documents.
 */
#[CoversClass(CourseMaterialsForm::class)]
#[Group('aculta_lms')]
final class CourseMaterialsFormTest extends UnitTestCase {

  /**
   * Current test account.
   *
   * @var AccountProxyInterface&MockObject
   */
  private AccountProxyInterface $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->account = $this->createMock(AccountProxyInterface::class);
    $container = new ContainerBuilder();
    $container->set('current_user', $this->account);
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('module_handler', $this->createMock(ModuleHandlerInterface::class));
    \Drupal::setContainer($container);
  }

  /**
   * Existing private documents get a contextual owner edit link.
   */
  public function testCourseDocumentsUseGroupCreateAndOwnUpdateAccess(): void {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('12');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->willReturn(TRUE);
    $course->method('hasField')->willReturnCallback(static fn(string $field): bool => $field === 'field_h5p_activities');
    $course->method('getCacheTags')->willReturn(['group:12']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $groupType = $this->createMock(GroupTypeInterface::class);
    $groupType->method('hasPlugin')->with('aculta_document_viewer:course_document')->willReturn(TRUE);
    $course->method('getGroupType')->willReturn($groupType);

    $document = $this->createMock(MediaInterface::class);
    $document->method('bundle')->willReturn('course_document');
    $document->method('id')->willReturn('55');
    $document->method('label')->willReturn('Guia privado');
    $document->method('getCacheTags')->willReturn(['media:55']);
    $document->method('getCacheContexts')->willReturn([]);
    $document->method('getCacheMaxAge')->willReturn(-1);
    $document->method('access')->willReturnCallback(static fn(string $operation): AccessResult => AccessResult::allowed());
    $readOnlyDocument = $this->createMock(MediaInterface::class);
    $readOnlyDocument->method('bundle')->willReturn('course_document');
    $readOnlyDocument->method('id')->willReturn('56');
    $readOnlyDocument->method('label')->willReturn('Documento de outra pessoa');
    $readOnlyDocument->method('getCacheTags')->willReturn(['media:56']);
    $readOnlyDocument->method('getCacheContexts')->willReturn([]);
    $readOnlyDocument->method('getCacheMaxAge')->willReturn(-1);
    $readOnlyDocument->method('access')->willReturnCallback(static fn(string $operation): AccessResult => $operation === 'view'
      ? AccessResult::allowed()
      : AccessResult::forbidden());
    $course->method('getRelatedEntities')
      ->with('aculta_document_viewer:course_document')
      ->willReturn([$document, $readOnlyDocument]);

    $groupTypeManager = $this->createMock(GroupRelationTypeManagerInterface::class);
    $groupTypeManager->method('hasDefinition')->willReturnCallback(static fn(string $id): bool => $id === 'aculta_document_viewer:course_document');
    $groupAccess = $this->createMock(AccessControlInterface::class);
    $groupAccess->method('entityCreateAccess')->willReturn(AccessResult::allowed());
    $groupTypeManager->method('getAccessControlHandler')->with('aculta_document_viewer:course_document')->willReturn($groupAccess);
    $documentAccess = new CourseDocumentAccessCheck(
      new CourseAuthoringAccessCheck($this->account, $this->createMock(EntityTypeManagerInterface::class)),
      $groupTypeManager,
      $this->account,
    );

    $displayStorage = $this->createMock(EntityStorageInterface::class);
    $sourceDisplay = $this->createMock(EntityFormDisplay::class);
    $h5pWidget = ['type' => 'h5p_editor', 'settings' => []];
    $sourceDisplay->method('getComponent')->willReturnCallback(
      static fn(string $field): ?array => $field === 'field_h5p_activities' ? $h5pWidget : NULL,
    );
    $displayStorage->method('load')->with('group.lms_course.default')->willReturn($sourceDisplay);
    $formDisplay = $this->createMock(EntityFormDisplay::class);
    $formDisplay->expects(self::once())->method('setComponent')->with('field_h5p_activities', $h5pWidget);
    $formDisplay->method('getComponent')->willReturnCallback(
      static fn(string $field): ?array => $field === 'field_h5p_activities' ? $h5pWidget : NULL,
    );
    $displayStorage->method('create')->willReturn($formDisplay);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('entity_form_display')->willReturn($displayStorage);
    $container = new ContainerBuilder();
    $container->set('current_user', $this->account);
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('module_handler', $this->createMock(ModuleHandlerInterface::class));
    $entityTypeRepository = $this->createMock(EntityTypeRepositoryInterface::class);
    $entityTypeRepository->method('getEntityTypeFromClass')->with(EntityFormDisplay::class)->willReturn('entity_form_display');
    $container->set('entity_type.repository', $entityTypeRepository);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $routeMatch = $this->createMock(CurrentRouteMatch::class);
    $routeMatch->method('getParameter')->with('course')->willReturn($course);
    $form = new CourseMaterialsForm(
      $routeMatch,
      new CourseAuthoringAccessCheck($this->account, $entityTypeManager),
      $entityTypeManager,
      $documentAccess,
      $this->createMock(ModuleHandlerInterface::class),
    );
    $form->setStringTranslation($this->getStringTranslationStub());
    $form->setMessenger($this->createMock(MessengerInterface::class));

    $build = $form->buildForm([], new FormState());
    $courseDocuments = $this->assertArray($build['course_documents'] ?? NULL);
    self::assertSame('aculta420_v2:lms-course-materials', $courseDocuments['#component'] ?? NULL);
    $slots = $this->assertArray($courseDocuments['#slots'] ?? NULL);
    $add = $this->assertArray($slots['add'] ?? NULL);
    $addUrl = $add['#url'] ?? NULL;
    self::assertInstanceOf(Url::class, $addUrl);
    self::assertSame('aculta_lms.course_document_add', $addUrl->getRouteName());
    $items = $this->assertArray($slots['items'] ?? NULL);
    $mediaItems = $items['#items'] ?? NULL;
    self::assertIsArray($mediaItems);
    self::assertArrayHasKey(0, $mediaItems);
    self::assertArrayHasKey(1, $mediaItems);
    $firstItem = $this->assertArray($mediaItems[0]);
    $label = $this->assertArray($firstItem['label'] ?? NULL);
    self::assertSame('Guia privado', $label['#plain_text'] ?? NULL);
    $edit = $this->assertArray($firstItem['edit'] ?? NULL);
    $editUrl = $edit['#url'] ?? NULL;
    self::assertInstanceOf(Url::class, $editUrl);
    self::assertSame('aculta_lms.course_document_edit', $editUrl->getRouteName());
    self::assertSame(['course' => '12', 'media' => '55'], $editUrl->getRouteParameters());
    self::assertArrayNotHasKey('edit', $this->assertArray($mediaItems[1]));
    $actions = $this->assertArray($build['actions'] ?? NULL);
    $submit = $this->assertArray($actions['submit'] ?? NULL);
    self::assertFalse($submit['#disabled'] ?? NULL);
  }

  /**
   * Resource references are built and saved through the owner's form display.
   */
  public function testCourseResourceReferencesUseOwnerFormDisplayOnBuildAndSubmit(): void {
    $fields = [
      'field_lms_readings' => ['type' => 'entity_reference_autocomplete', 'weight' => 6],
      'field_lms_galleries' => ['type' => 'entity_reference_autocomplete', 'weight' => 7],
      'field_lms_videos' => ['type' => 'entity_reference_autocomplete', 'weight' => 8],
    ];
    $isAdministrator = TRUE;
    $this->account->method('hasPermission')
      ->with('administer lms')
      ->willReturnCallback(static function () use (&$isAdministrator): bool {
        return $isAdministrator;
      });
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('12');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('hasPermission')->with('grade students', $this->account)->willReturn(FALSE);
    $course->method('hasField')->willReturnCallback(static fn(string $field): bool => isset($fields[$field]));
    $course->method('getCacheTags')->willReturn(['group:12']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $course->expects(self::once())->method('save');
    $groupType = $this->createMock(GroupTypeInterface::class);
    $groupType->method('hasPlugin')->willReturn(FALSE);
    $course->method('getGroupType')->willReturn($groupType);

    $sourceDisplay = $this->createMock(EntityFormDisplay::class);
    $sourceDisplay->method('getComponent')->willReturnCallback(
      static fn(string $field): ?array => $fields[$field] ?? NULL,
    );
    $transientDisplay = $this->createMock(EntityFormDisplay::class);
    $transientDisplay->expects(self::exactly(6))
      ->method('setComponent')
      ->willReturnCallback(static function (string $field, array $component) use ($fields, $transientDisplay): EntityFormDisplay {
        self::assertSame($fields[$field], $component);
        return $transientDisplay;
      });
    $transientDisplay->method('getComponent')->willReturnCallback(
      static fn(string $field): ?array => $fields[$field] ?? NULL,
    );
    $transientDisplay->expects(self::once())
      ->method('buildForm')
      ->with($course, self::isArray(), self::isInstanceOf(FormState::class));
    $transientDisplay->expects(self::once())
      ->method('extractFormValues')
      ->with($course, self::isArray(), self::isInstanceOf(FormState::class));

    $displayStorage = $this->createMock(EntityStorageInterface::class);
    $displayStorage->expects(self::exactly(2))
      ->method('load')
      ->with('group.lms_course.default')
      ->willReturn($sourceDisplay);
    $displayStorage->expects(self::exactly(2))->method('create')->willReturn($transientDisplay);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('entity_form_display')->willReturn($displayStorage);
    $entityTypeRepository = $this->createMock(EntityTypeRepositoryInterface::class);
    $entityTypeRepository->method('getEntityTypeFromClass')->with(EntityFormDisplay::class)->willReturn('entity_form_display');
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $container = new ContainerBuilder();
    $container->set('current_user', $this->account);
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('entity_type.repository', $entityTypeRepository);
    $container->set('module_handler', $moduleHandler);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $routeMatch = $this->createMock(CurrentRouteMatch::class);
    $routeMatch->method('getParameter')->with('course')->willReturn($course);
    $documentAccess = new CourseDocumentAccessCheck(
      new CourseAuthoringAccessCheck($this->account, $entityTypeManager),
      $this->createMock(GroupRelationTypeManagerInterface::class),
      $this->account,
    );
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addStatus');
    $form = new CourseMaterialsForm(
      $routeMatch,
      new CourseAuthoringAccessCheck($this->account, $entityTypeManager),
      $entityTypeManager,
      $documentAccess,
      $moduleHandler,
    );
    $form->setStringTranslation($this->getStringTranslationStub());
    $form->setMessenger($messenger);

    $formState = new FormState();
    $build = $form->buildForm([], $formState);
    $actions = $this->assertArray($build['actions'] ?? NULL);
    $submit = $this->assertArray($actions['submit'] ?? NULL);
    self::assertFalse($submit['#disabled'] ?? NULL);
    $form->submitForm($build, $formState);
    $redirect = $formState->getRedirect();
    self::assertInstanceOf(Url::class, $redirect);
    self::assertSame('aculta_lms.course_structure', $redirect->getRouteName());
    self::assertSame(['course' => '12'], $redirect->getRouteParameters());

    $isAdministrator = FALSE;
    $denied = FALSE;
    try {
      $form->submitForm($build, $formState);
    }
    catch (AccessDeniedHttpException) {
      $denied = TRUE;
    }
    self::assertTrue($denied, 'A permission revoked after the form was built must deny submission.');
  }

  /**
   * Asserts that a render array value has the expected array boundary.
   *
   * @param mixed $value
   *   The value to validate.
   *
   * @return array<array-key, mixed>
   *   The validated array.
   */
  private function assertArray(mixed $value): array {
    self::assertIsArray($value);
    return $value;
  }

}
