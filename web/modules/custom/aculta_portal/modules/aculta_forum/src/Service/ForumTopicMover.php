<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'logger.channel.aculta_forum')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns whether the account may start the category movement workflow.
   */
  public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    if ($node->bundle() !== 'aculta_forum_topic'
      || !$node->hasField('field_domain_source')
      || !$node->hasField('field_autor_legado')
      || !$node->hasField('field_aculta_categories')) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }

    $forum_domain = $this->domainPurposeManager->getDomain('forum');
    $source_category_id = (int) ($node->get('field_aculta_categories')->getValue()[0]['target_id'] ?? 0);
    $source_category = $source_category_id > 0
      ? $this->entityTypeManager->getStorage('taxonomy_term')->load($source_category_id)
      : NULL;
    $allowed = $forum_domain !== NULL
      && $account->hasPermission('move aculta forum topics')
      && $node->get('field_autor_legado')->isEmpty()
      && $source_category instanceof TermInterface
      && $source_category->bundle() === 'aculta_forum_categories'
      && $source_category->access('view', $account)
      && (string) ($node->get('field_domain_source')->getValue()[0]['target_id'] ?? '') === (string) $forum_domain->id();

    $result = ($allowed ? AccessResult::allowed() : AccessResult::forbidden())
      ->addCacheContexts(['user.permissions'])
      ->addCacheableDependency($node);
    if ($forum_domain !== NULL) {
      $result->addCacheableDependency($forum_domain);
    }
    if ($source_category instanceof TermInterface) {
      $result->addCacheableDependency($source_category);
    }

    if ($allowed) {
      $node_access = $this->domainPurposeManager->runInPurpose('forum', fn () => $node->access('view', $account, TRUE));
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
    if (!$node instanceof NodeInterface || !$term instanceof TermInterface
      || !$this->access($node, $account)->isAllowed()
      || $term->bundle() !== 'aculta_forum_categories'
      || !$term->access('view', $account)
      || (int) ($node->get('field_aculta_categories')->getValue()[0]['target_id'] ?? 0) === (int) $term->id()) {
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
