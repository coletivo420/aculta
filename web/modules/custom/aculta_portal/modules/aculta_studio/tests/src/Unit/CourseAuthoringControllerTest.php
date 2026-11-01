<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_studio\Controller\CourseAuthoringController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests the Studio adapter around the existing LMS Group Form API.
 */
#[CoversClass(CourseAuthoringController::class)]
#[Group('aculta_studio')]
final class CourseAuthoringControllerTest extends UnitTestCase {

  /**
   * Provides Drupal translation and URL generation for render helpers.
   */
  protected function setUp(): void {
    parent::setUp();
    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')->willReturnCallback(
      static fn (string $name, array $parameters, array $options, bool $collect): string|GeneratedUrl => $collect
        ? (new GeneratedUrl())->setGeneratedUrl('/ganjalab')
        : '/ganjalab',
    );
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);
  }

  /**
   * The controller builds the normal Group add form and keeps it in Studio.
   */
  public function testBuildsOwnerFormInsideStudioPresentation(): void {
    $account = $this->createMock(AccountInterface::class);
    $access = $this->createMock(AccessResultInterface::class);
    $access->method('isAllowed')->willReturn(TRUE);
    $course = $this->createMock(EntityInterface::class);
    $course->expects(self::once())->method('access')->with('create', $account, TRUE)->willReturn($access);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('create')->with(['type' => 'lms_course'])->willReturn($course);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->expects(self::once())->method('getStorage')->with('group')->willReturn($storage);
    $form = ['#type' => 'form', '#id' => 'group-lms-course-add-form'];
    $form_builder = $this->createMock(EntityFormBuilderInterface::class);
    $form_builder->expects(self::once())->method('getForm')->with($course, 'add')->willReturn($form);

    $build = (new CourseAuthoringController(
      $entity_manager,
      $form_builder,
      $account,
      $this->getStringTranslationStub(),
    ))->create();

    self::assertSame('studio-shell studio-course-authoring', $build['#attributes']['data-portal-ui']);
    self::assertArrayNotHasKey('class', $build['#attributes']);
    self::assertSame('course-authoring', $build['#attributes']['data-aculta-studio']);
    self::assertSame('studio-main', $build['content']['#attributes']['data-portal-ui']);
    self::assertArrayNotHasKey('class', $build['content']['#attributes']);
    self::assertSame(
      'studio-action studio-course-authoring-back',
      (string) $build['back']['#element_attributes']['data-portal-ui'],
    );
    self::assertSame('form', $build['content']['form']['#type']);
    self::assertSame('group-lms-course-add-form', $build['content']['form']['#id']);
    self::assertSame(0, $build['#cache']['max-age']);
  }

  /**
   * The entity owner access result still denies the form before construction.
   */
  public function testDeniesWhenGroupEntityAccessDeniesCreation(): void {
    $account = $this->createMock(AccountInterface::class);
    $access = $this->createMock(AccessResultInterface::class);
    $access->method('isAllowed')->willReturn(FALSE);
    $course = $this->createMock(EntityInterface::class);
    $course->expects(self::once())->method('access')->with('create', $account, TRUE)->willReturn($access);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('create')->with(['type' => 'lms_course'])->willReturn($course);
    $entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_manager->method('getStorage')->with('group')->willReturn($storage);
    $form_builder = $this->createMock(EntityFormBuilderInterface::class);
    $form_builder->expects(self::never())->method('getForm');

    $this->expectException(AccessDeniedHttpException::class);
    (new CourseAuthoringController(
      $entity_manager,
      $form_builder,
      $account,
      $this->getStringTranslationStub(),
    ))->create();
  }

}
