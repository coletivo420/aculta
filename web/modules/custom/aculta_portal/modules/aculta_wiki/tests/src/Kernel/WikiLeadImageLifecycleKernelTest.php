<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Kernel;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\aculta_wiki\Upload\WikiLeadImageUploader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests Wiki lead image publication and rollback with real entity storage.
 */
#[Group('aculta_wiki')]
#[RunTestsInSeparateProcesses]
final class WikiLeadImageLifecycleKernelTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * Temporary private scheme root for this isolated Kernel.
   */
  private string $privateDirectory;

  /**
   * {@inheritdoc}
   */
  protected function bootKernel(): void {
    // Portal consumes the shared account ledger when aculta_user is present.
    static::$modules = $this->resolveModuleDependencyClosure(['aculta_lms', 'aculta_user', 'aculta_wiki']);
    parent::bootKernel();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->privateDirectory = sys_get_temp_dir() . '/aculta-wiki-private-' . bin2hex(random_bytes(6));
    parent::setUp();
    $this->setSetting('file_private_path', $this->privateDirectory);
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'file', 'media', 'node']);

    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
      'cardinality' => 1,
    ])->save();
    NodeType::create(['type' => 'wiki_entry', 'name' => 'Wiki entry'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => WikiLeadImageUploader::FIELD,
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'wiki_entry',
      'field_name' => WikiLeadImageUploader::FIELD,
      'label' => 'Wiki lead image',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => ['target_bundles' => ['image' => 'image']],
      ],
    ])->save();

    foreach (['anonymous', 'authenticated'] as $roleId) {
      $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load($roleId);
      $role->grantPermission('access content')->grantPermission('view media')->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', PrivateStream::class)
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->container->get('file_system')->deleteRecursive($this->privateDirectory);
    parent::tearDown();
  }

  /**
   * A lead image is public only when the submitted Wiki revision is published.
   *
   * @dataProvider publicationStates
   */
  public function testMediaAndPrivateFileFollowNodePublication(bool $published): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage($owner->id());
    $state = $this->uploadState((int) $file->id());
    $form = [];
    $uploader = $this->uploader();
    $uploader->applyUpload($form, $state);

    $mediaId = (int) $state->getValue([WikiLeadImageUploader::FIELD, 0, 'target_id']);
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Imagem com publicação controlada',
      'uid' => $owner->id(),
      'status' => $published,
      WikiLeadImageUploader::FIELD => ['target_id' => $mediaId],
    ]);
    $formObject = $this->entityForm($node, static fn () => $node->save());
    $state->setFormObject($formObject);

    $uploader->prepareNodeSave($form, $state);
    $uploader->saveNode($form, $state);

    $media = Media::load($mediaId);
    self::assertInstanceOf(Media::class, $media);
    self::assertSame($published, $media->isPublished());
    self::assertSame($published, $media->access('view', new AnonymousUserSession()));
    self::assertStringStartsWith('private://wiki/imagens/', $file->getFileUri());
    self::assertSame($published, $file->access('download', new AnonymousUserSession()));
  }

  /**
   * A failed Node save rolls back staged Media and File permanence together.
   */
  public function testFailedNodeSaveRollsBackMediaAndFileState(): void {
    $owner = $this->createOwner();
    $file = $this->createTemporaryPrivateImage($owner->id());
    $fileId = (int) $file->id();
    $state = $this->uploadState($fileId);
    $form = [];
    $uploader = $this->uploader();
    $uploader->applyUpload($form, $state);
    $mediaId = (int) $state->getValue([WikiLeadImageUploader::FIELD, 0, 'target_id']);
    $node = Node::create([
      'type' => 'wiki_entry',
      'title' => 'Falha de salvamento',
      'uid' => $owner->id(),
      'status' => 0,
      WikiLeadImageUploader::FIELD => ['target_id' => $mediaId],
    ]);
    $formObject = $this->entityForm($node, static function () use ($node): void {
      $node->save();
      throw new \RuntimeException('Simulated failure after Node persistence.');
    });
    $state->setFormObject($formObject);
    $uploader->prepareNodeSave($form, $state);

    try {
      $uploader->saveNode($form, $state);
      self::fail('The simulated Node save failure should be rethrown.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('Simulated failure after Node persistence.', $exception->getMessage());
    }

    self::assertNull(Node::load((int) $node->id()));
    self::assertNull(Media::load($mediaId));
    $fileStorage = $this->container->get('entity_type.manager')->getStorage('file');
    $fileStorage->resetCache([$fileId]);
    self::assertTrue($fileStorage->load($fileId)->isTemporary());
  }

  /**
   * Publication states covered by the image visibility test.
   *
   * @return array<string, array{0: bool}>
   *   Whether the submitted Node revision is published.
   */
  public static function publicationStates(): array {
    return [
      'draft keeps Media and private file inaccessible' => [FALSE],
      'published entry allows its lead image' => [TRUE],
    ];
  }

  /**
   * Gets the real Wiki uploader service and swaps the test author into it.
   */
  private function uploader(): WikiLeadImageUploader {
    $owner = $this->container->get('current_user');
    $owner->setAccount($this->owner);
    return $this->container->get('aculta_wiki.wiki_lead_image_uploader');
  }

  /**
   * Test author used for uploads and nodes.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $owner;

  /**
   * Creates a confirmed, authenticated upload owner.
   */
  private function createOwner(): AccountInterface {
    $this->owner = new AnonymousUserSession();
    return $this->owner;
  }

  /**
   * Creates a temporary managed file containing a valid 1x1 PNG privately.
   */
  private function createTemporaryPrivateImage(int $uid): File {
    $directory = WikiLeadImageUploader::DIRECTORY;
    self::assertTrue($this->container->get('file_system')->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    ));
    $uri = 'private://wiki/imagens/' . $this->randomMachineName() . '.png';
    $repository = $this->container->get('file.repository');
    $file = $repository->writeData(
      base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pksAAAAASUVORK5CYII='),
      $uri,
      FileExists::Rename,
    );
    $file->setOwnerId($uid);
    $file->setTemporary();
    $file->save();
    return $file;
  }

  /**
   * Builds an upload state for the submitted managed file.
   */
  private function uploadState(int $fid): FormState {
    $state = new FormState();
    $state->setValues([
      WikiLeadImageUploader::TAB => [
        WikiLeadImageUploader::FILE => [$fid],
        WikiLeadImageUploader::ALT => 'Imagem de teste',
      ],
    ]);
    return $state;
  }

  /**
   * Builds a form object that invokes a real Node save callback.
   */
  private function entityForm(Node $node, callable $save): EntityFormInterface {
    $form = $this->createMock(EntityFormInterface::class);
    $form->method('getEntity')->willReturn($node);
    $form->method('save')->willReturnCallback(static function (array $form, FormState $state) use ($save): void {
      $save();
    });
    return $form;
  }

  /**
   * Resolves the installed module dependency closure in dependency order.
   *
   * @param string[] $roots
   *   Module IDs to boot.
   *
   * @return string[]
   *   Ordered module IDs including dependencies.
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

}
