<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Resources\Access;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\media\MediaInterface;

/**
 * Applies the educator and Group ownership rules to private course documents.
 */
final class CourseDocumentAccessCheck {

  /**
   * Group relation IDs supported by the document viewer owner.
   *
   * @var list<string>
   */
  private const RELATION_PLUGIN_IDS = [
    'aculta_document_viewer:course_document',
    'aculta_document_viewer_group:course_document',
  ];

  public function __construct(
    private readonly CourseAuthoringAccessCheck $authoringAccess,
    private readonly GroupRelationTypeManagerInterface $relationTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Checks whether the educator may create a private document in this course.
   */
  public function createAccess(GroupInterface $course): AccessResultInterface {
    $pluginId = $this->relationPluginId($course);
    if ($pluginId === NULL || $course->bundle() !== 'lms_course') {
      return AccessResult::forbidden()->addCacheableDependency($course);
    }

    $courseAccess = $this->authoringAccess->access($course);
    $documentAccess = $this->relationTypeManager
      ->getAccessControlHandler($pluginId)
      ->entityCreateAccess($course, $this->currentUser, TRUE);
    return $courseAccess->andIf($documentAccess)->addCacheableDependency($course);
  }

  /**
   * Checks update access for a document attached to this exact course.
   */
  public function updateAccess(GroupInterface $course, MediaInterface $document): AccessResultInterface {
    $pluginId = $this->relationPluginId($course);
    if ($pluginId === NULL || $course->bundle() !== 'lms_course'
      || $document->bundle() !== 'course_document'
      || !$this->belongsToCourse($course, $document, $pluginId)) {
      return AccessResult::forbidden()
        ->addCacheableDependency($course)
        ->addCacheableDependency($document);
    }

    $courseAccess = $this->authoringAccess->access($course);
    $documentAccess = $document->access('update', $this->currentUser, TRUE);
    return $courseAccess->andIf($documentAccess)
      ->addCacheableDependency($course)
      ->addCacheableDependency($document);
  }

  /**
   * Gets the installed relation plugin for course documents, if any.
   */
  public function relationPluginId(GroupInterface $course): ?string {
    foreach (self::RELATION_PLUGIN_IDS as $pluginId) {
      if ($course->getGroupType()->hasPlugin($pluginId)
        && $this->relationTypeManager->hasDefinition($pluginId)) {
        return $pluginId;
      }
    }
    return NULL;
  }

  /**
   * Confirms a document is related to the requested Group, not another one.
   */
  private function belongsToCourse(GroupInterface $course, MediaInterface $document, string $pluginId): bool {
    foreach ($course->getRelatedEntities($pluginId) as $related) {
      if ($related instanceof MediaInterface && (int) $related->id() === (int) $document->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
