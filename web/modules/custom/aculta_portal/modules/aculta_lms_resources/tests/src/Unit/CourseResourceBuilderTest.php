<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit;

use Drupal\aculta_lms_resources\CourseResourceBuilder;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\aculta_lms_resources\CourseResourceBuilder
 * @group aculta_lms_resources
 */
final class CourseResourceBuilderTest extends UnitTestCase {

  /**
   * Verifies that a course outsider receives no selected-resource metadata.
   */
  public function testOutsiderDoesNotReceiveCourseResources(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->createMock(CourseInterface::class);
    $course->expects(self::once())
      ->method('takeAccess')
      ->with($account)
      ->willReturn(AccessResult::forbidden()->addCacheContexts(['user.group_permissions']));
    $course->expects(self::exactly(2))
      ->method('hasPermission')
      ->willReturnMap([
        ['administer members', $account, FALSE],
        ['grade students', $account, FALSE],
      ]);
    $course->method('getCacheTags')->willReturn(['group:course']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $builder = $this->builder($account);
    $build = $builder->build($course);

    self::assertSame('#type', array_key_first($build));
    self::assertSame('container', $build['#type']);
    self::assertArrayNotHasKey('readings', $build);
    self::assertArrayNotHasKey('galleries', $build);
    self::assertContains('user.group_permissions', $build['#cache']['contexts']);
    self::assertContains('domain', $build['#cache']['contexts']);
    self::assertContains('group:course', $build['#cache']['tags']);
  }

  /**
   * Gallery publication is exposed by Media Gallery's own interface.
   */
  public function testMediaGalleryPublicationUsesItsPublicInterface(): void {
    $builder = $this->builder($this->createMock(AccountProxyInterface::class));
    $method = new \ReflectionMethod(CourseResourceBuilder::class, 'isPublishedResource');

    $published = $this->createMock(MediaGalleryInterface::class);
    $published->expects(self::once())->method('isEnabled')->willReturn(TRUE);
    self::assertTrue($method->invoke($builder, $published));

    $unpublished = $this->createMock(MediaGalleryInterface::class);
    $unpublished->expects(self::once())->method('isEnabled')->willReturn(FALSE);
    self::assertFalse($method->invoke($builder, $unpublished));
  }

  /**
   * Core publication entities keep using EntityPublishedInterface.
   */
  public function testCorePublicationInterfaceIsRespected(): void {
    $builder = $this->builder($this->createMock(AccountProxyInterface::class));
    $method = new \ReflectionMethod(CourseResourceBuilder::class, 'isPublishedResource');

    $entity = $this->createMock(EntityPublishedInterface::class);
    $entity->expects(self::once())->method('isPublished')->willReturn(TRUE);
    self::assertTrue($method->invoke($builder, $entity));
  }

  /**
   * Creates the builder with isolated Domain dependencies. */
  private function builder(AccountProxyInterface $account): CourseResourceBuilder {
    $purposeManager = new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    return new CourseResourceBuilder(
      $purposeManager,
      $account,
      $this->createMock(TranslationInterface::class),
    );
  }

}
