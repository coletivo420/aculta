<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Participation;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_user\Participation\PublishedParticipationEvidence;
use Drupal\aculta_user\Participation\PublishedParticipationLookupInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Looks up only published default Wiki entries in the canonical Wiki Domain.
 */
final class WikiPublishedParticipationLookup implements PublishedParticipationLookupInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainSiteManager $siteManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc} */
  public function getContextId(): string {
    return 'wiki_entry';
  }

  /**
   * {@inheritdoc} */
  public function lookup(int $accountId): PublishedParticipationEvidence {
    $wikiDomain = $this->siteManager->getDomain(SiteKey::Wiki);
    if ($accountId < 1 || $wikiDomain === NULL || !$wikiDomain->status()) {
      return PublishedParticipationEvidence::unavailable();
    }

    try {
      $found = $this->siteManager->runInSite('wiki', function () use ($accountId, $wikiDomain): bool {
        $ids = $this->entityTypeManager->getStorage('node')->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', 'wiki_entry')
          ->condition('uid', $accountId)
          ->condition('field_domain_source.target_id', $wikiDomain->id())
          ->condition('status', 1)
          ->range(0, 1)
          ->execute();
        return $ids !== [];
      });
      if (!is_bool($found)) {
        return PublishedParticipationEvidence::unavailable();
      }
    }
    catch (\Throwable) {
      // The unavailable status records the failure and keeps the lookup
      // fail-closed: it is never evidence that publication has occurred.
      return PublishedParticipationEvidence::unavailable();
    }

    $cacheContexts = $accountId === (int) $this->currentUser->id() ? ['user'] : [];
    $cacheMaxAge = $cacheContexts ? Cache::PERMANENT : 0;
    return PublishedParticipationEvidence::known(
      $found,
      $cacheContexts,
      Cache::mergeTags(['node_list:wiki_entry'], $wikiDomain->getCacheTags()),
      $cacheMaxAge,
    );
  }

}
