<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Controller;

use Drupal\aculta_magazine\Form\EditorialWorkspaceFilterForm;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\aculta_magazine\Access\EditorialCapability;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Studio Marley presentation adapter delegating persistence to Node Form API. */
final class EditorialWorkspaceController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFormBuilderInterface $entityFormBuilder,
    private readonly FormBuilderInterface $formBuilder,
    private readonly DiscussionContextResolver $discussionContexts,
    private readonly EditorialCapability $capabilities,
    private readonly AccountInterface $account,
    private readonly RequestStack $requestStack,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Requires the configured authoring role and Drupal create access.
   */
  public function accessCreate(): AccessResult {
    return $this->capabilities->access(EditorialCapability::AUTHORING, $this->account)
      ->andIf(AccessResult::allowedIfHasPermission($this->account, 'create revista content'));
  }

  /**
   * Allows accounts with any Magazine capability group to open its workspace.
   */
  public function accessWorkspace(): AccessResult {
    $access = $this->capabilities->access(EditorialCapability::AUTHORING, $this->account)
      ->orIf($this->capabilities->access(EditorialCapability::REVIEW, $this->account));
    return $access->orIf($this->capabilities->access(EditorialCapability::COMMENTS, $this->account)
      ->andIf(AccessResult::allowedIfHasPermission($this->account, 'administer comments')));
  }

  /**
   * Combines authoring capability with Node update access.
   */
  public function accessEdit(NodeInterface $node): AccessResult {
    $latest = $this->latestRevision($node);
    if ($latest === NULL) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    return $this->capabilities->access(EditorialCapability::AUTHORING, $this->account)
      ->andIf($node->access('update', $this->account, TRUE))
      ->andIf($latest->access('view revision', $this->account, TRUE));
  }

  /**
   * Combines review capability with update and revision access.
   */
  public function accessReview(NodeInterface $node): AccessResult {
    $latest = $this->latestRevision($node);
    if ($latest === NULL) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    return $this->capabilities->access(EditorialCapability::REVIEW, $this->account)
      ->andIf($node->access('update', $this->account, TRUE))
      ->andIf($latest->access('update', $this->account, TRUE))
      ->andIf($latest->access('view revision', $this->account, TRUE));
  }

  /**
   * Checks contextual read access before rendering the discussion field.
   */
  public function accessDiscussion(NodeInterface $node): AccessResult {
    if ($node->bundle() !== 'revista' || !$node->hasField('field_aculta_discussion')) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    $context = $this->discussionContexts->resolve($node, $this->account);
    if ($context === NULL || $context->getType() !== 'publication') {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    $access = $this->capabilities->access(EditorialCapability::COMMENTS, $this->account)
      ->andIf($context->getReadAccess())
      ->addCacheableDependency($context);
    if ($this->requestStack->getCurrentRequest()?->isMethod('POST')) {
      $access = $access->andIf($context->getWriteAccess());
    }
    return $access;
  }

  /**
   * Renders the owner-managed Comment field for participation in Marley.
   */
  public function discussion(NodeInterface $node): array {
    if (!$this->accessDiscussion($node)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $field = $node->get('field_aculta_discussion');
    $context = $this->discussionContexts->resolve($node, $this->account);
    $build = [
      'title' => ['#type' => 'link', '#title' => $node->label(), '#url' => $node->toUrl()],
      'discussion' => $this->entityTypeManager->getViewBuilder('node')->viewField($field),
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'user.roles'],
        'tags' => ['config:aculta_magazine.settings'],
      ],
    ];
    if ($context !== NULL) {
      CacheableMetadata::createFromObject($node)->addCacheableDependency($context)->applyTo($build);
    }
    return $build;
  }

  /**
   * Checks the Forum context and Drupal access before contextual moderation.
   */
  public function accessComment(CommentInterface $comment): AccessResult {
    $node = $comment->getCommentedEntity();
    if (!$node instanceof NodeInterface || $node->bundle() !== 'revista' || $comment->getFieldName() !== 'field_aculta_discussion') {
      return AccessResult::forbidden();
    }
    $context = $this->discussionContexts->resolve($node, $this->account);
    if ($context === NULL || $context->getType() !== 'publication') {
      return AccessResult::forbidden()->addCacheableDependency($comment);
    }
    return $this->capabilities->access(EditorialCapability::COMMENTS, $this->account)
      ->andIf(AccessResult::allowedIfHasPermission($this->account, 'administer comments'))
      ->andIf($context->getReadAccess())
      ->andIf($context->getModerationAccess())
      ->andIf($comment->access('view', $this->account, TRUE));
  }

  /**
   * Builds the native Node creation form in the Marley purpose.
   */
  public function create(): array {
    $node = $this->entityTypeManager->getStorage('node')->create(['type' => 'revista']);
    return $this->entityFormBuilder->getForm($node, 'default');
  }

  /**
   * Builds the native Node edit form for the latest accessible revision.
   */
  public function edit(NodeInterface $node): array {
    $latest = $this->latestRevision($node);
    if ($latest === NULL || !$latest->access('update', $this->account) || !$latest->access('view revision', $this->account)) {
      throw new AccessDeniedHttpException();
    }
    return $this->entityFormBuilder->getForm($latest, 'default');
  }

  /**
   * Access-checked, filtered Studio inventory of visible Revista revisions. */
  public function listing(): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)->condition('type', 'revista')->sort('changed', 'DESC');
    $title = $this->queryString('titulo');
    if ($title !== '') {
      $query->condition('title', $title, 'CONTAINS');
    }
    $category = filter_var($this->queryString('categoria'), FILTER_VALIDATE_INT) ?: 0;
    if ($category > 0) {
      $query->condition('field_editorial_category.target_id', $category);
    }
    $language = $this->queryString('idioma');
    if ($language !== '') {
      $query->condition('langcode', $language);
    }
    $author = $this->queryString('autoria');
    if ($author !== '') {
      $uids = ctype_digit($author)
        ? [(int) $author]
        : array_map(
          static fn ($user) => (int) $user->id(),
          $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $author]),
        );
      if ($uids === []) {
        return [
          'filters' => $this->formBuilder->getForm(EditorialWorkspaceFilterForm::class),
          'empty' => ['#plain_text' => $this->t('Nenhuma publicação acessível para esses filtros.')],
        ];
      }
      $query->condition($query->orConditionGroup()->condition('uid', $uids, 'IN')->condition('co_authors.target_id', $uids, 'IN'));
    }
    $requestedState = $this->queryString('estado');
    $page = max(0, min(100, (int) $this->queryString('pagina')));
    $storage = $this->entityTypeManager->getStorage('node');
    // The latest moderation state can live on a non-default revision, so
    // filter after checking the accessible latest revision, then paginate.
    $matchingIds = [];
    $scanOffset = 0;
    $targetCount = ($page * 25) + 26;
    do {
      $batch = $query->range($scanOffset, 100)->execute();
      foreach (array_values($batch) as $nid) {
        $candidate = $storage->load($nid);
        if (!$candidate instanceof NodeInterface || !$candidate->access('view', $this->account)) {
          continue;
        }
        $candidateLatest = $this->latestRevision($candidate);
        if ($candidateLatest === NULL || !$candidateLatest->access('view revision', $this->account)) {
          continue;
        }
        $state = (string) ($candidateLatest->get('moderation_state')->value ?? '');
        if ($requestedState !== '' && $state !== $requestedState) {
          continue;
        }
        $matchingIds[] = $nid;
        if (count($matchingIds) >= $targetCount) {
          break;
        }
      }
      $scanOffset += count($batch);
    } while (count($matchingIds) < $targetCount && count($batch) === 100);
    $ids = array_slice($matchingIds, $page * 25, 26);
    $hasNext = count($ids) > 25;
    $ids = array_slice($ids, 0, 25);
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface || !$node->access('view', $this->account)) {
        continue;
      }
      $latestId = $storage->getLatestRevisionId($node->id());
      $latest = $latestId ? $storage->loadRevision($latestId) : NULL;
      if (!$latest instanceof NodeInterface || !$latest->access('view revision', $this->account)) {
        continue;
      }
      $state = (string) ($latest->get('moderation_state')->value ?? '');
      $edit = $this->accessEdit($node)->isAllowed()
        ? [
          '#type' => 'link',
          '#title' => $this->t('Editar'),
          '#url' => Url::fromRoute('aculta_magazine.studio_edit', ['node' => $node->id()]),
        ]
        : ['#plain_text' => ''];
      $discussion = $this->accessDiscussion($node)->isAllowed()
        ? [
          '#type' => 'link',
          '#title' => $this->t('Abrir discussão'),
          '#url' => Url::fromRoute('aculta_magazine.studio_discussion', ['node' => $node->id()]),
        ]
        : ['#plain_text' => ''];
      $rows[] = [
        'title' => ['data' => ['#type' => 'link', '#title' => $node->label(), '#url' => $node->toUrl()]],
        'state' => $state,
        'author' => $node->getOwner()?->getDisplayName() ?? '',
        'changed' => $this->dateFormatter->format($node->getChangedTime(), 'short'),
        'actions' => ['data' => $edit],
        'discussion' => ['data' => $discussion],
      ];
    }
    return [
      'filters' => $this->formBuilder->getForm(EditorialWorkspaceFilterForm::class),
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Publicação'),
          $this->t('Estado'),
          $this->t('Autoria'),
          $this->t('Atualizada'),
          $this->t('Ações'),
          $this->t('Discussão'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma publicação acessível.'),
      ],
      'pager' => $hasNext ? [
        '#type' => 'link',
        '#title' => $this->t('Próxima página'),
        '#url' => Url::fromRoute('aculta_magazine.studio_list', [], [
          'query' => array_replace($request->query->all(), ['pagina' => $page + 1]),
        ]),
      ] : [],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'user.roles', 'url.query_args'],
        'tags' => ['node_list:revista', 'config:aculta_magazine.settings'],
      ],
    ];
  }

  /**
   * Loads the latest revision while failing closed if it cannot be resolved.
   */
  private function latestRevision(NodeInterface $node): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $latestId = $storage->getLatestRevisionId($node->id());
    if (!$latestId) {
      return $node;
    }
    $latest = $storage->loadRevision($latestId);
    return $latest instanceof NodeInterface ? $latest : NULL;
  }

  /**
   * Returns only scalar query values, avoiding array-to-string coercion.
   */
  private function queryString(string $key): string {
    $value = $this->requestStack->getCurrentRequest()?->query->all()[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
  }

}
