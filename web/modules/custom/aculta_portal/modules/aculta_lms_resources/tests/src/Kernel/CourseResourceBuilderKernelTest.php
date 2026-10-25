<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_resources\Kernel;

use Drupal\Core\Url;
use Drupal\aculta_lms_resources\CourseResourceBuilder;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves resource visibility is evaluated under each source Domain purpose.
 */
#[Group('aculta_lms_resources')]
#[RunTestsInSeparateProcesses]
final class CourseResourceBuilderKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'domain'];

  /**
   * The Domain purpose resolver used by the integration. */
  private DomainPurposeManager $purposeManager;

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
    $this->createDomains();
    $negotiator = $this->container->get(DomainNegotiatorInterface::class);
    $negotiator->getActiveDomain();
    $negotiator->setActiveDomain(Domain::load('cursos_aculta_org'));
    $this->purposeManager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $negotiator,
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
  }

  /**
   * Published article, Wiki entry and gallery links keep their source purpose.
   */
  public function testLinksUsePublishedResourcesAndTheirSourceDomains(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->course($account);
    $article = $this->node(11, 'article', 'Artigo do curso', 'magazine', $account);
    $wiki = $this->node(12, 'wiki_entry', 'Verbete do curso', 'wiki', $account);
    $gallery = $this->gallery(13, 'Galeria do curso', $account);
    $draft = $this->node(14, 'blog', 'Rascunho oculto', 'magazine', $account, FALSE);
    $course->method('get')->willReturnMap([
      ['field_lms_readings', $this->referenceList([$article, $wiki, $draft])],
      ['field_lms_galleries', $this->referenceList([$gallery])],
    ]);
    $course->method('hasField')->willReturn(TRUE);

    $builder = new CourseResourceBuilder(
      $this->purposeManager,
      $account,
      $this->container->get('string_translation'),
    );
    $build = $builder->build($course);

    self::assertCount(2, $build['readings']['#items']);
    self::assertCount(1, $build['galleries']['#items']);
    self::assertSame('Artigo do curso', $build['readings']['#items'][0]['#title']);
    self::assertSame('entity.node.canonical', $build['readings']['#items'][0]['#url']->getRouteName());
    self::assertSame(['node' => 11], $build['readings']['#items'][0]['#url']->getRouteParameters());
    self::assertSame('entity.media_gallery.canonical', $build['galleries']['#items'][0]['#url']->getRouteName());
    self::assertSame(['media_gallery' => 13], $build['galleries']['#items'][0]['#url']->getRouteParameters());
    self::assertContains('domain', $build['#cache']['contexts']);
    self::assertContains('user.permissions', $build['#cache']['contexts']);
    self::assertContains('user.group_permissions', $build['#cache']['contexts']);
    self::assertContains('node:11', $build['#cache']['tags']);
    self::assertContains('node:12', $build['#cache']['tags']);
    self::assertContains('media_gallery:13', $build['#cache']['tags']);
    self::assertContains(
      'node:14',
      $build['#cache']['tags'],
      'Unpublished references remain dependencies so publishing invalidates the course cache.',
    );
    self::assertSame('cursos_aculta_org', $this->container->get(DomainNegotiatorInterface::class)->getActiveDomain()?->id());
  }

  /**
   * A denied target access result is omitted and its cache metadata is kept.
   */
  public function testDeniedTargetIsNotRenderedAndInvalidatesWithAccess(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->course($account);
    $restricted = $this->node(
      21,
      'wiki_entry',
      'Verbete restrito',
      'wiki',
      $account,
      accessResult: AccessResult::forbidden()->addCacheContexts(['user.permissions']),
    );
    $course->method('get')->willReturnMap([
      ['field_lms_readings', $this->referenceList([$restricted])],
      ['field_lms_galleries', $this->referenceList([])],
    ]);
    $course->method('hasField')->willReturn(TRUE);

    $builder = new CourseResourceBuilder(
      $this->purposeManager,
      $account,
      $this->container->get('string_translation'),
    );
    $build = $builder->build($course);

    self::assertArrayNotHasKey('readings', $build);
    self::assertArrayNotHasKey('galleries', $build);
    self::assertContains('user.permissions', $build['#cache']['contexts']);
    self::assertContains('node:21', $build['#cache']['tags']);
  }

  /**
   * Creates a course that grants contextual access to the learner. */
  private function course(AccountProxyInterface $account): CourseInterface {
    $course = $this->createMock(CourseInterface::class);
    $course->method('takeAccess')
      ->with($account)
      ->willReturn(AccessResult::allowed()->addCacheContexts(['user.group_permissions']));
    $course->method('hasPermission')->willReturn(FALSE);
    $course->method('getCacheTags')->willReturn(['group:course']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    return $course;
  }

  /**
   * Creates a published or draft node whose access depends on its purpose. */
  private function node(
    int $id,
    string $bundle,
    string $label,
    string $purpose,
    AccountProxyInterface $account,
    bool $published = TRUE,
    ?AccessResult $accessResult = NULL,
  ): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getEntityTypeId')->willReturn('node');
    $node->method('bundle')->willReturn($bundle);
    $node->method('label')->willReturn($label);
    $node->method('isPublished')->willReturn($published);
    $node->method('getCacheTags')->willReturn(['node:' . $id]);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);
    $node->method('access')->willReturnCallback(function (string $operation, $accessAccount, bool $returnAsObject) use ($account, $purpose, $accessResult): AccessResult {
      self::assertSame('view', $operation);
      self::assertSame($account, $accessAccount);
      self::assertTrue($returnAsObject);
      $expectedDomain = match ($purpose) {
        'magazine' => 'revista420_aculta_org',
        'wiki' => 'wiki420_aculta_org',
        default => '',
      };
      self::assertSame($expectedDomain, $this->container->get(DomainNegotiatorInterface::class)->getActiveDomain()?->id());
      return $accessResult ?? AccessResult::allowed()->addCacheContexts(['user.permissions']);
    });
    $node->method('toUrl')->with('canonical')->willReturn(Url::fromRoute('entity.node.canonical', ['node' => $id]));
    return $node;
  }

  /**
   * Creates a published gallery using the Media Gallery public interface. */
  private function gallery(int $id, string $label, AccountProxyInterface $account): MediaGalleryInterface {
    $gallery = $this->createMock(MediaGalleryInterface::class);
    $gallery->method('getEntityTypeId')->willReturn('media_gallery');
    $gallery->method('isEnabled')->willReturn(TRUE);
    $gallery->method('label')->willReturn($label);
    $gallery->method('getCacheTags')->willReturn(['media_gallery:' . $id]);
    $gallery->method('getCacheContexts')->willReturn([]);
    $gallery->method('getCacheMaxAge')->willReturn(-1);
    $gallery->method('access')->willReturnCallback(function (string $operation, $accessAccount, bool $returnAsObject) use ($account): AccessResult {
      self::assertSame('view', $operation);
      self::assertSame($account, $accessAccount);
      self::assertTrue($returnAsObject);
      self::assertSame('revista420_aculta_org', $this->container->get(DomainNegotiatorInterface::class)->getActiveDomain()?->id());
      return AccessResult::allowed()->addCacheContexts(['user.permissions']);
    });
    $gallery->method('toUrl')->with('canonical')->willReturn(Url::fromRoute('entity.media_gallery.canonical', ['media_gallery' => $id]));
    return $gallery;
  }

  /**
   * Creates an entity-reference field list.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Referenced resources.
   */
  private function referenceList(array $entities): EntityReferenceFieldItemListInterface {
    $list = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $list->method('referencedEntities')->willReturn($entities);
    return $list;
  }

  /**
   * Creates the source Domains used by cross-purpose access checks. */
  private function createDomains(): void {
    foreach (['courses' => 'cursos', 'magazine' => 'revista420', 'wiki' => 'wiki420'] as $purpose => $host) {
      Domain::create([
        'id' => match ($purpose) {
          'courses' => 'cursos_aculta_org',
          'magazine' => 'revista420_aculta_org',
          'wiki' => 'wiki420_aculta_org',
        },
        'hostname' => $host . '.example.invalid',
        'name' => $purpose,
        'scheme' => 'https',
        'weight' => 0,
        'status' => TRUE,
      ])->save();
    }
  }

}
