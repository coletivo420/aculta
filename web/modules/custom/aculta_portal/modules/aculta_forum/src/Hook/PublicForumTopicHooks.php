<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maintains the public Forum purpose on independent topic nodes.
 */
final class PublicForumTopicHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_site')]
    private readonly DomainSiteManager $siteManager,
  ) {}

  /**
   * Prevents topic creation when the public Forum domain is not configured.
   */
  #[Hook('entity_create_access')]
  public function entityCreateAccess(AccountInterface $account, array $context, ?string $entity_bundle): ?AccessResult {
    if (($context['entity_type_id'] ?? NULL) !== 'node' || $entity_bundle !== 'aculta_forum_topic') {
      return NULL;
    }

    $domain = $this->siteManager->getDomain(SiteKey::Forum);
    $isForumPurpose = $domain !== NULL
      && $this->siteManager->getCurrentSiteKey()?->value === 'forum';
    $result = AccessResult::forbiddenIf(!$isForumPurpose)
      ->addCacheContexts(['domain'])
      ->addCacheTags(['config:domain.record.forum_aculta_org']);
    if ($domain !== NULL) {
      $result->addCacheableDependency($domain);
    }
    return $result;
  }

  /**
   * Assigns the canonical Forum domain when a topic has no explicit source.
   */
  #[Hook('node_presave')]
  public function nodePresave(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'aculta_forum_topic'
      || !$entity->hasField('field_domain_source')
      || !$entity->get('field_domain_source')->isEmpty()) {
      return;
    }

    $domain = $this->siteManager->getDomain(SiteKey::Forum);
    if ($domain !== NULL) {
      $entity->set('field_domain_source', ['target_id' => $domain->id()]);
    }
  }

}
