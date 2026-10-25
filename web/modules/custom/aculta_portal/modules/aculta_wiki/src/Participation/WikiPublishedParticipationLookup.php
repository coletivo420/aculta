<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Participation;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
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
    private readonly DomainPurposeManager $domainPurposeManager,
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
    $wikiDomain = $this->domainPurposeManager->getDomain('wiki');
    if ($accountId < 1 || $wikiDomain === NULL || !$wikiDomain->status()) {
      return PublishedParticipationEvidence::unavailable();
    }

    try {
      $found = $this->domainPurposeManager->runInPurpose('wiki', function () use ($accountId, $wikiDomain): bool {
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
