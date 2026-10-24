<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\RestrictedForum;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Adapter padrão de Groups restritos do Fórum.
 *
 * Um tópico é restrito quando está relacionado, como conteúdo de Group, a um
 * Group do tipo configurado em `aculta_forum.settings.restricted_group_type`.
 * A restrição também vale quando a categoria do tópico pertence ao Group, o
 * que permite restringir uma categoria inteira sem tocar em cada tópico.
 *
 * Tipos de curso/turma do LMS nunca são aceitos: fóruns de associados e
 * Groups pedagógicos são isolamentos distintos. Configuração inválida é
 * tratada como ausência de configuração (fail closed para o recurso, sem
 * bloquear conteúdo público).
 */
final class RestrictedForumGroupResolver implements RestrictedForumGroupResolverInterface {

  /**
   * Group content plugin for Forum topics. */
  public const TOPIC_PLUGIN = 'group_node:aculta_forum_topic';

  /**
   * Group content plugin for Forum category terms. */
  public const CATEGORY_PLUGIN = 'group_taxonomy_term:aculta_forum_categories';

  /**
   * Plugins de conteúdo de Group aceitos para o Fórum. */
  public const CONTENT_PLUGINS = [
    self::TOPIC_PLUGIN,
    self::CATEGORY_PLUGIN,
  ];

  /**
   * Tipos de Group reservados ao LMS; nunca válidos para fóruns restritos. */
  public const LMS_GROUP_TYPES = ['lms_course', 'lms_class'];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function configuredGroupType(): ?string {
    $type = trim((string) $this->configFactory->get('aculta_forum.settings')->get('restricted_group_type'));
    if ($type === '') {
      return NULL;
    }
    if (in_array($type, self::LMS_GROUP_TYPES, TRUE)) {
      $this->logger->error('O tipo de Group @type é exclusivo do LMS e não pode ser usado em fóruns restritos.', ['@type' => $type]);
      return NULL;
    }
    if ($this->entityTypeManager->getStorage('group_type')->load($type) === NULL) {
      $this->logger->warning('O tipo de Group @type configurado para fóruns restritos não existe; nenhum tópico será restrito.', ['@type' => $type]);
      return NULL;
    }
    return $type;
  }

  /**
   * {@inheritdoc}
   */
  public function groupIdsForTopic(NodeInterface $topic): array {
    $groupType = $this->configuredGroupType();
    if ($groupType === NULL || $topic->bundle() !== 'aculta_forum_topic') {
      return [];
    }

    $targets = [];
    if ($topic->id() !== NULL) {
      $targets[self::TOPIC_PLUGIN] = [(int) $topic->id()];
    }
    $categoryIds = $this->categoryIds($topic);
    if ($categoryIds !== []) {
      $targets[self::CATEGORY_PLUGIN] = $categoryIds;
    }
    if ($targets === []) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('group_relationship');
    $relationshipIds = [];
    // A node ID and a term ID with the same number are different Group
    // content. Query each plugin with its own IDs so a public topic is not
    // restricted by an unrelated category that shares its numeric ID.
    foreach ($targets as $plugin => $ids) {
      $relationshipIds = array_merge($relationshipIds, $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('plugin_id', $plugin)
        ->condition('entity_id', $ids, 'IN')
        ->execute());
    }
    if ($relationshipIds === []) {
      return [];
    }

    $groupIds = [];
    foreach ($storage->loadMultiple($relationshipIds) as $relationship) {
      if (!$relationship instanceof GroupRelationshipInterface) {
        continue;
      }
      // getGroup() lê a referência `gid`; um Group apagado sem a relação
      // removida devolve NULL e é ignorado.
      $group = $relationship->get('gid')->entity;
      if ($group instanceof GroupInterface && $group->bundle() === $groupType) {
        $groupIds[(int) $group->id()] = (int) $group->id();
      }
    }
    ksort($groupIds);
    return array_values($groupIds);
  }

  /**
   * {@inheritdoc}
   */
  public function groupsOfType(): array {
    $groupType = $this->configuredGroupType();
    if ($groupType === NULL) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('group');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', $groupType)
      ->sort('id')
      ->execute();
    if ($ids === []) {
      return [];
    }
    $groups = [];
    foreach ($storage->loadMultiple($ids) as $id => $group) {
      if ($group instanceof GroupInterface) {
        $groups[(int) $id] = $group;
      }
    }
    return $groups;
  }

  /**
   * IDs das categorias do tópico (campo field_aculta_categories).
   *
   * @param \Drupal\node\NodeInterface $topic
   *   Tópico do Fórum.
   *
   * @return int[]
   *   IDs dos termos de categoria.
   */
  private function categoryIds(NodeInterface $topic): array {
    if (!$topic->hasField('field_aculta_categories')) {
      return [];
    }
    $ids = [];
    foreach ($topic->get('field_aculta_categories')->getValue() as $item) {
      if (isset($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    return $ids;
  }

}
