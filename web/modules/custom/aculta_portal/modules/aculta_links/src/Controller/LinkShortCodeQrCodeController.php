<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\aculta_links\Entity\Link;
use Drupal\aculta_links\Service\QrCodeSvgGenerator;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves a QR code for a published short link. */
final class LinkShortCodeQrCodeController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly DomainPurposeManager $domainPurpose,
    private readonly QrCodeSvgGenerator $generator,
  ) {}

  /**
   * Generates an SVG QR code for the canonical short redirect URL.
   */
  public function download(string $codigo): CacheableResponse {
    $storage = $this->entityTypeManager->getStorage('aculta_link');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('short_code', $codigo)
      ->condition('status', TRUE)
      ->range(0, 2)
      ->execute();
    if (count($ids) !== 1) {
      throw new NotFoundHttpException();
    }

    $link = $storage->load(reset($ids));
    $access = $link instanceof Link
      ? $link->access('view', $this->currentUser, TRUE)
      : NULL;
    if (!$link instanceof Link || !$access?->isAllowed() || $link->validate()->count() > 0) {
      throw new NotFoundHttpException();
    }

    $canonical = $this->domainPurpose->canonicalRouteUrl(
      'links',
      'aculta_links.short_redirect',
      ['codigo' => $codigo],
    );
    $canonical_uri = $canonical?->getUri();
    $parts = is_string($canonical_uri) ? parse_url($canonical_uri) : FALSE;
    if (!$canonical instanceof Url
      || !$canonical->isExternal()
      || !is_array($parts)
      || ($parts['scheme'] ?? NULL) !== 'https'
      || empty($parts['host'])) {
      throw new NotFoundHttpException();
    }

    $response = new CacheableResponse($this->generator->generate($canonical->toString()));
    $response->headers->set('Content-Type', 'image/svg+xml; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="link-curto-' . $codigo . '.svg"');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->addCacheableDependency($link);
    $response->addCacheableDependency($access);
    $domain = $this->domainPurpose->getDomain('links');
    if ($domain !== NULL) {
      $response->addCacheableDependency($domain);
    }

    return $response;
  }

}
