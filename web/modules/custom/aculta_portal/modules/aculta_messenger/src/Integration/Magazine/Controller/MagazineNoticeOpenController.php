<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Magazine\Controller;

use Drupal\aculta_messenger\Integration\Magazine\MagazineNoticeManagerInterface;
use Drupal\aculta_messenger\Service\MemberInitiatedRequest;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opens an owned Magazine notice after revalidating its source access. */
final class MagazineNoticeOpenController {

  public function __construct(
    private readonly MagazineNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Marks an authorized notice read and redirects to the source. */
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
