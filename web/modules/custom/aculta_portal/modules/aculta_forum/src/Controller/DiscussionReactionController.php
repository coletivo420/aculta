<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Access\DiscussionReactionEndpointAccess;
use Drupal\aculta_forum\Service\DiscussionReactionBuilder;
use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for handling reaction requests via HTMX or fallback redirect.
 */
class DiscussionReactionController extends ControllerBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $forumEntityTypeManager,
    protected readonly DiscussionReactionManager $reactionManager,
    protected readonly DiscussionReactionBuilder $reactionBuilder,
    protected readonly DiscussionReactionEndpointAccess $reactionEndpointAccess,
    protected readonly AccountProxyInterface $forumCurrentUser,
    protected readonly CsrfTokenGenerator $csrfTokenGenerator,
  ) {}

  /**
   * Processes a reaction to an entity.
   */
  public function react(string $entity_type, string $entity_id, Request $request): Response|array {
    if (!in_array($entity_type, ['comment', 'aculta_forum_post', 'node'], TRUE)) {
      throw new NotFoundHttpException('Invalid entity type.');
    }

    $storage = $this->forumEntityTypeManager->getStorage($entity_type);
    if ($storage === NULL) {
      throw new NotFoundHttpException();
    }

    $entity = $storage->load($entity_id);
    if ($entity === NULL) {
      throw new NotFoundHttpException('Entity not found.');
    }

    // CSRF Token validation.
    $token = $request->request->get('csrf_token') ?? $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfTokenGenerator->validate((string) $token, "aculta_forum:reaction:{$entity_type}:{$entity_id}")) {
      throw new AccessDeniedHttpException('Invalid or missing CSRF token.');
    }

    // Access check.
    $access = $this->reactionEndpointAccess->access($entity_type, $entity_id, $this->forumCurrentUser);
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException($access->getReason() ?: 'Access denied.');
    }

    // Reaction identifier from payload.
    $reactionId = (string) $request->request->get('reaction', '');
    if ($reactionId === '') {
      $reactionId = $this->reactionManager->getDefaultReaction();
    }

    $allowed = $this->reactionManager->getAllowedReactions();
    if (!isset($allowed[$reactionId])) {
      throw new BadRequestHttpException('Invalid reaction identifier.');
    }

    // Execute reaction toggle.
    $this->reactionManager->toggleReaction($entity, (int) $this->forumCurrentUser->id(), $reactionId);

    $renderArray = $this->reactionBuilder->build($entity, $this->forumCurrentUser);

    // If request comes from HTMX, render just the reaction bar component.
    if ($request->headers->has('HX-Request')) {
      return $renderArray;
    }

    // Non-JS / Form POST fallback: redirect back to destination or referer.
    $destination = $request->query->get('destination') ?: $request->headers->get('Referer');
    if ($destination) {
      return new RedirectResponse($destination);
    }

    return $renderArray;
  }

  /**
   * Returns the extra reactions picker popover markup via HTMX.
   */
  public function picker(string $entity_type, string $entity_id, Request $request): Response|array {
    if (!in_array($entity_type, ['comment', 'aculta_forum_post', 'node'], TRUE)) {
      throw new NotFoundHttpException('Invalid entity type.');
    }

    $storage = $this->forumEntityTypeManager->getStorage($entity_type);
    if ($storage === NULL) {
      throw new NotFoundHttpException();
    }

    $entity = $storage->load($entity_id);
    if ($entity === NULL) {
      throw new NotFoundHttpException('Entity not found.');
    }

    $renderArray = $this->reactionBuilder->buildPicker($entity, $this->forumCurrentUser);
    return $renderArray;
  }

}
