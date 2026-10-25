<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_links\Entity\LinkPage;
use Drupal\aculta_links\Service\QrCodeSvgGenerator;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves a downloadable QR code for a published public link page.
 */
final class LinkPageQrCodeController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly DomainPurposeManager $domainPurpose,
    private readonly QrCodeSvgGenerator $generator,
  ) {}

  /**
   * Generates a QR code for the page's canonical links-purpose URL.
   */
  public function download(string $slug): CacheableResponse {
    $storage = $this->entityTypeManager->getStorage('aculta_link_page');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('slug', $slug)
      ->condition('status', TRUE)
      ->range(0, 2)
      ->execute();
    if (count($ids) !== 1) {
      throw new NotFoundHttpException();
    }

    $page = $storage->load(reset($ids));
    $access = $page instanceof LinkPage
      ? $page->access('view', $this->currentUser, TRUE)
      : NULL;
    if (!$page instanceof LinkPage || !$access?->isAllowed()) {
      throw new NotFoundHttpException();
    }

    $canonical = $this->domainPurpose->canonicalPathUrl('links', '/pagina/' . $slug);
    $canonical_uri = $canonical?->getUri();
    $canonical_parts = is_string($canonical_uri) ? parse_url($canonical_uri) : FALSE;
    if (!$canonical?->isExternal()
      || !is_array($canonical_parts)
      || ($canonical_parts['scheme'] ?? NULL) !== 'https'
      || empty($canonical_parts['host'])) {
      throw new NotFoundHttpException();
    }

    $response = new CacheableResponse($this->generator->generate($canonical->toString()));
    $response->headers->set('Content-Type', 'image/svg+xml; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="micropagina-' . $slug . '.svg"');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->addCacheableDependency($page);
    $response->addCacheableDependency($access);
    $domain = $this->domainPurpose->getDomain('links');
    if ($domain !== NULL) {
      $response->addCacheableDependency($domain);
    }

    return $response;
  }

}
