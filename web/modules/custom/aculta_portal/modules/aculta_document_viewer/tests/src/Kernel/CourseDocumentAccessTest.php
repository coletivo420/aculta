<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Kernel;

use Drupal\Core\DrupalKernel;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\KernelTests\KernelTestBase;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies private course PDFs follow Group membership at the Core endpoint.
 */
#[TestGroup('aculta_document_viewer')]
#[RunTestsInSeparateProcesses]
final class CourseDocumentAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected function bootKernel(): void {
    static::$modules = $this->resolveModuleDependencyClosure([
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
      'domain',
      'search_api_db',
      'aculta_user',
      'aculta_document_viewer',
    ]);
    parent::bootKernel();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installSchema('aculta_user', ['aculta_user_agreement', 'aculta_user_nickname']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'user', 'media', 'path_alias', 'path', 'group']);

    $this->assertTrue($this->container->get('module_installer')->install([
      'aculta_user',
      'aculta_document_viewer',
    ], TRUE));
    $this->installConfig([
      'aculta_user',
      'aculta_document_viewer',
    ]);
    foreach ($this->container->get('entity_type.manager')->getStorage('profile_type')->loadMultiple() as $bundle => $_profile_type) {
      FieldConfig::create([
        'field_name' => 'field_nickname',
        'entity_type' => 'profile',
        'bundle' => $bundle,
        'type' => 'string',
        'label' => 'Apelido',
      ])->save();
      FieldConfig::create([
        'field_name' => 'field_perfil_publico',
        'entity_type' => 'profile',
        'bundle' => $bundle,
        'type' => 'boolean',
        'label' => 'Perfil público',
      ])->save();
    }

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
      'aculta_document_viewer:course_document',
    );
    if (!$relationship_types->load($relationship_id)) {
      $relationship_types->createFromPlugin(
        GroupType::load('lms_course'),
        'aculta_document_viewer:course_document',
      )->save();
    }

    GroupRole::create([
      'id' => 'lms_course-member',
      'label' => 'Member',
      'group_type' => 'lms_course',
      'scope' => 'individual',
      'permissions' => [
        'view aculta_document_viewer:course_document entity',
        'view any unpublished aculta_document_viewer:course_document entity',
      ],
      'weight' => 0,
      'admin' => FALSE,
    ])->save();

    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Resolves the real transitive dependencies of the integrated modules.
   *
   * @param string[] $roots
   *   Modules whose full dependency graph must be available to the Kernel.
   *
   * @return string[]
   *   Module IDs ordered with dependencies before dependents.
   */
  private function resolveModuleDependencyClosure(array $roots): array {
    $discovery = new ExtensionDiscovery(DRUPAL_ROOT);
    $discovery->setProfileDirectories([]);
    $extensions = $discovery->scan('module');
    $ordered = [];
    $visited = [];
    $visiting = [];

    $visit = function (string $module) use (&$visit, &$ordered, &$visited, &$visiting, $extensions): void {
      if (isset($visited[$module])) {
        return;
      }
      if (isset($visiting[$module])) {
        throw new \LogicException(sprintf('Circular module dependency detected at %s.', $module));
      }
      if (!isset($extensions[$module])) {
        throw new \RuntimeException(sprintf('Required module %s is unavailable.', $module));
      }

      $visiting[$module] = TRUE;
      $info = Yaml::parseFile(DRUPAL_ROOT . '/' . $extensions[$module]->getPathname());
      foreach ($info['dependencies'] ?? [] as $dependency) {
        if (!is_string($dependency)) {
          continue;
        }
        $dependency = substr($dependency, strrpos($dependency, ':') === FALSE ? 0 : strrpos($dependency, ':') + 1);
        if (isset($extensions[$dependency])) {
          $visit($dependency);
        }
      }
      unset($visiting[$module]);
      $visited[$module] = TRUE;
      $ordered[] = $module;
    };

    foreach ($roots as $root) {
      $visit($root);
    }

    return $ordered;
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
    $relationship = $group->addRelationship($media, 'aculta_document_viewer:course_document');
    $relationship->save();

    $this->container->get('current_user')->setAccount($member);
    $build = $this->container->get('aculta_document_viewer.pdf_builder')->build($media, 'field_media_course_document');
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
    $group->addRelationship($media, 'aculta_document_viewer:course_document');
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
