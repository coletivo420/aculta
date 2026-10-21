<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\link\LinkItemInterface;

/**
 * Resolves internal node destinations through their canonical Domain purpose.
 */
final class LinkDestinationResolver {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurpose,
  ) {}

  /**
   * Resolves a reusable Link destination and its cacheability.
   *
   * Internal node references use the node's Domain Source. Public pages and
   * redirects check the target as anonymous so an editor's session cannot make
   * private content appear in a public link page.
   */
  public function resolve(?LinkItemInterface $item, AccountInterface $account, bool $public): LinkDestinationResolution {
    if ($item === NULL) {
      return new LinkDestinationResolution(NULL, new CacheableMetadata());
    }

    $uri = (string) $item->uri;
    if (preg_match('/^entity:node\/([1-9][0-9]*)$/D', $uri, $matches) !== 1) {
      try {
        return new LinkDestinationResolution($item->getUrl(), new CacheableMetadata());
      }
      catch (\InvalidArgumentException) {
        return new LinkDestinationResolution(NULL, new CacheableMetadata());
      }
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->load((int) $matches[1]);
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['url.site'])
      ->addCacheTags($storage->getEntityType()->getListCacheTags());
    if (!$node instanceof NodeInterface) {
      return new LinkDestinationResolution(NULL, $cacheability);
    }
    $cacheability->addCacheableDependency($node);

    if (!$node->hasField('field_domain_source') || $node->get('field_domain_source')->isEmpty()) {
      return new LinkDestinationResolution(NULL, $cacheability);
    }
    $purpose = $this->domainPurpose->getPurposeForDomainId((string) $node->get('field_domain_source')->target_id);
    $domain = $purpose !== NULL ? $this->domainPurpose->getDomain($purpose) : NULL;
    if ($purpose === NULL || $domain === NULL || ($node->isPublished() === FALSE && $public)) {
      if ($domain !== NULL) {
        $cacheability->addCacheableDependency($domain);
      }
      return new LinkDestinationResolution(NULL, $cacheability);
    }

    $access_account = $public ? new AnonymousUserSession() : $account;
    $access = $this->domainPurpose->runInPurpose(
      $purpose,
      static fn () => $node->access('view', $access_account, TRUE),
    );
    if (!$access instanceof AccessResultInterface) {
      return new LinkDestinationResolution(NULL, $cacheability->addCacheableDependency($domain));
    }
    $cacheability
      ->addCacheableDependency($domain)
      ->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return new LinkDestinationResolution(NULL, $cacheability);
    }

    $url = $this->domainPurpose->routeUrl($purpose, 'entity.node.canonical', ['node' => $node->id()]);
    if (!$url instanceof Url) {
      return new LinkDestinationResolution(NULL, $cacheability);
    }
    $options = $item->getUrl()->getOptions();
    foreach (['query', 'fragment'] as $option) {
      if (isset($options[$option])) {
        $url->setOption($option, $options[$option]);
      }
    }
    return new LinkDestinationResolution($url, $cacheability);
  }

}
