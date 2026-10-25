<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_lms_resources\Plugin\EntityReferenceSelection\PublishedGallerySelection;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests selection of published galleries from a course editing form.
 *
 * @group aculta_lms_resources
 * @coversDefaultClass \Drupal\aculta_lms_resources\Plugin\EntityReferenceSelection\PublishedGallerySelection
 */
final class PublishedGallerySelectionTest extends UnitTestCase {

  /**
   * Tests that only published galleries are selectable by course educators.
   *
   * @covers ::getReferenceableEntities
   * @covers ::buildEntityQuery
   */
  public function testSelectionRestrictsToPublishedGalleriesAndCourseEducators(): void {
    $account = $this->createMock(AccountInterface::class);
    $course = $this->createMock(CourseInterface::class);
    $course->method('isNew')->willReturn(FALSE);
    $course->method('hasPermission')->willReturn(TRUE);

    $gallery = $this->createMock(MediaGalleryInterface::class);
    $gallery->method('bundle')->willReturn('media_gallery');
    $gallery->method('label')->willReturn('Galeria publicada');

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(TRUE)->willReturnSelf();
    $query->expects(self::once())->method('condition')->with('status', 1)->willReturnSelf();
    $query->method('addTag')->willReturnSelf();
    $query->method('addMetaData')->willReturnSelf();
    $query->method('execute')->willReturn(['7']);

    $gallery_storage = $this->createMock(EntityStorageInterface::class);
    $gallery_storage->method('getQuery')->willReturn($query);
    $gallery_storage->method('loadMultiple')->with(['7'])->willReturn(['7' => $gallery]);

    $domain = $this->createMock(DomainInterface::class);
    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $domain_storage->method('load')->with('revista420_aculta_org')->willReturn($domain);

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getKey')->willReturnMap([
      ['id', 'id'],
      ['label', 'title'],
    ]);
    $entity_type->method('hasKey')->with('bundle')->willReturn(TRUE);
    $entity_type->method('getKey')->willReturnMap([
      ['id', 'id'],
      ['label', 'title'],
      ['bundle', 'bundle'],
    ]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')->with('media_gallery')->willReturn($entity_type);
    $entity_type_manager->method('getStorage')->willReturnCallback(
      static fn (string $entity_type_id) => $entity_type_id === 'domain' ? $domain_storage : $gallery_storage,
    );
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn(NULL);
    $purpose_manager = new DomainPurposeManager(
      $entity_type_manager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $entity_repository = $this->createMock(EntityRepositoryInterface::class);
    $entity_repository->method('getTranslationFromContext')->with($gallery)->willReturn($gallery);

    $selection = new PublishedGallerySelection(
      [
        'target_type' => 'media_gallery',
        'entity' => $course,
        'target_bundles' => NULL,
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
      ],
      'aculta_lms_resources:published_gallery',
      [],
      $entity_type_manager,
      $this->createMock(ModuleHandlerInterface::class),
      $account,
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(EntityTypeBundleInfoInterface::class),
      $entity_repository,
      $purpose_manager,
    );

    self::assertSame(
      ['media_gallery' => ['7' => 'Galeria publicada']],
      $selection->getReferenceableEntities(),
    );
  }

  /**
   * Tests that the field does not disclose galleries outside course context.
   *
   * @covers ::getReferenceableEntities
   */
  public function testSelectionWithoutCourseContextFailsClosed(): void {
    $selection = $this->createSelectionWithoutCourse();

    self::assertSame([], $selection->getReferenceableEntities());
  }

  /**
   * Creates a selector without a course; it must return before using DI.
   */
  private function createSelectionWithoutCourse(): PublishedGallerySelection {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $entity_type_manager->method('getStorage')->willReturn($domain_storage);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $purpose_manager = new DomainPurposeManager(
      $entity_type_manager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    return new PublishedGallerySelection(
      ['target_type' => 'media_gallery'],
      'aculta_lms_resources:published_gallery',
      [],
      $entity_type_manager,
      $this->createMock(ModuleHandlerInterface::class),
      $this->createMock(AccountInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(EntityTypeBundleInfoInterface::class),
      $this->createMock(EntityRepositoryInterface::class),
      $purpose_manager,
    );
  }

}
