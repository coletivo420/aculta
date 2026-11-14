<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Form\CourseStructureOrderForm;
use Drupal\aculta_lms\Order\CourseStructureOrderManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the drag-and-drop course order form.
 *
 * @coversDefaultClass \Drupal\aculta_lms\Form\CourseStructureOrderForm
 */
#[CoversClass(CourseStructureOrderForm::class)]
final class CourseStructureOrderFormTest extends UnitTestCase {

  /**
   * The embedded form honors Form API's base buildForm signature and arguments.
   */
  public function testFormBuildUsesFormStateArguments(): void {
    $container = new ContainerBuilder();
    $contexts = $this->createMock(CacheContextsManager::class);
    $contexts->method('assertValidTokens')->willReturn(TRUE);
    $container->set('cache_contexts_manager', $contexts);
    \Drupal::setContainer($container);

    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('88');
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $course->expects(self::once())
      ->method('hasPermission')
      ->with('grade students', $account)
      ->willReturn(TRUE);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $accessCheck = new CourseAuthoringAccessCheck($account, $entityTypeManager);
    $orderManager = (new \ReflectionClass(CourseStructureOrderManager::class))
      ->newInstanceWithoutConstructor();
    $form = new CourseStructureOrderForm(
      $this->createMock(CurrentRouteMatch::class),
      $accessCheck,
      $orderManager,
      $entityTypeManager,
    );
    $form->setStringTranslation($this->getStringTranslationStub());
    $formState = new FormState();
    $formState->addBuildInfo('args', [$course, 'lessons', 88, [11, 12]]);

    $build = $form->buildForm([], $formState);

    self::assertSame('aculta_lms_course_structure_order_form', $form->getFormId());
    self::assertIsArray($build['#attributes'] ?? NULL);
    $attributes = $build['#attributes'];
    self::assertSame('aculta-course-order-lessons-88', $attributes['id'] ?? NULL);
    self::assertTrue($attributes['data-aculta-unsaved-changes'] ?? NULL);
    self::assertIsArray($build['order'] ?? NULL);
    self::assertSame('11,12', $build['order']['#default_value'] ?? NULL);
  }

}
