<?php

namespace Drupal\aculta_donation\Routing;

use Drupal\Component\HttpFoundation\SecuredRedirectResponse;
use Drupal\Core\Routing\LocalAwareRedirectResponseTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Creates an uncached, but safe redirect response for /donate route.
 */
class DonationSecuredRedirectResponse extends SecuredRedirectResponse {

  use LocalAwareRedirectResponseTrait;

  /**
   * {@inheritdoc}
   */
  public function setTargetUrl($url): static {
    if (!$this->isSafe($url)) {
      throw new \InvalidArgumentException(sprintf('It is not safe to redirect to %s', $url));
    }
    $redirect = Url::fromUri($url);
    $buildRedirect = RedirectResponse::setTargetUrl($redirect->toString());
    $this->setContent($buildRedirect->getContent());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function isSafe($url) {
    return $this->isLocal($url) && preg_match('/^route:aculta_donation/', $url) === 1;
  }

}
