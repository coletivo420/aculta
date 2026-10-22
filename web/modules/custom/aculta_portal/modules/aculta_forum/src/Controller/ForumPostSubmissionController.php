<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds a new public Forum post in its validated topic context.
 */
final class ForumPostSubmissionController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFormBuilderInterface $entityFormBuilder,
    private readonly RequestStack $requestStack,
    private readonly AccountProxyInterface $currentUser,
    private readonly DiscussionContextResolver $discussionContextResolver,
  ) {}

  /**
   * Returns the Core entity form for a new post in the requested topic.
   */
  public function form(): array {
    $uuid = (string) $this->requestStack->getCurrentRequest()?->query->get('topico', '');
    $topicIds = $uuid === '' ? [] : $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $uuid)
      ->range(0, 1)
      ->execute();
    $topic = $topicIds === [] ? NULL : $this->entityTypeManager->getStorage('node')->load(reset($topicIds));
    $context = $topic === NULL ? NULL : $this->discussionContextResolver->resolve($topic, $this->currentUser);
    if ($topic === NULL || $context === NULL || $context->getType() !== 'public_forum' || !$context->getWriteAccess()->isAllowed()) {
      throw new NotFoundHttpException();
    }

    $parentUuid = (string) $this->requestStack->getCurrentRequest()?->query->get('resposta', '');
    $parent = NULL;
    if ($parentUuid !== '') {
      $postStorage = $this->entityTypeManager->getStorage('aculta_forum_post');
      $parentIds = $postStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('uuid', $parentUuid)
        ->condition('status', 1)
        ->range(0, 1)
        ->execute();
      $parent = $parentIds === [] ? NULL : $postStorage->load(reset($parentIds));
      if ($parent === NULL
        || !$parent->isPublished()
        || !$parent->isDefaultRevision()
        || (string) $parent->get('topic')->target_id !== (string) $topic->id()
        || !$parent->access('view', $this->currentUser)->isAllowed()) {
        throw new NotFoundHttpException();
      }
    }

    $post = $this->entityTypeManager->getStorage('aculta_forum_post')->create([
      'uid' => $this->currentUser->id(),
      'topic' => $topic->id(),
      'parent' => $parent?->id(),
      'thread_position' => 0,
      'moderation_state' => 'draft',
    ]);
    return $this->entityFormBuilder->getForm($post, 'public_submission');
  }

}
