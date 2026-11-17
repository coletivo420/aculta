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
   *
   * @param string $url
   */
  public function setTargetUrl($url): static {
    $urlString = (string) $url;
    if (!$this->isSafe($urlString)) {
      throw new \InvalidArgumentException(sprintf('It is not safe to redirect to %s', $urlString));
    }
    $redirect = Url::fromUri($urlString);
    $buildRedirect = RedirectResponse::setTargetUrl($redirect->toString());
    $content = $buildRedirect->getContent();
    $this->setContent($content !== FALSE ? $content : '');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function isSafe($url) {
    return $this->isLocal($url) && preg_match('/^route:aculta_donation/', $url) === 1;
  }

}
