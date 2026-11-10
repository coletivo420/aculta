<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Analytics;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolver;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolve o escopo analítico de qualquer discussão do Fórum.
 *
 * A publicidade é decidida pelo conteúdo: bundle, purpose canônico, estado de
 * publicação e Group restrito direto ou herdado da categoria. A conta que
 * executa a consulta não participa da decisão. Qualquer falha de resolução,
 * contexto ausente ou Group indeterminado devolve `unknown`, que nunca é
 * tratado como público (fail closed).
 */
class ForumAnalyticsScopeResolver {

  /**
   * Bundle do tópico independente do Fórum.
   */
  public const TOPIC_BUNDLE = 'aculta_forum_topic';

  /**
   * Bundles contextuais reconhecidos, mapeados ao escopo analítico.
   */
  private const CONTEXT_BUNDLES = [
    'wiki_entry' => ForumAnalyticsScope::WIKI,
    'revista' => ForumAnalyticsScope::PUBLICATION,
    'aculta_lms_topic' => ForumAnalyticsScope::LMS,
  ];

  /**
   * Purpose canônico mapeado ao escopo contextual.
   */
  private const CONTEXT_PURPOSES = [
    'wiki' => ForumAnalyticsScope::WIKI,
    'magazine' => ForumAnalyticsScope::PUBLICATION,
    'courses' => ForumAnalyticsScope::LMS,
  ];

  public function __construct(
    private readonly RestrictedForumGroupResolverInterface $groupResolver,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly Connection $database,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Classifica uma entidade reagível, comentário ou tópico.
   *
   * Comentários e ForumPosts são classificados pelo host da discussão: um
   * comentário restrito por Group de categoria nunca é público só porque ele
   * está publicado.
   */
  public function resolve(EntityInterface $entity): ForumAnalyticsScope {
    if ($entity->isNew()) {
      return new ForumAnalyticsScope(ForumAnalyticsScope::UNKNOWN, NULL, 'unsaved_entity');
    }
    $host = $this->hostOf($entity);
    if ($host === NULL) {
      return new ForumAnalyticsScope(ForumAnalyticsScope::UNKNOWN, NULL, 'host_unavailable');
    }
    return $this->resolveHost($host);
  }

  /**
   * Classifica um host de discussão.
   */
  public function resolveHost(EntityInterface $host): ForumAnalyticsScope {
    if ($host->isNew()) {
      return new ForumAnalyticsScope(ForumAnalyticsScope::UNKNOWN, $host, 'unsaved_host');
    }
    if (!$host instanceof NodeInterface) {
      return new ForumAnalyticsScope(ForumAnalyticsScope::UNKNOWN, $host, 'unsupported_host');
    }

    $bundle = $host->bundle();
    if (isset(self::CONTEXT_BUNDLES[$bundle])) {
      return new ForumAnalyticsScope(self::CONTEXT_BUNDLES[$bundle], $host);
    }
    if ($bundle !== self::TOPIC_BUNDLE) {
      return new ForumAnalyticsScope(ForumAnalyticsScope::UNKNOWN, $host, 'unsupported_bundle');
    }

    if (!$host->isPublished()) {
      return new ForumAnalyticsScope(ForumAnalyticsScope::UNKNOWN, $host, 'host_not_published');
    }

    $purpose = $this->purposeOf($host);
    if ($purpose === NULL) {
      return new ForumAnalyticsScope(ForumAnalyticsScope::UNKNOWN, $host, 'purpose_unavailable');
    }
    if ($purpose !== 'forum') {
      $contextScope = self::CONTEXT_PURPOSES[$purpose] ?? ForumAnalyticsScope::UNKNOWN;
      return new ForumAnalyticsScope($contextScope, $host, 'purpose_mismatch');
    }

    try {
      $groupIds = $this->groupResolver->groupIdsForTopic($host);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Falha ao resolver Groups restritos do tópico @nid: @message', [
        '@nid' => (string) $host->id(),
        '@message' => $exception->getMessage(),
      ]);
      return new ForumAnalyticsScope(ForumAnalyticsScope::UNKNOWN, $host, 'group_resolution_failed');
    }

    return $groupIds === []
      ? new ForumAnalyticsScope(ForumAnalyticsScope::PUBLIC_FORUM, $host)
      : new ForumAnalyticsScope(ForumAnalyticsScope::RESTRICTED_FORUM, $host);
  }

  /**
   * IDs de tópicos restritos por Group direto ou herdado da categoria.
   *
   * @return int[]|null
   *   IDs em ordem estável; NULL quando não é possível provar a ausência de
   *   restrição (fail closed).
   */
  public function restrictedTopicIds(): ?array {
    $groupType = $this->groupResolver->configuredGroupType();
    if ($groupType === NULL) {
      return [];
    }
    $schema = $this->database->schema();
    if (!$schema->tableExists('group_relationship_field_data')
      || !$schema->tableExists('group_field_data')) {
      return NULL;
    }

    $topicIds = [];
    $topicRelationships = $this->database->select('group_relationship_field_data', 'gr');
    $topicRelationships->join('group_field_data', 'g', 'g.id = gr.gid');
    $topicRelationships->fields('gr', ['entity_id']);
    $topicRelationships->condition('gr.plugin_id', RestrictedForumGroupResolver::TOPIC_PLUGIN);
    $topicRelationships->condition('g.type', $groupType);
    foreach ($topicRelationships->execute()->fetchCol() as $id) {
      $topicIds[(int) $id] = (int) $id;
    }

    $categoryIds = $this->restrictedCategoryIds();
    if ($categoryIds === NULL) {
      return NULL;
    }
    if ($categoryIds !== [] && $schema->tableExists('node__field_aculta_categories')) {
      $byCategory = $this->database->select('node__field_aculta_categories', 'nc');
      $byCategory->fields('nc', ['entity_id']);
      $byCategory->condition('nc.field_aculta_categories_target_id', $categoryIds, 'IN');
      foreach ($byCategory->execute()->fetchCol() as $id) {
        $topicIds[(int) $id] = (int) $id;
      }
    }

    return array_values($topicIds);
  }

  /**
   * IDs de categorias pertencentes a Groups restritos.
   *
   * @return int[]|null
   *   IDs em ordem estável; NULL quando não é possível provar a ausência.
   */
  public function restrictedCategoryIds(): ?array {
    $groupType = $this->groupResolver->configuredGroupType();
    if ($groupType === NULL) {
      return [];
    }
    $schema = $this->database->schema();
    if (!$schema->tableExists('group_relationship_field_data')
      || !$schema->tableExists('group_field_data')) {
      return NULL;
    }
    $query = $this->database->select('group_relationship_field_data', 'cgr');
    $query->join('group_field_data', 'cg', 'cg.id = cgr.gid');
    $query->fields('cgr', ['entity_id']);
    $query->condition('cgr.plugin_id', RestrictedForumGroupResolver::CATEGORY_PLUGIN);
    $query->condition('cg.type', $groupType);
    $ids = array_map('intval', $query->execute()->fetchCol());
    sort($ids);
    return array_values(array_unique($ids));
  }

  /**
   * Adiciona as condições de categoria pública a uma consulta de termos.
   *
   * Uma categoria restrita por Group deixa de ser pública e some de rankings
   * e contadores, mesmo que o vocabulário seja compartilhado.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   Consulta com o alias de `taxonomy_term_field_data`.
   * @param string $alias
   *   Alias de `taxonomy_term_field_data`.
   */
  public function applyPublicCategoryConditions(SelectInterface $query, string $alias): bool {
    $schema = $this->database->schema();
    if (!$schema->tableExists('taxonomy_term_field_data')) {
      return FALSE;
    }
    $query->condition($alias . '.vid', 'aculta_forum_categories');
    $query->condition($alias . '.default_langcode', 1);

    $groupType = $this->groupResolver->configuredGroupType();
    if ($groupType === NULL) {
      return TRUE;
    }
    if (!$schema->tableExists('group_relationship_field_data')
      || !$schema->tableExists('group_field_data')) {
      return FALSE;
    }

    $restrictedCategories = $this->database->select('group_relationship_field_data', 'cgr');
    $restrictedCategories->join('group_field_data', 'cg', 'cg.id = cgr.gid');
    $restrictedCategories->fields('cgr', ['entity_id']);
    $restrictedCategories->condition('cgr.plugin_id', RestrictedForumGroupResolver::CATEGORY_PLUGIN);
    $restrictedCategories->condition('cg.type', $groupType);
    $query->condition($alias . '.tid', $restrictedCategories, 'NOT IN');

    return TRUE;
  }

  /**
   * Resolve o host da discussão para Comment, ForumPost e tópico.
   */
  public function hostOf(EntityInterface $entity): ?EntityInterface {
    if ($entity instanceof CommentInterface) {
      $host = $entity->getCommentedEntity();
      return $host instanceof EntityInterface ? $host : NULL;
    }
    if ($entity instanceof ForumPost) {
      $topic = $entity->get('topic')->entity;
      return $topic instanceof EntityInterface ? $topic : NULL;
    }
    return $entity;
  }

  /**
   * Purpose canônico do host, quando declarado no campo de domínio.
   */
  public function purposeOf(EntityInterface $host): ?string {
    if (!$host instanceof NodeInterface || !$host->hasField('field_domain_source')) {
      return NULL;
    }
    $field = $host->get('field_domain_source');
    if ($field->isEmpty()) {
      return NULL;
    }
    $value = $field->getValue();
    $domainId = (string) ($value[0]['target_id'] ?? '');
    if ($domainId === '') {
      return NULL;
    }
    try {
      return $this->domainPurposeManager->getPurposeForDomainId($domainId);
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * ID do Domain canônico do Fórum público, ou NULL quando indisponível.
   */
  public function forumDomainId(): ?string {
    try {
      $domain = $this->domainPurposeManager->getDomain('forum');
      return $domain === NULL ? NULL : (string) $domain->id();
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Adiciona as condições de visibilidade pública a uma consulta de nós.
   *
   * O alias precisa apontar para `node_field_data`. Devolve FALSE quando não é
   * possível provar a publicidade (domínio ausente, tabela de Group ausente):
   * o consumidor deve tratar como conjunto vazio, nunca como público total.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   Consulta com o alias de nó já adicionado.
   * @param string $alias
   *   Alias de `node_field_data`.
   */
  public function applyPublicTopicConditions(SelectInterface $query, string $alias): bool {
    $domainId = $this->forumDomainId();
    $schema = $this->database->schema();
    if ($domainId === NULL
      || !$schema->tableExists('node__field_domain_source')
      || !$schema->tableExists('node_field_data')) {
      return FALSE;
    }

    $query->condition($alias . '.type', self::TOPIC_BUNDLE);
    $query->condition($alias . '.status', 1);
    $query->condition($alias . '.default_langcode', 1);

    $domains = $this->database->select('node__field_domain_source', 'ds');
    $domains->fields('ds', ['entity_id']);
    $domains->condition('ds.field_domain_source_target_id', $domainId);
    $query->condition($alias . '.nid', $domains, 'IN');

    $groupType = $this->groupResolver->configuredGroupType();
    if ($groupType === NULL) {
      return TRUE;
    }
    if (!$schema->tableExists('group_relationship_field_data')
      || !$schema->tableExists('group_field_data')) {
      return FALSE;
    }

    // Group restrito ligado diretamente ao tópico.
    $topicRelationships = $this->database->select('group_relationship_field_data', 'gr');
    $topicRelationships->join('group_field_data', 'g', 'g.id = gr.gid');
    $topicRelationships->fields('gr', ['entity_id']);
    $topicRelationships->condition('gr.plugin_id', RestrictedForumGroupResolver::TOPIC_PLUGIN);
    $topicRelationships->condition('g.type', $groupType);
    $query->condition($alias . '.nid', $topicRelationships, 'NOT IN');

    if (!$schema->tableExists('node__field_aculta_categories')) {
      return FALSE;
    }

    // Group restrito herdado da categoria do tópico.
    $categoryRelationships = $this->database->select('group_relationship_field_data', 'cgr');
    $categoryRelationships->join('group_field_data', 'cg', 'cg.id = cgr.gid');
    $categoryRelationships->fields('cgr', ['entity_id']);
    $categoryRelationships->condition('cgr.plugin_id', RestrictedForumGroupResolver::CATEGORY_PLUGIN);
    $categoryRelationships->condition('cg.type', $groupType);

    $topicsWithRestrictedCategory = $this->database->select('node__field_aculta_categories', 'nc');
    $topicsWithRestrictedCategory->fields('nc', ['entity_id']);
    $topicsWithRestrictedCategory->condition('nc.field_aculta_categories_target_id', $categoryRelationships, 'IN');
    $query->condition($alias . '.nid', $topicsWithRestrictedCategory, 'NOT IN');

    return TRUE;
  }

}
