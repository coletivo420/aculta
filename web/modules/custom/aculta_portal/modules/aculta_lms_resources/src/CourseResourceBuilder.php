<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\media_gallery\MediaGalleryInterface;

/**
 * Builds safe links to existing editorial resources attached to a course.
 */
final class CourseResourceBuilder {

  /**
   * Node bundles and their canonical purposes.
   *
   * @var array<string, string>
   */
  private const NODE_PURPOSES = [
    'article' => 'magazine',
    'blog' => 'magazine',
    'wiki_entry' => 'wiki',
  ];

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Renders visible links only for course members and educators.
   *
   * @return array<string, mixed>
   *   Render array with access and cacheability metadata.
   */
  public function build(CourseInterface $course): array {
    $takeAccess = $course->takeAccess($this->currentUser);
    $canManage = $course->hasPermission('administer members', $this->currentUser)
      || $course->hasPermission('grade students', $this->currentUser);
    $cache = (new CacheableMetadata())
      ->addCacheableDependency($course)
      ->addCacheableDependency($takeAccess)
      ->addCacheContexts(['user', 'user.permissions', 'user.group_permissions', 'domain']);

    $build = ['#type' => 'container'];
    if (!$takeAccess->isAllowed() && !$canManage) {
      $cache->applyTo($build);
      return $build;
    }

    $readings = $this->linksFromField($course, 'field_lms_readings', 'node', $cache);
    $galleries = $this->linksFromField($course, 'field_lms_galleries', 'media_gallery', $cache);
    if ($readings === [] && $galleries === []) {
      $cache->applyTo($build);
      return $build;
    }

    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->translation->translate('Materiais de apoio'),
    ];
    if ($readings !== []) {
      $build['readings'] = [
        '#theme' => 'item_list',
        '#title' => $this->translation->translate('Leituras e verbetes'),
        '#items' => $readings,
      ];
    }
    if ($galleries !== []) {
      $build['galleries'] = [
        '#theme' => 'item_list',
        '#title' => $this->translation->translate('Galerias de imagens'),
        '#items' => $galleries,
      ];
    }
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Creates visible canonical links for a course resource field.
   *
   * @param \Drupal\lms\Entity\Bundle\CourseInterface $course
   *   Course containing resource references.
   * @param string $fieldName
   *   Course reference field name.
   * @param string $entityType
   *   Referenced entity type ID.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Metadata accumulated by the course room render array.
   *
   * @return array<int, array<string, mixed>>
   *   Accessible resource links.
   */
  private function linksFromField(
    CourseInterface $course,
    string $fieldName,
    string $entityType,
    CacheableMetadata $cache,
  ): array {
    if (!$course->hasField($fieldName)) {
      return [];
    }
    $items = [];
    foreach ($course->get($fieldName)->referencedEntities() as $entity) {
      if (!$entity instanceof EntityInterface || $entity->getEntityTypeId() !== $entityType
        || !$this->isPublishedResource($entity)) {
        if ($entity instanceof EntityInterface) {
          $cache->addCacheableDependency($entity);
        }
        continue;
      }
      $purpose = $this->purposeFor($entity);
      if ($purpose === NULL) {
        $cache->addCacheableDependency($entity);
        continue;
      }
      $domain = $this->domainPurposeManager->getDomain($purpose);
      if ($domain === NULL) {
        $cache->addCacheableDependency($entity);
        continue;
      }
      $access = $this->domainPurposeManager->runInPurpose(
        $purpose,
        fn (): AccessResultInterface => $entity->access('view', $this->currentUser, TRUE),
      );
      $cache->addCacheableDependency($domain)
        ->addCacheableDependency($entity);
      if (!$access instanceof AccessResultInterface) {
        continue;
      }
      $cache->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }

      $canonical = $entity->toUrl('canonical');
      $url = $this->domainPurposeManager->routeUrl(
        $purpose,
        $canonical->getRouteName(),
        $canonical->getRouteParameters(),
      );
      if ($url !== NULL) {
        $items[] = Link::fromTextAndUrl((string) $entity->label(), $url)->toRenderable();
      }
    }
    return $items;
  }

  /**
   * Returns the editorial purpose for an allowed resource entity.
   */
  private function purposeFor(EntityInterface $entity): ?string {
    if ($entity->getEntityTypeId() === 'node') {
      return self::NODE_PURPOSES[$entity->bundle()] ?? NULL;
    }
    return $entity->getEntityTypeId() === 'media_gallery' ? 'magazine' : NULL;
  }

  /**
   * Checks publication through the public API of each supported entity type.
   */
  private function isPublishedResource(EntityInterface $entity): bool {
    if ($entity instanceof EntityPublishedInterface) {
      return $entity->isPublished();
    }
    // Media Gallery 3.x exposes publication through MediaGalleryInterface and
    // its isEnabled() method; its entity class uses EntityPublishedTrait but
    // does not implement Core's EntityPublishedInterface.
    return $entity instanceof MediaGalleryInterface && $entity->isEnabled();
  }

}
