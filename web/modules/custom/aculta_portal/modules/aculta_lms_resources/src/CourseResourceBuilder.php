<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;
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
    'revista' => 'magazine',
    'wiki_entry' => 'wiki',
  ];

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly TranslationInterface $translation,
    private readonly GroupRelationTypeManagerInterface $relationTypeManager,
  ) {}

  /**
   * Renders visible links only for course members and educators.
   *
   * @return array<string, mixed>
   *   Render array with access and cacheability metadata.
   */
  public function build(GroupInterface $course): array {
    $takeAccess = AccessResult::allowedIf($course->hasPermission('take course', $this->currentUser))
      ->cachePerUser()
      ->addCacheContexts(['user.permissions', 'user.group_permissions']);
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
    $videos = $this->linksFromField($course, 'field_lms_videos', 'media', $cache);
    $documents = $this->documentLinks($course, $cache);
    if ($readings === [] && $galleries === [] && $videos === [] && $documents === []) {
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
    if ($videos !== []) {
      $build['videos'] = [
        '#theme' => 'item_list',
        '#title' => $this->translation->translate('Vídeos do curso'),
        '#items' => $videos,
      ];
    }
    if ($documents !== []) {
      $build['documents'] = [
        '#theme' => 'item_list',
        '#title' => $this->translation->translate('Documentos do curso'),
        '#items' => $documents,
      ];
    }
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Creates visible canonical links for a course resource field.
   *
   * @param \Drupal\group\Entity\GroupInterface $course
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
    GroupInterface $course,
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

      if ($entity instanceof MediaInterface && $entity->bundle() === 'remote_video') {
        // With standalone Media URLs disabled, Core's canonical route points
        // to the editorial form (/media/{id}/edit). Link to the validated
        // public oEmbed source instead, after the Media access check above.
        $url = $this->videoSourceUrl($entity);
      }
      else {
        $canonical = $entity->toUrl('canonical');
        $url = $this->domainPurposeManager->routeUrl(
          $purpose,
          $canonical->getRouteName(),
          $canonical->getRouteParameters(),
        );
      }
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
    if ($entity->getEntityTypeId() === 'media_gallery') {
      return 'magazine';
    }
    return $entity->getEntityTypeId() === 'media' && $entity->bundle() === 'remote_video'
      ? 'courses'
      : NULL;
  }

  /**
   * Returns a safe external destination for an authorized remote video.
   */
  private function videoSourceUrl(MediaInterface $video): ?Url {
    $source = $video->getSource();
    if (!$source instanceof MediaSourceInterface || $source->getPluginId() !== 'oembed:video') {
      return NULL;
    }

    $uri = $source->getSourceFieldValue($video);
    if (!is_string($uri) || !UrlHelper::isValid($uri, TRUE)) {
      return NULL;
    }
    $parts = parse_url($uri);
    if (!is_array($parts)
      || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], TRUE)
      || empty($parts['host'])
      || isset($parts['user'])
      || isset($parts['pass'])) {
      return NULL;
    }

    return Url::fromUri($uri);
  }

  /**
   * Builds links to documents attached through the private Group relation.
   *
   * Course-document Media is intentionally unpublished. Group relation access
   * controls visibility, so this path checks entity access directly instead of
   * requiring publication as the public editorial references do.
   *
   * @return list<array<string, mixed>>
   *   Links to accessible course documents.
   */
  private function documentLinks(GroupInterface $course, CacheableMetadata $cache): array {
    $pluginId = $this->relationTypeManager->hasDefinition('aculta_document_viewer:course_document')
      ? 'aculta_document_viewer:course_document'
      : ($this->relationTypeManager->hasDefinition('aculta_document_viewer_group:course_document')
        ? 'aculta_document_viewer_group:course_document'
        : NULL);
    if ($pluginId === NULL) {
      return [];
    }

    $items = [];
    foreach ($course->getRelatedEntities($pluginId) as $entity) {
      if (!$entity instanceof MediaInterface || $entity->bundle() !== 'course_document') {
        continue;
      }
      $domain = $this->domainPurposeManager->getDomain('courses');
      if ($domain === NULL) {
        $cache->addCacheableDependency($entity);
        continue;
      }
      $access = $this->domainPurposeManager->runInPurpose(
        'courses',
        fn (): AccessResultInterface => $entity->access('view', $this->currentUser, TRUE),
      );
      $cache->addCacheableDependency($domain)->addCacheableDependency($entity);
      if (!$access instanceof AccessResultInterface) {
        continue;
      }
      $cache->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $canonical = $entity->toUrl('canonical');
      $url = $this->domainPurposeManager->routeUrl(
        'courses',
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
