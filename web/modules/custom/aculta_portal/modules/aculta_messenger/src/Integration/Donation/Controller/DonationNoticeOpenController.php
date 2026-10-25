<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation\Controller;

use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;
use Drupal\aculta_messenger\Service\MemberInitiatedRequest;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Opens an owned Donation notice after access is revalidated. */
final class DonationNoticeOpenController {

  public function __construct(
    private readonly DonationNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Marks the notice read and returns the member to the account dashboard. */
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
      && !$this->notices->markRead($message, $this->currentUser)) {
      throw new NotFoundHttpException();
    }
    return new TrustedRedirectResponse($resolved['url']->toString());
  }

}
