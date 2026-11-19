<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit\Resources;

use Drupal\aculta_lms\Resources\CourseResourceBuilder;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(\Drupal\aculta_lms\Resources\CourseResourceBuilder::class)]
#[Group('aculta_lms')]
/**
 * Tests access-safe rendering of course resources.
 */
final class CourseResourceBuilderTest extends UnitTestCase {

  /**
   * Verifies that a course outsider receives no selected-resource metadata.
   */
  public function testOutsiderDoesNotReceiveCourseResources(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->createMock(GroupInterface::class);
    $course->expects(self::exactly(3))
      ->method('hasPermission')
      ->willReturnMap([
        ['take course', $account, FALSE],
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
    self::assertIsArray($build['#cache'] ?? NULL);
    $cache = $build['#cache'];
    self::assertIsArray($cache['contexts'] ?? NULL);
    self::assertIsArray($cache['tags'] ?? NULL);
    $contexts = $cache['contexts'];
    $tags = $cache['tags'];
    self::assertContains('user.group_permissions', $contexts);
    self::assertContains('domain', $contexts);
    self::assertContains('group:course', $tags);
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
   * Remote course videos resolve through the courses Domain.
   */
  public function testRemoteVideoUsesCoursesPurpose(): void {
    $builder = $this->builder($this->createMock(AccountProxyInterface::class));
    $method = new \ReflectionMethod(CourseResourceBuilder::class, 'purposeFor');
    $video = $this->createMock(MediaInterface::class);
    $video->method('getEntityTypeId')->willReturn('media');
    $video->method('bundle')->willReturn('remote_video');

    self::assertSame('courses', $method->invoke($builder, $video));
  }

  /**
   * Creates the builder with isolated Domain dependencies. */
  private function builder(AccountProxyInterface $account): CourseResourceBuilder {
    $siteManager = new DomainSiteManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    return new CourseResourceBuilder(
      $siteManager,
      $account,
      $this->createMock(TranslationInterface::class),
      $this->createMock(GroupRelationTypeManagerInterface::class),
    );
  }

}
