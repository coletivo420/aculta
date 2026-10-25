<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_lms_resources\Hook\GalleryCourseAccessHooks;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\Storage\GroupStorage;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests course-scoped access for gallery resources.
 *
 * @group aculta_lms_resources
 * @coversDefaultClass \Drupal\aculta_lms_resources\Hook\GalleryCourseAccessHooks
 */
final class GalleryCourseAccessHooksTest extends UnitTestCase {

  /**
   * The account used for access assertions.
   */
  private AccountInterface $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->account = $this->createMock(AccountInterface::class);
  }

  /**
   * Tests that an active course member can view a gallery linked to the course.
   *
   * @covers ::entityAccess
   */
  public function testLinkedCourseMemberIsGrantedAccess(): void {
    $course = $this->course(AccessResult::allowed()->addCacheContexts(['user.group_permissions']));
    $hook = $this->hook([$course]);

    $result = $hook->entityAccess($this->gallery(), 'view', $this->account);

    self::assertTrue($result->isAllowed());
    self::assertContains('user.group_permissions', $result->getCacheContexts());
    self::assertContains('group:course-42', $result->getCacheTags());
  }

  /**
   * Tests that a student without a linked course membership is denied safely.
   *
   * @covers ::entityAccess
   */
  public function testUnlinkedAccountDoesNotReceiveGalleryAccess(): void {
    $hook = $this->hook([]);

    $result = $hook->entityAccess($this->gallery(), 'view', $this->account);

    self::assertTrue($result->isNeutral());
    self::assertSame(0, $result->getCacheMaxAge());
  }

  /**
   * Tests that an educator with contextual grading permission can view it.
   *
   * @covers ::entityAccess
   */
  public function testCourseEducatorIsGrantedAccess(): void {
    $course = $this->course(AccessResult::forbidden(), TRUE);
    $hook = $this->hook([$course]);

    $result = $hook->entityAccess($this->gallery(), 'view', $this->account);

    self::assertTrue($result->isAllowed());
  }

  /**
   * Creates the access hook with course candidates returned from Group storage.
   *
   * @param \Drupal\lms\Entity\Bundle\CourseInterface[] $courses
   *   Matching course entities.
   */
  private function hook(array $courses): GalleryCourseAccessHooks {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn($courses === [] ? [] : [42]);

    $group_storage = $this->getMockBuilder(GroupStorage::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getQuery', 'loadMultiple'])
      ->getMock();
    $group_storage->method('getQuery')->willReturn($query);
    $group_storage->method('loadMultiple')->willReturn($courses);

    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturnCallback(
      static fn (string $entity_type_id) => $entity_type_id === 'group' ? $group_storage : $domain_storage,
    );
    $group_definition = $this->createMock(EntityTypeInterface::class);
    $group_definition->method('getListCacheTags')->willReturn(['group_list']);
    $entity_type_manager->method('getDefinition')->with('group')->willReturn($group_definition);

    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('revista420_aculta_org');
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($domain);
    $purpose_manager = new DomainPurposeManager(
      $entity_type_manager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    return new GalleryCourseAccessHooks($entity_type_manager, $purpose_manager);
  }

  /**
   * Creates a course with the requested membership and educator grants.
   */
  private function course(AccessResult $take_access, bool $can_grade = FALSE): CourseInterface {
    $course = $this->createMock(CourseInterface::class);
    $course->method('takeAccess')->with($this->account)->willReturn($take_access);
    $course->method('hasPermission')->willReturn($can_grade);
    $course->method('getCacheTags')->willReturn(['group:course-42']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    return $course;
  }

  /**
   * Creates a published Gallery entity.
   */
  private function gallery(): MediaGalleryInterface {
    $gallery = $this->createMock(MediaGalleryInterface::class);
    $gallery->method('getEntityTypeId')->willReturn('media_gallery');
    $gallery->method('isEnabled')->willReturn(TRUE);
    $gallery->method('id')->willReturn('7');
    $gallery->method('getCacheTags')->willReturn(['media_gallery:7']);
    $gallery->method('getCacheContexts')->willReturn([]);
    $gallery->method('getCacheMaxAge')->willReturn(-1);
    return $gallery;
  }

}
