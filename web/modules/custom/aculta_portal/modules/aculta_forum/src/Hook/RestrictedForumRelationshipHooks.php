<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\RestrictedForum\RestrictedForumGrantRebuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\group\Entity\GroupRelationshipInterface;

/**
 * Regrava grants quando a relação entre tópico/categoria e Group muda.
 *
 * Salvar o nó regrava os grants, mas inserir ou apagar a relação de Group não
 * salva o nó. Sem estes hooks, um tópico recém-relacionado continuaria
 * público na listagem até ser editado. Relações de membership são ignoradas
 * para não haver recursão com a sincronização.
 */
final class RestrictedForumRelationshipHooks {

  public const TOPIC_PLUGIN = 'group_node:aculta_forum_topic';

  public const CATEGORY_PLUGIN = 'group_taxonomy_term:aculta_forum_categories';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RestrictedForumGrantRebuilder $grantRebuilder,
  ) {}

  /**
   * Implements hook_group_relationship_insert().
   */
  #[Hook('group_relationship_insert')]
  public function relationshipInsert(GroupRelationshipInterface $relationship): void {
    $this->rebuild($relationship);
  }

  /**
   * Implements hook_group_relationship_update().
   */
  #[Hook('group_relationship_update')]
  public function relationshipUpdate(GroupRelationshipInterface $relationship): void {
    $this->rebuild($relationship);
  }

  /**
   * Implements hook_group_relationship_delete().
   *
   * A relação já foi apagada, mas `plugin_id` e `entity_id` continuam no
   * objeto carregado.
   */
  #[Hook('group_relationship_delete')]
  public function relationshipDelete(GroupRelationshipInterface $relationship): void {
    $this->rebuild($relationship);
  }

  /**
   * Reconstrói os grants do tópico afetado pela relação do Group.
   */
  private function rebuild(GroupRelationshipInterface $relationship): void {
    $pluginId = $relationship->getPluginId();
    $entityId = (int) $relationship->getEntityId();
    if ($entityId <= 0) {
      return;
    }
    if ($pluginId === self::TOPIC_PLUGIN) {
      $topic = $this->entityTypeManager->getStorage('node')->load($entityId);
      if ($topic !== NULL) {
        $this->grantRebuilder->rebuildForTopic($topic);
      }
      return;
    }
    if ($pluginId === self::CATEGORY_PLUGIN) {
      $this->grantRebuilder->rebuildForCategory($entityId);
    }
  }

}
