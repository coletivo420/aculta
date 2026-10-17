<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer_group\Kernel;

use Drupal\Core\DrupalKernel;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\file\Entity\File;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies private course PDFs follow Group membership at the Core endpoint.
 */
#[TestGroup('aculta_pdf_viewer_group')]
#[RunTestsInSeparateProcesses]
final class CourseDocumentAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\\Core\\StreamWrapper\\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

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
    'path_alias',
    'path',
    'options',
    'entity',
    'flexible_permissions',
    'group',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'user', 'media', 'path_alias', 'path', 'group']);

    $this->assertTrue($this->container->get('module_installer')->install([
      'aculta_pdf_viewer',
      'aculta_pdf_viewer_group',
    ], TRUE));

    GroupType::create([
      'id' => 'lms_course',
      'label' => 'Course',
      'creator_membership' => FALSE,
    ])->save();

    $this->container->get('group_relation_type.manager')->clearCachedDefinitions();

    $relationship_types = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    assert($relationship_types instanceof GroupRelationshipTypeStorageInterface);
    $relationship_id = $relationship_types->getRelationshipTypeId(
      'lms_course',
      'aculta_pdf_viewer_group:course_document',
    );
    if (!$relationship_types->load($relationship_id)) {
      $relationship_types->createFromPlugin(
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
   * Tests member, non-member, detached and private endpoint access.
   */
  public function testPrivatePdfRequiresCurrentGroupMembership(): void {
    $site_path = DrupalKernel::findSitePath(Request::create('/'));
    $private_directory = $site_path . '/private';
    $fixture_directory = $private_directory . '/course-pdf';
    mkdir($fixture_directory, 0775, TRUE);
    $this->setSetting('file_private_path', $private_directory);
    $private_path = $fixture_directory . '/membership-fixture.pdf';
    file_put_contents($private_path, "%PDF-1.4\n% private Group fixture\n");
    $file_uri = 'private://course-pdf/membership-fixture.pdf';

    $teacher = $this->createUser(['view media']);
    $member = $this->createUser(['view media']);
    $outsider = $this->createUser(['view media']);
    $file = File::create([
      'uri' => $file_uri,
      'filename' => 'membership-fixture.pdf',
      'filemime' => 'application/pdf',
      'uid' => $teacher->id(),
    ]);
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => 'course_document',
      'name' => 'Course access fixture',
      'status' => TRUE,
      'field_media_course_document' => [['target_id' => $file->id()]],
    ]);
    $media->setOwner($teacher);
    $media->save();
    $this->assertFalse($media->isPublished(), 'Course documents are forced unpublished.');
    $this->assertSame('private', parse_url($file->getFileUri(), PHP_URL_SCHEME));

    $group = Group::create([
      'type' => 'lms_course',
      'label' => 'Course access fixture',
      'uid' => $teacher->id(),
    ]);
    $group->save();
    $group->addMember($member, ['group_roles' => ['lms_course-member']]);
    $relationship = $group->addRelationship($media, 'aculta_pdf_viewer_group:course_document');
    $relationship->save();

    $this->container->get('current_user')->setAccount($member);
    $build = $this->container->get('aculta_pdf_viewer.builder')->build($media, 'field_media_course_document');
    $private_url = $build['#attributes']['data-aculta-pdf-url'];
    $this->assertSame('/system/files?file=course-pdf/membership-fixture.pdf', $private_url);
    $this->assertSame(0, $build['#cache']['max-age']);
    $this->assertArrayNotHasKey('thumbnail', $build);

    $entity_access = $this->container->get('entity_type.manager')->getAccessControlHandler('media');
    $file_access = $this->container->get('entity_type.manager')->getAccessControlHandler('file');
    $kernel = $this->container->get('http_kernel');
    $this->container->get('current_user')->setAccount($member);
    $this->assertTrue($media->access('view', $member, TRUE)->isAllowed());
    $member_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(200, $member_response->getStatusCode());

    // Removing the Group relationship must revoke a previously known URL too.
    $relationship->delete();
    $entity_access->resetCache();
    $file_access->resetCache();
    $detached_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(403, $detached_response->getStatusCode());

    // Reattach the Media so outsider and membership-revocation checks exercise
    // their own access conditions rather than the detached state above.
    $group->addRelationship($media, 'aculta_pdf_viewer_group:course_document');
    $entity_access->resetCache();
    $file_access->resetCache();
    $this->assertSame(200, $kernel->handle(Request::create($private_url))->getStatusCode());

    $this->container->get('current_user')->setAccount($outsider);
    $entity_access->resetCache();
    $file_access->resetCache();
    $this->assertFalse($media->access('view', $outsider, TRUE)->isAllowed());
    $outsider_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(403, $outsider_response->getStatusCode());

    $group->removeMember($member);
    $entity_access->resetCache();
    $file_access->resetCache();
    $this->container->get('current_user')->setAccount($member);
    $revoked_response = $kernel->handle(Request::create($private_url));
    $this->assertSame(403, $revoked_response->getStatusCode());
  }

}
