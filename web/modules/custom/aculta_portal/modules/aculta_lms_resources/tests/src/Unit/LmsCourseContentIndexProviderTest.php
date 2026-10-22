<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit;

use Drupal\aculta_lms_resources\ContentIndex\LmsCourseContentIndexProvider;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the allowlisted LMS course and resource projection.
 *
 * @group aculta_lms_resources
 */
final class LmsCourseContentIndexProviderTest extends UnitTestCase {

  /**
   * Ensures only supported editorial relations are projected.
   */
  public function testCourseProjectionContainsOnlyExplicitResourceRelations(): void {
    $magazine = $this->createMock(NodeInterface::class);
    $magazine->method('bundle')->willReturn('revista');
    $magazine->method('id')->willReturn('17');
    $wiki = $this->createMock(NodeInterface::class);
    $wiki->method('bundle')->willReturn('wiki_entry');
    $wiki->method('id')->willReturn('21');
    $otherNode = $this->createMock(NodeInterface::class);
    $otherNode->method('bundle')->willReturn('page');
    $otherNode->method('id')->willReturn('22');
    $gallery = $this->createMock(MediaGalleryInterface::class);
    $gallery->method('bundle')->willReturn('media_gallery');
    $gallery->method('id')->willReturn('31');

    $readings = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $readings->method('isEmpty')->willReturn(FALSE);
    $readings->method('referencedEntities')->willReturn([$magazine, $wiki, $otherNode]);
    $galleries = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $galleries->method('isEmpty')->willReturn(FALSE);
    $galleries->method('referencedEntities')->willReturn([$gallery]);

    $course = $this->createCourse($readings, $galleries);
    $projection = $this->createProvider()->project($course);

    self::assertNotNull($projection);
    self::assertSame('group', $projection->entityTypeId);
    self::assertSame('lms_course', $projection->bundle);
    self::assertSame('cursos_aculta_org', $projection->domainId);
    self::assertSame(['course_reading', 'course_reading', 'course_gallery'], array_map(
      static fn ($relation): string => $relation->type,
      $projection->relations,
    ));
    self::assertSame(['17', '21', '31'], array_map(
      static fn ($relation): string => $relation->targetEntityId,
      $projection->relations,
    ));
  }

  /**
   * Ensures provider ownership, canonical purpose and educator access.
   */
  public function testProviderDeclaresCanonicalPurposeAndCourseAccess(): void {
    $provider = $this->createProvider();
    $course = $this->createCourse();
    $account = $this->createMock(AccountInterface::class);

    self::assertSame('courses', $provider->getAccessPurpose($course));
    self::assertSame(['group' => ['lms_course']], $provider->getSupportedEntityTypes());

    $course->method('takeAccess')->willReturn(AccessResult::forbidden());
    $course->method('hasPermission')->willReturnMap([
      ['administer members', $account, FALSE],
      ['grade students', $account, TRUE],
    ]);
    self::assertTrue($provider->checkAccess($course, $account)->isAllowed());
  }

  /**
   * Ensures student access is honored and unrelated Group bundles are rejected.
   */
  public function testStudentTakeAccessIsAcceptedAndUnrelatedBundleIsRejected(): void {
    $provider = $this->createProvider();
    $course = $this->createCourse();
    $account = $this->createMock(AccountInterface::class);
    $course->method('takeAccess')->willReturn(AccessResult::allowed());
    $course->method('hasPermission')->willReturn(FALSE);
    self::assertTrue($provider->checkAccess($course, $account)->isAllowed());

    $unrelated = $this->createMock(CourseInterface::class);
    $unrelated->method('bundle')->willReturn('lms_class');
    self::assertNull($provider->project($unrelated));
    self::assertFalse($provider->checkAccess($unrelated, $account)->isAllowed());
  }

  /**
   * Creates a course fixture with optional resource field lists.
   */
  private function createCourse(
    ?EntityReferenceFieldItemListInterface $readings = NULL,
    ?EntityReferenceFieldItemListInterface $galleries = NULL,
  ): CourseInterface {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $course = $this->createMock(CourseInterface::class);
    $course->method('getEntityTypeId')->willReturn('group');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('8');
    $course->method('uuid')->willReturn('course-uuid');
    $course->method('label')->willReturn('Curso demonstrativo');
    $course->method('language')->willReturn($language);
    $course->method('isPublished')->willReturn(TRUE);
    $course->method('getCreatedTime')->willReturn(100);
    $course->method('getChangedTime')->willReturn(200);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    $course->method('hasField')->willReturnMap([
      ['field_lms_readings', $readings !== NULL],
      ['field_lms_galleries', $galleries !== NULL],
    ]);
    $course->method('get')->willReturnMap([
      ['field_lms_readings', $readings],
      ['field_lms_galleries', $galleries],
    ]);
    return $course;
  }

  /**
   * Creates the provider with a canonical courses Domain fixture.
   */
  private function createProvider(): LmsCourseContentIndexProvider {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('cursos_aculta_org');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('cursos_aculta_org')->willReturn($domain);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($storage);
    $domainPurposeManager = new DomainPurposeManager(
      $entityTypeManager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    return new LmsCourseContentIndexProvider($domainPurposeManager);
  }

}
