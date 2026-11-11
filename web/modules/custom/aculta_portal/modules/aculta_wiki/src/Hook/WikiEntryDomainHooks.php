<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Assigns new Wiki entries to their canonical Wiki Domain.
 */
final class WikiEntryDomainHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_site')]
    private readonly DomainSiteManager $siteManagers,
  ) {}

  /**
   * Assigns the canonical Wiki Domain when an entry has no explicit source.
   */
  #[Hook('node_presave')]
  public function nodePresave(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'wiki_entry'
      || !$entity->hasField('field_domain_source')
      || !$entity->get('field_domain_source')->isEmpty()) {
      return;
    }

    $domain = $this->siteManagers->getDomain(SiteKey::Wiki);
    if ($domain !== NULL) {
      $entity->set('field_domain_source', ['target_id' => $domain->id()]);
    }
  }

}
