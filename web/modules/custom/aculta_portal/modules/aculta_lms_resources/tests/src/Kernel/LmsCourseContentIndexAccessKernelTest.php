<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Query\ContentIndexAccessFilter;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\PermissionScopeInterface;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\media_gallery\Entity\MediaGallery;
use Drupal\media\Entity\Media;
use Drupal\domain\DomainInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies Content Index LMS policy with real Group courses and memberships.
 */
#[TestGroup('aculta_lms_resources')]
#[RunTestsInSeparateProcesses]
final class LmsCourseContentIndexAccessKernelTest extends KernelTestBase {

  use UserCreationTrait;
  use MediaTypeCreationTrait;

  /**
   * Canonical domain IDs activated during the current access test.
   *
   * @var list<string>
   */
  private array $domainSwitches = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'image',
    'media',
    'entity_usage',
    'media_duplicates',
    'aculta_image',
    'field',
    'filter',
    'flexible_permissions',
    'group',
    'domain',
    'views',
    'media_library',
    'photoswipe',
    'media_gallery',
    'options',
    'text',
    'node',
    'lms',
    'lms_classes',
    'aculta_index',
    'aculta_lms_resources_gallery_access_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturnCallback(static fn (string $id): ?Domain => Domain::load($id));
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($domainStorage);
    $domainNegotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domainNegotiator->method('getActiveDomain')->willReturnCallback(
      static function (bool $reset = FALSE): ?DomainInterface {
        return \Drupal::service('domain.negotiator')->getActiveDomain($reset);
      },
    );
    $domainNegotiator->method('setActiveDomain')->willReturnCallback(
      function (DomainInterface $domain): void {
        \Drupal::service('domain.negotiator')->setActiveDomain($domain);
        $this->domainSwitches[] = $domain->id();
      },
    );
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $entityTypeManager,
      $domainNegotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));

    // Compile the provider's production declaration and tag from the owner
    // module without enabling its full dependency chain in this Kernel.
    $ownerServices = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_lms_resources/aculta_lms_resources.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $service = $ownerServices['aculta_lms_resources.course_content_index_provider'];
    $definition = (new Definition($service['class'], [
      new Reference('aculta_portal.domain_purpose'),
      new Reference('group_relation_type.manager'),
    ]))
      ->setPublic(TRUE);
    foreach ($service['tags'] ?? [] as $tag) {
      $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_lms_resources.course_content_index_provider', $definition);
    $magazineServices = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_magazine/aculta_magazine.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $magazineService = $magazineServices['aculta_magazine.content_index_provider'];
    $magazineDefinition = (new Definition($magazineService['class'], $magazineService['arguments'] ?? []))
      ->setPublic(TRUE);
    foreach ($magazineService['tags'] ?? [] as $tag) {
      $magazineDefinition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_magazine.content_index_provider', $magazineDefinition);
    $galleryServices = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_gallery/aculta_gallery.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $galleryService = $galleryServices['aculta_gallery.content_index_provider'];
    $galleryDefinition = (new Definition($galleryService['class'], $galleryService['arguments'] ?? []))
      ->setPublic(TRUE);
    foreach ($galleryService['tags'] ?? [] as $tag) {
      $galleryDefinition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_gallery.content_index_provider', $galleryDefinition);

    $pdfServices = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/aculta_pdf_viewer_group.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $pdfService = $pdfServices['aculta_pdf_viewer_group.course_document_content_index_provider'];
    $pdfDefinition = (new Definition($pdfService['class'], $pdfService['arguments'] ?? []))
      ->setPublic(TRUE);
    foreach ($pdfService['tags'] ?? [] as $tag) {
      $pdfDefinition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_pdf_viewer_group.course_document_content_index_provider', $pdfDefinition);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['group', 'lms', 'lms_classes', 'user']);
    $this->installEntitySchema('domain');
    foreach ([
      'aculta_org' => 'main',
      'cursos_aculta_org' => 'courses',
      'revista420_aculta_org' => 'magazine',
    ] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'weight' => 0,
        'status' => TRUE,
      ])->save();
    }
    $domainNegotiator = $this->container->get(DomainNegotiatorInterface::class);
    $domainNegotiator->getActiveDomain();
    $domainNegotiator->setActiveDomain(Domain::load('aculta_org'));
    $this->domainSwitches = [];
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_gallery');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['node', 'system', 'file', 'image', 'media', 'media_library', 'photoswipe', 'views', 'text']);
    aculta_index_install();
    NodeType::create(['type' => 'revista', 'name' => 'Revista'])->save();
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $this->createMediaType('oembed:video', ['id' => 'remote_video', 'label' => 'Remote video']);
    $this->createMediaType('video_file', ['id' => 'video', 'label' => 'Video']);
    $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);
    $this->createMediaType('file', ['id' => 'course_document', 'label' => 'Course document']);
    $this->createMediaType('audio_file', ['id' => 'audio', 'label' => 'Audio']);
    $this->createCourseDocumentRelationType();
    $this->installConfig(['media_gallery']);
    $this->installCourseReadingField();
    $this->installCourseGalleryField();
  }

  /**
   * Combines Entity Access, canonical Domain and LMS policy.
   */
  public function testStudentAndEducatorPolicyUsesTheirCourseMembership(): void {
    $owner = $this->createUser(['administer lms']);
    $student = $this->createUser();
    $educator = $this->createUser();
    $outsider = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso para validar acesso do Index',
      'uid' => $owner->id(),
    ]);
    $course->save();

    GroupRole::create([
      'id' => 'lms_course-index_student',
      'label' => 'Estudante de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['take course', 'view group'],
    ])->save();
    GroupRole::create([
      'id' => 'lms_course-index_educator',
      'label' => 'Educador de teste',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students', 'view group'],
    ])->save();
    $course->addMember($student, ['group_roles' => ['lms_course-index_student']]);
    $course->addMember($educator, ['group_roles' => ['lms_course-index_educator']]);

    $provider = $this->container->get('aculta_lms_resources.course_content_index_provider');
    $providers = new ContentIndexProviderRegistry([$provider]);
    $filter = new ContentIndexAccessFilter(
      $this->container->get('entity_type.manager'),
      $providers,
      $this->container->get('aculta_portal.domain_purpose'),
    );
    $candidate = [
      [
        'entity_type' => 'group',
        'entity_id' => (string) $course->id(),
        'langcode' => $course->language()->getId(),
      ],
    ];

    self::assertTrue($course->access('view', $student, TRUE)->isAllowed(), 'The student fixture has Group Entity Access.');
    self::assertCount(1, $filter->filter($candidate, $student)->entities, 'The Index accepts a student with takeAccess.');
    self::assertSame(['cursos_aculta_org', 'aculta_org'], $this->domainSwitches, 'The student access check uses and restores its canonical Domain.');
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose(), 'The active Domain is restored after each access check.');
    $this->domainSwitches = [];
    self::assertFalse($course->takeAccess($educator)->isAllowed(), 'The educator fixture does not have student access.');
    self::assertTrue($course->hasPermission('grade students', $educator));
    self::assertTrue($course->access('view', $educator, TRUE)->isAllowed(), 'The educator fixture has Group Entity Access.');
    self::assertCount(1, $filter->filter($candidate, $educator)->entities, 'The Index accepts an educator with contextual permission.');
    self::assertSame(['cursos_aculta_org', 'aculta_org'], $this->domainSwitches, 'The educator access check uses and restores its canonical Domain.');
    self::assertSame('main', $this->container->get('aculta_portal.domain_purpose')->getCurrentPurpose(), 'The Domain context is restored after educator access.');
    self::assertCount(0, $filter->filter($candidate, $outsider)->entities, 'An unrelated account is denied.');
  }

  /**
   * Runs a real course resource relation through rebuild, query and access.
   */
  public function testCourseReadingRelationQueryRevalidatesCourseAndTargetAccess(): void {
    $authenticated = $this->container->get('entity_type.manager')->getStorage('user_role')->load('authenticated');
    $authenticated->grantPermission('access content')->save();
    $owner = $this->createUser(['administer lms']);
    $student = $this->createUser();
    $educator = $this->createUser();
    $outsider = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso de recursos do Index',
      'uid' => $owner->id(),
    ]);
    $course->save();

    GroupRole::create([
      'id' => 'lms_course-index_reader',
      'label' => 'Estudante do teste de consulta',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['take course', 'view group'],
    ])->save();
    GroupRole::create([
      'id' => 'lms_course-index_grader',
      'label' => 'Educador do teste de consulta',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students', 'view group'],
    ])->save();
    $course->addMember($student, ['group_roles' => ['lms_course-index_reader']]);
    $course->addMember($educator, ['group_roles' => ['lms_course-index_grader']]);

    $publishedReading = Node::create([
      'type' => 'revista',
      'title' => 'Leitura publicada',
      'status' => TRUE,
    ]);
    $publishedReading->save();
    $privateReading = Node::create([
      'type' => 'revista',
      'title' => 'Leitura em rascunho',
      'status' => FALSE,
    ]);
    $privateReading->save();
    $course->set('field_lms_readings', [
      ['target_id' => $publishedReading->id()],
      ['target_id' => $privateReading->id()],
    ]);
    $course->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The course and reading providers should backfill the generation.');
    $this->processAllIndexWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $relationQuery = $this->container->get('aculta_index.relation_query');
    $studentReadings = $relationQuery->queryOutgoing(
      'group',
      (string) $course->id(),
      $course->language()->getId(),
      'course_reading',
      $student,
      NULL,
      10,
    );
    self::assertSame([(string) $publishedReading->id()], array_map(
      static fn ($entity): string => (string) $entity->id(),
      $studentReadings->entities,
    ), 'The student sees only currently accessible reading resources.');

    $educatorReadings = $relationQuery->queryOutgoing(
      'group',
      (string) $course->id(),
      $course->language()->getId(),
      'course_reading',
      $educator,
      NULL,
      10,
    );
    self::assertSame([(string) $publishedReading->id()], array_map(
      static fn ($entity): string => (string) $entity->id(),
      $educatorReadings->entities,
    ), 'An educator with contextual grading permission can query course resources.');
    $outsiderReadings = $relationQuery->queryOutgoing(
      'group',
      (string) $course->id(),
      $course->language()->getId(),
      'course_reading',
      $outsider,
      NULL,
      10,
    );
    self::assertSame([], $outsiderReadings->entities, 'A user without course access cannot discover its resources.');
  }

  /**
   * Queries a Gallery relation through the production course membership hook.
   */
  public function testCourseGalleryRelationUsesMembershipAccessInMagazinePurpose(): void {
    $owner = $this->createUser(['administer lms']);
    $student = $this->createUser();
    $educator = $this->createUser();
    $outsider = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso com galeria do Index',
      'uid' => $owner->id(),
    ]);
    $course->save();
    GroupRole::create([
      'id' => 'lms_course-index_gallery_student',
      'label' => 'Estudante da galeria',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['take course', 'view group'],
    ])->save();
    GroupRole::create([
      'id' => 'lms_course-index_gallery_educator',
      'label' => 'Educador da galeria',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['grade students', 'view group'],
    ])->save();
    $course->addMember($student, ['group_roles' => ['lms_course-index_gallery_student']]);
    $course->addMember($educator, ['group_roles' => ['lms_course-index_gallery_educator']]);

    $gallery = MediaGallery::create([
      'title' => 'Galeria autorizada pelo curso',
      'status' => TRUE,
      'images' => [],
    ]);
    $gallery->save();
    $course->set('field_lms_galleries', [['target_id' => $gallery->id()]]);
    $course->save();

    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The course, Gallery and Media providers should backfill the generation.');
    $this->processAllIndexWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);

    $providers = $this->container->get('aculta_index.provider_registry');
    self::assertSame('lms_course', $providers->findProviderIdForBundle('group', 'lms_course'));
    self::assertSame('gallery', $providers->findProviderIdForBundle('media_gallery', 'media_gallery'));
    self::assertFalse(
      $gallery->access('view', $student, TRUE)->isAllowed(),
      'Gallery access is not granted in the initiating main Domain.',
    );
    $relationQuery = $this->container->get('aculta_index.relation_query');
    foreach ([$student, $educator] as $authorizedAccount) {
      $this->domainSwitches = [];
      $result = $relationQuery->queryOutgoing(
        'group',
        (string) $course->id(),
        $course->language()->getId(),
        'course_gallery',
        $authorizedAccount,
        NULL,
        10,
      );
      self::assertSame([(string) $gallery->id()], array_map(
        static fn ($entity): string => (string) $entity->id(),
        $result->entities,
      ));
      self::assertSame(
        ['cursos_aculta_org', 'aculta_org', 'revista420_aculta_org', 'aculta_org'],
        $this->domainSwitches,
        'The Index checks course and Gallery access in their respective canonical Domains.',
      );
    }
    $this->domainSwitches = [];
    $denied = $relationQuery->queryOutgoing(
      'group',
      (string) $course->id(),
      $course->language()->getId(),
      'course_gallery',
      $outsider,
      NULL,
      10,
    );
    self::assertSame([], $denied->entities, 'An outsider cannot discover the linked gallery.');
    self::assertSame(['cursos_aculta_org', 'aculta_org'], $this->domainSwitches);
  }

  /**
   * Queries a course-document relation through its owner provider and access.
   */
  public function testCourseDocumentRelationUsesCourseMembershipAccess(): void {
    $authenticated = $this->container->get('entity_type.manager')->getStorage('user_role')->load('authenticated');
    $authenticated->grantPermission('view media')->save();
    $owner = $this->createUser(['administer lms']);
    $student = $this->createUser();
    $outsider = $this->createUser();
    $course = Group::create([
      'type' => 'lms_course',
      'label' => 'Curso com documento do Index',
      'uid' => $owner->id(),
    ]);
    $course->save();
    GroupRole::create([
      'id' => 'lms_course-index_document_student',
      'label' => 'Estudante do documento',
      'group_type' => 'lms_course',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => [
        'take course',
        'view group',
        'view aculta_pdf_viewer_group:course_document entity',
        'view any unpublished aculta_pdf_viewer_group:course_document entity',
      ],
    ])->save();
    $course->addMember($student, ['group_roles' => ['lms_course-index_document_student']]);

    $document = Media::create([
      'bundle' => 'course_document',
      'name' => 'Leitura privada do curso',
      'status' => FALSE,
    ]);
    $document->save();
    $course->addRelationship($document, 'aculta_pdf_viewer_group:course_document')->save();

    $generationId = $this->buildCourseResourceGeneration();
    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertSame('lms_course', $registry->findProviderIdForBundle('group', 'lms_course'));
    self::assertSame('course_document', $registry->findProviderIdForBundle('media', 'course_document'));

    $relationQuery = $this->container->get('aculta_index.relation_query');
    $studentDocuments = $relationQuery->queryOutgoing(
      'group',
      (string) $course->id(),
      $course->language()->getId(),
      'course_document',
      $student,
      NULL,
      10,
    );
    self::assertSame([(string) $document->id()], array_map(
      static fn ($entity): string => (string) $entity->id(),
      $studentDocuments->entities,
    ), 'A student with course membership can query its unpublished document.');

    $outsideDocuments = $relationQuery->queryOutgoing(
      'group',
      (string) $course->id(),
      $course->language()->getId(),
      'course_document',
      $outsider,
      NULL,
      10,
    );
    self::assertSame([], $outsideDocuments->entities, 'An outsider cannot discover a course document.');

    self::assertSame(1, (int) $this->container->get('database')->select('aculta_index_relation', 'relation')
      ->condition('generation_id', $generationId)
      ->condition('source_entity_id', (string) $course->id())
      ->condition('target_entity_id', (string) $document->id())
      ->condition('relation_type', 'course_document')
      ->countQuery()
      ->execute()
      ->fetchField());
  }

  /**
   * Installs the explicit LMS reading reference field for the real Group type.
   */
  private function installCourseReadingField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_lms_readings',
      'entity_type' => 'group',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_lms_readings',
      'entity_type' => 'group',
      'bundle' => 'lms_course',
      'label' => 'Course readings',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['revista' => 'revista']],
      ],
    ])->save();
  }

  /**
   * Installs the explicit LMS gallery reference field for the real Group type.
   */
  private function installCourseGalleryField(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_lms_galleries',
      'entity_type' => 'group',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media_gallery'],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_lms_galleries',
      'entity_type' => 'group',
      'bundle' => 'lms_course',
      'label' => 'Course galleries',
      'settings' => [
        'handler' => 'default:media_gallery',
        'handler_settings' => [],
      ],
    ])->save();
  }

  /**
   * Creates the fixture relation that mirrors the PDF owner plugin ID.
   */
  private function createCourseDocumentRelationType(): void {
    $this->container->get('group_relation_type.manager')->clearCachedDefinitions();
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $storage);
    $id = $storage->getRelationshipTypeId('lms_course', 'aculta_pdf_viewer_group:course_document');
    if (!$storage->load($id)) {
      $groupType = GroupType::load('lms_course');
      self::assertInstanceOf(GroupType::class, $groupType);
      $storage->createFromPlugin($groupType, 'aculta_pdf_viewer_group:course_document')->save();
    }
  }

  /**
   * Rebuilds and activates a generation with all source work drained.
   */
  private function buildCourseResourceGeneration(): string {
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 10 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    self::assertTrue($complete, 'The course and document providers should backfill the generation.');
    $this->processAllIndexWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
    return $generationId;
  }

  /**
   * Drains queued projections through the production Index worker.
   */
  private function processAllIndexWork(): void {
    $database = $this->container->get('database');
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('status', 'queued')
      ->orderBy('work_id')
      ->execute()
      ->fetchCol();
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

}
