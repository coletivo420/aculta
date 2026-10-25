<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies Index rechecks Group-controlled access for private course documents.
 */
#[TestGroup('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexCourseDocumentAccessKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'entity',
    'flexible_permissions',
    'group',
    'domain',
    'aculta_index',
    'aculta_index_course_document_access_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setDefinition('aculta_portal.domain_purpose', (new Definition(DomainPurposeManager::class, [
      new Reference('entity_type.manager'),
      new Reference('domain.negotiator'),
      new Reference('config.factory'),
      new Reference('request_stack'),
    ]))->setPublic(TRUE));

    // Use the exact owner declaration while keeping the Portal dependency
    // chain out of this focused authorization harness.
    $serviceMap = Yaml::parseFile(
      DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/aculta_pdf_viewer_group.services.yml',
      Yaml::PARSE_CUSTOM_TAGS,
    )['services'];
    $service = $serviceMap['aculta_pdf_viewer_group.course_document_content_index_provider'];
    $definition = (new Definition($service['class'], $service['arguments'] ?? []))->setPublic(TRUE);
    foreach ($service['tags'] as $tag) {
      $definition->addTag($tag['name'], array_diff_key($tag, ['name' => TRUE]));
    }
    $container->setDefinition('aculta_pdf_viewer_group.course_document_content_index_provider', $definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('domain');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['system', 'user', 'media', 'image', 'group', 'domain']);
    aculta_index_install();
    $this->createMediaType('file', ['id' => 'course_document', 'label' => 'Course document']);
    $this->createDomains();
    $this->installCourseDocumentField();
    $this->createCourseGroupType();

    $authenticated = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('view media')->save();
  }

  /**
   * Student access follows Group membership and is rechecked after revocation.
   */
  public function testFilterUsesCourseDocumentGroupAccessAndCanonicalDomain(): void {
    // Reserve UID 1 to prevent Core's superuser access shortcut.
    User::create(['name' => 'course-document-root-fixture', 'status' => TRUE])->save();
    $teacher = $this->createAccount('course-document-teacher');
    $student = $this->createAccount('course-document-student');
    $outsider = $this->createAccount('course-document-outsider');

    $group = Group::create([
      'type' => 'lms_course',
      'label' => 'Course document access fixture',
      'uid' => $teacher->id(),
    ]);
    $group->save();
    $group->addMember($student, ['group_roles' => ['lms_course-member']]);

    $file = File::create([
      'uri' => 'private://course-pdf/index-access-fixture.pdf',
      'filename' => 'index-access-fixture.pdf',
      'filemime' => 'application/pdf',
      'uid' => $teacher->id(),
    ]);
    $file->setPermanent();
    $file->save();
    $mediaType = MediaType::load('course_document');
    self::assertInstanceOf(MediaType::class, $mediaType);
    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType)->getName();
    $document = Media::create([
      'bundle' => 'course_document',
      'name' => 'Private course reading',
      'status' => FALSE,
      $sourceField => ['target_id' => $file->id()],
    ]);
    $document->setOwner($teacher);
    $document->save();
    $relationship = $group->addRelationship($document, 'aculta_pdf_viewer_group:course_document');
    $relationship->save();

    $providers = $this->container->get('aculta_index.provider_registry');
    self::assertSame('course_document', $providers->findProviderIdForBundle('media', 'course_document'));
    self::assertSame('courses', $providers->get('course_document')->getAccessPurpose($document));

    $mainDomain = Domain::load('aculta_org');
    self::assertInstanceOf(Domain::class, $mainDomain);
    $negotiator = $this->container->get('domain.negotiator');
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain($mainDomain);
    $candidate = [
      [
        'entity_type' => 'media',
        'entity_id' => (string) $document->id(),
        'langcode' => $document->language()->getId(),
      ],
    ];
    $filter = $this->container->get('aculta_index.access_filter');

    self::assertFalse($document->access('view', $outsider));
    self::assertCount(1, $filter->filter($candidate, $student)->entities);
    self::assertCount(0, $filter->filter($candidate, $outsider)->entities);
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());

    $group->removeMember($student);
    self::assertCount(0, $filter->filter($candidate, $student)->entities);
    self::assertSame('aculta_org', $negotiator->getActiveDomain()?->id());
  }

  /**
   * Creates an authenticated account with the media permission.
   */
  private function createAccount(string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => TRUE,
      'roles' => ['authenticated'],
    ]);
    $user->save();
    return $user;
  }

  /**
   * Installs the Media source field used by the private PDF bundle.
   */
  private function installCourseDocumentField(): void {
    FieldStorageConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_course_document',
      'type' => 'file',
      'settings' => ['uri_scheme' => 'private', 'file_directory' => 'course-pdf'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media',
      'bundle' => 'course_document',
      'field_name' => 'field_media_course_document',
      'label' => 'Course document file',
    ])->save();
  }

  /**
   * Creates the LMS Group type and its access-controlled document relation.
   */
  private function createCourseGroupType(): void {
    GroupType::create([
      'id' => 'lms_course',
      'label' => 'Course',
      'creator_membership' => FALSE,
    ])->save();
    $this->container->get('group_relation_type.manager')->clearCachedDefinitions();
    $relationshipTypes = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypes);
    $relationshipId = $relationshipTypes->getRelationshipTypeId(
      'lms_course',
      'aculta_pdf_viewer_group:course_document',
    );
    if (!$relationshipTypes->load($relationshipId)) {
      $relationshipTypes->createFromPlugin(
        GroupType::load('lms_course'),
        'aculta_pdf_viewer_group:course_document',
      )->save();
    }

    GroupRole::create([
      'id' => 'lms_course-member',
      'label' => 'Member',
      'group_type' => 'lms_course',
      'scope' => 'individual',
      'permissions' => [
        'view aculta_pdf_viewer_group:course_document entity',
        'view any unpublished aculta_pdf_viewer_group:course_document entity',
      ],
      'weight' => 0,
      'admin' => FALSE,
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Creates main and canonical Courses Domains.
   */
  private function createDomains(): void {
    foreach (['aculta_org' => 'main', 'cursos_aculta_org' => 'courses'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
        'weight' => 0,
      ])->save();
    }
  }

}
