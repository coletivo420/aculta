<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Events\Controller;

use Drupal\aculta_messenger\Integration\Events\EventsContributionNoticeManagerInterface;
use Drupal\aculta_messenger\Service\MemberInitiatedRequest;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Opens an owned Events proposal notice after revalidating source access. */
final class EventsContributionNoticeOpenController {

  /**
   * Creates the controller with account, notice manager, and request stack.
   */
  public function __construct(
    private readonly EventsContributionNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Opens an authorized notice and marks it read for member requests. */
  public function open(MessageInterface $message): Response {
    $resolved = $this->notices->resolve($message, $this->currentUser);
    if ($resolved === NULL) {
      throw new NotFoundHttpException();
    }
    $request = $this->requestStack->getCurrentRequest();
    if (MemberInitiatedRequest::isPrefetchOrPrerender($request)) {
      return new Response('', Response::HTTP_NO_CONTENT, ['Cache-Control' => 'private, no-store']);
    }
    if (MemberInitiatedRequest::isMemberInitiated($request)
      && !$this->notices->setRead($message, $this->currentUser, TRUE)) {
      throw new NotFoundHttpException();
    }
    return new TrustedRedirectResponse($resolved['url']->toString(), Response::HTTP_FOUND, [
      'Cache-Control' => 'private, no-store',
    ]);
  }

}
