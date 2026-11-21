<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Moves a public ACULTA Forum topic between its public taxonomy categories.
 *
 * The existing Node is revised in place. Its identity, discussion, URL, author,
 * attachments, flags, and comment history therefore remain attached.
 */
final class ForumTopicMover {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.domain_site')]
    private readonly DomainSiteManager $siteManager,
    private readonly DiscussionOriginResolverInterface $originResolver,
    #[Autowire(service: 'logger.channel.aculta_forum')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns whether the account may start the category movement workflow.
   */
  public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    $origin = $this->originResolver->classify($node);
    if ($node->bundle() !== 'aculta_forum_topic'
      || !$node->hasField('field_domain_source')
      || !$node->hasField('field_aculta_categories')
      || $origin->origin !== DiscussionOrigin::CURRENT) {
      return AccessResult::forbidden()
        ->addCacheableDependency($node)
        ->addCacheableDependency($origin);
    }

    $forum_domain = $this->siteManager->getDomain(SiteKey::Forum);
    $catValues = $node->get('field_aculta_categories')->getValue();
    $catTarget = is_array($catValues) && isset($catValues[0]) && is_array($catValues[0]) ? ($catValues[0]['target_id'] ?? 0) : $node->get('field_aculta_categories')->target_id;
    $source_category_id = is_numeric($catTarget) ? (int) $catTarget : 0;
    $source_category = $source_category_id > 0
      ? $this->entityTypeManager->getStorage('taxonomy_term')->load($source_category_id)
      : NULL;
    $domainValues = $node->get('field_domain_source')->getValue();
    $domainTarget = is_array($domainValues) && isset($domainValues[0]) && is_array($domainValues[0]) ? ($domainValues[0]['target_id'] ?? '') : $node->get('field_domain_source')->target_id;
    $domainSourceId = is_scalar($domainTarget) ? (string) $domainTarget : '';
    $allowed = $forum_domain !== NULL
      && $account->hasPermission('move aculta forum topics')
      && $source_category instanceof TermInterface
      && $source_category->bundle() === 'aculta_forum_categories'
      && $source_category->access('view', $account)
      && $domainSourceId === (string) $forum_domain->id();

    $result = ($allowed ? AccessResult::allowed() : AccessResult::forbidden())
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($node)
      ->addCacheableDependency($origin);
    if ($forum_domain !== NULL) {
      $result->addCacheableDependency($forum_domain);
    }
    if ($source_category instanceof TermInterface) {
      $result->addCacheableDependency($source_category);
    }

    if ($allowed) {
      $node_access = $this->siteManager->runInSite('forum', fn () => $node->access('view', $account, TRUE));
      if ($node_access instanceof AccessResultInterface) {
        $result = $result->andIf($node_access);
      }
      else {
        $result = $result->andIf(AccessResult::forbidden());
      }
    }

    return $result;
  }

  /**
   * Moves a topic and records the operation as a new Node revision.
   *
   * @throws \InvalidArgumentException
   *   If either endpoint is outside the public Forum or access has changed.
   */
  public function move(int $nodeId, int $destinationId, AccountInterface $account): NodeInterface {
    $node = $this->entityTypeManager->getStorage('node')->load($nodeId);
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($destinationId);
    if (!$node instanceof NodeInterface || !$term instanceof TermInterface) {
      throw new \InvalidArgumentException('The Forum topic move is not available for this source or destination.');
    }
    $currentCatValues = $node->get('field_aculta_categories')->getValue();
    $currentCatTarget = is_array($currentCatValues) && isset($currentCatValues[0]) && is_array($currentCatValues[0]) ? ($currentCatValues[0]['target_id'] ?? 0) : $node->get('field_aculta_categories')->target_id;
    $currentCatId = is_numeric($currentCatTarget) ? (int) $currentCatTarget : 0;
    if (!$this->access($node, $account)->isAllowed()
      || $term->bundle() !== 'aculta_forum_categories'
      || !$term->access('view', $account)
      || $currentCatId === (int) $term->id()) {
      throw new \InvalidArgumentException('The Forum topic move is not available for this source or destination.');
    }

    $node->setNewRevision(TRUE);
    $node->set('field_aculta_categories', ['target_id' => $term->id()]);
    $node->setRevisionLogMessage('Categoria do tópico reorganizada pela administração do Fórum.');
    $node->setRevisionUserId((int) $account->id());
    $node->save();

    $this->logger->notice('Public Forum topic @node moved to category @category by uid @uid.', [
      '@node' => $node->id(),
      '@category' => $term->id(),
      '@uid' => $account->id(),
    ]);

    return $node;
  }

}
