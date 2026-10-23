<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the contributor's own Wiki work to WE SATIVA.
 *
 * The service is owned by Wiki. Studio receives only authorized entities and
 * render links; it does not define contributor access or store editorial data.
 */
final class WikiContributorWorkspace {

  use StringTranslationTrait;

  private const PAGE_SIZE = 20;

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly DomainPurposeManager $domainPurposes,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Cacheable access to the contributor experience.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if (!$account->isAuthenticated()) {
      return AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions']);
    }

    $result = $this->domainPurposes->runInPurpose('wiki', function () use ($account): AccessResultInterface {
      $create = $this->entities->getAccessControlHandler('node')
        ->createAccess('wiki_entry', $account, [], TRUE);
      $node = $this->entities->getStorage('node')->getQuery()
        // This is a candidate-only existence check restricted to the exact
        // owner UID. Every loaded Node is revalidated with view Entity Access
        // before any title or link is rendered below.
        ->accessCheck(FALSE)
        ->condition('type', 'wiki_entry')
        ->condition('uid', $account->id())
        ->range(0, 1)
        ->execute();
      $proposal = $this->entities->getStorage('aculta_wiki_proposal')->getQuery()
        // Like Node discovery, this only checks whether the exact author UID
        // has a candidate. Proposal access is revalidated before rendering.
        ->accessCheck(FALSE)
        ->condition('uid', $account->id())
        ->range(0, 1)
        ->execute();
      return $create->orIf(AccessResult::allowedIf($node !== [] || $proposal !== []))
        ->addCacheContexts(['user', 'user.permissions', 'domain'])
        ->addCacheTags(['node_list:wiki_entry', 'aculta_wiki_proposal_list']);
    });
    if (!$result instanceof AccessResultInterface) {
      return AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions', 'domain']);
    }
    return $result->addCacheContexts(['user', 'user.permissions']);
  }

  /**
   * Builds the private, bounded "Meu trabalho" page.
   */
  public function build(AccountInterface $account): array {
    $access = $this->access($account);
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $cacheability = CacheableMetadata::createFromObject($access)
      ->addCacheContexts(['user', 'user.permissions', 'domain'])
      ->addCacheTags(['node_list:wiki_entry', 'aculta_wiki_proposal_list']);
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'we-sativa-workspace'],
      'title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h1',
        '#value' => $this->t('WE SATIVA'),
      ],
      'intro' => ['#plain_text' => $this->t('Acompanhe seus verbetes e propostas para a Wiki420.')],
    ];

    $create = $this->domainPurposes->runInPurpose('wiki', fn () => $this->entities
      ->getAccessControlHandler('node')->createAccess('wiki_entry', $account, [], TRUE));
    $create ??= AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions', 'domain']);
    $cacheability->addCacheableDependency($create);
    if ($create->isAllowed()) {
      $url = $this->domainPurposes->routeUrl('wiki', 'node.add', ['node_type' => 'wiki_entry']);
      if ($url !== NULL) {
        $build['create'] = Link::fromTextAndUrl($this->t('Criar verbete'), $url)->toRenderable();
      }
    }

    $nodeCandidates = $this->domainPurposes->runInPurpose('wiki', function () use ($account, $cacheability): array {
      $items = [];
      foreach ($this->ownNodes($account) as $node) {
        if (!$node instanceof NodeInterface || $node->bundle() !== 'wiki_entry') {
          continue;
        }
        $view = $node->access('view', $account, TRUE);
        $cacheability->addCacheableDependency($node)->addCacheableDependency($view);
        $items[] = [$node, $view];
      }
      return $items;
    });
    $nodeLinks = [];
    foreach ($nodeCandidates ?? [] as [$node, $view]) {
      if (!$view->isAllowed()) {
        continue;
      }
      $label = $this->t('@title — @state', ['@title' => $node->label(), '@state' => $this->nodeState($node)]);
      $url = $this->domainPurposes->routeUrl('wiki', 'entity.node.canonical', ['node' => $node->id()]);
      if ($url !== NULL) {
        $nodeLinks[] = Link::fromTextAndUrl($label, $url)->toRenderable();
      }
    }
    $build['entries'] = $this->listSection($this->t('Meus verbetes'), $nodeLinks, $this->t('Você ainda não criou verbetes.'));

    $proposalCandidates = $this->domainPurposes->runInPurpose('wiki', function () use ($account, $cacheability): array {
      $items = [];
      foreach ($this->ownProposals($account) as $proposal) {
        if (!$proposal instanceof WikiProposal) {
          continue;
        }
        $view = $proposal->access('view', $account, TRUE);
        $cacheability->addCacheableDependency($proposal)->addCacheableDependency($view);
        $items[] = [$proposal, $view];
      }
      return $items;
    });
    $proposalLinks = [];
    foreach ($proposalCandidates ?? [] as [$proposal, $view]) {
      if (!$view->isAllowed()) {
        continue;
      }
      $label = $this->t('@title — @state', ['@title' => $proposal->label(), '@state' => $this->proposalState($proposal)]);
      $url = $this->domainPurposes->routeUrl('wiki', 'aculta_wiki.proposal_status', ['proposal' => $proposal->id()]);
      if ($url !== NULL) {
        $proposalLinks[] = Link::fromTextAndUrl($label, $url)->toRenderable();
      }
    }
    $build['proposals'] = $this->listSection($this->t('Minhas propostas'), $proposalLinks, $this->t('Você ainda não enviou propostas.'));
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Finds a bounded set of candidate Nodes authored by the account.
   *
   * @return array<int, \Drupal\node\NodeInterface>
   *   Candidate Nodes; callers must still check Entity Access.
   */
  private function ownNodes(AccountInterface $account): array {
    $storage = $this->entities->getStorage('node');
    $ids = $storage->getQuery()
      // Owner-scoped candidates only; each loaded entity must pass view access
      // in build() before its fields are included in the response.
      ->accessCheck(FALSE)
      ->condition('type', 'wiki_entry')
      ->condition('uid', $account->id())
      ->sort('changed', 'DESC')
      ->sort('nid', 'DESC')
      ->range(0, self::PAGE_SIZE)
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

  /**
   * Finds a bounded set of candidate proposals authored by the account.
   *
   * @return array<int, \Drupal\aculta_wiki\Entity\WikiProposal>
   *   Candidate proposals; callers must still check Entity Access.
   */
  private function ownProposals(AccountInterface $account): array {
    $storage = $this->entities->getStorage('aculta_wiki_proposal');
    $ids = $storage->getQuery()
      // Owner-scoped candidates only; every proposal is revalidated below.
      ->accessCheck(FALSE)
      ->condition('uid', $account->id())
      ->sort('changed', 'DESC')
      ->sort('id', 'DESC')
      ->range(0, self::PAGE_SIZE)
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

  /**
   * Returns the user-facing state of a Wiki entry.
   */
  private function nodeState(NodeInterface $node): string {
    $state = $node->hasField('moderation_state') ? (string) $node->get('moderation_state')->value : '';
    return match ($state) {
      'draft' => (string) $this->t('Rascunho'),
      'review' => (string) $this->t('Em revisão'),
      'published' => (string) $this->t('Publicado'),
      default => (string) $this->t('Em edição'),
    };
  }

  /**
   * Returns the user-facing state of a proposal.
   */
  private function proposalState(WikiProposal $proposal): string {
    return match ((string) $proposal->get('state')->value) {
      'submitted' => (string) $this->t('Enviada'),
      'needs_changes' => (string) $this->t('Precisa de ajustes'),
      'accepted' => (string) $this->t('Aceita'),
      'declined' => (string) $this->t('Recusada'),
      'withdrawn' => (string) $this->t('Retirada'),
      default => (string) $this->t('Estado desconhecido'),
    };
  }

  /**
   * Builds a titled list with an explicit empty state.
   *
   * @param string|\Stringable $title
   *   Section heading.
   * @param array<int, array<string, mixed>> $items
   *   Renderable links authorized by the owner module.
   * @param string|\Stringable $emptyMessage
   *   Text shown when no authorized item is available.
   *
   * @return array<string, mixed>
   *   Render array for the section.
   */
  private function listSection(\Stringable|string $title, array $items, \Stringable|string $emptyMessage): array {
    return [
      '#type' => 'container',
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $title],
      'items' => $items === []
        ? ['#plain_text' => $emptyMessage]
        : ['#theme' => 'item_list', '#items' => $items],
    ];
  }

}
