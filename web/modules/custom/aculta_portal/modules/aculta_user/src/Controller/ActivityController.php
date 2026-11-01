<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Atividade da própria pessoa em Minha Conta (plano aculta_user, seção E2).
 *
 * Cada aba lista só o que a pessoa fez e ainda está publicado. Ninguém vê a atividade de outra pessoa por esta seção.
 * Só serve no propósito account. Abas: wiki, forum, revista, comentarios e conquistas (badges entram na fase 6).
 */
final class ActivityController implements ContainerInjectionInterface {

  /** Rótulos das abas, na ordem do menu. */
  public const TABS = [
    'wiki' => 'Wiki',
    'forum' => 'Fórum',
    'revista' => 'Revista',
    'comentarios' => 'Comentários',
    'conquistas' => 'Conquistas',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly DomainPurposeManager $purposeManager,
    private readonly ModuleHandlerInterface $modules,
    private readonly TranslationInterface $stringTranslation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('module_handler'),
      $container->get('string_translation'),
    );
  }

  /** Renderiza a aba pedida. */
  public function view(string $aba): array {
    if ($this->purposeManager->getCurrentPurpose() !== 'account' || !isset(self::TABS[$aba])) {
      throw new NotFoundHttpException();
    }
    $uid = (int) $this->currentUser->id();
    $cache = (new CacheableMetadata())->addCacheContexts(['user'])->addCacheTags([
      'node_list',
      'comment_list',
      'aculta_forum_post_list',
    ]);

    $conteudo = match ($aba) {
      'wiki' => $this->wiki($uid),
      'forum' => $this->forum($uid),
      'revista' => $this->revista($uid),
      'comentarios' => $this->comentarios($uid),
      default => ['#markup' => (string) t('Nenhuma conquista ainda. As conquistas chegam em uma etapa futura.')],
    };
    $this->modules->alter('aculta_user_activity_content', $conteudo, $aba, $uid);
    // Mesmo padrão de Meus Dados e Segurança: descrição, abas (links com URL própria) e o conteúdo da aba.
    return [
      '#cache' => ['contexts' => $cache->getCacheContexts(), 'tags' => $cache->getCacheTags(), 'max-age' => 0],
      'description' => ['#plain_text' => t('Veja o que você fez no site: verbetes, tópicos, publicações e comentários.')],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-data-nav', 'aria-label' => t('Seções de atividade'), 'data-portal-account-data-nav' => 'true'],
        'links' => $this->tabLinks($aba),
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-data-content', 'data-portal-account-data-content' => 'true', 'aria-busy' => 'false'],
        'heading' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => t(self::TABS[$aba]), '#element_attributes' => new Attribute(['tabindex' => '-1'])],
        'list' => $conteudo,
      ],
    ];
  }

  /** Links das abas, com a aba atual marcada (aria-current e is-active), como em Meus Dados e Segurança. */
  private function tabLinks(string $atual): array {
    $links = [];
    foreach (self::TABS as $id => $label) {
      $link = PortalLinkElement::build(t($label), Url::fromRoute('aculta_user.atividade', ['aba' => $id]), ['data-portal-account-data-link' => 'true', 'data-portal-ui' => 'account-data-link']);
      if ($id === $atual) {
        $link['#element_attributes']->setAttribute('aria-current', 'page');
        $link['#element_attributes']->addClass('is-active');
      }
      $links[] = $link;
    }
    return $links;
  }

  /** Verbetes publicados em que a pessoa criou pelo menos uma revisão, com link para o histórico público. */
  private function wiki(int $uid): array {
    $revisions = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('type', 'wiki_entry')
      ->condition('revision_uid', $uid)
      ->execute();
    $nids = $this->publishedNodeIds(array_keys($revisions), 'wiki_entry');
    return $this->nodeList($nids, 'wiki_entry', 'Você ainda não revisou verbetes publicados.', TRUE);
  }

  /** Tópicos de Fórum criados pela pessoa que continuam publicados. */
  private function forum(int $uid): array {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $nids = $query
      ->accessCheck(FALSE)
      ->condition('type', 'aculta_forum_topic')
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->execute();

    return $this->nodeList(
      array_values($nids),
      'aculta_forum_topic',
      'Você ainda não criou tópicos no Fórum.',
      FALSE,
      TRUE,
    );
  }

  /** Publicações da Revista de autoria da pessoa, publicadas. */
  private function revista(int $uid): array {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)->condition('type', 'revista')->condition('uid', $uid)->condition('status', 1)->execute();
    return $this->nodeList(array_values($nids), 'revista', 'Você ainda não tem publicações na Revista.', FALSE);
  }

  /** Respostas publicadas pela pessoa, em Comments ou posts revisionáveis do Fórum. */
  private function comentarios(int $uid): array {
    $ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'aculta_discussion_post')
      ->condition('entity_type', 'node')
      ->condition('field_name', 'field_aculta_discussion')
      ->condition('entity_id.entity:node.type', 'aculta_forum_topic')
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, 50)
      ->execute();
    $entries = [];
    $comments = $this->entityTypeManager->getStorage('comment')->loadMultiple($ids);
    foreach ($comments as $comment) {
      if (!($comment instanceof CommentInterface)) {
        continue;
      }
      $topic = $comment->getCommentedEntity();
      if (!($topic instanceof NodeInterface)
        || $topic->bundle() !== 'aculta_forum_topic'
        || !$topic->isPublished()) {
        continue;
      }
      $access = $this->purposeManager->runInPurpose('forum', function () use (
        $comment,
        $topic,
      ): bool {
        $topic_access = $topic->access('view', $this->currentUser, TRUE);
        $comment_access = $comment->access('view', $this->currentUser, TRUE);
        return $topic_access->isAllowed() && $comment_access->isAllowed();
      });
      if ($access !== TRUE) {
        continue;
      }
      $url = $this->purposeManager->routeUrl(
        'forum',
        'entity.node.canonical',
        ['node' => $topic->id()],
      );
      if ($url === NULL) {
        continue;
      }
      $url->setOption('fragment', 'comment-' . $comment->id());
      $entries[] = [
        'created' => (int) $comment->getCreatedTime(),
        'sort_id' => 'comment-' . $comment->id(),
        'link' => [
          '#type' => 'link',
          '#title' => $topic->label(),
          '#url' => $url,
        ],
      ];
    }

    if ($this->entityTypeManager->getDefinition('aculta_forum_post', FALSE) !== NULL) {
      $post_storage = $this->entityTypeManager->getStorage('aculta_forum_post');
      $post_ids = $post_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('uid', $uid)
        ->condition('status', 1)
        ->sort('created', 'DESC')
        ->range(0, 50)
        ->execute();
      foreach ($post_storage->loadMultiple($post_ids) as $post) {
        if (!$post instanceof FieldableEntityInterface
          || !$post instanceof EntityPublishedInterface
          || !$post instanceof RevisionableInterface
          || !$post->isPublished()
          || !$post->isDefaultRevision()
          || !$post->hasField('topic')) {
          continue;
        }
        $topic = $post->get('topic')->entity;
        if (!$topic instanceof NodeInterface
          || $topic->bundle() !== 'aculta_forum_topic'
          || !$topic->isPublished()) {
          continue;
        }
        $access = $this->purposeManager->runInPurpose('forum', fn (): bool =>
          $topic->access('view', $this->currentUser, TRUE)->isAllowed()
          && $post->access('view', $this->currentUser, TRUE)->isAllowed()
        );
        if ($access !== TRUE) {
          continue;
        }
        $url = $this->purposeManager->routeUrl(
          'forum',
          'entity.node.canonical',
          ['node' => $topic->id()],
        );
        if ($url === NULL) {
          continue;
        }
        $url->setOption('fragment', 'forum-post-' . $post->id());
        $entries[] = [
          'created' => (int) $post->get('created')->value,
          'sort_id' => 'post-' . $post->id(),
          'link' => [
            '#type' => 'link',
            '#title' => $this->stringTranslation->translate(
              '@subject — @topic',
              ['@subject' => $post->get('subject')->value, '@topic' => $topic->label()],
            ),
            '#url' => $url,
          ],
        ];
      }
    }

    if ($entries === []) {
      return ['#markup' => (string) t('Você ainda não comentou nas discussões do Fórum.')];
    }
    usort($entries, static fn (array $left, array $right): int =>
      ($right['created'] <=> $left['created']) ?: strcmp($left['sort_id'], $right['sort_id'])
    );
    return ['#theme' => 'item_list', '#items' => array_column(array_slice($entries, 0, 50), 'link')];
  }

  /** Mantém os nós publicados do tipo indicado. */
  private function publishedNodeIds(array $nids, string $bundle): array {
    if ($nids === []) {
      return [];
    }
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $ids = [];
    foreach ($nodes as $node) {
      if ($node->bundle() === $bundle && $node->isPublished()) {
        $ids[] = (int) $node->id();
      }
    }
    return array_values(array_unique($ids));
  }

  /** Lista de nós com link, ou mensagem de vazio com link para a área. */
  private function nodeList(
    array $nids,
    string $bundle,
    string $vazio,
    bool $history,
    bool $showReplies = FALSE,
  ): array {
    if ($nids === []) {
      return ['#markup' => (string) t($vazio)];
    }
    $items = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $node) {
      if (!$node instanceof NodeInterface || !$node->isPublished()) {
        continue;
      }
      $title = $node->label();
      if ($showReplies && $node->hasField('field_aculta_discussion')) {
        // Core's Comment field reads its published-only aggregate from
        // comment_entity_statistics; do not count replies with a query per row.
        $replyCount = (int) $node->get('field_aculta_discussion')->comment_count;
        $title = $this->stringTranslation->formatPlural(
          $replyCount,
          '1 comentário — @title',
          '@count comentários — @title',
          ['@title' => $title],
        );
      }
      $url = $history ? Url::fromRoute('entity.node.version_history', ['node' => $node->id()]) : $node->toUrl();
      if ($bundle === 'aculta_forum_topic') {
        $access = $this->purposeManager->runInPurpose(
          'forum',
          fn () => $node->access('view', $this->currentUser, TRUE)->isAllowed(),
        );
        $url = $access
          ? $this->purposeManager->routeUrl(
            'forum',
            'entity.node.canonical',
            ['node' => $node->id()],
          )
          : NULL;
      }
      if ($url === NULL) {
        continue;
      }
      $items[] = [
        '#type' => 'link',
        '#title' => $title,
        '#url' => $url,
      ];
    }
    return ['#theme' => 'item_list', '#items' => $items];
  }

}
