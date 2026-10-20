<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
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
    $cache = (new CacheableMetadata())->addCacheContexts(['user'])->addCacheTags(['node_list', 'comment_list']);

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
      'description' => ['#plain_text' => t('Veja o que você fez no site: verbetes, tópicos, artigos e comentários.')],
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

  /** Artigos da revista (tipo article) de autoria da pessoa, publicados. */
  private function revista(int $uid): array {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)->condition('type', 'article')->condition('uid', $uid)->condition('status', 1)->execute();
    return $this->nodeList(array_values($nids), 'article', 'Você ainda não tem artigos publicados na revista.', FALSE);
  }

  /** Comentários publicados pela pessoa no Fórum, com link para o tópico. */
  private function comentarios(int $uid): array {
    $ids = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'comment_forum')
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, 50)
      ->execute();
    $comments = $this->entityTypeManager->getStorage('comment')->loadMultiple($ids);
    if ($comments === []) {
      return ['#markup' => (string) t('Você ainda não comentou nas discussões do Fórum.')];
    }
    $items = [];
    foreach ($comments as $comment) {
      $topic = $comment->getCommentedEntity();
      if ($topic === NULL || !$topic->isPublished()) {
        continue;
      }
      $items[] = ['#type' => 'link', '#title' => $topic->label(), '#url' => $topic->toUrl()];
    }
    return $items === [] ? ['#markup' => (string) t('Você ainda não comentou nas discussões do Fórum.')] : ['#theme' => 'item_list', '#items' => $items];
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
      $items[] = [
        '#type' => 'link',
        '#title' => $title,
        '#url' => $history ? Url::fromRoute('entity.node.version_history', ['node' => $node->id()]) : $node->toUrl(),
      ];
    }
    return ['#theme' => 'item_list', '#items' => $items];
  }

}
